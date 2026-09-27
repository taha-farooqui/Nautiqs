<?php

namespace App\Support\Pii;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Encryption at rest for a dealer's client data.
 *
 * Laravel's encrypter: AES-256-CBC with a random IV per value and an
 * HMAC-SHA256 over the ciphertext (encrypt-then-MAC), keyed by APP_KEY. The
 * same value encrypted twice gives two different strings, so nothing can be
 * inferred by comparing documents, and a tampered value fails its MAC instead
 * of decrypting to something wrong.
 *
 * Two properties matter more than the algorithm:
 *
 * **Legacy plaintext still reads.** Code ships before the migration runs, so
 * for a while the database holds both. A value that is not ciphertext is
 * returned as it is; writes always encrypt, and `pii:encrypt` finishes the
 * job. Nothing breaks in the gap.
 *
 * **Ciphertext that will not decrypt is an error, never a value.** That only
 * happens when APP_KEY is not the key the data was written with. Returning
 * the raw string would put `eyJpdiI6…` on a quote PDF, and a save would then
 * encrypt the ciphertext a second time — recoverable, but only by someone who
 * knows to decrypt twice. Stopping is the kinder failure. Keys listed in
 * APP_PREVIOUS_KEYS are tried automatically, so a rotation does not trip this.
 */
final class PiiCipher
{
    /** Encrypt a value. Empty stays empty; already-encrypted is left alone. */
    public static function encrypt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = (string) $value;

        // Never encrypt twice. Makes `pii:encrypt` safe to re-run. Ciphertext
        // is only accepted if this key opens it: one from another key would
        // otherwise be wrapped a second time, so decrypt() throws instead.
        if (self::looksEncrypted($value)) {
            self::decrypt($value);
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /** Decrypt a value. Legacy plaintext passes through unchanged. */
    public static function decrypt(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || ! self::looksEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            throw new RuntimeException(
                'Client data could not be decrypted: APP_KEY is not the key it was encrypted with. '
                . 'Restore the original key (or add it to APP_PREVIOUS_KEYS) before serving any page.',
                0,
                $e
            );
        }
    }

    /**
     * Encrypt $value, but hand back $stored untouched when it already holds
     * exactly this plaintext. Every encryption uses a fresh IV, so without
     * this a form saved with no edits would rewrite every field and read as a
     * change — and the "Client updated" notification would fire for nothing.
     */
    public static function encryptKeeping(mixed $value, mixed $stored): ?string
    {
        if ($value !== null && $value !== '' && is_string($stored) && self::looksEncrypted($stored)
            && self::decrypt($stored) === (string) $value) {
            return $stored;
        }

        return self::encrypt($value);
    }

    /**
     * Shaped like Laravel ciphertext: base64 of a JSON object carrying the IV,
     * the payload and the MAC. Says nothing about which key produced it.
     */
    public static function looksEncrypted(string $value): bool
    {
        // Shortest real payload is well over 100 characters; this is only a
        // cheap way to skip base64-decoding every name and phone number.
        if (strlen($value) < 100 || ! str_starts_with($value, 'eyJ')) {
            return false;
        }

        $json = base64_decode($value, true);
        if ($json === false) {
            return false;
        }

        $payload = json_decode($json, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /** Ciphertext that the current key (or a previous one) actually opens. */
    public static function isEncrypted(string $value): bool
    {
        if (! self::looksEncrypted($value)) {
            return false;
        }

        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Encrypt the named keys of an array, leaving the rest as they are. For
     * embedded documents — the client snapshot frozen onto each quote — where
     * a cast cannot reach inside. $current is what is stored now, so unchanged
     * values keep their ciphertext (see encryptKeeping).
     *
     * @param  array<string, mixed>       $data
     * @param  array<int, string>         $keys
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    public static function encryptKeys(array $data, array $keys, ?array $current = null): array
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = self::encryptKeeping($data[$k], $current[$k] ?? null);
            }
        }
        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>    $keys
     * @return array<string, mixed>
     */
    public static function decryptKeys(array $data, array $keys): array
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = self::decrypt($data[$k]);
            }
        }
        return $data;
    }
}
