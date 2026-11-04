{{-- Figma "02 / Demander un accès / Light"; once sent, "03 / Demande envoyée". --}}
<x-guest-layout :title="__('Request access') . ' · Nautiqs'">
    @if (session('status'))
        <x-auth.heading :title="__('Request sent')">{{ session('status') }}</x-auth.heading>
        <x-auth.button :href="route('login')" variant="secondary">{{ __('Back to log in') }}</x-auth.button>
    @else
        <x-auth.heading :title="__('Request your access')">{{ __('Tell us about your company to join Nautiqs.') }}</x-auth.heading>

        <form method="POST" action="{{ route('account-request.store') }}" class="flex flex-col gap-6">
            @csrf

            {{-- Honeypot — hidden from humans, tempting for bots. --}}
            <div style="position:absolute; left:-9999px;" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off" />
            </div>

            <div class="flex flex-col gap-5">
                <x-auth.field :label="__('Full name')" name="name" :value="old('name')"
                    :placeholder="__('First and last name')" required autofocus autocomplete="name" />
                <x-auth.field :label="__('Company')" name="company_name" :value="old('company_name')"
                    :placeholder="__('Your company name')" required autocomplete="organization" />
                <x-auth.field :label="__('Work email')" name="email" type="email" :value="old('email')"
                    :placeholder="__('name@company.com')" required autocomplete="email" />
                <x-auth.select :label="__('Company type')" name="company_type" :value="old('company_type', 'dealer')"
                    :options="\App\Models\AccountRequest::companyTypes()" required />
            </div>

            <div class="flex flex-col gap-4">
                <x-auth.button>{{ __('Request access') }}</x-auth.button>
                <p class="text-xs leading-5 text-nq-text-2">{{ __('Your request will be reviewed before your access is activated.') }}</p>
            </div>
        </form>

        <x-auth.link :href="route('login')">{{ __('Already have an account? Log in') }}</x-auth.link>
    @endif
</x-guest-layout>
