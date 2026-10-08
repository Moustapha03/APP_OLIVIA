{{--
    Native select (best on low-end Android: native picker, no JS).

    <x-select name="stop_id" label="Votre arrêt" :options="$stops" placeholder="Choisissez un arrêt" required />

    options: [value => label]
--}}
@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
])

@php
    $id ??= str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $error = isset($errors) ? $errors->first($dotName) : null;
    $selected = (string) old($dotName, $value);
    $describedBy = trim(($hint ? "{$id}-hint " : '')."{$id}-error");
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :required="$required">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @required($required)
        aria-describedby="{{ $describedBy }}"
        @if ($error) aria-invalid="true" @endif
        data-validate
        {{ $attributes->class([
            'block min-h-12 w-full rounded-full border-0 bg-white py-3 pl-5 pr-10 text-base text-slate-900 ring-1 ring-inset focus:ring-2 focus:ring-primary-600',
            'ring-danger-600' => $error,
            'ring-slate-300' => ! $error,
        ]) }}
    >
        @if ($placeholder)
            <option value="" @selected($selected === '') disabled>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-field>
