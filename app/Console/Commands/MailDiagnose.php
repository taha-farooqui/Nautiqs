<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * One-shot mail diagnostic. Run on the deployed server to verify mail
 * config + actually attempt a send and surface the real error if it fails.
 *
 *   php artisan mail:diagnose you@email.com
 *
 * Output covers: which mailer is active, Brevo SMTP host/port, FROM address,
 * and the result of a real send. If the send fails, the underlying transport
 * exception bubbles up so you can see exactly what Brevo / Mailgun / etc. said.
 */
class MailDiagnose extends Command
{
    protected $signature = 'mail:diagnose {to}';
    protected $description = 'Print mail config and try a test send';

    public function handle(): int
    {
        $this->info('=== Mail config ===');
        $this->table(
            ['Key', 'Value'],
            [
                ['MAIL_MAILER',       config('mail.default')],
                ['MAIL_HOST',         config('mail.mailers.smtp.host')],
                ['MAIL_PORT',         config('mail.mailers.smtp.port')],
                ['MAIL_USERNAME',     config('mail.mailers.smtp.username')],
                ['MAIL_PASSWORD',     config('mail.mailers.smtp.password') ? '*** (set)' : '(empty)'],
                ['MAIL_ENCRYPTION',   config('mail.mailers.smtp.encryption') ?? '(none)'],
                ['MAIL_FROM_ADDRESS', config('mail.from.address')],
                ['MAIL_FROM_NAME',    config('mail.from.name')],
                ['SMTP timeout (s)',  config('mail.mailers.smtp.timeout')],
            ]
        );

        $to = $this->argument('to');
        $this->info("=== Sending test to {$to} ===");

        try {
            Mail::raw(
                'Mail diagnose test from Nautiqs at ' . now()->toDateTimeString() . '. If you see this, the wiring works.',
                function ($m) use ($to) {
                    $m->to($to)->subject('Nautiqs · mail:diagnose');
                }
            );
            $this->info('OK — Symfony Mailer accepted the message.');
            $this->line('Now check your inbox AND your Brevo dashboard → Transactional → Logs.');
            $this->line('If Brevo logs show nothing, the message left Symfony but never reached Brevo (network / firewall / wrong host).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAILED at the transport layer.');
            $this->line(get_class($e));
            $this->line($e->getMessage());
            return self::FAILURE;
        }
    }
}
