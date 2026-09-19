<x-admin-layout :title="__('Backups')" :header="__('Backups')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- ─────────────────────────── Health ───────────────────────────
         The first question anyone opening this page has is "are backups
         actually happening?", so it is answered before anything else. --}}
    @php
        $last = $stats['last'];
        $lastOk = $last && $last->status === \App\Models\BackupRun::STATUS_OK;
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border {{ $stats['is_stale'] ? 'border-red-200' : 'border-gray-200' }} p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Last backup') }}</p>
            @if ($last)
                <p class="text-lg font-bold {{ $stats['is_stale'] ? 'text-red-700' : 'text-gray-900' }} mt-1">
                    {{ $last->started_at?->diffForHumans() }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $last->started_at?->translatedFormat('j M Y, H:i') }}
                    @if (! $lastOk)
                        · <span class="text-red-700 font-semibold">{{ __('failed') }}</span>
                    @endif
                </p>
            @else
                <p class="text-lg font-bold text-red-700 mt-1">{{ __('Never') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('No backup has run yet.') }}</p>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('On the server') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ $stats['count'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('archive(s)') }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Space used') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ $service->humanBytes($stats['disk_bytes']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('by backups') }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Disk free') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ $service->humanBytes($stats['free_bytes']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('on the server') }}</p>
        </div>
    </div>

    @if ($stats['is_stale'] && $last)
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <p class="font-semibold">{{ __('Backups are not running as expected.') }}</p>
            <p class="mt-1">
                {{ __('The last successful backup is older than :hours hours. Check that the scheduler cron is still running on the server.', ['hours' => config('backup.stale_after_hours')]) }}
            </p>
            @if ($last->status === \App\Models\BackupRun::STATUS_FAILED && $last->error)
                <p class="mt-2 text-xs">{{ __('Last error') }}: {{ $last->error }}</p>
            @endif
        </div>
    @endif

    {{-- ──────────────────── Take one now + how it works ──────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Back up now') }}</h3>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('Runs automatically at 00:00 and 12:30 (Paris). Each archive holds the whole database, the uploaded logos and the server configuration.') }}
                </p>
            </div>
            <form method="POST" action="{{ route('admin.backups.store') }}" class="shrink-0">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm">
                    <i class="ri-save-3-line"></i> {{ __('Back up now') }}
                </button>
            </form>
        </div>
    </div>

    {{-- ─────────────────────── The archive cycle ───────────────────────
         The server keeps the last :retention days. Older archives are handed
         over here and only then removed, so space is reclaimed without any
         backup being destroyed before a copy exists elsewhere. --}}
    @if ($archivable->isNotEmpty())
        <div class="bg-white rounded-2xl border border-amber-200 p-5 mb-6">
            <div class="flex items-start gap-3 mb-3">
                <i class="ri-archive-line text-amber-700"></i>
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900">
                        {{ __(':count archive(s) are older than :days days', ['count' => $archivable->count(), 'days' => $retention]) }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Download them to your computer, then free the space on the server. Nothing is deleted until you have downloaded it.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.backups.download-archivable') }}"
                    class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm">
                    <i class="ri-download-2-line"></i>
                    {{ __('Download all :count (:size)', [
                        'count' => $archivable->count(),
                        'size'  => $service->humanBytes((int) $archivable->sum('size_bytes')),
                    ]) }}
                </a>

                @if ($prunable->isNotEmpty())
                    <form method="POST" action="{{ route('admin.backups.prune') }}"
                        data-confirm="{{ __('Free up space on the server?') }}"
                        data-confirm-text="{{ __('This permanently deletes :count downloaded archive(s) from the server. Make sure your downloaded copy is safe.', ['count' => $prunable->count()]) }}"
                        data-confirm-danger="1">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm">
                            <i class="ri-delete-bin-line"></i>
                            {{ __('Free up space (:count downloaded)', ['count' => $prunable->count()]) }}
                        </button>
                    </form>
                @else
                    <span class="text-xs text-gray-500">{{ __('Download them first — then this is where you free the space.') }}</span>
                @endif
            </div>
        </div>
    @endif

    {{-- ──────────────────────────── History ──────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">{{ __('History') }}</h3>
            <p class="text-xs text-gray-500">
                {{ __('Every run is kept here, including the ones whose file has been removed — so the record of what was taken survives the archive itself.') }}
            </p>
        </div>

        @if ($runs->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-database-2-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No backups yet.') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('The next scheduled run is at 00:00 or 12:30 Paris time, or press Back up now.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('When') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Archive') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Size') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($runs as $run)
                            @php
                                $isProtected = in_array((string) $run->_id, $protectedIds, true);
                                $available   = $run->isAvailable();
                            @endphp
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="text-gray-900">{{ $run->started_at?->translatedFormat('j M Y, H:i') }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $run->trigger === \App\Models\BackupRun::TRIGGER_MANUAL ? __('Manual') : __('Scheduled') }}
                                        @if ($run->started_by) · {{ $run->started_by }} @endif
                                    </div>
                                </td>

                                <td class="px-5 py-3">
                                    <div class="text-gray-900 truncate max-w-[220px]">{{ $run->filename ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">
                                        @if ($run->document_count)
                                            {{ __(':count documents', ['count' => number_format($run->document_count, 0, ',', ' ')]) }}
                                        @endif
                                        @if (is_array($run->contents))
                                            @php
                                                $parts = [];
                                                if ($run->contents['database'] ?? false) $parts[] = __('database');
                                                if ($run->contents['storage']  ?? false) $parts[] = __('files');
                                                if ($run->contents['env']      ?? false) $parts[] = __('config');
                                            @endphp
                                            @if ($parts) · {{ implode(' + ', $parts) }} @endif
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ $run->humanSize() }}</td>

                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($run->status === \App\Models\BackupRun::STATUS_FAILED)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold">
                                            <i class="ri-close-circle-line"></i> {{ __('Failed') }}
                                        </span>
                                        @if ($run->error)
                                            <div class="text-xs text-gray-500 mt-1 truncate max-w-[220px]" title="{{ $run->error }}">{{ $run->error }}</div>
                                        @endif
                                    @elseif ($run->status === \App\Models\BackupRun::STATUS_RUNNING)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                                            <i class="ri-loader-4-line"></i> {{ __('Running') }}
                                        </span>
                                    @elseif ($run->deleted_at)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                                            <i class="ri-archive-line"></i> {{ __('Removed from server') }}
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ __('Downloaded :when', ['when' => $run->downloaded_at?->translatedFormat('j M Y') ?: '—']) }}
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                            <i class="ri-checkbox-circle-line"></i> {{ __('On server') }}
                                        </span>
                                        @if ($run->downloaded_at)
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ __('Downloaded :when', ['when' => $run->downloaded_at->translatedFormat('j M Y')]) }}
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                <td class="px-5 py-3 whitespace-nowrap text-right">
                                    @if ($available)
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('admin.backups.download', $run->_id) }}"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">
                                                <i class="ri-download-2-line"></i> {{ __('Download') }}
                                            </a>

                                            @if ($isProtected)
                                                {{-- The newest few archives are the live restore points; the
                                                     service refuses to delete them however it is called. --}}
                                                <span class="text-xs text-gray-400 px-2" title="{{ __('Kept as a recent restore point') }}">
                                                    <i class="ri-lock-line"></i>
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('admin.backups.destroy', $run->_id) }}" class="inline"
                                                    data-confirm="{{ __('Delete this archive?') }}"
                                                    data-confirm-text="{{ __('The file is removed from the server permanently. This cannot be undone.') }}"
                                                    data-confirm-danger="1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded-lg"
                                                        title="{{ __('Delete') }}">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($runs->hasPages())
                <div class="px-5 py-3 border-t border-gray-100">{{ $runs->links() }}</div>
            @endif
        @endif
    </div>

</x-admin-layout>
