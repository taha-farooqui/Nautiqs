<x-app-layout :title="$lead->displayName()" :header="$lead->displayName()">
    <div class="mb-4">
        <a href="{{ route('leads.index') }}" class="text-sm text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Sales leads') }}</a>
    </div>
    @include('leads._form')
</x-app-layout>
