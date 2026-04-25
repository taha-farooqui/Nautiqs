<x-app-layout :title="'Edit ' . $client->full_name" :header="'Edit ' . $client->full_name">
    <div class="max-w-3xl">
        @include('clients._form', ['client' => $client])
    </div>
</x-app-layout>
