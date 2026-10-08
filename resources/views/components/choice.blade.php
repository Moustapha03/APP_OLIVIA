{{--
    Large selectable card (radio). Easier to hit and to read than a small
    radio button; the whole card is the touch target.

    <x-choice name="formula" value="daily" title="Ticket du jour" description="2 trajets, valable aujourd'hui" :amount="1500" icon="ticket" required />

    Group several with the same name inside a <fieldset> with a <legend>.
--}}
@props([
    'name',
    'value',
    'title',
    'description' => null,
    'amount' => null,
    'icon' => null,
    'checked' => false,
])

@php
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $isChecked = (string) old($dotName, $checked ? $value : null) === (string) $value;
@endphp

<label {{ $attributes->only('class')->class([
    'relative flex min-h-16 cursor-pointer items-center gap-4 rounded-tile bg-white p-4 ring-1 ring-inset ring-slate-200 transition',
    'hover:bg-slate-50 has-checked:bg-primary-50 has-checked:ring-2 has-checked:ring-primary-600',
    'has-focus-visible:outline-3 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary-500',
]) }}>
    <input
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($isChecked)
        class="peer sr-only"
        {{ $attributes->except('class') }}
    >

    @if ($icon)
        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 peer-checked:bg-primary-600 peer-checked:text-white">
            <x-dynamic-component :component="'lucide-'.$icon" class="size-6" aria-hidden="true" />
        </span>
    @endif

    {{-- flex-wrap: in a narrow card the price moves under the text instead of overlapping it --}}
    <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1">
        <span class="min-w-28 flex-1">
            <span class="block text-lg font-semibold text-slate-900">{{ $title }}</span>
            @if ($description)
                <span class="block text-base text-slate-600">{{ $description }}</span>
            @endif
        </span>

        @if (! is_null($amount))
            <x-money :amount="$amount" class="text-lg font-semibold text-slate-900" />
        @endif
    </span>

    <span class="flex size-6 shrink-0 items-center justify-center rounded-full ring-2 ring-slate-300 peer-checked:bg-primary-600 peer-checked:ring-primary-600" aria-hidden="true">
        <x-lucide-check class="size-4 text-white" />
    </span>
</label>
