{{--
    Session flash messages. Included once by every layout.
    In controllers: return back()->with('success', 'Ticket vendu.');
    Keys: success | error | warning | info
    ("status" is left to the Breeze auth views, which display it themselves.)
--}}
@php
    $messages = [
        'success' => session('success'),
        'danger' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ];
@endphp

@if (array_filter($messages))
    <div {{ $attributes->class('space-y-3') }} aria-live="polite">
        @foreach ($messages as $variant => $message)
            @if ($message)
                <x-alert :variant="$variant" dismissible>{{ $message }}</x-alert>
            @endif
        @endforeach
    </div>
@endif
