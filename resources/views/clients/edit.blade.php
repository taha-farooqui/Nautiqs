<x-app-layout :title="__('Edit') . ' ' . $client->full_name" :header="__('Edit') . ' ' . $client->full_name">
    <div class="max-w-3xl">
        @include('clients._form', ['client' => $client])
    </div>
</x-app-layout>
