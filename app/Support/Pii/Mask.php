<?php

namespace App\Support\Pii;

/**
 * What the platform side is allowed to see of a dealer's clients.
 *
 * Encryption keeps client data unreadable to anyone holding the database
 * without the key. It does not keep it from the platform itself, which holds
 * the key — so the superadmin screens show a shape, never a value. Enough to
 * tell two rows apart and to see that a field is filled; not enough to
 * contact, find or identify anyone.
 *
 *   Jean Dupont              →  J••• D•••
 *   jean.dupont@gmail.com    →  j•••@g•••.com
 *   06 28 92 21 93           →  •• •• •• •• 93
 */
final class Mask
{
    private const DOT = '•••';

    public static function name(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return collect(preg_split('/\s+/u', $value))
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)) . self::DOT)
            ->implode(' ');
    }

    public static function email(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || ! str_contains($value, '@')) {
            return $value === '' ? '' : self::DOT;
        }

        [$local, $domain] = explode('@', $value, 2);

        // Keep the TLD: ".com" or ".fr" says nothing about who this is, and it
        // is what makes the string still read as an email address. The domain
        // name itself goes — a company domain would identify the employer.
        $dot = strrpos($domain, '.');
        $tld = $dot === false ? '' : substr($domain, $dot);

        return mb_substr($local, 0, 1) . self::DOT . '@' . mb_substr($domain, 0, 1) . self::DOT . $tld;
    }

    public static function phone(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if ($digits === '') {
            return '';
        }

        // The last two digits, as on a card statement: enough to tell two
        // numbers apart, nowhere near enough to dial one.
        return '•• •• •• •• ' . substr($digits, -2);
    }

    /** Any other free text: that there is something, and nothing of what. */
    public static function text(?string $value): string
    {
        return trim((string) $value) === '' ? '' : self::DOT;
    }
}
