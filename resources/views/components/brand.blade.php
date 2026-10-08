{{--
    App logo + name.  <x-brand />   <x-brand tone="light" :name="false" />
--}}
@props([
    'tone' => 'dark',
    'name' => true,
])

<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <span class="flex size-11 items-center justify-center rounded-full bg-slate-950 text-white">
        <x-lucide-bus class="size-5" aria-hidden="true" />
    </span>
    @if ($name)
        <span @class(['text-lg font-medium tracking-tight', 'text-slate-900' => $tone === 'dark', 'text-white' => $tone === 'light'])>
            {{ config('app.name') }}
        </span>
    @endif
</span>
