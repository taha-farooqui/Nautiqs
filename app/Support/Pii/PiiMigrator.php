<?php

namespace App\Support\Pii;

use App\Models\Client;
use App\Models\EmailLog;
use App\Models\Notification;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Model;

/**
 * Brings stored documents in line with the models: encrypts what was written
 * before encryption existed, decrypts everything for a rollback, and reports
 * what is left. The pii:* commands are thin wrappers around this.
 *
 * One definition of what is personal data, taken from the models themselves,
 * so the migration, the scan and the running app cannot disagree about it.
 */
final class PiiMigrator
{
    /** Limit every operation to one dealership; null means all of them. */
    private ?string $companyId = null;

    public function forCompany(?string $companyId): self
    {
        $clone = clone $this;
        $clone->companyId = $companyId;
        return $clone;
    }

    /**
     * Where client data lives.
     *   fields  plain string fields on the document
     *   nested  embedded document => its personal keys
     *   list    array of embedded documents => the personal key of each
     *
     * @return array<string, array{model: class-string<Model>, fields: array<int,string>, nested?: array<string, array<int,string>>, list?: array<string, array<int,string>>}>
     */
    public static function map(): array
    {
        return [
            'clients'       => ['model' => Client::class,       'fields' => Client::PII],
            'quotes'        => ['model' => Quote::class,        'fields' => [], 'nested' => ['client_snapshot' => Quote::SNAPSHOT_PII]],
            'email_log'     => ['model' => EmailLog::class,     'fields' => EmailLog::PII, 'list' => ['attachments' => ['name']]],
            'notifications' => ['model' => Notification::class, 'fields' => ['message']],
        ];
    }

    /**
     * Read-only. For every personal field: how many hold plaintext, how many
     * hold ciphertext this key opens, how many hold ciphertext it does not.
     *
     * @return array<string, array{docs: int, plaintext: int, encrypted: int, unreadable: int, samples: array<int,string>}>
     */
    public function scan(): array
    {
        $out = [];

        foreach (self::map() as $name => $def) {
            $stat = ['docs' => 0, 'plaintext' => 0, 'encrypted' => 0, 'unreadable' => 0, 'samples' => []];

            foreach ($this->cursor($def['model']) as $doc) {
                $stat['docs']++;
                foreach ($this->leaves($doc, $def) as $path => $value) {
                    if (! is_string($value) || $value === '') {
                        continue;
                    }
                    if (! PiiCipher::looksEncrypted($value)) {
                        $stat['plaintext']++;
                        if (count($stat['samples']) < 3) {
                            $stat['samples'][] = (string) $doc->getKey() . ' ' . $path;
                        }
                    } elseif (PiiCipher::isEncrypted($value)) {
                        $stat['encrypted']++;
                    } else {
                        $stat['unreadable']++;
                    }
                }
            }

            $out[$name] = $stat;
        }

        return $out;
    }

    /**
     * Any OTHER string field in these collections that looks like an email
     * address. The allow-list is the dealer's own addresses, which are not a
     * client's. Anything else found here is a copy of client data that the
     * encryption does not cover yet.
     *
     * @return array<string, int> "collection.path" => occurrences
     */
    public function strays(): array
    {
        // The dealer's own staff, not their clients: the salesperson a reply
        // goes to, and the account that wrote the quote.
        $allowed = [
            'email_log.reply_to_email',
            'quotes.created_by_email',
        ];

        $found = [];
        foreach (self::map() as $name => $def) {
            foreach ($this->cursor($def['model']) as $doc) {
                $this->walk($doc->getAttributes(), '', function (string $path, $value) use ($name, $def, $allowed, &$found) {
                    if (! is_string($value) || PiiCipher::looksEncrypted($value)) {
                        return;
                    }
                    if (! preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $value)) {
                        return;
                    }
                    // Normalise list indexes: attachments.3.name -> attachments.*.name
                    $generic = preg_replace('/\.\d+(?=\.|$)/', '.*', $path);
                    if (in_array("{$name}.{$generic}", $allowed, true) || $this->isCovered($def, $generic)) {
                        return;
                    }
                    $found["{$name}.{$generic}"] = ($found["{$name}.{$generic}"] ?? 0) + 1;
                });
            }
        }

