@php $editing = $line->exists; @endphp

<x-layouts.admin :title="$editing ? 'Modifier la ligne' : 'Nouvelle ligne'">
    <div class="max-w-2xl space-y-6">
        <x-page-header
            :title="$editing ? 'Modifier la ligne' : 'Nouvelle ligne'"
            :back="$editing ? route('admin.lines.show', $line) : route('admin.lines.index')"
        />

        <x-card>
            <form method="POST" action="{{ $editing ? route('admin.lines.update', $line) : route('admin.lines.store') }}" class="space-y-5">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <x-input name="name" label="Nom de la ligne" :value="$line->name" placeholder="Bonoua – Angré" required autofocus />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="code" label="Code court" :value="$line->code" placeholder="ANGRE" hint="Lettres, chiffres et tirets." required />
                    <x-input name="destination_name" label="Destination" :value="$line->destination_name" hint="Où vont les passagers le matin." required />
                </div>
                <x-textarea name="description" label="Description" :value="$line->description" />
                <x-checkbox name="is_active" :checked="$line->is_active">Ligne active (visible et vendable)</x-checkbox>

                <x-button type="submit" size="lg" block>{{ $editing ? 'Enregistrer' : 'Créer la ligne' }}</x-button>
            </form>
        </x-card>
        @if ($editing)
            @can('delete', $line)
                <x-delete-card name="delete-line" :action="route('admin.lines.destroy', $line)" label="Supprimer la ligne" :title="'Supprimer la ligne '.$line->name.' ?'">
                    Ses zones, points de montée et prix sont supprimés aussi. Si des ventes ont déjà été faites, la ligne est seulement archivée (plus vendue) pour garder l’historique.
                </x-delete-card>
            @endcan
        @endif
    </div>
</x-layouts.admin>
