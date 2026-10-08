{{--
    Inline SVG QR code (crisp at any zoom, works offline, no image request).

    <x-qr :payload="$signedPayload" label="QR code de votre ticket" />

    The payload is generated server side (opaque id + signature, step 5):
    never put personal data in it.
--}}
@props([
    'payload',
    'size' => 220,
    'label' => 'QR code',
])

@php
    $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
        ->size($size)
        ->margin(0)
        ->errorCorrection('M')
        ->generate($payload);

    // Drop the XML prolog: invalid inside an HTML document.
    $svg = new \Illuminate\Support\HtmlString(preg_replace('/^<\?xml[^>]*>\s*/', '', $svg));
@endphp

<div role="img" aria-label="{{ $label }}" {{ $attributes->class('inline-flex rounded-2xl bg-white p-3 ring-1 ring-slate-200 [&_svg]:h-auto [&_svg]:max-w-full') }}>
    {{ $svg }}
</div>
