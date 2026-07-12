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
 *   - HEAD requests (link-warmers, bots).
 *   - User-Agent matching known outbound-provider / scanner patterns.
 *   - Hits within :GRACE_SECONDS of the quote being marked sent
 *     (catches the immediate Brevo / Gmail-proxy prefetch).
 *   - Same client IP hitting the same token within :DEDUP_SECONDS
 *     (Gmail's image proxy re-fetches the pixel multiple times during a
 *     single inbox view — we count those as one open).
 *
 * IMPORTANT: GoogleImageProxy is NOT in the bot list — that's the User-
 * Agent for genuine Gmail opens. Blocking it would zero out the count.
 *
 * Public route — no auth. The tracking_token in the URL is the only
 * credential; it's a 40-char random string scoped to a single quote.
 */
class EmailPixelController extends Controller
{
    // Pre-encoded 43-byte transparent 1x1 GIF. Returned regardless of
    // whether the token matched so we never give 200/404 timing hints.
    private const GIF_BYTES = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    // Ignore hits within this many seconds of `sent_at` — covers the
    // outbound provider's link-warmer and the inbox image-proxy
    // prefetch that fires the moment the email lands.
    private const GRACE_SECONDS = 60;

    // Per-IP dedup window. Gmail's image proxy re-hits the pixel a few
    // times in quick succession during a single inbox view — we treat
    // those as one open. A fresh open from the same client more than
    // this many seconds later counts as a new open.
    private const DEDUP_SECONDS = 60;

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

        // 3. Ignore the immediate post-send window. Provider link-warmers
        // and most spam scanners fire within the first minute. Compared as
        // an absolute instant (sent_at + grace) — Carbon 3's diffInSeconds()
        // is SIGNED, so `now()->diffInSeconds($sentAt)` is negative for any
        // past sent_at and would swallow every open forever.
        $sentAt = $quote->sent_at;
        if ($sentAt && now()->lt($sentAt->copy()->addSeconds(self::GRACE_SECONDS))) {
            return false;
        }

        // 4. Per-IP dedup: same IP hitting the same token within the
        // dedup window = one open. A genuine re-open >60s later (or any
        // hit from a different IP) gets a fresh count. Cache::add() is
        // atomic — only the first concurrent caller wins, so two
        // simultaneous Gmail proxy hits can't both pass through.
        $key = 'pixel:' . (string) $quote->_id . ':' . (string) $request->ip();
        if (! Cache::add($key, 1, self::DEDUP_SECONDS)) {
            return false;
        }

        return true;
    }
}
