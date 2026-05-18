<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records destructive superadmin actions. Always call this from inside a
 * superadmin-only controller path — it assumes auth()->user() is the actor.
 *
 * Usage:
 *   AuditLogger::record('tenant.suspend', $company, before: $oldAttrs, after: $newAttrs);
 *
 * The before/after arrays are stored verbatim. Strip sensitive fields
 * (passwords, tokens) before passing them in.
 */
class AuditLogger
{
    private const REDACT_KEYS = [
        'password',
        'password_hash',
        'remember_token',
        'access_token',
        'refresh_token',
        'api_token',
    ];

    public static function record(
        string $action,
        ?object $target = null,
        ?array $before = null,
        ?array $after = null,
        ?string $targetLabel = null,
    ): void {
        $user = Auth::user();

        AuditLog::create([
            'actor_user_id' => $user ? (string) $user->_id : null,
            'actor_email'   => $user?->email,
            'actor_role'    => $user?->role,
            'action'        => $action,
            'target_type'   => $target ? class_basename($target) : null,
            'target_id'     => $target ? (string) ($target->_id ?? $target->id ?? null) : null,
            'target_label'  => $targetLabel ?? self::deriveLabel($target),
            'before'        => $before ? self::redact($before) : null,
            'after'         => $after  ? self::redact($after)  : null,
            'ip_address'    => Request::ip(),
            'created_at'    => now(),
        ]);
    }

    private static function deriveLabel(?object $target): ?string
    {
        if (! $target) return null;
        foreach (['name', 'label', 'title', 'email', 'code'] as $field) {
            if (isset($target->$field) && $target->$field) {
                return (string) $target->$field;
            }
        }
        return null;
    }

    private static function redact(array $data): array
    {
        foreach ($data as $key => $val) {
            if (in_array($key, self::REDACT_KEYS, true)) {
                $data[$key] = '***';
            } elseif (is_array($val)) {
                $data[$key] = self::redact($val);
            }
        }
        return $data;
    }
}
