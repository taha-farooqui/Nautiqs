@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
        <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach (array_slice($errors->all(), 0, 6) as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif
