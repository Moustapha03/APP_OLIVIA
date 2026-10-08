{{--
    Large checkbox with its label (the whole line is clickable).

    <x-checkbox name="remember" checked>Rester connecté sur ce téléphone</x-checkbox>
    <x-checkbox name="privacy" required>J’accepte…</x-checkbox>
--}}
@props([
    'name',
    'value' => '1',
    'checked' => false,
    'required' => false,
    'id' => null,
])

@php
    $id ??= str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $error = isset($errors) ? $errors->first($dotName) : null;
    $isChecked = (bool) old($dotName, $checked);
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="flex min-h-12 cursor-pointer items-start gap-3 text-base text-slate-800">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @checked($isChecked)
            @required($required)
            aria-describedby="{{ $id }}-error"
            @if ($error) aria-invalid="true" @endif
            data-validate
            {{ $attributes->class('mt-0.5 size-6 shrink-0 rounded-md border-slate-400 text-primary-600 focus:ring-primary-600') }}
        >
        <span>{{ $slot }}</span>
    </label>

    <p id="{{ $id }}-error" data-error-for="{{ $id }}" @class(['flex items-start gap-1.5 text-sm font-medium text-danger-700', 'hidden' => ! $error]) role="alert">
        <x-lucide-circle-alert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span data-error-text>{{ $error }}</span>
    </p>
</div>
