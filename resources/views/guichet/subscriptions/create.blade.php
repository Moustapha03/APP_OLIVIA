@php
    use App\Enums\FareType;
    use App\Support\SubscriptionMonths;
@endphp

<x-layouts.guichet title="Abonnement">
    <h1 class="sr-only">Vendre un abonnement</h1>
    <x-slot:status>
        <x-badge variant="success" icon="lock-open" size="lg">Caisse ouverte</x-badge>
    </x-slot:status>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            @if (session('last_sale'))
                <x-button :href="route('guichet.sales.receipt', ['payment' => session('last_sale'), 'imprimer' => 1])" variant="secondary" block icon="printer">Imprimer le reçu de la dernière vente</x-button>
            @endif

            {{-- 1. Subscriber --}}
            <x-card title="1. Abonné">
                @if ($subscriber)
                    <div class="flex flex-wrap items-center gap-4 rounded-2xl bg-primary-50 p-4 ring-1 ring-inset ring-primary-200">
                        @if ($subscriber->hasPhoto())
                            <img src="{{ route('photos.subscriber', $subscriber) }}" alt="Photo de {{ $subscriber->full_name }}" class="size-16 rounded-xl object-cover">
                        @else
                            <span class="flex size-16 items-center justify-center rounded-xl bg-white text-slate-400"><x-lucide-user-round class="size-8" aria-hidden="true" /></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold">{{ $subscriber->full_name }}</p>
                            <p class="text-sm text-slate-600">{{ $subscriber->formatted_phone ?: 'Sans téléphone' }} · {{ $subscriber->stop?->name ?? 'Point de montée ?' }} ({{ $zone?->name }})</p>
                            @foreach ($subscriber->subscriptions as $running)
                                <p class="mt-1 text-sm font-medium text-success-700">Déjà abonné : {{ $running->formula->shortLabel() }}, {{ $running->monthLabel() }}</p>
                            @endforeach
                        </div>
                        <div class="flex gap-1">
                            <x-button :href="route('guichet.subscribers.edit', $subscriber)" variant="ghost" size="sm" icon="pencil">Modifier</x-button>
                            <x-button :href="route('guichet.subscriptions.create')" variant="ghost" size="sm" icon="x">Changer</x-button>
                        </div>
                    </div>
                @else
                    <form method="GET" action="{{ route('guichet.subscriptions.create') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-no-loading>
                        <x-input name="q" label="Nom ou téléphone de l’abonné" icon="search" :value="$search" class="sm:min-w-80" autofocus />
                        <x-button type="submit" variant="secondary" icon="search">Rechercher</x-button>
                    </form>

                    @if ($search !== '')
                        <ul class="mt-4 space-y-2">
                            @forelse ($results as $found)
                                <li>
                                    <a href="{{ route('guichet.subscriptions.create', ['abonne' => $found->id]) }}" class="flex items-center gap-3 rounded-2xl p-3 ring-1 ring-slate-200 hover:bg-slate-50">
                                        @if ($found->hasPhoto())
                                            <img src="{{ route('photos.subscriber', $found) }}" alt="" class="size-12 rounded-xl object-cover">
                                        @else
                                            <span class="flex size-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400"><x-lucide-user-round class="size-6" aria-hidden="true" /></span>
                                        @endif
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-semibold">{{ $found->full_name }}</span>
                                            <span class="block text-sm text-slate-600">{{ $found->formatted_phone }} · {{ $found->stop?->name }}</span>
                                        </span>
                                        <x-lucide-chevron-right class="size-5 text-slate-400" aria-hidden="true" />
                                    </a>
                                </li>
                            @empty
                                <li class="rounded-2xl bg-slate-50 p-4 text-base">Aucun abonné trouvé.</li>
                            @endforelse
                        </ul>
                    @endif

                    <x-button variant="primary" icon="user-plus" class="mt-4" data-modal-open="new-subscriber">Nouvel abonné</x-button>
                @endif
            </x-card>

            @if ($subscriber)
                <form method="POST" action="{{ route('guichet.subscriptions.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <input type="hidden" name="subscriber_id" value="{{ $subscriber->id }}">

                    <x-card title="2. Formule et mois" :subtitle="'Prix de la zone '.$zone?->name">
                        <div class="space-y-6">
                            <fieldset class="space-y-3">
                                <legend class="sr-only">Formule</legend>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    @foreach (FareType::subscriptions() as $type)
                                        @if ($prices[$type->value] ?? null)
                                            <x-choice name="formula" :value="$type->value" :title="$type->shortLabel()" :amount="$prices[$type->value]" :checked="old('formula', 'monthly') === $type->value" required />
                                        @else
                                            <div class="flex min-h-16 items-center rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-inset ring-slate-200">{{ $type->shortLabel() }} : pas de prix pour cette zone</div>
                                        @endif
                                    @endforeach
                                </div>
                                @error('formula')<p class="text-sm font-medium text-danger-700">{{ $message }}</p>@enderror
                            </fieldset>

                            <fieldset class="space-y-3">
                                <legend class="mb-3 text-base font-medium text-slate-800">Mois</legend>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($months as $month)
                                        <x-choice name="month" :value="$month->format('Y-m')" :title="ucfirst(SubscriptionMonths::label($month))" :description="$loop->first ? 'Mois en cours' : 'Mois prochain (payé d’avance)'" icon="calendar" :checked="old('month', $months[0]->format('Y-m')) === $month->format('Y-m')" required />
                                    @endforeach
                                </div>
                                @error('month')<x-alert variant="danger">{{ $message }}</x-alert>@enderror
                            </fieldset>
                        </div>
                    </x-card>

                    @if (! $subscriber->hasPhoto())
                        <x-card title="Photo de l’abonné" subtitle="Obligatoire : elle est montrée au contrôleur.">
                            @include('espace.profile._photo-field')
                        </x-card>
                    @endif

                    <x-card title="3. Paiement">
                        <div class="space-y-5">
                            @include('guichet._payment-fields')
                            @error('type')<x-alert variant="danger">{{ $message }}</x-alert>@enderror
                            @error('subscriber_id')<x-alert variant="danger">{{ $message }}</x-alert>@enderror
                        </div>
                    </x-card>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-button type="submit" variant="success" size="lg" block icon="banknote" loading-text="Enregistrement…">Encaisser</x-button>
                        <x-button type="submit" name="print" value="1" variant="secondary" size="lg" block icon="printer" loading-text="Enregistrement…">Encaisser et imprimer</x-button>
                    </div>
                </form>
            @endif
        </div>

        @include('guichet._sales-aside')
    </div>

    @unless ($subscriber)
        <x-modal name="new-subscriber" title="Nouvel abonné" :show="$errors->hasAny(['full_name', 'phone', 'stop_id', 'photo'])">
            @include('guichet.subscribers._form', ['action' => route('guichet.subscribers.store'), 'then' => 'subscription', 'model' => null])
        </x-modal>
    @endunless
</x-layouts.guichet>
