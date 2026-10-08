{{-- Section of the /design style guide. --}}
@props([
    'id',
    'title',
    'description' => null,
])

<section id="{{ $id }}" class="scroll-mt-24 space-y-5 border-t border-slate-200 pt-10 first:border-0 first:pt-0" aria-labelledby="{{ $id }}-title">
    <div class="space-y-1">
        <h2 id="{{ $id }}-title" class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h2>
        @if ($description)
            <p class="max-w-3xl text-base text-slate-600">{{ $description }}</p>
        @endif
    </div>
    {{ $slot }}
</section>
