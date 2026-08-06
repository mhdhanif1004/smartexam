@props(['label' => '', 'value' => '', 'icon' => '', 'color' => 'indigo'])

@php
    $colors = [
        'indigo' => 'bg-indigo-100 text-indigo-600',
        'emerald' => 'bg-emerald-100 text-emerald-600',
        'amber' => 'bg-amber-100 text-amber-600',
        'rose' => 'bg-rose-100 text-rose-600',
        'sky' => 'bg-sky-100 text-sky-600',
    ];
@endphp

<div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    @if ($icon)
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg {{ $colors[$color] ?? $colors['indigo'] }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        </div>
    @endif
    <div>
        <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
        <p class="text-2xl font-bold text-gray-900">{{ $value }}</p>
    </div>
</div>
