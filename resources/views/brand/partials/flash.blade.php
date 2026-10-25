@if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-transition.opacity
        class="pop mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm flex items-start gap-2">
        <i class="ri-checkbox-circle-fill text-lg text-emerald-500"></i>
        <span class="flex-1 pt-0.5">{{ session('status') }}</span>
        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800" aria-label="{{ __('Close') }}"><i class="ri-close-line"></i></button>
    </div>
@endif
@if ($errors->any())
    <div class="pop mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm flex items-start gap-2">
        <i class="ri-error-warning-fill text-lg text-red-500"></i>
        <ul class="flex-1 pt-0.5 space-y-0.5">
            @foreach (array_slice($errors->all(), 0, 6) as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif
