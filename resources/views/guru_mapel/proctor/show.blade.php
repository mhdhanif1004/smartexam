<x-layouts.guru_mapel title="Awasi Sesi Ujian">
    @php
        $firstStat = $statsBySchedule !== [] ? $statsBySchedule[array_key_first($statsBySchedule)] : null;
        $schedule = $firstStat['schedule'] ?? null;
        $stat = $firstStat ?? ['total' => 0, 'hadir' => 0, 'sedang_mengerjakan' => 0, 'selesai' => 0];
    @endphp
    <div class="space-y-6" x-data="{ busy: false }">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Awasi Sesi — {{ $examPeriod->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Jenis {{ $examPeriod->examType?->name ?? '-' }} ·
                        Kelas <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $schedule?->classroom?->name ?? $schedule?->class_name ?? '-' }}</span> ·
                        {{ \Illuminate\Support\Carbon::parse($examPeriod->exam_date->toDateString().' '.$examPeriod->start_time)->format('d/m/Y H:i') }}
                    </p>
                </div>
                <a href="{{ route('guru_mapel.exam-schedules.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    ← Kembali
                </a>
            </div>
        </div>

        {{-- Token aktif --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Token Ujian</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Token aktif saat ini — rotasi otomatis tiap 15 menit oleh scheduler.</p>
            @if ($activeToken)
                <div class="mt-3 flex items-center gap-3">
                    <span class="rounded-lg bg-indigo-50 px-4 py-2 font-mono text-2xl font-bold tracking-widest text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $activeToken->token_code }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Berlaku s.d. {{ $activeToken->valid_until?->format('H:i') }}</span>
                </div>
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 italic">Belum ada token aktif. Token otomatis muncul 5 menit sebelum ujian dimulai (pastikan scheduler berjalan).</p>
            @endif
        </div>

        {{-- Statistik --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Peserta</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stat['total'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Hadir</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stat['hadir'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sedang Mengerjakan</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $stat['sedang_mengerjakan'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Selesai</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stat['selesai'] }}</p>
            </div>
        </div>

        {{-- Absensi --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Absensi Peserta</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Konfirmasi kehadiran wajib sebelum siswa bisa memasukkan token.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NISN / Nama</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($students as $student)
                            @php
                                $session = $student->examSessions->first();
                                $attStatus = $session?->attendance_status;
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $student->nisn ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    @if ($attStatus === 'hadir')
                                        <x-badge-status status="hadir" />
                                    @elseif ($attStatus === 'tidak_hadir')
                                        <x-badge-status status="tidak_hadir" />
                                    @else
                                        <span class="text-xs text-gray-400 italic">belum dicek</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" :disabled="busy"
                                                @click="busy = true; fetch('{{ route('guru_mapel.proctor.attendance.confirm', [$examPeriod, $schedule ?? $examPeriod]) }}', { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ student_id: {{ $student->id }}, status: 'hadir' }) }).then(r => { window.location.reload(); }).catch(() => { busy = false; });"
                                                class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">Hadir</button>
                                        <button type="button" :disabled="busy"
                                                @click="busy = true; fetch('{{ route('guru_mapel.proctor.attendance.confirm', [$examPeriod, $schedule ?? $examPeriod]) }}', { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ student_id: {{ $student->id }}, status: 'tidak_hadir' }) }).then(r => { window.location.reload(); }).catch(() => { busy = false; });"
                                                class="rounded-md bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Tidak Hadir</button>
                                        <button type="button" :disabled="busy"
                                                @click="busy = true; fetch('{{ route('guru_mapel.proctor.attendance.confirm', [$examPeriod, $schedule ?? $examPeriod]) }}', { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ student_id: {{ $student->id }}, status: null }) }).then(r => { window.location.reload(); }).catch(() => { busy = false; });"
                                                class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Reset</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada peserta terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pelanggaran --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pelanggaran Sesi Ini</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Siswa</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Jenis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($violations as $violation)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $violation->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $violation->examSession?->student?->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $violation->typeLabel() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada pelanggaran tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.guru_mapel>