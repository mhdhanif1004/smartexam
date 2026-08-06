<x-layouts.peserta title="Dashboard Peserta">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-gray-900">Selamat datang, {{ auth()->user()->name }}!</h2>
            <p class="mt-1 text-sm text-gray-500">Berikut jadwal ujianmu hari ini. Kerjakan tepat waktu ya!</p>
        </div>

        @include('admin.partials.flash')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-card-stat label="Ujian Hari Ini" :value="$stats['today']" color="indigo" icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            <x-card-stat label="Selesai" :value="$stats['done']" color="emerald" icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <x-card-stat label="Belum Mulai" :value="$stats['upcoming']" color="sky" icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h3 class="text-base font-bold text-gray-900">Ujian Hari Ini</h3>
            </div>

            @if ($schedules->isEmpty())
                <div class="p-8 text-center text-sm text-gray-500">
                    Tidak ada jadwal ujian untuk hari ini.
                </div>
            @else
                <x-table :headers="['No', 'Mata Pelajaran', 'Ruangan', 'Waktu', 'Durasi', 'Status', 'Aksi']">
                    @foreach ($schedules as $index => $schedule)
                        @php($display = $schedule->display)
                        <tr class="transition hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $schedule->subject?->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule->room?->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule->duration_minutes }} menit</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge-status :status="$display['key']" />
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($display['can_start'] && $display['url'])
                                    <a href="{{ $display['url'] }}"
                                       class="inline-flex items-center gap-1.5 rounded-lg {{ $display['key'] === 'sedang_mengerjakan' ? 'bg-amber-500' : 'bg-indigo-600' }} px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-90">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                        </svg>
                                        {{ $display['key'] === 'sedang_mengerjakan' ? 'Lanjutkan' : 'Masuk Ujian' }}
                                    </a>
                                @elseif ($display['key'] === 'selesai' && $display['url'])
                                    <a href="{{ $display['url'] }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-90">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                        </svg>
                                        Lihat Hasil
                                    </a>
                                @else
                                    <span class="cursor-not-allowed rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-400">
                                        {{ $display['key'] === 'terlewat' ? 'Tidak Diikuti' : 'Tunggu Jadwal' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>
    </div>
</x-layouts.peserta>
