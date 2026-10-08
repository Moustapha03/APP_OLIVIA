{{--
    Shared wrapper for form controls: label, hint and error message.
    Used by <x-input>, <x-select> and <x-textarea>; rarely used directly.
    The error <p> is also filled by live validation (resources/js/forms.js).
--}}
@props([
    'id',
    'label' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-base font-medium text-slate-800">
            {{ $label }}
            @unless ($required)
                <span class="font-normal text-slate-500">(facultatif)</span>
            @endunless
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-slate-600">{{ $hint }}</p>
    @endif

    <p
        id="{{ $id }}-error"
        data-error-for="{{ $id }}"
        @class(['flex items-start gap-1.5 text-sm font-medium text-danger-700', 'hidden' => ! $error])
        role="alert"
    >
        <x-lucide-circle-alert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span data-error-text>{{ $error }}</span>
    </p>
</div>
