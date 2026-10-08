{{--
    <x-textarea name="justification" label="Expliquez l'écart" rows="3" required />
--}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
])

@php
    $id ??= str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $error = isset($errors) ? $errors->first($dotName) : null;
    $describedBy = trim(($hint ? "{$id}-hint " : '')."{$id}-error");
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :required="$required">
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        @required($required)
        aria-describedby="{{ $describedBy }}"
        @if ($error) aria-invalid="true" @endif
        data-validate
        {{ $attributes->merge(['rows' => 3])->class([
            'block w-full rounded-tile border-0 bg-white px-5 py-3 text-base text-slate-900 ring-1 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-primary-600',
            'ring-danger-600' => $error,
            'ring-slate-300' => ! $error,
        ]) }}
    >{{ old($dotName, $value) }}</textarea>
</x-field>
