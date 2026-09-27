<?php

namespace App\Casts;

use App\Support\Pii\PiiCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A client-data field stored encrypted and read as plaintext.
 *
 * Everything outside the model — controllers, Blade, PDFs, emails — sees the
 * plain value and needs no change. See PiiCipher for the algorithm and for
 * why legacy plaintext still reads.
 *
 * Not Laravel's built-in `encrypted` cast, which throws on any value that is
 * not ciphertext: the code has to ship before the existing documents are
 * migrated, and every page would 500 in between.
 */
class EncryptedPii implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return PiiCipher::decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return PiiCipher::encryptKeeping($value, $attributes[$key] ?? null);
    }
}
