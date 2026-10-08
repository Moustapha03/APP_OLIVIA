{{--
    Public pages: home (stops + fares), login, sign-up.
    <x-layouts.public title="Accueil">…</x-layouts.public>
--}}
@props([
    'title' => null,
])

<x-layouts.base :title="$title">
    <div class="flex min-h-dvh flex-col">
        <header class="border-b border-slate-200/70 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4">
                <a href="{{ route('home') }}" class="rounded-xl" aria-label="{{ config('app.name') }} – accueil">
                    <x-brand />
                </a>

                @if (Route::has('login'))
                    @auth
                        <x-button :href="route('dashboard')" variant="ghost" icon="user-round">Mon espace</x-button>
                    @else
                        <x-button :href="route('login')" variant="ghost" icon="log-in">Se connecter</x-button>
                    @endauth
                @endif
            </div>
        </header>

        <main id="main" {{ $attributes->class('mx-auto w-full max-w-5xl flex-1 px-4 py-8') }}>
            <x-flash class="mb-6" />
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200/70 bg-white">
            <div class="mx-auto flex max-w-5xl flex-col gap-1 px-4 py-6 text-sm text-slate-600 sm:flex-row sm:justify-between">
                <p>© {{ now()->year }} {{ config('app.name') }}</p>
                <p>Abidjan · Anani · Grand-Bassam · Bonoua</p>
            </div>
        </footer>
    </div>
</x-layouts.base>
