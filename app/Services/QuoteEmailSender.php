<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EmailLog;
use App\Models\Quote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * The one send pipeline for quote-related emails, shared by the manual
 * "Send by email" action (QuoteController::sendEmail) and the automatic
 * follow-up scheduler (quotes:send-follow-ups). Renders the template,
 * appends the open-tracking pixel, attaches the right PDF, sends via SMTP
 * and writes the EmailLog audit row — even on failure.
 */
class QuoteEmailSender
{
    /**
     * Limits for files the dealer attaches by hand, alongside the generated
     * PDF. The ceiling that matters is the mail server's, not ours: SMTP
     * providers commonly refuse a message over 25 MB, and base64 inflates
     * attachments by about a third on the way out. 12 MB of files therefore
     * arrives as roughly 16 MB — comfortably under, with the quote PDF and
     * the message body still to add.
     *
     * PHP's own upload_max_filesize / post_max_size must be at least this
     * large or the files never reach the application at all.
     */
    public const ATTACH_MAX_BYTES   = 10 * 1024 * 1024;   // one file
    public const ATTACH_TOTAL_BYTES = 12 * 1024 * 1024;   // all of them together
    public const ATTACH_MAX_COUNT   = 10;

    /** What a dealer actually sends a buyer: photos, brochures, spec sheets. */
    public const ATTACH_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip',
    ];

    public function __construct(private EmailTemplateService $templates)
    {
    }

    /**
     * Send one email about $quote. Transport errors never throw — the
     * returned EmailLog's status tells the caller what happened.
     *
     * @param string    $type      EmailLog::TYPE_* constant
     * @param string    $to        recipient email
     * @param User|null $actor     the human sending it; null = automated (scheduler)
     * @param array     $overrides ['subject' => ?, 'body' => ?, 'to_name' => ?,
     *                             'attachments' => UploadedFile[]]
     */
    public function send(Quote $quote, string $type, string $to, ?User $actor = null, array $overrides = []): EmailLog
    {
        /** @var Company $company */
        $company = $quote->company;

        $template = $this->templates->getOrCreate($company, $type);
        $rendered = $this->templates->render($template, $company, $quote);

        $subject  = filled($overrides['subject'] ?? null) ? $overrides['subject'] : $rendered['subject'];
        $bodyHtml = filled($overrides['body'] ?? null)    ? $overrides['body']    : $rendered['body'];

        // Email open-tracking pixel. Mint a per-quote token on first send and
        // re-use it for every follow-up so the open-count keeps accumulating
        // against the same quote. EMAIL_TRACKING_BASE_URL can override the
        // route host so local test sends still emit public URLs.
        $trackingToken = $quote->tracking_token;
        if (empty($trackingToken)) {
            $trackingToken = Str::random(40);
            $quote->update(['tracking_token' => $trackingToken]);
        }
        $trackingBase = rtrim(config('app.tracking_base_url') ?: '', '/');
        $pixelUrl = $trackingBase !== ''
            ? $trackingBase . '/e/p/' . $trackingToken
            : route('email.pixel', $trackingToken);

        // Cache-buster, unique per SEND. Gmail/Outlook proxy and cache remote
        // images by URL, so without this a follow-up re-using the quote's
        // tracking token could be served from the cached copy of the first
        // email and never register an open. The controller ignores the param.
        $pixelUrl .= (str_contains($pixelUrl, '?') ? '&' : '?') . 's=' . Str::random(10);

        $bodyHtml .= '<img src="' . e($pixelUrl) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />';

        // Order-confirmation emails attach the BC PDF; everything else
        // (quote + follow-up) attaches the quote PDF.
        if ($type === EmailLog::TYPE_ORDER_CONFIRMATION && ! empty($quote->order_confirmation_number)) {
            $pdf = Pdf::loadView('pdf.order-confirmation', compact('quote', 'company'))->setPaper('a4')->setOption('isPhpEnabled', true);
            $attachmentFilename = $quote->order_confirmation_number . '.pdf';
        } else {
            $pdf = Pdf::loadView('pdf.quote', compact('quote', 'company'))->setPaper('a4')->setOption('isPhpEnabled', true);
            $attachmentFilename = $quote->number . '.pdf';
        }
        $pdfBytes = $pdf->output();

        // Reply-To goes to the teammate who wrote the quote, so the client's
        // reply lands with them rather than with whoever registered the
        // dealership. Company salesperson stays as the fallback.
        $replyToName  = $quote->creatorName() ?: $company->salesperson_name;
        $replyToEmail = $quote->creatorEmail() ?: $company->salesperson_email;

        // The client sees the DEALERSHIP as the sender, not Nautiqs — the
        // platform is white-label from the boat buyer's point of view.
        //
        // Only the display NAME can carry the dealership: the envelope address
        // has to stay on our own authenticated domain, because SPF/DKIM are
        // published for nautiqs.fr and forging a dealer's address there would
        // send every quote to spam (or get it rejected outright). Pairing our
        // address with their name and their Reply-To gives the dealership's
        // identity in the inbox and lands replies in their mailbox.
        $fromName    = $company->name ?: config('mail.from.name');
        $fromAddress = config('mail.from.address');

        // Files the dealer picked in the send dialogue. Read into memory here
        // rather than attached by path: an uploaded temp file is removed when
        // the request ends, and the callback below can run after that.
        $extras = [];
        foreach (($overrides['attachments'] ?? []) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $extras[] = [
                'name'  => $file->getClientOriginalName(),
                'mime'  => $file->getMimeType() ?: 'application/octet-stream',
                'size'  => $file->getSize(),
                'bytes' => file_get_contents($file->getRealPath()),
            ];
        }

        $sendError = null;
        try {
            Mail::html($bodyHtml, function ($msg) use ($to, $subject, $pdfBytes, $attachmentFilename, $replyToEmail, $replyToName, $fromName, $fromAddress, $company, $extras) {
                $msg->to($to)
                    ->subject($subject)
                    ->from($fromAddress, $fromName)
                    ->attachData($pdfBytes, $attachmentFilename, ['mime' => 'application/pdf']);

                foreach ($extras as $extra) {
                    $msg->attachData($extra['bytes'], $extra['name'], ['mime' => $extra['mime']]);
                }

                // Without a Reply-To the client's reply goes to our platform
                // mailbox and the dealer never sees it, so fall back to the
                // company address before leaving it unset.
                $reply = $replyToEmail ?: $company->salesperson_email;
                if ($reply) {
                    $msg->replyTo($reply, $replyToName ?: $fromName);
                }
            });
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
        }

        // Audit row — written even on failure so the dealer can see what
        // happened and retry. company_id is taken from the quote (not auth)
        // so the scheduler writes correctly-scoped rows without a session.
        $log = EmailLog::create([
            'company_id'          => (string) $quote->company_id,
            'quote_id'            => (string) $quote->_id,
            'quote_number'        => $quote->number,
            'type'                => $type,
            'to_email'            => $to,
            'to_name'             => filled($overrides['to_name'] ?? null)
                ? $overrides['to_name']
                : (trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? '')) ?: null),
            'reply_to_email'      => $replyToEmail,
            'subject'             => $subject,
            'body_html'           => $bodyHtml,
            'attachment_filename' => $attachmentFilename,
            // Name and size only. The bytes are not kept, for the same reason
            // the quote PDF isn't: the log records what was sent, it is not a
            // second copy of it.
            'attachments'         => array_map(
                fn ($e) => ['name' => $e['name'], 'size' => $e['size']],
                $extras,
            ),
            'status'              => $sendError ? EmailLog::STATUS_FAILED : EmailLog::STATUS_SENT,
            'error_message'       => $sendError,
            'sent_by_user_id'     => $actor ? (string) $actor->_id : null,
            'sent_by_user_name'   => $actor ? $actor->name : __('Automatic follow-up'),
            'sent_at'             => now(),
            'automated'           => $actor === null,
        ]);

        // First successful send moves a draft into Sent state.
        if (! $sendError && $quote->status === Quote::STATUS_DRAFT) {
            $quote->update([
                'status'  => Quote::STATUS_SENT,
                'sent_at' => now(),
            ]);
        }

        return $log;
    }
}
