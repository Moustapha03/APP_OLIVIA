@php
    use App\Support\Money;
    $percent = fn (int $now, int $before): ?int => $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    $growth = $percent($kpis['revenue'], $kpis['previous_revenue']);
    $subscriberDelta = $kpis['active_subscribers'] - $kpis['previous_active_subscribers'];
    $isCurrent = $month->isSameMonth(today());
    $monthLabel = ucfirst($month->translatedFormat('F Y'));
    $cashShare = $kpis['revenue'] > 0 ? (int) round($kpis['cash'] / $kpis['revenue'] * 100) : 0;

    // Renewal: subscribers of last month who also have this month.
    $previous = $kpis['previous_active_subscribers'];
    $renewed = max(0, $previous - $kpis['to_renew']);
    $renewal = $previous > 0 ? (int) round($renewed / $previous * 100) : null;
    $ring = 2 * M_PI * 52;
@endphp

<x-layouts.admin title="Tableau de bord">
    <div class="space-y-6">
        {{-- Header: round date chip, title, month picker --}}
        <header class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex size-20 shrink-0 items-center justify-center rounded-full bg-white text-3xl font-light tabular-nums text-slate-950 ring-1 ring-slate-200/70" aria-hidden="true">{{ now()->format('d') }}</span>
                <div>
                    <h1 class="text-3xl font-medium tracking-tight text-slate-950 sm:text-4xl">Tableau de bord</h1>
                    <p class="text-base text-slate-500">{{ $isCurrent ? ucfirst(now()->translatedFormat('l j F Y')) : $monthLabel }}</p>
                </div>
            </div>
            <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-end gap-2" data-no-loading>
                <x-select name="mois" label="Mois" :options="$monthOptions" :value="$month->format('Y-m')" />
                <x-button type="submit" icon="refresh-cw">Afficher</x-button>
            </form>
        </header>

        {{-- Hero: revenue, renewal ring, subscribers --}}
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <section class="rounded-card bg-white p-6 ring-1 ring-slate-200/60 sm:p-8" aria-labelledby="revenue-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 id="revenue-title" class="text-base text-slate-500">Chiffre d’affaires de {{ $month->translatedFormat('F') }}</h2>
                    @if ($growth !== null)
                        <span @class(['rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset', 'bg-primary-50 text-primary-700 ring-primary-200' => $growth >= 0, 'bg-slate-100 text-slate-700 ring-slate-200' => $growth < 0])>
                            {{ ($growth >= 0 ? '+' : '').$growth }} % vs mois précédent
                        </span>
                    @elseif ($kpis['revenue'] > 0)
                        <span class="rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 ring-1 ring-inset ring-primary-200">Nouveau ce mois</span>
                    @endif
                </div>
                <p class="mt-4 text-4xl font-light tracking-tight text-slate-950 tabular-nums sm:text-6xl"><x-money :amount="$kpis['revenue']" /></p>
                <p class="mt-1 text-sm text-slate-500">{{ $kpis['receipts'] }} reçu(s), panier moyen {{ Money::format($kpis['average']) }}</p>

                {{-- Cash / Mobile Money split --}}
                <div class="mt-6 space-y-3">
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Espèces {{ $cashShare }} %, Mobile Money {{ 100 - $cashShare }} %">
                        @if ($kpis['revenue'] > 0)
                            <svg class="h-full w-full" viewBox="0 0 100 3" preserveAspectRatio="none" aria-hidden="true">
                                <rect x="0" y="0" width="{{ $cashShare }}" height="3" fill="#1c1c1c" />
                                <rect x="{{ $cashShare }}" y="0" width="{{ 100 - $cashShare }}" height="3" fill="#e4603a" />
                            </svg>
                        @endif
                    </div>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="flex items-center gap-2 text-slate-500"><span class="size-2.5 shrink-0 rounded-full bg-slate-950"></span>Espèces</dt>
                            <dd class="mt-0.5 text-lg font-medium text-slate-950"><x-money :amount="$kpis['cash']" /></dd>
                        </div>
                        <div>
                            <dt class="flex items-center gap-2 text-slate-500"><span class="size-2.5 shrink-0 rounded-full bg-primary-500"></span>Mobile Money</dt>
                            <dd class="mt-0.5 text-lg font-medium text-slate-950"><x-money :amount="$kpis['transfers']" /></dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Signature element: renewal ring on black --}}
            <section class="flex flex-col items-center justify-center gap-4 rounded-card bg-slate-950 p-6 text-center text-white" aria-labelledby="renewal-title">
                <div class="relative size-40">
                    <svg viewBox="0 0 120 120" class="size-40 -rotate-90" aria-hidden="true">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="#333333" stroke-width="10" />
                        @if ($renewal !== null && $renewal > 0)
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#e4603a" stroke-width="10" stroke-linecap="round"
                                stroke-dasharray="{{ round($ring * $renewal / 100, 2) }} {{ round($ring, 2) }}" />
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-4xl font-light tabular-nums">{{ $renewal !== null ? $renewal.' %' : '—' }}</span>
                        <span class="text-xs text-slate-400">renouvelés</span>
                    </div>
                </div>
                <div>
                    <h2 id="renewal-title" class="text-base font-medium">Taux de renouvellement</h2>
                    <p class="text-sm text-slate-400">
                        {{ $previous > 0 ? $renewed.' sur '.$previous.' abonnés du mois précédent' : 'Pas d’abonnés le mois précédent' }}
                    </p>
                </div>
            </section>

            <div class="grid gap-4">
                <x-stat label="Abonnés actifs" icon="users" :value="$kpis['active_subscribers']"
                    :hint="($subscriberDelta >= 0 ? '+' : '').$subscriberDelta.' vs mois précédent'" />
                <a href="{{ route('admin.subscribers.index', ['statut' => 'sans']) }}" class="block rounded-card focus-visible:outline-2 focus-visible:outline-primary-600">
                    <x-stat label="Abonnés à relancer" icon="bell-ring" :variant="$kpis['to_renew'] > 0 ? 'primary' : 'neutral'" :value="$kpis['to_renew']" hint="Abonnés le mois dernier, pas encore ce mois" class="h-full hover:ring-slate-300" />
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat label="Tickets du jour" icon="ticket" variant="neutral" :value="$kpis['tickets']" :hint="Money::format($kpis['tickets_revenue'])" />
            <x-stat label="Abonnements encaissés" icon="id-card" variant="neutral" :hint="($kpis['receipts'] - $kpis['tickets']).' reçu(s)'"><x-money :amount="$kpis['subscriptions_revenue']" /></x-stat>
            <x-stat label="Remises accordées" icon="badge-percent" :variant="$kpis['discounts'] > 0 ? 'warning' : 'neutral'" :hint="$kpis['discount_count'].' reçu(s) sous la grille'"><x-money :amount="$kpis['discounts']" /></x-stat>
        </div>

        {{-- Expenses and net result --}}
        @can('expenses.view')
            @php
                $expenseGrowth = $percent($kpis['expenses'], $kpis['previous_expenses']);
            @endphp
            <div class="grid gap-4 lg:grid-cols-3">
                <a href="{{ route('admin.expenses.index', ['mois' => $month->format('Y-m')]) }}" class="block rounded-card focus-visible:outline-2 focus-visible:outline-primary-600">
                    <x-stat label="Dépenses du mois" icon="wallet-cards" variant="neutral" class="h-full hover:ring-slate-300"
                        :hint="$expenseGrowth !== null ? ($expenseGrowth >= 0 ? '+' : '').$expenseGrowth.' % vs mois précédent' : 'Voir le détail par type'">
                        <x-money :amount="$kpis['expenses']" />
                    </x-stat>
                </a>
                <x-stat label="Résultat (chiffre d’affaires − dépenses)" icon="scale" :variant="$kpis['net'] >= 0 ? 'success' : 'danger'"
                    :hint="Money::format($kpis['revenue']).' − '.Money::format($kpis['expenses'])">
                    <x-money :amount="$kpis['net']" />
                </x-stat>
                <x-chart-card title="Dépenses par type" :subtitle="$monthLabel" height="h-40" :empty="$expensesByCategory === []"
                    :chart="['type' => 'doughnut', 'money' => true, 'labels' => array_keys($expensesByCategory), 'datasets' => [['label' => 'Montant', 'data' => array_values($expensesByCategory)]]]" />
            </div>
        @endcan

        {{-- Revenue --}}
        <div class="grid gap-6 xl:grid-cols-2">
            <x-chart-card :title="'Chiffre d’affaires par jour · '.$monthLabel" subtitle="Abonnements et tickets du jour, à la date du paiement"
                :empty="$kpis['revenue'] === 0"
                :chart="['type' => 'bar', 'stacked' => true, 'money' => true, 'labels' => $daily['labels'], 'datasets' => [
                    ['label' => 'Abonnements', 'data' => $daily['subscriptions'], 'color' => 'primary'],
                    ['label' => 'Tickets du jour', 'data' => $daily['tickets'], 'color' => 'sky'],
                ]]" />

            <x-chart-card :title="auth()->user()?->can('expenses.view') ? 'Recettes et dépenses sur 12 mois' : 'Chiffre d’affaires sur 12 mois'" subtitle="Évolution mois par mois"
                :empty="array_sum($monthly['subscriptions']) + array_sum($monthly['tickets']) + array_sum($monthly['expenses']) === 0"
                :chart="['type' => 'bar', 'stacked' => true, 'money' => true, 'labels' => $monthly['labels'], 'datasets' => array_values(array_filter([
                    ['label' => 'Abonnements', 'data' => $monthly['subscriptions'], 'color' => 'primary', 'stack' => 'recettes'],
                    ['label' => 'Tickets du jour', 'data' => $monthly['tickets'], 'color' => 'sky', 'stack' => 'recettes'],
                    auth()->user()?->can('expenses.view') ? ['label' => 'Dépenses', 'data' => $monthly['expenses'], 'color' => 'teal', 'stack' => 'depenses'] : null,
                ]))]" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-chart-card title="Moyens de paiement" :subtitle="$monthLabel" height="h-64" :empty="$byMethod === []"
                :chart="['type' => 'doughnut', 'money' => true, 'labels' => array_keys($byMethod), 'datasets' => [['label' => 'Montant', 'data' => array_values($byMethod)]]]" />

            <x-chart-card title="Abonnés par formule" :subtitle="'Abonnements valables en '.$month->translatedFormat('F Y')" height="h-64" :empty="array_sum($byFormula) === 0"
                :chart="['type' => 'doughnut', 'labels' => array_keys($byFormula), 'datasets' => [['label' => 'Abonnés', 'data' => array_values($byFormula)]]]" />

            <x-chart-card title="Chiffre d’affaires par zone" :subtitle="$monthLabel" height="h-64" :empty="$byZone === []"
                :chart="['type' => 'bar', 'horizontal' => true, 'money' => true, 'labels' => array_keys($byZone), 'datasets' => [['label' => 'Montant', 'data' => array_values($byZone), 'color' => 'primary']]]" />
        </div>

        <div class="grid gap-6 2xl:grid-cols-2">
            <section class="min-w-0 space-y-3" aria-labelledby="top-stops">
            <h2 id="top-stops" class="text-lg font-semibold text-slate-900">Points de montée les plus actifs · {{ $monthLabel }}</h2>
            <x-table caption="Points de montée les plus actifs" empty="Aucune vente sur ce mois.">
                <x-slot:head><th>Point de montée</th><th>Zone</th><th class="text-right">Reçus</th><th class="text-right">Montant</th></x-slot:head>
                @foreach ($topStops as $stop)
                    <tr>
                        <td class="font-medium">{{ $stop['name'] }}</td>
                        <td class="text-slate-600">{{ $stop['zone'] }}</td>
                        <td class="text-right tabular-nums">{{ $stop['count'] }}</td>
                        <td class="text-right"><x-money :amount="$stop['amount']" class="font-semibold" /></td>
                    </tr>
                @endforeach
            </x-table>
            </section>

            <section class="min-w-0 space-y-3" aria-labelledby="latest-payments">
            <div class="flex items-center justify-between gap-3">
                <h2 id="latest-payments" class="text-lg font-semibold text-slate-900">Derniers paiements</h2>
                @can('subscriptions.view-all')
                    <x-button :href="route('admin.receipts.index')" variant="ghost" size="sm" icon-right="chevron-right">Tout voir</x-button>
                @endcan
            </div>
            <x-table caption="Derniers paiements" empty="Aucun paiement.">
                <x-slot:head><th>N°</th><th>Client</th><th>Date</th><th class="text-right">Montant</th></x-slot:head>
                @foreach ($latest as $payment)
                    <tr>
                        <td class="whitespace-nowrap font-mono text-sm">
                            @can('subscriptions.view-all')
                                <a href="{{ route('admin.receipts.show', $payment) }}" class="text-primary-700 hover:underline">{{ $payment->receipt_number }}</a>
                            @else
                                {{ $payment->receipt_number }}
                            @endcan
                        </td>
                        <td>{{ $payment->subscriber?->full_name ?? 'Ticket du jour' }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ ($payment->confirmed_at ?? $payment->created_at)->format('d/m/Y') }}</td>
                        <td class="text-right"><x-money :amount="$payment->amount" class="font-semibold" /></td>
                    </tr>
                @endforeach
            </x-table>
            </section>
        </div>

        <x-card title="Contrôles à bord aujourd’hui" subtitle="Titres scannés par les convoyeurs">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Valides" icon="circle-check" variant="success" :value="$scans['valid']" />
                <x-stat label="Attention" icon="triangle-alert" variant="warning" :value="$scans['warning']" />
                <x-stat label="Refusés" icon="circle-x" variant="danger" :value="$scans['refused']" />
            </div>
        </x-card>
    </div>
</x-layouts.admin>
