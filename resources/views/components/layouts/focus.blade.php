{{--
    One centred task, nothing else: login, sign-up, passwords.

    <x-layouts.focus title="Connexion" heading="Se connecter" subtitle="…">…</x-layouts.focus>
--}}
@props([
    'title' => null,
    'heading' => null,
    'subtitle' => null,
])

<x-layouts.base :title="$title">
    <div class="flex min-h-dvh flex-col">
        <header class="mx-auto flex h-16 w-full max-w-md items-center px-4">
            <a href="{{ route('home') }}" class="rounded-xl" aria-label="{{ config('app.name') }} – accueil">
                <x-brand />
            </a>
        </header>

        <main id="main" class="mx-auto w-full max-w-md flex-1 px-4 pb-12 pt-4">
            <x-flash class="mb-6" />

            <div class="rounded-3xl bg-white p-6 shadow-soft ring-1 ring-slate-200/70 sm:p-8">
                @if ($heading)
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $heading }}</h1>
                @endif
                @if ($subtitle)
                    <p class="mt-1 text-base text-slate-600">{{ $subtitle }}</p>
                @endif

                <div @class(['mt-6' => $heading || $subtitle])>
                    {{ $slot }}
                </div>
            </div>

            @isset($after)
                <div class="mt-6 text-center text-base text-slate-700">{{ $after }}</div>
            @endisset
        </main>
    </div>
</x-layouts.base>
