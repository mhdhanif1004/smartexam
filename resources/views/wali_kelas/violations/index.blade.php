<x-layouts.wali_kelas title="Rekap Pelanggaran">
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Rekap Pelanggaran</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Kelas <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $wali->classroom?->name ?? '-' }}</span> — ringkasan pelanggaran dari seluruh sesi ujian di kelas ini.
                    </p>
                </div>
                <x-semester-selector :semesters="$semesters" :selected-semester-id="$selectedSemesterId" />
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Rekap Pelanggaran Per Siswa</h3>
            </div>

            @if ($totalViolations === 0)
                <div class="px-6 py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Tidak ada pelanggaran tercatat</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelas ini belum memiliki pelanggaran dari sesi ujian manapun.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nama Siswa</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Rincian Tipe</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ditangani</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @php $index = 0; @endphp
                            @foreach ($violationsByStudent as $vData)
                                @php
                                    $vIndex = $index++;
                                    $vStudent = $vData['student'];
                                    $vTotal = $vData['total'];
                                    $vTypes = $vData['types'];
                                    $vHandled = $vData['handled'];
                                    $vUnhandled = $vData['unhandled'];
                                @endphp
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $vIndex + 1 }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $vStudent->user?->name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">NISN: {{ $vStudent->nisn ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="inline-flex items-center justify-center min-w-[2rem] rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700 dark:bg-red-500/10 dark:text-red-300">
                                            {{ $vTotal }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @forelse ($vTypes as $type => $count)
                                            <span class="mr-2 inline-block">
                                                <span class="text-gray-600 dark:text-gray-400">{{ \App\Models\Violation::typeLabel($type) }}</span>
                                                <span class="ml-1 font-semibold text-gray-900 dark:text-gray-100">×{{ $count }}</span>
                                            </span>
                                        @empty
                                            <span class="text-gray-400 italic">-</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        @if ($vUnhandled > 0)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                                {{ $vUnhandled }} belum ditangani
                                            </span>
                                        @elseif ($vTotal > 0)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                Semua ditangani
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.wali_kelas>