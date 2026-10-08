{{--
    Simple error page: what happened, in plain words, and one way out.
    No dependency on the database or the logged-in user (works on a 500).
--}}
@props([
    'code',
    'title',
    'icon' => 'circle-alert',
    'tone' => 'warning',
])

@php
    $tones = [
        'warning' => 'bg-warning-100 text-warning-700',
        'danger' => 'bg-danger-100 text-danger-700',
        'primary' => 'bg-primary-100 text-primary-700',
    ];
@endphp

<x-layouts.base :title="$title">
    <main id="main" class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-6 px-4 py-12 text-center">
        <span class="flex size-20 items-center justify-center rounded-full {{ $tones[$tone] ?? $tones['warning'] }}">
            <x-dynamic-component :component="'lucide-'.$icon" class="size-10" aria-hidden="true" />
        </span>

        <div class="space-y-2">
            <p class="text-sm font-semibold text-slate-500">Erreur {{ $code }}</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
            <p class="text-base text-slate-600">{{ $slot }}</p>
        </div>

        <div class="flex w-full flex-col gap-3">
            {{ $actions ?? '' }}
            <x-button :href="url('/')" variant="secondary" size="lg" block icon="house">Retour à l’accueil</x-button>
        </div>
    </main>
</x-layouts.base>
