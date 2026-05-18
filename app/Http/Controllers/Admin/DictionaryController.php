<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Superadmin Dictionary — search every translation key in the app, edit
 * the FR/EN value, reset to file default. Powered by the file-based
 * translations in lang/{locale}.json as the canonical source, with DB
 * overrides from the Translation collection.
 *
 * Spec: user-requested feature. Goal is to let the platform owner tweak
 * any user-facing word without redeploying the app.
 */
class DictionaryController extends Controller
{
    private const PER_PAGE = 50;
    private const LOCALES  = ['fr', 'en'];

    public function index(Request $request)
    {
        $locale = $request->query('locale', 'fr');
        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'fr';
        }

        $q      = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all'); // all | customised | defaults
        $page   = max(1, (int) $request->query('page', 1));

        // Canonical source: load every key from the locale's JSON file.
        // For EN we use the keys themselves as the default values (Laravel
        // convention — English source strings double as keys).
        $fileMap = $this->loadFileTranslations($locale);

        // DB overrides for this locale.
        $overrides = Translation::where('locale', $locale)
            ->get(['key', 'value', 'updated_at', 'updated_by'])
            ->keyBy('key');

        // Merge into a list of rows the view consumes.
        $allKeys = collect($fileMap)->keys()
            ->merge($overrides->keys())
            ->unique()
            ->values();

        $rows = $allKeys->map(function ($key) use ($fileMap, $overrides) {
            $override = $overrides->get($key);
            return [
                'key'        => $key,
                'default'    => $fileMap[$key] ?? $key,
                'current'    => $override?->value ?? ($fileMap[$key] ?? $key),
                'customised' => $override !== null,
                'updated_at' => $override?->updated_at,
            ];
        });

        // Search filter — substring match on key OR value (case-insensitive).
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower($r['key']), $needle)
                    || str_contains(mb_strtolower($r['default']), $needle)
                    || str_contains(mb_strtolower($r['current']), $needle);
            });
        }

        // Filter — customised only / defaults only.
        if ($filter === 'customised') {
            $rows = $rows->where('customised', true);
        } elseif ($filter === 'defaults') {
            $rows = $rows->where('customised', false);
        }

        $rows = $rows->sortBy(fn ($r) => mb_strtolower($r['key']))->values();
        $total = $rows->count();
        $rows  = $rows->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();

        $stats = [
            'total_keys'     => count($fileMap),
            'customised'     => $overrides->count(),
        ];

        return view('admin.dictionary.index', compact(
            'rows', 'total', 'q', 'locale', 'filter', 'page', 'stats'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'key'    => 'required|string|max:500',
            'locale' => 'required|in:fr,en',
            'value'  => 'required|string|max:2000',
        ]);

        $fileMap = $this->loadFileTranslations($data['locale']);
        $default = $fileMap[$data['key']] ?? $data['key'];

        // Guard against breaking interpolation: if the file default has
        // ":variable" placeholders, the override must include the same set.
        $originalVars  = $this->extractPlaceholders($default);
        $candidateVars = $this->extractPlaceholders($data['value']);
        $missing = array_diff($originalVars, $candidateVars);
        if (! empty($missing)) {
            return back()->withErrors([
                'value' => 'Translation is missing required placeholder(s): :' . implode(', :', $missing),
            ])->withInput();
        }

        $row = Translation::updateOrCreate(
            ['key' => $data['key'], 'locale' => $data['locale']],
            [
                'value'      => $data['value'],
                'updated_by' => (string) auth()->user()->_id,
                'updated_at' => now(),
            ]
        );

        AuditLogger::record(
            'translation.update',
            target: $row,
            before: ['value' => $default],
            after:  ['value' => $data['value']],
            targetLabel: "[{$data['locale']}] {$data['key']}",
        );

        return back()->with('status', __('Translation saved.'));
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'key'    => 'required|string|max:500',
            'locale' => 'required|in:fr,en',
        ]);

        $row = Translation::where('key', $data['key'])->where('locale', $data['locale'])->first();
        if ($row) {
            AuditLogger::record(
                'translation.reset',
                target: $row,
                before: ['value' => $row->value],
                after:  null,
                targetLabel: "[{$data['locale']}] {$data['key']}",
            );
            $row->delete();
        }

        return back()->with('status', __('Translation reset to default.'));
    }

    public function export(Request $request)
    {
        $locale = $request->query('locale', 'fr');
        $rows = Translation::where('locale', $locale)
            ->orderBy('key')
            ->get(['key', 'value', 'updated_at']);

        $filename = "nautiqs-translations-{$locale}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['key', 'value', 'updated_at']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->key, $r->value, $r->updated_at?->toIso8601String() ?? '']);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Load every key/value from lang/{locale}.json. For EN the file usually
     * holds only a placeholder comment — English source strings live in the
     * Blade/PHP code itself as the keys — so we seed the EN dictionary from
     * FR's key list to make both locales searchable.
     */
    private function loadFileTranslations(string $locale): array
    {
        $loaded = [];
        $path = lang_path("{$locale}.json");
        if (File::exists($path)) {
            $loaded = json_decode(File::get($path), true) ?: [];
            // Skip lines starting with _ (we use _comment as a file header).
            $loaded = array_filter($loaded, fn ($_, $k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_BOTH);
        }

        // For EN, also seed from FR's key list so the view shows every key
        // that exists in the app, not just the few explicitly stored in
        // en.json. The default value is the key itself.
        if ($locale === 'en') {
            $frPath = lang_path('fr.json');
            if (File::exists($frPath)) {
                $fr = json_decode(File::get($frPath), true) ?: [];
                foreach ($fr as $key => $_) {
                    if (str_starts_with((string) $key, '_')) continue;
                    if (! isset($loaded[$key])) {
                        $loaded[$key] = $key;
                    }
                }
            }
        }

        return $loaded;
    }

    /** Extract :placeholder names from a Laravel translation string. */
    private function extractPlaceholders(string $value): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $m);
        return array_values(array_unique($m[1] ?? []));
    }
}
