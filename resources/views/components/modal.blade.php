{{--
    Accessible modal: focus trapped, Escape closes, page behind is inert.
    Bottom sheet on phones, centred dialog from 640 px.

    <x-modal name="help" title="Comment ça marche ?">…<x-slot:footer>…</x-slot:footer></x-modal>

    Open it from:
      - any element:   <button data-modal-open="help">        (no Alpine needed)
      - Alpine:        $dispatch('open-modal', 'help')
    Close it with:     $dispatch('close') inside, or $dispatch('close-modal', 'help')
--}}
@props([
    'name',
    'title' => null,
    'show' => false,
    'maxWidth' => 'lg',
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
    $titleId = 'modal-'.$name.'-title';
@endphp

<div
    x-data="modal"
    data-modal-name="{{ $name }}"
    data-show="{{ $show ? 'true' : 'false' }}"
    x-on:open-modal.window="openFor($event.detail)"
    x-on:close-modal.window="closeFor($event.detail)"
    x-on:close.stop="close"
    x-on:keydown.escape.window="close"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="{{ $titleId }}" @endif
>
    <div
        x-show="show"
        x-on:click="close"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-950/50"
        aria-hidden="true"
    ></div>

    <div
        x-show="show"
        x-trap.inert.noscroll="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave-end="translate-y-8 opacity-0 sm:translate-y-0 sm:scale-95"
        {{ $attributes->class(['relative max-h-[92vh] w-full overflow-y-auto rounded-t-card bg-white shadow-lifted sm:rounded-card', $widths[$maxWidth] ?? $widths['lg']]) }}
    >
        {{-- Grab handle: tells phone users this is a sheet --}}
        <div class="flex justify-center pt-3 sm:hidden" aria-hidden="true">
            <span class="h-1.5 w-12 rounded-full bg-slate-300"></span>
        </div>

        @if ($title)
            <div class="flex items-start justify-between gap-4 px-6 pt-4 sm:pt-6">
                <h2 id="{{ $titleId }}" class="text-xl font-semibold text-slate-900">{{ $title }}</h2>
                <button type="button" x-on:click="close" class="-m-2 flex size-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-700 hover:bg-slate-100 hover:text-slate-800" aria-label="Fermer">
                    <x-lucide-x class="size-6" aria-hidden="true" />
                </button>
            </div>
        @endif

        <div class="px-6 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:justify-end">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
