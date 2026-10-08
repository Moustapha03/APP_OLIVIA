@php use App\Support\PhoneNumber; @endphp

<x-layouts.admin title="Importer des clients">
    <div class="max-w-5xl space-y-6">
        <x-page-header title="Importer une liste de clients" subtitle="Depuis un fichier Excel : nom et numéro de téléphone." :back="route('admin.subscribers.index')" back-label="Clients" />

        @if ($lines === null)
            <x-card title="1. Préparer le fichier">
                <div class="space-y-4 text-base text-slate-700">
                    <p>Une ligne par client, avec ces colonnes (la première ligne peut contenir les titres) :</p>
                    <ul class="space-y-2">
                        <li class="flex gap-3"><x-lucide-user-round class="mt-0.5 size-5 shrink-0 text-primary-600" aria-hidden="true" /><span><strong class="font-medium">Nom et prénom</strong> : obligatoire.</span></li>
                        <li class="flex gap-3"><x-lucide-phone class="mt-0.5 size-5 shrink-0 text-primary-600" aria-hidden="true" /><span><strong class="font-medium">Téléphone</strong> : 10 chiffres, avec ou sans espaces (07 08 26 44 21). Plusieurs clients peuvent avoir le même numéro.</span></li>
                        <li class="flex gap-3"><x-lucide-map-pin class="mt-0.5 size-5 shrink-0 text-primary-600" aria-hidden="true" /><span><strong class="font-medium">Point de montée</strong> : facultatif, écrit comme dans « Lignes, zones et trajets ».</span></li>
                    </ul>
                    <x-button :href="route('admin.subscribers.import.template')" variant="secondary" icon="file-spreadsheet" data-no-loading>Télécharger le modèle Excel</x-button>
                </div>
            </x-card>

            <x-card title="2. Envoyer le fichier" subtitle="Rien n’est enregistré à cette étape : vous verrez d’abord le résultat.">
                <form method="POST" action="{{ route('admin.subscribers.import.preview') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <x-field id="file" label="Fichier Excel ou CSV" :error="$errors->first('file')" hint=".xlsx, .xls ou .csv, 5 Mo maximum." required>
                        <input id="file" name="file" type="file" required accept=".xlsx,.xls,.ods,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" aria-describedby="file-hint file-error"
                            class="block w-full rounded-full bg-white text-base text-slate-700 ring-1 ring-inset ring-slate-200 file:mr-4 file:min-h-12 file:cursor-pointer file:rounded-full file:border-0 file:bg-slate-950 file:px-5 file:font-medium file:text-white">
                    </x-field>
                    <x-button type="submit" size="lg" icon="search-check" loading-text="Lecture du fichier…">Analyser le fichier</x-button>
                </form>
            </x-card>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="À ajouter" icon="user-plus" variant="primary" :value="$counts['new']" hint="Nouveaux clients" />
                <x-stat label="Déjà enregistrés" icon="user-check" variant="neutral" :value="$counts['duplicate']" hint="Ignorés (aucun doublon créé)" />
                <x-stat label="Lignes à corriger" icon="triangle-alert" :variant="$counts['error'] > 0 ? 'warning' : 'neutral'" :value="$counts['error']" hint="Non importées" />
            </div>

            <x-card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-base text-slate-700">
                        Fichier « {{ $file }} ».
                        @if ($counts['new'] > 0)
                            Confirmez pour ajouter <strong class="font-medium text-slate-950">{{ $counts['new'] }} client(s)</strong>.
                        @else
                            Aucun nouveau client à ajouter.
                        @endif
                        @if ($counts['error'] > 0)
                            Corrigez les lignes en erreur dans Excel puis renvoyez le fichier : les clients déjà ajoutés ne seront pas recréés.
                        @endif
                    </p>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <x-button :href="route('admin.subscribers.import')" variant="secondary" icon="file-up">Autre fichier</x-button>
                        @if ($counts['new'] > 0)
                            <form method="POST" action="{{ route('admin.subscribers.import.store') }}">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">
                                <x-button type="submit" icon="user-plus" loading-text="Ajout en cours…">Ajouter {{ $counts['new'] }} client(s)</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-table caption="Contenu du fichier">
                <x-slot:head>
                    <th class="text-right">Ligne</th><th>Résultat</th><th>Nom</th><th>Téléphone</th><th>Point de montée</th><th>Détail</th>
                </x-slot:head>
                @foreach ($lines as $line)
                    <tr>
                        <td class="text-right tabular-nums text-slate-500">{{ $line['line'] }}</td>
                        <td>
                            @switch($line['status'])
                                @case('new') <x-badge variant="success" icon="plus">À ajouter</x-badge> @break
                                @case('duplicate') <x-badge icon="user-check">Déjà enregistré</x-badge> @break
                                @default <x-badge variant="warning" icon="triangle-alert">À corriger</x-badge>
                            @endswitch
                        </td>
                        <td class="font-medium">{{ $line['name'] ?: '—' }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ $line['phone'] ? PhoneNumber::format($line['phone']) : '—' }}</td>
                        <td>{{ $line['stop'] ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $line['message'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-layouts.admin>
