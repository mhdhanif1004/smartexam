@props(['status' => '', 'label' => null])

@php
    $map = [
        'aktif' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'nonaktif' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'belum_mulai' => 'bg-gray-100 text-gray-600 ring-gray-500/20',
        'sedang_mengerjakan' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'selesai' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'terjadwal' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'berlangsung' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'dibatalkan' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'scheduled' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'ongoing' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'finished' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'lulus' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'gagal' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'dilaporkan' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'hadir' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'tidak_hadir' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'izin' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'bisa_dimulai' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'terlewat' => 'bg-gray-100 text-gray-500 ring-gray-500/20',
    ];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $map[$status] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20' }}">
    {{ $label ?? ucwords(str_replace('_', ' ', $status)) }}
</span>
