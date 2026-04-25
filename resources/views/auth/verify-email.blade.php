<x-guest-layout title="Verify email · Nautiqs">
    <div class="max-w-md">
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Verify your email</h1>
        <p class="text-gray-500 mb-6">
            Thanks for signing up! Please click the link we just emailed to you to verify your address.
            Didn't receive it? We'll gladly send another.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-6 text-sm text-green-600 font-medium">
                A new verification link has been sent to your email.
            </div>
        @endif

        <div class="flex items-center justify-between gap-3">
            <form method="POST" action="{{ route('verification.send') }}" class="flex-1">
                @csrf
                <button type="submit"
                    class="w-full bg-primary-800 hover:bg-primary-900 text-white font-semibold py-3 rounded-lg transition">
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">
                    Log out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
