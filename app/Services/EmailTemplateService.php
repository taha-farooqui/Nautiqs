<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Quote;

/**
 * Spec §14 — three email templates per company, one per send context:
 *
 *   - quote              : initial quote-sending email (PDF attached)
 *   - order_confirmation : when a quote is marked Won and the BC PDF is sent
 *   - follow_up          : reminder for sent-but-cold quotes
 *
 * Each template has its own subject + body. Variables resolve from the quote
 * being sent, with sensible empty fallbacks (e.g. {{order_number}} stays
 * blank on a quote-stage send). The company logo is automatically prepended
 * to every outgoing email at send time via wrapWithLogo().
 */
class EmailTemplateService
{
    public const TYPE_QUOTE              = 'quote';
    public const TYPE_ORDER_CONFIRMATION = 'order_confirmation';
    public const TYPE_FOLLOW_UP          = 'follow_up';

    public const TYPES = [
        self::TYPE_QUOTE,
        self::TYPE_ORDER_CONFIRMATION,
        self::TYPE_FOLLOW_UP,
    ];

    /**
     * Display metadata for each template type, used by the table view and
     * the editor heading. Keep wording dealer-friendly (no jargon like
     * "context" or "type slug").
     */
    public const META = [
        self::TYPE_QUOTE => [
            'name'        => 'Quote sending',
            'description' => 'Sent when you email a quote to a client. PDF attached.',
            'icon'        => 'ri-file-list-3-line',
        ],
        self::TYPE_ORDER_CONFIRMATION => [
            'name'        => 'Order confirmation',
            'description' => 'Sent once a quote is marked Won — the order confirmation PDF goes with it.',
            'icon'        => 'ri-file-paper-2-line',
        ],
        self::TYPE_FOLLOW_UP => [
            'name'        => 'Follow-up',
            'description' => 'A polite nudge for quotes that have been sitting on the client side for a while.',
            'icon'        => 'ri-mail-send-line',
        ],
    ];

    /**
     * Factory defaults per type, keyed by locale. Used on first read and on
     * Reset. defaultsFor() picks the right locale.
     */
    public const DEFAULTS = [
        'en' => [
            self::TYPE_QUOTE => [
                'subject' => 'Your quotation {{quote_number}} from {{company_name}}',
                'body'    => <<<'HTML'
<div>Hello {{client_first_name}},</div>
<div><br></div>
<div>Please find attached your quotation for the <strong>{{boat_model}}</strong>.</div>
<div><br></div>
<div>The total amount is <strong>{{total_ttc}}</strong>, including VAT.</div>
<div><br></div>
<div>Let me know if you have any questions or would like to discuss the details.</div>
<div><br></div>
<div>Best regards,<br>{{salesperson_name}}<br>{{company_name}}</div>
HTML,
            ],
            self::TYPE_ORDER_CONFIRMATION => [
                'subject' => 'Order confirmation {{order_number}} — {{boat_model}}',
                'body'    => <<<'HTML'
<div>Hello {{client_first_name}},</div>
<div><br></div>
<div>Thank you for trusting us with your order. Please find attached the official order confirmation <strong>{{order_number}}</strong> for the <strong>{{boat_model}}</strong>.</div>
<div><br></div>
<div>The total amount confirmed is <strong>{{total_ttc}}</strong>, including VAT.</div>
<div><br></div>
<div>We'll be in touch shortly with the next steps. In the meantime, don't hesitate to reach out if you have any questions.</div>
<div><br></div>
<div>Best regards,<br>{{salesperson_name}}<br>{{company_name}}</div>
HTML,
            ],
            self::TYPE_FOLLOW_UP => [
                'subject' => 'Quick follow-up on quotation {{quote_number}}',
                'body'    => <<<'HTML'
<div>Hello {{client_first_name}},</div>
<div><br></div>
<div>I wanted to check in on quotation <strong>{{quote_number}}</strong> for the <strong>{{boat_model}}</strong> we sent you recently.</div>
<div><br></div>
<div>Do you have any questions, or would you like to schedule a call to walk through the details together?</div>
<div><br></div>
<div>Happy to adjust anything that would make the offer work better for you.</div>
<div><br></div>
<div>Best regards,<br>{{salesperson_name}}<br>{{company_name}}</div>
HTML,
            ],
        ],
        'fr' => [
            self::TYPE_QUOTE => [
                'subject' => 'Votre devis {{quote_number}} de {{company_name}}',
                'body'    => <<<'HTML'
<div>Bonjour {{client_first_name}},</div>
<div><br></div>
<div>Veuillez trouver ci-joint votre devis pour le <strong>{{boat_model}}</strong>.</div>
<div><br></div>
<div>Le montant total est de <strong>{{total_ttc}}</strong> TTC.</div>
<div><br></div>
<div>N'hésitez pas à me contacter pour toute question ou pour discuter des détails.</div>
<div><br></div>
<div>Cordialement,<br>{{salesperson_name}}<br>{{company_name}}</div>
HTML,
            ],
            self::TYPE_ORDER_CONFIRMATION => [
                'subject' => 'Bon de commande {{order_number}} — {{boat_model}}',
                'body'    => <<<'HTML'
<div>Bonjour {{client_first_name}},</div>
<div><br></div>
<div>Merci de votre confiance pour cette commande. Veuillez trouver ci-joint le bon de commande officiel <strong>{{order_number}}</strong> pour le <strong>{{boat_model}}</strong>.</div>
<div><br></div>
<div>Le montant total confirmé est de <strong>{{total_ttc}}</strong> TTC.</div>
<div><br></div>
<div>Nous reviendrons vers vous très prochainement pour la suite. En attendant, n'hésitez pas à nous contacter pour toute question.</div>
<div><br></div>
<div>Cordialement,<br>{{salesperson_name}}<br>{{company_name}}</div>
HTML,
            ],
            self::TYPE_FOLLOW_UP => [
                'subject' => 'Petite relance concernant le devis {{quote_number}}',
                'body'    => <<<'HTML'
<div>Bonjour {{client_first_name}},</div>
<div><br></div>
<div>Je me permets de revenir vers vous concernant le devis <strong>{{quote_number}}</strong> pour le <strong>{{boat_model}}</strong> que nous vous avons adressé récemment.</div>
<div><br></div>
<div>Avez-vous des questions, ou souhaitez-vous que nous prenions le temps d'en discuter ensemble ?</div>
<div><br></div>
<div>Je reste disponible pour ajuster l'offre afin qu'elle corresponde au mieux à vos attentes.</div>
<div><br></div>
<div>Cordialement,<br>{{salesperson_name}}<br>{{company_name}}</div>
HTML,
            ],
        ],
    ];

