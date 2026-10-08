@php
    use App\Enums\PaymentStatus;
    use App\Models\Subscription;
    use App\Support\Money;
    $isSubscription = $pass instanceof Subscription;
    $statusVariant = match ($payment->status) { PaymentStatus::Succeeded => 'success', PaymentStatus::Cancelled => 'danger', default => 'warning' };
    $origin = match ($payment->gateway) { 'counter' => 'Agence (guichet)', 'import' => 'Historique importé', default => 'En ligne' };
@endphp

<x-layouts.admin :title="'Reçu '.($payment->receipt_number ?? $payment->reference)">
    <div class="space-y-6">
        <x-page-header :title="$payment->receipt_number ? 'Reçu N° '.$payment->receipt_number : 'Paiement '.$payment->reference" :back="route('admin.receipts.index')" back-label="Paiements et reçus">
            <x-slot:actions>
                <x-badge :variant="$statusVariant" size="lg">{{ $payment->status->label() }}</x-badge>
                @if (in_array($payment->gateway, ['counter', 'import'], true) && $pass)
                    <x-button :href="route('admin.receipts.print', $payment)" variant="secondary" icon="printer">Imprimer le reçu</x-button>
                @endif
                @if ($qr && $payment->receipt_number)
                    @include('receipts._share-buttons', ['whatsappTo' => $payment->subscriber?->formatted_phone ?: null])
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0 space-y-6">
                <x-card title="Transaction">
                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-slate-500">Client</dt><dd class="font-medium">
                            @if ($payment->subscriber)
                                <a href="{{ route('admin.subscribers.show', $payment->subscriber) }}" class="text-primary-700 hover:underline">{{ $payment->subscriber->full_name }}</a>
                                @if ($payment->subscriber->trashed()) <span class="text-sm text-slate-500">(supprimé)</span> @endif
                            @else
                                {{ $payment->user?->name ?? 'Client de passage' }}
                            @endif
                        </dd></div>
                        <div><dt class="text-sm text-slate-500">Téléphone</dt><dd class="tabular-nums">{{ $payment->subscriber?->formatted_phone ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Formule</dt><dd>{{ $payment->purpose->label() }}</dd></div>
                        <div><dt class="text-sm text-slate-500">{{ $isSubscription ? 'Mois' : 'Date de validité' }}</dt><dd>
                            {{ $payment->period_month ? ucfirst($payment->period_month->translatedFormat('F Y')) : ($pass?->valid_on?->format('d/m/Y') ?? '—') }}
                        </dd></div>
                        <div><dt class="text-sm text-slate-500">Point de montée</dt><dd>{{ $payment->stop?->name ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Zone</dt><dd>{{ $payment->zone->name }} <span class="text-sm text-slate-500">({{ $payment->zone->line->name }})</span></dd></div>
                        <div><dt class="text-sm text-slate-500">Moyen de paiement</dt><dd>{{ $payment->payment_method?->label() ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Référence du transfert</dt><dd class="font-mono text-sm">{{ $payment->external_reference ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Montant</dt><dd>
                            <x-money :amount="$payment->amount" class="text-xl font-bold" />
                            @if ($payment->isDiscounted())
                                <span class="block text-sm text-warning-700">Prix de la grille : {{ Money::format($payment->list_amount) }} · Motif : {{ $payment->discount_reason ?: '—' }}</span>
                            @endif
                        </dd></div>
                        <div><dt class="text-sm text-slate-500">Date</dt><dd class="tabular-nums">{{ ($payment->confirmed_at ?? $payment->created_at)->format($payment->gateway === 'import' ? 'd/m/Y' : 'd/m/Y H:i') }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Origine</dt><dd>{{ $origin }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Encaissé par</dt><dd>{{ $payment->seller?->name ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sm text-slate-500">Référence interne</dt><dd class="font-mono text-sm">{{ $payment->reference }}</dd></div>
                    </dl>

                    @if ($payment->status === PaymentStatus::Cancelled)
                        <x-alert variant="danger" class="mt-5" title="Vente annulée">
                            Le {{ $payment->cancelled_at?->format('d/m/Y à H:i') }} par {{ $payment->canceller?->name ?? '—' }}. Motif : {{ $payment->cancellation_reason ?: '—' }}
                        </x-alert>
                    @elseif ($payment->failure_reason)
                        <x-alert variant="warning" class="mt-5">{{ $payment->failure_reason }}</x-alert>
                    @endif
                </x-card>

                <x-card title="Historique" :subtitle="$history->isEmpty() ? 'Aucune modification enregistrée.' : null">
                    @if ($history->isNotEmpty())
                        <ol class="space-y-3">
                            @foreach ($history as $entry)
                                <li class="flex gap-3">
                                    <x-lucide-circle-dot class="mt-1 size-4 shrink-0 text-primary-600" aria-hidden="true" />
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $entry->description }}</p>
                                        <p class="text-sm text-slate-600">{{ $entry->created_at->format('d/m/Y H:i') }} · {{ $entry->causer?->name ?? 'Système' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            </div>

            <x-card title="QR code du titre" subtitle="Le convoyeur le scanne pour vérifier le titre.">
                @if ($qr)
                    <div class="flex flex-col items-center gap-3">
                        <x-qr :payload="$qr" :label="'QR code du reçu '.$payment->receipt_number" />
                        <p class="text-center text-sm text-slate-600">Vous pouvez aussi saisir le N° de reçu dans le scanner.</p>
                    </div>
                @else
                    <x-alert variant="warning">Pas de QR code : ce paiement n’est pas validé.</x-alert>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.admin>
