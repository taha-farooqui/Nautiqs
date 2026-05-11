<x-app-layout :title="__('New client')" :header="__('New client')">
    <div class="max-w-3xl">
        @include('clients._form', ['client' => $client])
    </div>
</x-app-layout>
