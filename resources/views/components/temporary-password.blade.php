{{--
    Temporary password shown ONCE (flash), to hand over in person.
    <x-temporary-password :password="$p" for="Awa Koné" phone="07 12 34 56 78" />
--}}
@props([
    'password',
    'for',
    'phone',
])

<div {{ $attributes->class('rounded-2xl bg-warning-50 p-5 ring-2 ring-inset ring-warning-400') }} role="alert">
    <p class="flex items-center gap-2 font-semibold text-warning-800">
        <x-lucide-key-round class="size-5" aria-hidden="true" />
        Mot de passe provisoire de {{ $for }}
    </p>
    <p class="mt-3 select-all rounded-xl bg-white px-4 py-3 text-center font-mono text-3xl font-bold tracking-wider text-slate-900 ring-1 ring-warning-100">{{ $password }}</p>
    <p class="mt-3 text-base text-warning-800">
        Notez-le pour le client. <strong>Il ne sera plus jamais affiché.</strong>
        Il se connecte avec le numéro {{ $phone }} et devra choisir son propre mot de passe.
    </p>
</div>
