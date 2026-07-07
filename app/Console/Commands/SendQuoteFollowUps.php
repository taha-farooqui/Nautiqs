<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EmailLog;
use App\Models\Notification;
use App\Models\Quote;
use App\Services\QuoteEmailSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Automatic follow-up emails ("Relances"). For every company that enabled
 * the feature, finds quotes still sitting in SENT after the configured
 * delay and sends the follow-up template once — never twice (idempotent
 * via email_log). Scheduled daily; see routes/console.php.
 *
 * Tenancy note: TenantScope is inert in console (no auth), so every Quote /
 * EmailLog query here filters company_id explicitly. The `not_trashed`
 * global scope on Quote stays active, which is what we want.
 */
class SendQuoteFollowUps extends Command
{
    protected $signature = 'quotes:send-follow-ups
        {--dry-run : List eligible quotes without sending or writing anything}
        {--company= : Restrict to a single company id}';

    protected $description = 'Send the automatic follow-up email for quotes that have gone quiet';

    /** Stop retrying a quote after this many failed follow-up attempts. */
    private const MAX_FAILED_ATTEMPTS = 3;

    public function handle(QuoteEmailSender $sender): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $companies = Company::where('follow_up_enabled', true)
            ->when($this->option('company'), fn ($q, $id) => $q->where('_id', $id))
            ->get();

        if ($companies->isEmpty()) {
            $this->info('No company has automatic follow-up enabled.');
            return self::SUCCESS;
        }

        $sent = 0; $failed = 0; $skipped = 0;

        foreach ($companies as $company) {
            if (($company->status ?? 'active') === 'suspended') {
                $this->line("→ {$company->name}: suspended — skipped.");
                continue;
            }

            $cutoff = $company->followUpCutoff();
            $enabledAt = $company->follow_up_enabled_at;
            if ($cutoff === null || $enabledAt === null) {
                $this->warn("→ {$company->name}: follow-up misconfigured (delay/watermark) — skipped.");
                continue;
            }

            $quotes = Quote::where('company_id', (string) $company->_id)
                ->where('status', Quote::STATUS_SENT)
                ->where('follow_up_disabled', '!=', true) // $ne also matches missing field
                ->whereNotNull('sent_at')
                ->where('sent_at', '<=', $cutoff)
                ->where('sent_at', '>=', $enabledAt)
                ->get();

            if ($quotes->isEmpty()) {
                continue;
            }

            $this->line("→ {$company->name}: {$quotes->count()} candidate(s)");

            foreach ($quotes as $quote) {
                try {
                    // Idempotency: one follow-up per quote, ever. A manual
                    // follow-up sent from the modal counts too.
                    $alreadySent = EmailLog::where('company_id', (string) $company->_id)
                        ->where('quote_id', (string) $quote->_id)
                        ->where('type', EmailLog::TYPE_FOLLOW_UP)
                        ->where('status', EmailLog::STATUS_SENT)
                        ->exists();
                    if ($alreadySent) {
                        $skipped++;
                        $this->line("  • {$quote->number}: already followed up — skipped.");
                        continue;
                    }

                    // Give up after repeated transport failures (bad address…).
                    $failedAttempts = EmailLog::where('company_id', (string) $company->_id)
                        ->where('quote_id', (string) $quote->_id)
                        ->where('type', EmailLog::TYPE_FOLLOW_UP)
                        ->where('status', EmailLog::STATUS_FAILED)
                        ->count();
                    if ($failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
                        $skipped++;
                        $this->line("  • {$quote->number}: {$failedAttempts} failed attempts — giving up.");
                        continue;
                    }

                    // Recipient: where the quote email actually went, falling
                    // back to the client snapshot (covers "Mark sent" quotes
                    // that never had an email).
                    $to = EmailLog::where('company_id', (string) $company->_id)
                        ->where('quote_id', (string) $quote->_id)
                        ->where('type', EmailLog::TYPE_QUOTE)
                        ->where('status', EmailLog::STATUS_SENT)
                        ->orderBy('sent_at', 'desc')
                        ->value('to_email')
                        ?: ($quote->client_snapshot['email'] ?? null);

                    if (empty($to)) {
                        $skipped++;
                        $this->line("  • {$quote->number}: no recipient email — skipped.");
                        continue;
                    }

                    if ($dryRun) {
                        $sent++;
                        $this->info("  ✓ [dry-run] {$quote->number} → {$to} (sent {$quote->sent_at?->format('d/m/Y')})");
                        continue;
                    }

                    $log = $sender->send($quote, EmailLog::TYPE_FOLLOW_UP, $to, actor: null);

                    if ($log->status === EmailLog::STATUS_SENT) {
                        $sent++;
                        $this->info("  ✓ {$quote->number} → {$to}");

                        // Tell the quote's creator (NotificationService no-ops
                        // without auth, so build the row explicitly).
                        if ($quote->created_by_user_id) {
                            Notification::create([
                                'user_id'    => (string) $quote->created_by_user_id,
                                'company_id' => (string) $quote->company_id,
                                'type'       => 'quote.follow_up_sent',
                                'title'      => __('Follow-up sent'),
                                'message'    => __(':number — automatic follow-up sent to :email', ['number' => $quote->number, 'email' => $to]),
                                'icon'       => 'ri-mail-send-line',
                                'color'      => 'primary',
                                'link'       => route('quotes.show', (string) $quote->_id),
                                'read_at'    => null,
                            ]);
                        }
                    } else {
                        $failed++;
                        $this->error("  ✗ {$quote->number} → {$to}: {$log->error_message}");
                    }
                } catch (\Throwable $e) {
                    // One broken quote (corrupt snapshot, PDF error…) must
                    // never abort the whole run.
                    $failed++;
                    $this->error("  ✗ {$quote->number}: {$e->getMessage()}");
                    Log::error('Follow-up send crashed', ['quote' => (string) $quote->_id, 'error' => $e->getMessage()]);
                }
            }
        }

        $summary = ($dryRun ? '[dry-run] ' : '') . "Follow-ups: sent {$sent}, failed {$failed}, skipped {$skipped}.";
        $this->info($summary);
        Log::info($summary);

        return self::SUCCESS;
    }
}
