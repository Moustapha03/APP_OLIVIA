{{--
    <x-page-header title="Acheter un ticket" subtitle="Choisissez votre arrêt" :back="route('espace.home')">
        <x-slot:actions>…</x-slot:actions>
    </x-page-header>
--}}
@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'backLabel' => 'Retour',
])

<header {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0 space-y-2">
        @if ($back)
            <a href="{{ $back }}" class="-ml-2 inline-flex min-h-11 items-center gap-1 rounded-full px-3 text-base font-medium text-slate-600 hover:bg-white hover:text-slate-950">
                <x-lucide-chevron-left class="size-5" aria-hidden="true" />
                {{ $backLabel }}
            </a>
        @endif
        <h1 class="text-3xl font-medium tracking-tight text-slate-950 sm:text-4xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-base text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset
</header>
