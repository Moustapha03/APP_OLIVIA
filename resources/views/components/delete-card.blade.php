{{--
    "Supprimer" block at the bottom of an edit page, with confirmation.

    <x-delete-card name="delete-line" :action="route('admin.lines.destroy', $line)" label="Supprimer la ligne"
                   title="Supprimer la ligne Bonoua – Angré ?">
        Ses zones, points de montée et prix sont supprimés aussi. Si des ventes
        ont déjà été faites, elle est seulement archivée.
    </x-delete-card>
--}}
@props([
    'name',
    'action',
    'label' => 'Supprimer',
    'title',
])

<x-card {{ $attributes }}>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 space-y-1">
            <p class="font-semibold text-slate-900">{{ $label }}</p>
            <p class="text-sm text-slate-600">{{ $slot }}</p>
        </div>
        <x-button variant="danger" icon="trash-2" data-modal-open="{{ $name }}" class="shrink-0">{{ $label }}</x-button>
    </div>

    <x-confirm :name="$name" :title="$title" :action="$action" method="DELETE" :confirm-label="'Oui, '.lcfirst($label)">
        {{ $slot }}
    </x-confirm>
</x-card>
