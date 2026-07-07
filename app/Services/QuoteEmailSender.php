<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EmailLog;
use App\Models\Quote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
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
     * @param array     $overrides ['subject' => ?, 'body' => ?, 'to_name' => ?]
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

        // Reply-To: the company mailbox, displayed under the name of the
        // person who made the quote (fallback: the configured salesperson).
        $replyToName = $quote->creatorName() ?: $company->salesperson_name;

        $sendError = null;
        try {
            Mail::html($bodyHtml, function ($msg) use ($to, $subject, $pdfBytes, $attachmentFilename, $company, $replyToName) {
                $msg->to($to)
                    ->subject($subject)
                    ->attachData($pdfBytes, $attachmentFilename, ['mime' => 'application/pdf']);

                if ($company->salesperson_email) {
                    $msg->replyTo($company->salesperson_email, $replyToName);
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
            'reply_to_email'      => $company->salesperson_email,
            'subject'             => $subject,
            'body_html'           => $bodyHtml,
            'attachment_filename' => $attachmentFilename,
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
