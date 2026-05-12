<x-app-layout :title="__('New quote')" :header="__('New quote')">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <livewire:quote-builder :preselected-client-id="$preselectedClientId" />
</x-app-layout>
