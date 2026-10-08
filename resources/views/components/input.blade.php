{{--
    Text input with label, hint, server + live validation errors.

    <x-input name="phone" label="Numéro de téléphone" type="tel" prefix="+225"
             inputmode="numeric" autocomplete="tel" required />

    Old input and validation errors are picked up automatically by name.
    type="password" adds a "Afficher" toggle. suffix="FCFA" shows a unit after the field.
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'prefix' => null,
    'suffix' => null,
    'icon' => null,
    'required' => false,
    'id' => null,
])

@php
    $id ??= str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $error = isset($errors) ? $errors->first($dotName) : null;
    $describedBy = trim(($hint ? "{$id}-hint " : '')."{$id}-error");
    $isPassword = $type === 'password';
    $current = $isPassword ? null : old($dotName, $value);
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :required="$required">
    <div @class([
        'flex min-h-12 items-stretch overflow-hidden rounded-full bg-white ring-1 ring-inset transition',
        'focus-within:ring-2 focus-within:ring-primary-600',
        'ring-danger-600' => $error,
        'ring-slate-300' => ! $error,
    ]) data-field-shell>
        @if ($prefix)
            <span class="flex items-center border-r border-slate-200 bg-slate-50 px-3.5 text-base font-medium text-slate-700">{{ $prefix }}</span>
        @elseif ($icon)
            <span class="flex items-center pl-3.5 text-slate-500">
                <x-dynamic-component :component="'lucide-'.$icon" class="size-5" aria-hidden="true" />
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if (! is_null($current)) value="{{ $current }}" @endif
            @required($required)
            aria-describedby="{{ $describedBy }}"
            @if ($error) aria-invalid="true" @endif
            data-validate
            {{ $attributes->class('block w-full min-w-0 flex-1 border-0 bg-transparent px-3.5 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0') }}
        >

        @if ($suffix)
            <span class="flex items-center border-l border-slate-200 bg-slate-50 px-3.5 text-base font-medium text-slate-700">{{ $suffix }}</span>
        @endif

        @if ($isPassword)
            <button
                type="button"
                data-password-toggle="{{ $id }}"
                aria-controls="{{ $id }}"
                aria-pressed="false"
                class="flex items-center gap-1.5 px-3.5 text-sm font-semibold text-primary-700 hover:bg-primary-50"
            >
                <x-lucide-eye class="size-5" aria-hidden="true" />
                <span data-toggle-label>Afficher</span>
            </button>
        @endif
    </div>
</x-field>
