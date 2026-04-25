<x-app-layout title="New client" header="New client">
    <div class="max-w-3xl">
        @include('clients._form', ['client' => $client])
    </div>
</x-app-layout>
