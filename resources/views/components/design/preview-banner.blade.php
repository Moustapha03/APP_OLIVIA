{{-- Banner shown on layout previews of the /design page. --}}
@props([
    'tone' => 'light',
])

<div @class([
    'mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl px-4 py-3 text-sm',
    'bg-warning-50 text-warning-800 ring-1 ring-inset ring-warning-100' => $tone === 'light',
    'bg-white/10 text-slate-100' => $tone === 'dark',
])>
    <span class="inline-flex items-center gap-2 font-medium">
        <x-lucide-eye class="size-5" aria-hidden="true" />
        Aperçu de la charte · données fictives
    </span>
    <a href="{{ route('design.index') }}#layouts" class="inline-flex min-h-10 items-center gap-1 rounded-xl px-2 font-semibold underline underline-offset-4">
        <x-lucide-chevron-left class="size-4" aria-hidden="true" />
        Retour à la charte
    </a>
</div>
