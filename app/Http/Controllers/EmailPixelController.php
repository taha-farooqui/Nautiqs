<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 1x1 transparent GIF embedded in outbound quote emails. When a real
 * recipient opens the email, the <img> hits this endpoint and we bump
 * the quote's open counter.
 *
 * Filters that PREVENT a hit from counting:
 *   - Anything that isn't a plain GET (link-warmers, bots).
 *   - Empty User-Agent, or one matching a known scanner / ESP pattern.
 *   - A second hit for the same QUOTE inside :DEDUP_SECONDS (one inbox
 *     view fans out into several proxy fetches — that's one open).
 *
 * IMPORTANT: GoogleImageProxy is NOT in the bot list — that's the User-
 * Agent for genuine Gmail opens. Blocking it would zero out the count.
 *
 * Equally important: do NOT re-introduce a "skip hits soon after sending"
 * window. It looks sensible but the proxy fetch happens when the client
 * OPENS the mail, typically seconds after the dealer sent it, so such a
 * window discards real opens and the UI wrongly reads "never opened".
 *
 * Public route — no auth. The tracking_token in the URL is the only
 * credential; it's a 40-char random string scoped to a single quote.
 */
class EmailPixelController extends Controller
{
    // Pre-encoded 43-byte transparent 1x1 GIF. Returned regardless of
    // whether the token matched so we never give 200/404 timing hints.
    private const GIF_BYTES = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    // Dedup window, keyed on the QUOTE (not the IP). A single inbox view
    // produces a burst of fetches — measured on this install: +5s from a
    // Gmail IP carrying a mail.google.com referer, then +17s and +30s from
    // two DIFFERENT GoogleImageProxy IPs (142.250.32.2 and .3). Keying on
    // the IP would score that one read as three opens, so we collapse per
    // quote instead: one open per viewing session, and a genuine re-open
    // later still counts.
    //
    // There is deliberately NO "ignore hits just after sending" window.
    // The proxy fetches when the recipient OPENS the mail, which is often
    // seconds after it was sent (the dealer sends, the client is waiting
    // for it) — a time-based guard cannot tell that apart from a scanner
    // prefetch and silently ate every real open. Scanners are filtered by
    // user agent below instead.
    private const DEDUP_SECONDS = 300;

    // User-Agent substrings we treat as non-human. We DO NOT include
    // GoogleImageProxy / ggpht.com here: those are real Gmail opens.
    private const BOT_AGENTS = [
        'mailgun', 'sendgrid', 'brevo', 'sib-msys', 'sib-tracker',
        'mailchimp', 'campaign-monitor',
        'mimecast', 'barracuda', 'symantec', 'ironport', 'proofpoint',
        'spamassassin', 'spamcop',
        'curl/', 'wget/', 'python-requests', 'go-http-client', 'okhttp',
        'headlesschrome', 'phantomjs', 'puppeteer',
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'slackbot',
    ];

    public function __invoke(string $token, Request $request): Response
    {
        $quote = Quote::where('tracking_token', $token)->first();

        if ($quote && $this->shouldCount($request, $quote)) {
            try {
                $existing = $quote->tracking ?? [];
                $count    = (int) ($existing['open_count'] ?? 0) + 1;
                $now      = now();

                $quote->update([
                    'tracking' => [
                        'open_count'      => $count,
                        'first_opened_at' => $existing['first_opened_at'] ?? $now,
                        'last_opened_at'  => $now,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Email pixel tracking failed', [
                    'token' => substr($token, 0, 8) . '…',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Aggressive no-cache so genuine re-opens still re-fetch.
        return response(self::GIF_BYTES, 200, [
            'Content-Type'                  => 'image/gif',
            'Content-Length'                => (string) strlen(self::GIF_BYTES),
            'Cache-Control'                 => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                        => 'no-cache',
            'Expires'                       => '0',
            // Privacy: don't leak the dealer's tracking URL via Referer.
            'Referrer-Policy'               => 'no-referrer',
        ]);
    }

    /**
     * Should this pixel hit increment the counter? Filters out the
     * provider-side link-warmer + inbox image-proxy multi-fire that
     * otherwise inflates the count.
     */
    private function shouldCount(Request $request, Quote $quote): bool
    {
        // 1. Anything that isn't a regular GET is suspicious.
        if (! $request->isMethod('GET')) return false;

        // 2. Drop empty / known-provider user agents.
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') return false;
        foreach (self::BOT_AGENTS as $needle) {
            if (str_contains($ua, $needle)) return false;
        }

        // 3. Per-quote dedup: the burst of fetches behind one inbox view
        // counts once, however many proxy IPs it arrives from. Cache::add()
        // is atomic — only the first concurrent caller wins, so simultaneous
        // proxy hits can't both pass through.
        $key = 'pixel:' . (string) $quote->_id;
        if (! Cache::add($key, 1, self::DEDUP_SECONDS)) {
            return false;
        }

        return true;
    }
}
