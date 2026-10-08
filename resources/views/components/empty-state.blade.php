{{--
    <x-empty-state icon="ticket" title="Vous n'avez pas encore de ticket">
        Achetez votre premier ticket en moins d'une minute.
        <x-slot:action><x-button :href="…" size="lg" block>Acheter un ticket</x-button></x-slot:action>
    </x-empty-state>
--}}
@props([
    'icon' => 'inbox',
    'title',
])

<div {{ $attributes->class('flex flex-col items-center gap-4 rounded-card border-2 border-dashed border-slate-200 px-6 py-10 text-center') }}>
    <span class="flex size-16 items-center justify-center rounded-full bg-white text-primary-600 ring-1 ring-slate-200">
        <x-dynamic-component :component="'lucide-'.$icon" class="size-8" aria-hidden="true" />
    </span>
    <div class="space-y-1">
        <p class="text-lg font-semibold text-slate-900">{{ $title }}</p>
        @if ($slot->isNotEmpty())
            <p class="text-base text-slate-600">{{ $slot }}</p>
        @endif
    </div>
    @isset($action)
        <div class="w-full max-w-xs">{{ $action }}</div>
    @endisset
</div>
