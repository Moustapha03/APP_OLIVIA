@php use App\Enums\PaymentStatus; @endphp

<x-layouts.admin :title="$subscriber->full_name">
    <div class="space-y-6">
        <x-page-header :title="$subscriber->full_name" :subtitle="$subscriber->trashed() ? 'Client supprimé le '.$subscriber->deleted_at->format('d/m/Y').' (archives)' : null" :back="route('admin.subscribers.index')" back-label="Clients">
            @unless ($subscriber->trashed())
                <x-slot:actions>
                    @can('tickets.sell')
                        <x-button :href="route('guichet.subscriptions.create', ['abonne' => $subscriber->id])" icon="banknote">Encaisser un abonnement</x-button>
                    @endcan
                    @can('update', $subscriber)
                        <x-button :href="route('admin.subscribers.edit', $subscriber)" variant="secondary" icon="pencil">Modifier</x-button>
                    @endcan
                </x-slot:actions>
            @endunless
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <x-card title="Fiche">
                <div class="space-y-4">
                    @if ($subscriber->hasPhoto())
                        @can('viewPhoto', $subscriber)
                            <img src="{{ route('photos.subscriber', $subscriber) }}" alt="Photo de {{ $subscriber->full_name }}" class="size-32 rounded-2xl object-cover ring-1 ring-slate-200">
                        @endcan
                    @elseif (! $subscriber->trashed())
                        <x-alert variant="warning">Photo manquante : elle est obligatoire pour vendre un abonnement.</x-alert>
                    @endif

                    <dl class="space-y-3 text-base">
                        <div><dt class="text-sm text-slate-500">Téléphone</dt><dd class="tabular-nums">{{ $subscriber->formatted_phone ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Point de montée</dt><dd>{{ $subscriber->stop?->name ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Zone (prix)</dt><dd>{{ $subscriber->stop?->zone->name ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Compte en ligne</dt><dd>{{ $subscriber->user?->name ?? 'Aucun' }}</dd></div>
                        @if ($subscriber->notes)
                            <div><dt class="text-sm text-slate-500">Remarques</dt><dd>{{ $subscriber->notes }}</dd></div>
                        @endif
                        <div><dt class="text-sm text-slate-500">Client depuis</dt><dd>{{ $subscriber->created_at->format('d/m/Y') }}</dd></div>
                    </dl>
                </div>
            </x-card>

            <div class="min-w-0 space-y-6">
                <x-card title="Abonnements" :subtitle="$subscriptions->count().' abonnement(s)'" :padding="false">
                    <ul class="divide-y divide-slate-100">
                        @forelse ($subscriptions as $subscription)
                            @php $running = $subscription->starts_on->lte(today()) && $subscription->ends_on->gte(today()); @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ ucfirst($subscription->monthLabel()) }} · {{ $subscription->formula->shortLabel() }}</p>
                                    <p class="text-sm text-slate-600">
                                        {{ $subscription->payment?->receipt_number ?? '—' }} · {{ $subscription->status->label() }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if ($running && $subscription->status->value === 'active')
                                        <x-badge variant="success" icon="circle-check">En cours</x-badge>
                                    @endif
                                    <x-money :amount="$subscription->price_paid" class="font-semibold" />
                                </div>
                            </li>
                        @empty
                            <li class="px-5 pb-5 text-sm text-slate-600">Aucun abonnement.</li>
                        @endforelse
                    </ul>
                </x-card>

                <x-table caption="Historique des paiements" empty="Aucun paiement.">
                    <x-slot:head>
                        <th>N° de reçu</th><th>Date</th><th>Formule</th><th>Paiement</th><th>Statut</th><th class="text-right">Montant</th>
                    </x-slot:head>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="whitespace-nowrap font-mono text-sm">
                                @can('subscriptions.view-all')
                                    <a href="{{ route('admin.receipts.show', $payment) }}" class="text-primary-700 hover:underline">{{ $payment->receipt_number ?? $payment->reference }}</a>
                                @else
                                    {{ $payment->receipt_number ?? $payment->reference }}
                                @endcan
                            </td>
                            <td class="whitespace-nowrap tabular-nums">{{ ($payment->confirmed_at ?? $payment->created_at)->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap">{{ $payment->purpose->shortLabel() }}{{ $payment->period_month ? ' · '.$payment->period_month->translatedFormat('F Y') : '' }}</td>
                            <td>{{ $payment->payment_method?->label() ?? '—' }}</td>
                            <td><x-badge :variant="match ($payment->status) { PaymentStatus::Succeeded => 'success', PaymentStatus::Cancelled => 'danger', default => 'warning' }">{{ $payment->status->label() }}</x-badge></td>
                            <td class="text-right"><x-money :amount="$payment->amount" class="font-semibold" /></td>
                        </tr>
                    @endforeach
                </x-table>
            </div>
        </div>
    </div>
</x-layouts.admin>
