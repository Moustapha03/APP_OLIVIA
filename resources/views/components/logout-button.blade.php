{{--
    Logout is a POST (CSRF protected), never a GET link.
    <x-logout-button />   <x-logout-button tone="light" />
--}}
@props([
    'tone' => 'dark',
])

@if (Route::has('logout'))
    <form method="POST" action="{{ route('logout') }}" data-logout data-no-loading {{ $attributes }}>
        @csrf
        <button type="submit" @class([
            'inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-sm font-medium transition',
            'text-slate-700 hover:bg-slate-100' => $tone === 'dark',
            'text-slate-200 hover:bg-white/10' => $tone === 'light',
        ])>
            <x-lucide-log-out class="size-5" aria-hidden="true" />
            Se déconnecter
        </button>
    </form>
@endif
