{{--
    <x-money :amount="1500" />  →  1 500 FCFA (never wraps, tabular digits)
--}}
@props([
    'amount',
    'currency' => true,
])

<span {{ $attributes->class('whitespace-nowrap tabular-nums') }}>{{ \App\Support\Money::format((int) $amount, $currency) }}</span>
