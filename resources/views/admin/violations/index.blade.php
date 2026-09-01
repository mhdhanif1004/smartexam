<x-layouts.admin title="Riwayat Pelanggaran">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Riwayat Pelanggaran</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Daftar pelanggaran peserta selama ujian berlangsung.</p>
        </div>

        @include('admin.partials.flash')

        {{-- Statistik Ringkas --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Pelanggaran</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Belum Ditangani</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ $stats['unhandled'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Siswa Terlibat</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['unique_students'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis Pelanggaran</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['by_type']->count() }}</p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.violations.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="date_from" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Mulai</label>
                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Selesai</label>
                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>
            <div>
                <label for="room_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ruangan</label>
                <select name="room_id" id="room_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">Semua Ruangan</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room_id'] === $room->id)>{{ $room->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="violation_type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Pelanggaran</label>
                <select name="violation_type" id="violation_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">Semua Jenis</option>
                    @foreach ($violationTypes as $type)
                        <option value="{{ $type }}" @selected($filters['violation_type'] === $type)>{{ \App\Models\Violation::typeLabel($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="student_search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Cari Siswa</label>
                <input type="text" name="student_search" id="student_search" value="{{ $filters['student_search'] }}" placeholder="Nama atau NISN..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>
            <div>
                <label for="subject_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Mata Pelajaran</label>
                <select name="subject_id" id="subject_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">Semua Mapel</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected($filters['subject_id'] == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="handled_status" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status Penanganan</label>
                <select name="handled_status" id="handled_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">Semua Status</option>
                    <option value="unhandled" @selected($filters['handled_status'] === 'unhandled')>Belum Ditangani</option>
                    <option value="handled" @selected($filters['handled_status'] === 'handled')>Sudah Ditangani</option>
                </select>
            </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                <span class="text-xs text-gray-500 dark:text-gray-400">Cepat:</span>
                <a href="{{ route('admin.violations.index', array_merge($filters, ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()])) }}" class="rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">Hari Ini</a>
                <a href="{{ route('admin.violations.index', array_merge($filters, ['date_from' => now()->startOfWeek()->toDateString(), 'date_to' => now()->endOfWeek()->toDateString()])) }}" class="rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">Minggu Ini</a>
                <a href="{{ route('admin.violations.index', array_merge($filters, ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()])) }}" class="rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">Bulan Ini</a>
                <div class="ml-auto flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Terapkan</button>
                    @if ($filters['date_from'] || $filters['date_to'] || $filters['room_id'] || $filters['violation_type'] || $filters['student_search'] || $filters['handled_status'] || $filters['subject_id'])
                        <a href="{{ route('admin.violations.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        <x-table :headers="['No', 'Siswa', 'Kelas', 'Ruangan', 'Mata Pelajaran', 'Jumlah Pelanggaran', 'Checklist Aktif', 'Hentikan Paksa', 'Status']"
                 x-data="{ expanded: null, toggleRow(key) { this.expanded = (this.expanded === key) ? null : key; } }">
            @forelse ($violations as $index => $session)
                @php
                    $student = $session?->student;
                    $schedule = $session?->examSchedule;
                    $sessionViolations = $session?->violations ?? collect();
                    $latestViolation = $sessionViolations->sortByDesc('occurred_at')->first();
                @endphp
                <tr
                    x-data="{
                        lockCount: {{ $sessionViolations->count() }},
                        locked: @js((bool) ($session?->locked_by_admin ?? false)),
                        busy: false,
                        showModal: false,
                        pendingAction: null,
                        confirmToggle() {
                            this.pendingAction = !this.locked;
                            this.showModal = true;
                        },
                        async executeToggle() {
                            this.showModal = false;
                            if (this.busy) return;
                            this.busy = true;
                            try {
                                const res = await fetch(@js($session ? route('admin.violations.lock', $session->id) : '#'), {
                                    method: 'PATCH',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': @js(csrf_token()),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: JSON.stringify({ locked: this.pendingAction }),
                                });
                                if (res.ok) {
                                    this.locked = (await res.json()).locked;
                                } else {
                                    alert('Gagal mengubah status kunci.');
                                }
                            } finally {
                                this.busy = false;
                            }
                        }
                    }"
                    class="border-t border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50"
                >
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $violations->firstItem() + $index }}</td>
                    <td class="px-4 py-3">
                        <button type="button" @click="toggleRow(@js($session->id))"
                                class="flex w-full items-center gap-2 text-left"
                                :aria-expanded="expanded === @js($session->id)">
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform dark:text-gray-500"
                                 :class="expanded === @js($session->id) && 'rotate-90'"
                                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $student?->user?->name ?? '-' }}</span>
                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $student?->nisn ?? '' }}</span>
                            <span x-show="lockCount > 1"
                                  class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-indigo-100 px-1.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
                                  x-text="lockCount + ' x'"></span>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student?->class_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $schedule?->room?->display_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $schedule?->subject?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex h-6 min-w-[28px] items-center justify-center rounded-full bg-rose-100 px-2 text-xs font-bold text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">
                            {{ $sessionViolations->count() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-0.5">
                                <span class="inline-block h-3 w-3 rounded-sm {{ $session?->violation_flag_1 ? 'bg-rose-500' : 'bg-gray-200 dark:bg-gray-700' }}"></span>
                                <span class="inline-block h-3 w-3 rounded-sm {{ $session?->violation_flag_2 ? 'bg-rose-500' : 'bg-gray-200 dark:bg-gray-700' }}"></span>
                                <span class="inline-block h-3 w-3 rounded-sm {{ $session?->violation_flag_3 ? 'bg-rose-500' : 'bg-gray-200 dark:bg-gray-700' }}"></span>
                            </span>
                            <span class="text-xs font-semibold {{ ($session?->activeViolationFlags() ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $session?->activeViolationFlags() ?? 0 }} dari 3
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <button type="button" @click="confirmToggle()" :disabled="busy"
                                :class="locked ? 'bg-rose-600 text-white shadow-sm' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
                                class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50">
                            <span class="relative inline-flex h-4 w-8 items-center rounded-full transition" :class="locked ? 'bg-rose-500' : 'bg-gray-300 dark:bg-gray-600'">
                                <span class="inline-block h-3 w-3 transform rounded-full bg-white transition dark:bg-gray-300" :class="locked ? 'translate-x-4' : 'translate-x-0.5'"></span>
                            </span>
                            <span x-text="locked ? 'Dikunci' : 'Aktif'"></span>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if ($session?->locked_by_admin)
                            @if ($session?->locked_by_admin_by === null)
                                <x-badge-status :status="'nonaktif'" :label="'Dikunci Otomatis'" />
                            @else
                                <x-badge-status :status="'nonaktif'" :label="'Dikunci Admin'" />
                            @endif
                        @elseif ($sessionViolations->where('handled_by_supervisor', true)->count() === $sessionViolations->count() && $sessionViolations->count() > 0)
                            <x-badge-status :status="'aktif'" :label="'Ditangani'" />
                        @else
                            <x-badge-status :status="'dilaporkan'" :label="'Perlu Ditangani'" />
                        @endif
                    </td>

                    {{-- Modal Konfirmasi --}}
                    <template x-if="showModal">
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                            <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                                         :class="pendingAction ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400'">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100" x-text="pendingAction ? 'Hentikan Ujian?' : 'Aktifkan Kembali?'"></h3>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="pendingAction ? 'Siswa tidak akan bisa mengerjakan ujian setelah dikunci.' : 'Siswa akan bisa melanjutkan ujian.'"></p>
                                    </div>
                                </div>
                                <div class="mt-5 flex justify-end gap-2">
                                    <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</button>
                                    <button type="button" @click="executeToggle()" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition" :class="pendingAction ? 'bg-rose-600 hover:bg-rose-500' : 'bg-emerald-600 hover:bg-emerald-500'" x-text="pendingAction ? 'Ya, Kunci' : 'Ya, Aktifkan'"></button>
                                </div>
                            </div>
                        </div>
                    </template>
                </tr>
                {{-- Detail pelanggaran — baris terpisah penuh lebar (di bawah baris utama),
                     membaca scope 'expanded' dari <tbody> sehingga toggle berfungsi. --}}
                <tr x-show="expanded === @js($session->id)" x-cloak class="bg-gray-50/70 dark:bg-gray-800/40">
                    <td colspan="9" class="px-4 py-0">
                        <div class="overflow-hidden origin-top p-4"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1">
                            <div class="mb-2 flex items-center gap-2">
                                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Detail Pelanggaran</h4>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $sessionViolations->count() }} catatan</span>
                            </div>
                            @if ($sessionViolations->isNotEmpty())
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                            <th class="py-2 pr-4 font-medium">Waktu</th>
                                            <th class="py-2 pr-4 font-medium">Jenis Pelanggaran</th>
                                            <th class="py-2 pr-4 font-medium">Status</th>
                                            <th class="py-2 font-medium">Dilaporkan Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sessionViolations->sortByDesc('occurred_at') as $v)
                                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $v->occurred_at->format('d M Y H:i') }}</td>
                                                <td class="py-2 pr-4 text-gray-800 dark:text-gray-200">{{ \App\Models\Violation::typeLabel($v->violation_type) }}</td>
                                                <td class="py-2 pr-4">
                                                    @if ($v->handled_by_supervisor)
                                                        <x-badge-status :status="'aktif'" :label="'Ditangani'" />
                                                    @else
                                                        <x-badge-status :status="'dilaporkan'" :label="'Belum Ditangani'" />
                                                    @endif
                                                </td>
                                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $v->reportedBy?->name ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada detail pelanggaran.</p>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <svg class="h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada pelanggaran yang tercatat.</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Pelanggaran akan muncul di sini saat terdeteksi selama ujian berlangsung.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-table>

        <div>{{ $violations->links() }}</div>
    </div>
</x-layouts.admin>
