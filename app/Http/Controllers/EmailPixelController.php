<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 1x1 transparent GIF that lives inside outbound quote emails. When the
 * client opens the email, the embedded <img> hits this endpoint and we
 * bump the quote's open counter + stamp first/last open timestamps.
 *
 * Public route — no auth. The tracking_token in the URL is the only
 * credential; it's a 40-char random string scoped to a single quote.
 */
class EmailPixelController extends Controller
{
    // Pre-encoded 43-byte transparent 1x1 GIF. Returned regardless of
    // whether the token matched so we never give 200/404 timing hints.
    private const GIF_BYTES = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    public function __invoke(string $token, Request $request): Response
    {
        // The Quote model has a `not_trashed` global scope — that's fine;
        // we don't want trashed quotes resurrecting on open. Soft-failing
        // (no token match) just serves the pixel without logging.
        $quote = Quote::where('tracking_token', $token)->first();

        if ($quote) {
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
                // Never let a tracking failure break the pixel response —
                // the recipient's email client would surface a broken
                // image and the open would be lost anyway.
                Log::warning('Email pixel tracking failed', [
                    'token' => substr($token, 0, 8) . '…',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Aggressive no-cache so opens count every time the mail is viewed.
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
}
