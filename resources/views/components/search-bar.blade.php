{{--
    Search bar of a list page (GET form, works without JavaScript).

    <x-search-bar :action="route('admin.vehicles.index')" :value="$search" placeholder="Nom ou immatriculation">
        <x-select name="statut" … />   (optional extra filters)
    </x-search-bar>
--}}
@props([
    'action',
    'value' => '',
    'placeholder' => 'Rechercher',
])

<form method="GET" action="{{ $action }}" role="search" data-no-loading {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:items-end') }}>
    <div class="min-w-0 flex-1">
        <x-input name="q" label="Rechercher" icon="search" :value="$value" :placeholder="$placeholder" />
    </div>
    {{ $slot }}
    <div class="flex gap-2">
        <x-button type="submit" variant="secondary" icon="search">Rechercher</x-button>
        @if (filled($value) || request()->except('q', 'page') !== [])
            <x-button :href="$action" variant="ghost" icon="x">Effacer</x-button>
        @endif
    </div>
</form>
