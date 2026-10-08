<x-layouts.guichet :title="$subscriber->full_name">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header :title="$subscriber->full_name" :back="route('guichet.subscribers.index', ['q' => $subscriber->full_name])" back-label="Abonnés">
            @if (auth()->user()->openCashSession()->exists())
                <x-slot:actions>
                    <x-button :href="route('guichet.subscriptions.create', ['abonne' => $subscriber->id])" icon="id-card">Vendre un abonnement</x-button>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-card title="Fiche abonné">
            @include('guichet.subscribers._form', ['action' => route('guichet.subscribers.update', $subscriber), 'then' => null, 'model' => $subscriber])
        </x-card>

        <x-card title="Abonnements" :padding="false">
            <ul class="divide-y divide-slate-100">
                @forelse ($subscriber->subscriptions as $subscription)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="font-medium">{{ ucfirst($subscription->monthLabel()) }} · {{ $subscription->formula->shortLabel() }}</p>
                            <p class="text-sm text-slate-600">{{ $subscription->channel->label() }} · {{ $subscription->status->label() }}</p>
                        </div>
                        <x-money :amount="$subscription->price_paid" class="font-semibold" />
                    </li>
                @empty
                    <li class="px-5 pb-5 text-sm text-slate-600">Aucun abonnement.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</x-layouts.guichet>
