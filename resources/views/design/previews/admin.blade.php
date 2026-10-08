@php
    $statusBadges = [
        'paid' => ['success', 'Payé', 'circle-check'],
        'pending' => ['warning', 'En attente', 'clock'],
        'cancelled' => ['danger', 'Annulé', 'circle-x'],
    ];
@endphp

<x-layouts.admin title="Aperçu · Administration" :preview="true">
    <x-design.preview-banner />

    <div class="space-y-8">
        <x-page-header title="Tableau de bord" subtitle="Dimanche 5 octobre">
            <x-slot:actions>
                <x-button variant="secondary" icon="download">Exporter</x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Recettes du jour" icon="banknote" hint="+8 % par rapport à dimanche dernier"><x-money :amount="185500" /></x-stat>
            <x-stat label="Titres vendus" value="142" icon="ticket" hint="dont 18 abonnements" />
            <x-stat label="Abonnés actifs" value="214" icon="users" variant="success" />
            <x-stat label="Fraudes détectées" value="5" icon="shield-alert" variant="danger" hint="QR re-scannés" />
        </div>

        <x-table caption="Dernières ventes">
            <x-slot:head>
                <th>Heure</th><th>Client</th><th>Arrêt</th><th>Paiement</th><th class="text-right">Montant</th><th>Statut</th>
            </x-slot:head>
            @foreach ($sales as $sale)
                @php [$variant, $label, $icon] = $statusBadges[$sale['status']]; @endphp
                <tr>
                    <td class="tabular-nums">{{ $sale['time'] }}</td>
                    <td class="font-medium">{{ $sale['customer'] }}</td>
                    <td>{{ $sale['stop'] }}</td>
                    <td>{{ $sale['channel'] }}</td>
                    <td class="text-right font-semibold"><x-money :amount="$sale['amount']" /></td>
                    <td><x-badge :variant="$variant" :icon="$icon">{{ $label }}</x-badge></td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-layouts.admin>
