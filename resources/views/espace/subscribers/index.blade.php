<x-layouts.espace title="Mes abonnés">
    <div class="space-y-6">
        <x-page-header title="Mes abonnés" subtitle="Les personnes pour qui vous achetez un abonnement (vous-même ou un proche)." :back="$back ?: route('espace.passes.index')" />

        @foreach ($subscribers as $subscriber)
            <x-card>
                <div class="flex items-center gap-4">
                    @if ($subscriber->hasPhoto())
                        <img src="{{ route('photos.subscriber', $subscriber) }}" alt="Photo de {{ $subscriber->full_name }}" class="size-16 rounded-2xl object-cover">
                    @else
                        <span class="flex size-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><x-lucide-user-round class="size-8" aria-hidden="true" /></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-semibold">{{ $subscriber->full_name }}</p>
                        <p class="text-sm text-slate-600">{{ $subscriber->stop?->name }} · {{ $subscriber->stop?->zone?->name }}</p>
                        @foreach ($subscriber->subscriptions as $running)
                            <p class="text-sm font-medium text-success-700">{{ $running->formula->shortLabel() }} · {{ $running->monthLabel() }}</p>
                        @endforeach
                        @unless ($subscriber->hasPhoto())
                            <x-badge variant="warning" icon="camera">Photo manquante</x-badge>
                        @endunless
                    </div>
                </div>

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-semibold text-primary-700">Modifier</summary>
                    <form method="POST" action="{{ route('espace.subscribers.update', $subscriber) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        @if ($subscriber->subscriptions->isNotEmpty())
                            {{-- Pass in progress: name and photo are changed at the agency only. --}}
                            <input type="hidden" name="full_name" value="{{ $subscriber->full_name }}">
                            <x-alert variant="info">Abonnement en cours : le nom et la photo se changent à l’agence, avec une pièce d’identité.</x-alert>
                        @else
                            <x-input name="full_name" label="Nom et prénom" :value="$subscriber->full_name" :id="'full_name-'.$subscriber->id" required />
                        @endif
                        <x-input name="phone" label="Téléphone" type="tel" prefix="+225" inputmode="numeric" :value="$subscriber->formatted_phone" :id="'phone-'.$subscriber->id" />
                        <x-select name="stop_id" label="Point de montée" :options="$stopOptions" :value="$subscriber->stop_id" :id="'stop_id-'.$subscriber->id" required />
                        @if ($subscriber->subscriptions->isEmpty())
                        <x-field :id="'photo-'.$subscriber->id" label="Changer la photo" :required="false">
                            <input id="photo-{{ $subscriber->id }}" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="user" class="block w-full rounded-2xl bg-white text-base ring-1 ring-inset ring-slate-300 file:mr-4 file:min-h-12 file:border-0 file:bg-primary-50 file:px-4 file:font-semibold file:text-primary-700">
                        </x-field>
                        @endif
                        <x-button type="submit" variant="secondary" block>Enregistrer</x-button>
                    </form>
                </details>
            </x-card>
        @endforeach

        <x-card title="Ajouter un abonné" subtitle="Sa photo sera montrée au contrôleur.">
            <form method="POST" action="{{ route('espace.subscribers.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @if ($back)
                    <input type="hidden" name="back" value="{{ $back }}">
                @endif
                <x-input name="full_name" label="Nom et prénom" :value="$subscribers->isEmpty() ? auth()->user()->name : null" required />
                <x-input name="phone" label="Téléphone" type="tel" prefix="+225" inputmode="numeric" :value="auth()->user()->formatted_phone" />
                <x-select name="stop_id" label="Point de montée" :options="$stopOptions" placeholder="Choisissez votre point de montée" required />
                @include('espace.profile._photo-field')
                <x-button type="submit" size="lg" block icon="user-plus" loading-text="Enregistrement…">Enregistrer l’abonné</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.espace>
