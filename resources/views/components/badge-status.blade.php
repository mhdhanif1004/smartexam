@props(['status' => '', 'label' => null])

@php
    $map = [
        'aktif' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'nonaktif' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
        'belum_mulai' => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-gray-700/60 dark:text-gray-300 dark:ring-gray-500/40',
        'sedang_mengerjakan' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'selesai' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'terjadwal' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
        'berlangsung' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'dibatalkan' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
        'scheduled' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
        'ongoing' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'finished' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'lulus' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'gagal' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
        'dilaporkan' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
        'hadir' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'tidak_hadir' => 'bg-rose-600 text-white ring-rose-600 shadow-sm dark:bg-rose-500 dark:text-white dark:ring-rose-400',
        'izin' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
        'bisa_dimulai' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'susulan' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
        'terlewat' => 'bg-gray-100 text-gray-500 ring-gray-500/20 dark:bg-gray-700/60 dark:text-gray-400 dark:ring-gray-500/40',
        'absensi_tertutup' => 'bg-gray-100 text-gray-500 ring-gray-500/20 dark:bg-gray-700/60 dark:text-gray-400 dark:ring-gray-500/40',
        'timed_out' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
    ];
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $map[$status] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-700/60 dark:text-gray-300 dark:ring-gray-500/40' }}">
    @if ($status === 'tidak_hadir')
        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    @elseif ($status === 'hadir')
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-600 dark:bg-emerald-300" aria-hidden="true"></span>
    @endif
    {{ $label ?? ucwords(str_replace('_', ' ', $status)) }}
</span>
