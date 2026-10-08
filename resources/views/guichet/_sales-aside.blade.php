{{-- Register totals, last sales, supervised cancellation (rule 9). --}}
@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
@endphp

<aside class="space-y-4">
    <x-stat label="Espèces dans le tiroir" icon="calculator" variant="success" :hint="'Fond '.Money::format($totals->openingFloat).' + ventes en espèces'"><x-money :amount="$totals->expected()" /></x-stat>
    @if ($totals->transferAmount > 0)
        <x-stat label="Mobile Money reçu" icon="smartphone" :hint="collect($totals->transfersByMethod)->map(fn ($v, $k) => $k.' '.Money::format($v))->join(' · ')"><x-money :amount="$totals->transferAmount" /></x-stat>
    @endif

    <x-card title="Dernières ventes" :padding="false">
        @if ($recentSales->isEmpty())
            <p class="px-5 pb-5 text-sm text-slate-600">Aucune vente pour l’instant.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($recentSales as $sale)
                    @php $cancelled = $sale->status === PaymentStatus::Cancelled; @endphp
                    <li class="space-y-2 px-5 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p @class(['truncate font-medium', 'line-through text-slate-500' => $cancelled])>{{ $sale->subscriber?->full_name ?? 'Ticket du jour' }}</p>
                                <p class="text-sm text-slate-600">{{ $sale->receipt_number }} · {{ $sale->purpose->shortLabel() }} · {{ $sale->payment_method?->label() }}</p>
                            </div>
                            <x-money :amount="$sale->amount" :class="\Illuminate\Support\Arr::toCssClasses(['font-semibold', 'text-slate-400 line-through' => $cancelled])" />
                        </div>
                        @if ($cancelled)
                            <x-badge variant="neutral" icon="ban">Annulée</x-badge>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @if ($sale->isDiscounted())
                                    <x-badge variant="warning">Prix négocié</x-badge>
                                @endif
                                <x-button :href="route('guichet.sales.receipt', $sale->reference)" variant="ghost" size="sm" icon="printer">Reçu</x-button>
                                <x-button variant="ghost" size="sm" icon="ban" data-modal-open="cancel-{{ $sale->reference }}">Annuler</x-button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-button :href="route('guichet.cash.show')" variant="secondary" block icon="lock">Clôturer ma caisse</x-button>
</aside>

@foreach ($recentSales->where('status', PaymentStatus::Succeeded) as $sale)
    @php $bag = $errors->getBag('cancel_'.$sale->reference); @endphp
    <x-confirm
        :name="'cancel-'.$sale->reference"
        title="Annuler cette vente ?"
        :action="route('guichet.sales.cancel', $sale->reference)"
        confirm-label="Valider l’annulation"
        :show="$bag->isNotEmpty()"
    >
        Reçu {{ $sale->receipt_number }} : {{ $sale->purpose->shortLabel() }}{{ $sale->subscriber ? ' de '.$sale->subscriber->full_name : '' }}.
        Remboursez <strong>{{ Money::format($sale->amount) }}</strong> au client.
        Un <strong>superviseur</strong> doit saisir ses identifiants.
        <x-slot:fields>
            @foreach ([
                ['supervisor_login', 'Téléphone du superviseur', 'text', 'username', 'tel'],
                ['supervisor_password', 'Mot de passe du superviseur', 'password', 'off', null],
            ] as [$field, $label, $type, $autocomplete, $inputmode])
                @php $id = $field.'-'.$sale->id; @endphp
                <x-field :id="$id" :label="$label" :error="$bag->first($field)" required>
                    <div class="flex min-h-12 items-stretch overflow-hidden rounded-2xl bg-white ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-primary-600" data-field-shell>
                        <input id="{{ $id }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" @if ($type !== 'password' && $bag->isNotEmpty()) value="{{ old($field) }}" @endif @if ($inputmode) inputmode="{{ $inputmode }}" @endif required data-validate aria-describedby="{{ $id }}-error" class="block w-full border-0 bg-transparent px-3.5 py-3 text-base focus:ring-0">
                    </div>
                </x-field>
            @endforeach
            @php $id = 'reason-'.$sale->id; @endphp
            <x-field :id="$id" label="Motif" :error="$bag->first('reason')" required>
                <textarea id="{{ $id }}" name="reason" rows="2" required minlength="5" data-validate aria-describedby="{{ $id }}-error" placeholder="Ex. : mauvaise formule choisie" class="block w-full rounded-2xl border-0 bg-white px-3.5 py-3 text-base ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-primary-600">{{ $bag->isNotEmpty() ? old('reason') : '' }}</textarea>
            </x-field>
        </x-slot:fields>
    </x-confirm>
@endforeach
