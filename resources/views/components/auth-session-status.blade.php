@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-secondary dark:text-emerald-400']) }}>
        {{ $status }}
    </div>
@endif
