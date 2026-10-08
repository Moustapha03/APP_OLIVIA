@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
    $paidOnly = $filters['status'] === 'paid';
@endphp

<x-layouts.admin title="Paiements et reçus">
    <div class="space-y-6">
        <x-page-header title="Paiements et reçus" subtitle="L’historique de chaque transaction. Cliquez sur un N° pour voir le détail, le QR code et réimprimer le reçu.">
            <x-slot:actions>
                @can('tickets.sell')
                    <x-button :href="route('guichet.subscriptions.create')" icon="banknote">Encaisser un paiement</x-button>
                @endcan
                <x-button :href="route('admin.receipts.export', request()->query())" variant="secondary" icon="download">Exporter (CSV Excel)</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <form method="GET" action="{{ route('admin.receipts.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-[repeat(5,minmax(0,1fr))_minmax(0,1.4fr)_auto] xl:items-end" data-no-loading>
                <x-select name="month" label="Mois" :options="$months" :value="$filters['month'] ?? ''" />
                <x-select name="status" label="Statut" :options="['paid' => 'Payés', 'cancelled' => 'Annulés', 'unpaid' => 'En attente ou échoués', 'all' => 'Tous']" :value="$filters['status']" />
                <x-select name="kind" label="Type" :options="['subscriptions' => 'Abonnements', 'tickets' => 'Tickets du jour', 'all' => 'Tout']" :value="$filters['kind']" />
                <x-select name="method" label="Paiement" :options="['' => 'Tous'] + collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" :value="$filters['method'] ?? ''" />
                <x-select name="zone" label="Zone" :options="['' => 'Toutes'] + $zones->mapWithKeys(fn ($z) => [$z->id => $z->name])->all()" :value="$filters['zone'] ?? ''" />
                <x-input name="q" label="Rechercher" icon="search" :value="$filters['q']" placeholder="Nom, téléphone, N° de reçu, réf." />
                <x-button type="submit" variant="secondary" icon="filter">Filtrer</x-button>
            </form>
        </x-card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat :label="$paidOnly ? 'Total encaissé' : 'Total'" icon="banknote" :hint="$count.' transaction(s)'"><x-money :amount="$grandTotal" /></x-stat>
            @foreach ($totals as $row)
                <x-stat :label="$row->payment_method?->label() ?? '—'" icon="wallet" :hint="$row->n.' transaction(s)'"><x-money :amount="(int) $row->total" /></x-stat>
            @endforeach
        </div>

        <x-table caption="Transactions" empty="Aucune transaction pour ces critères.">
            <x-slot:head>
                <th>N°</th><th>Date</th><th>Client</th><th>Téléphone</th><th>Point de montée</th><th>Mois</th><th>Formule</th><th>Paiement</th><th>Réf.</th>
                @unless ($paidOnly)<th>Statut</th>@endunless
                <th class="text-right">Montant</th>
            </x-slot:head>
            @foreach ($receipts as $receipt)
                <tr>
                    <td class="whitespace-nowrap font-mono text-sm">
                        <a href="{{ route('admin.receipts.show', $receipt) }}" class="text-primary-700 hover:underline">{{ $receipt->receipt_number ?? $receipt->reference }}</a>
                    </td>
                    <td class="whitespace-nowrap tabular-nums">{{ ($receipt->confirmed_at ?? $receipt->created_at)->format('d/m/Y') }}</td>
                    <td class="font-medium">{{ $receipt->subscriber?->full_name ?? 'Ticket du jour' }}</td>
                    <td class="whitespace-nowrap tabular-nums">{{ $receipt->subscriber?->formatted_phone }}</td>
                    <td>{{ $receipt->stop?->name ?? $receipt->zone->name }}</td>
                    <td class="whitespace-nowrap">{{ $receipt->period_month ? ucfirst($receipt->period_month->translatedFormat('F Y')) : '—' }}</td>
                    <td class="whitespace-nowrap">{{ $receipt->purpose->shortLabel() }}</td>
                    <td>{{ $receipt->payment_method?->label() }}</td>
                    <td class="font-mono text-xs">{{ $receipt->external_reference }}</td>
                    @unless ($paidOnly)
                        <td><x-badge :variant="match ($receipt->status) { PaymentStatus::Succeeded => 'success', PaymentStatus::Cancelled => 'danger', default => 'warning' }">{{ $receipt->status->label() }}</x-badge></td>
                    @endunless
                    <td class="text-right">
                        <x-money :amount="$receipt->amount" class="font-semibold" />
                        @if ($receipt->isDiscounted())
                            <span class="block text-xs text-warning-700" title="{{ $receipt->discount_reason }}">grille {{ Money::format($receipt->list_amount) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($receipts->hasPages())
                <x-slot:footer>{{ $receipts->links() }}</x-slot:footer>
            @endif
        </x-table>
    </div>
</x-layouts.admin>