        ksort($found);
        return $found;
    }

    /**
     * Encrypt every personal value still stored in plaintext.
     *
     * @return array<string, array{docs: int, changed: int, fields: int}>
     */
    public function encrypt(bool $write): array
    {
        return $this->rewrite($write, function (Model $doc, array $def): int {
            $n = 0;
            $raw = $doc->getAttributes();

            foreach ($def['fields'] as $f) {
                $v = $raw[$f] ?? null;
                if (is_string($v) && $v !== '' && ! PiiCipher::looksEncrypted($v)) {
                    // Through the model's own setter: the migration exercises
                    // exactly the write path the app uses.
                    $doc->{$f} = $v;
                    $n++;
                }
            }

            foreach (array_merge($def['nested'] ?? [], $def['list'] ?? []) as $attr => $keys) {
                $before = $this->plainCount($raw[$attr] ?? null, $keys, isset($def['list'][$attr]));
                if ($before > 0) {
                    $doc->{$attr} = $doc->{$attr};   // read decrypts (or passes through), write encrypts
                    $n += $before;
                }
            }

            return $n;
        });
    }

    /**
     * Decrypt every personal value back to plaintext. The rollback.
     *
     * Writes raw attributes on purpose: the model's setters would encrypt
     * again, which is the whole point of them.
     *
     * @return array<string, array{docs: int, changed: int, fields: int}>
     */
    public function decrypt(bool $write): array
    {
        return $this->rewrite($write, function (Model $doc, array $def): int {
            $n = 0;
            $raw = $doc->getAttributes();

            foreach ($def['fields'] as $f) {
                $v = $raw[$f] ?? null;
                if (is_string($v) && PiiCipher::looksEncrypted($v)) {
                    $raw[$f] = PiiCipher::decrypt($v);
                    $n++;
                }
            }

            foreach ($def['nested'] ?? [] as $attr => $keys) {
                $sub = $this->asArray($raw[$attr] ?? null);
                if ($sub === null) continue;
                foreach ($keys as $k) {
                    if (is_string($sub[$k] ?? null) && PiiCipher::looksEncrypted($sub[$k])) {
                        $sub[$k] = PiiCipher::decrypt($sub[$k]);
                        $n++;
                    }
                }
                $raw[$attr] = $sub;
            }

            foreach ($def['list'] ?? [] as $attr => $keys) {
                $items = $this->asArray($raw[$attr] ?? null) ?? [];
                foreach ($items as $i => $item) {
                    $item = $this->asArray($item) ?? [];
                    foreach ($keys as $k) {
                        if (is_string($item[$k] ?? null) && PiiCipher::looksEncrypted($item[$k])) {
                            $item[$k] = PiiCipher::decrypt($item[$k]);
                            $n++;
                        }
                    }
                    $items[$i] = $item;
                }
                $raw[$attr] = array_values($items);
            }

            if ($n > 0) {
                $doc->setRawAttributes($raw);
            }

            return $n;
        });
    }

    /* ------------------------------------------------------------ internals */

    /**
     * @param  callable(Model, array): int  $change  returns the number of fields it changed
     * @return array<string, array{docs: int, changed: int, fields: int}>
     */
    private function rewrite(bool $write, callable $change): array
    {
        $out = [];

        foreach (self::map() as $name => $def) {
            $stat = ['docs' => 0, 'changed' => 0, 'fields' => 0];

            foreach ($this->cursor($def['model']) as $doc) {
                $stat['docs']++;
                $n = $change($doc, $def);
                if ($n === 0) {
                    continue;
                }
                $stat['changed']++;
                $stat['fields'] += $n;

                if ($write) {
                    // A migration is not an edit: no "Client updated"
                    // notifications, and updated_at keeps its real meaning.
                    $doc->timestamps = false;
                    $doc->saveQuietly();
                }
            }

            $out[$name] = $stat;
        }

        return $out;
    }

    /** Every document, trashed or not, of every tenant or just the one asked for. */
    private function cursor(string $model): iterable
    {
        $q = $model::withoutGlobalScopes();
        if ($this->companyId !== null) {
            $q->where('company_id', $this->companyId);
        }

        return $q->cursor();
    }

    /** "path" => raw value for each personal leaf of one document. */
    private function leaves(Model $doc, array $def): iterable
    {
        return $this->leavesFor($def, $doc->getAttributes());
    }

    private function leavesFor(array $def, array $raw): \Generator
    {
        foreach ($def['fields'] as $f) {
            yield $f => $raw[$f] ?? null;
        }
        foreach ($def['nested'] ?? [] as $attr => $keys) {
            $sub = $this->asArray($raw[$attr] ?? null) ?? [];
            foreach ($keys as $k) {
                yield "{$attr}.{$k}" => $sub[$k] ?? null;
            }
        }
        foreach ($def['list'] ?? [] as $attr => $keys) {
            foreach ($this->asArray($raw[$attr] ?? null) ?? [] as $i => $item) {
                $item = $this->asArray($item) ?? [];
                foreach ($keys as $k) {
                    yield "{$attr}.{$i}.{$k}" => $item[$k] ?? null;
                }
            }
        }
    }

    private function isCovered(array $def, string $genericPath): bool
    {
        if (in_array($genericPath, $def['fields'], true)) {
            return true;
        }
        foreach ($def['nested'] ?? [] as $attr => $keys) {
            foreach ($keys as $k) if ($genericPath === "{$attr}.{$k}") return true;
        }
        foreach ($def['list'] ?? [] as $attr => $keys) {
            foreach ($keys as $k) if ($genericPath === "{$attr}.*.{$k}") return true;
        }
        return false;
    }

    private function plainCount($value, array $keys, bool $isList): int
    {
        $items = $isList ? ($this->asArray($value) ?? []) : [$value];
        $n = 0;
        foreach ($items as $item) {
            $item = $this->asArray($item) ?? [];
            foreach ($keys as $k) {
                $v = $item[$k] ?? null;
                if (is_string($v) && $v !== '' && ! PiiCipher::looksEncrypted($v)) $n++;
            }
        }
        return $n;
    }

    private function walk($value, string $path, callable $visit): void
    {
        $arr = $this->asArray($value);
        if ($arr === null) {
            $visit($path, $value);
            return;
        }
        foreach ($arr as $k => $v) {
            $this->walk($v, $path === '' ? (string) $k : "{$path}.{$k}", $visit);
        }
    }

    /** Array, BSON document or legacy JSON-string snapshot as an array; null for a scalar. */
    private function asArray($value): ?array
    {
        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && ($value[0] ?? '') === '{') {
            $d = json_decode($value, true);
            return is_array($d) ? $d : null;
        }
        return null;
    }
}