    /**
     * Pick the defaults bundle for the current app locale, falling back to
     * English when the locale isn't supported or when DEFAULTS doesn't have
     * an entry for it.
     */
    private function defaultsFor(string $type): array
    {
        $locale = app()->getLocale();
        if (! isset(self::DEFAULTS[$locale][$type])) {
            $locale = 'en';
        }
        return self::DEFAULTS[$locale][$type];
    }

    /**
     * Get one template by type, creating it from defaults if missing.
     * Also handles a one-time legacy migration: rows stored with the old
     * 'default' type get renamed to 'quote' on first read.
     */
    public function getOrCreate(Company $company, string $type = self::TYPE_QUOTE): EmailTemplate
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown template type: {$type}");
        }

        // Legacy migration: pre-multi-template rows used type='default'. We
        // can transparently promote them to TYPE_QUOTE on first lookup so the
        // dealer's customised wording isn't lost.
        if ($type === self::TYPE_QUOTE) {
            $legacy = EmailTemplate::where('company_id', (string) $company->_id)
                ->where('type', 'default')
                ->first();
            if ($legacy) {
                $legacy->update(['type' => self::TYPE_QUOTE]);
            }
        }

        $existing = EmailTemplate::where('company_id', (string) $company->_id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        $defaults = $this->defaultsFor($type);
        return EmailTemplate::create([
            'company_id' => (string) $company->_id,
            'type'       => $type,
            'subject'    => $defaults['subject'],
            'body'       => $defaults['body'],
        ]);
    }

    /**
     * Return all three templates for a company, creating any missing ones
     * from defaults. Used by the index/table view.
     */
    public function getAll(Company $company): array
    {
        $out = [];
        foreach (self::TYPES as $type) {
            $out[$type] = $this->getOrCreate($company, $type);
        }
        return $out;
    }

    /**
     * Reset a template to its factory default for its current type.
     */
    public function reset(EmailTemplate $template): EmailTemplate
    {
        $type = $template->type;
        if (! in_array($type, self::TYPES, true)) {
            // Treat unknown legacy rows as quote-type defaults.
            $type = self::TYPE_QUOTE;
        }
        $defaults = $this->defaultsFor($type);
        $template->update([
            'subject' => $defaults['subject'],
            'body'    => $defaults['body'],
        ]);
        return $template;
    }

    /**
     * Substitute {{variables}} in subject + body. Returns ['subject', 'body'].
     * The company logo is prepended to the body automatically.
     */
    public function render(EmailTemplate $template, Company $company, ?Quote $quote = null, array $extra = []): array
    {
        $vars = $this->buildVariables($company, $quote, $extra);

        $subject = $this->substitute($template->subject ?? '', $vars);
        $body    = $this->substitute($template->body ?? '', $vars);

        return [
            'subject' => $subject,
            'body'    => $this->wrapWithLogo($body, $company),
        ];
    }

    /**
     * Sample data for the live preview pane in the editor.
     */
    public function sampleVariables(Company $company): array
    {
        return [
            'client_name'        => 'Jean Martin',
            'client_first_name'  => 'Jean',
            'quote_number'       => 'Q-' . date('Y') . '-001',
            'order_number'       => 'BC-' . date('Y') . '-001',
            'boat_model'         => 'Eagle 10 — 2× 200HP',
            'total_ttc'          => '€89 500,00',
            'salesperson_name'   => $company->salesperson_name ?? __('Salesperson'),
            'company_name'       => $company->name ?? __('Company'),
            'date'               => now()->translatedFormat('F j, Y'),
        ];
    }

    /**
     * Variables actually used at send time, derived from real quote data.
     */
    public function buildVariables(Company $company, ?Quote $quote = null, array $extra = []): array
    {
        $first = $quote?->client_snapshot['first_name'] ?? '';
        $last  = $quote?->client_snapshot['last_name']  ?? '';
        $totalTtc = $quote ? ($quote->totals['total_ttc'] ?? 0) : 0;

        return array_merge([
            'client_name'        => trim($first . ' ' . $last) ?: __('Client'),
            'client_first_name'  => $first ?: __('Client'),
            'quote_number'       => $quote->number ?? '',
            'order_number'       => $quote->order_confirmation_number ?? '',
            'boat_model'         => $quote->model_snapshot['name'] ?? '',
            'total_ttc'          => '€' . number_format($totalTtc, 2, ',', ' '),
            'salesperson_name'   => $company->salesperson_name ?? '',
            'company_name'       => $company->name ?? '',
            'date'               => now()->translatedFormat('F j, Y'),
        ], $extra);
    }

    /**
     * Legacy placeholder kept for backwards compatibility with already-saved
     * email bodies that contain it. New code should not introduce it.
     */
    public const LOGO_PLACEHOLDER = '__NAUTIQS_LOGO_SRC__';

    /**
     * Wrap the rendered body with a branded text-only header. We don't
     * inline a logo image because:
     *   - cid: attachments don't work via Brevo's HTTPS API
     *   - data: URIs are blocked by Gmail / Outlook for anti-phishing
     *   - public asset URLs only work when APP_URL is publicly reachable
     * A bold company name in primary navy reads as a brand header in every
     * client and never breaks. If/when a public CDN URL is available later,
     * we can re-introduce the <img>.
     *
     * The optional $logoSrc argument is unused now but kept in the signature
     * so existing callers don't need to change.
     */
    public function wrapWithLogo(string $body, Company $company, ?string $logoSrc = null): string
    {
        $companyName = e($company->name ?? config('app.name', 'Nautiqs'));
        $sentFrom    = e(__('Sent from'));

        return <<<HTML
<div style="font-family: Inter, Arial, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">
    <div style="text-align: left; padding: 16px 0; border-bottom: 3px solid #0e4f79; margin-bottom: 24px;">
        <span style="font-size: 22px; font-weight: 700; color: #0e4f79;">{$companyName}</span>
    </div>
    <div style="font-size: 14px; line-height: 1.6;">
        {$body}
    </div>
    <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
        {$sentFrom} {$companyName}
    </div>
</div>
HTML;
    }

    private function substitute(string $template, array $vars): string
    {
        $keys = array_map(fn ($k) => '{{' . $k . '}}', array_keys($vars));
        return str_replace($keys, array_values($vars), $template);
    }

    /**
     * Available variable list for the picker in the UI.
     */
    public static function availableVariables(): array
    {
        return [
            'client_name'        => __('Client full name'),
            'client_first_name'  => __('Client first name'),
            'quote_number'       => __('Quote reference'),
            'order_number'       => __('Order confirmation reference'),
            'boat_model'         => __('Boat model + variant'),
            'total_ttc'          => __('Total incl. VAT'),
            'salesperson_name'   => __('Salesperson name'),
            'company_name'       => __('Your company name'),
            'date'               => __("Today's date"),
        ];
    }
}
