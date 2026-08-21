<x-layouts.admin title="Jadwal Ujian">
    <div
        x-data="{
            detailUrl: '',
            detailLoading: false,
            detail: null,
            openSessions: [],
            openRooms: [],
            toggleSession(id) {
                const idx = this.openSessions.indexOf(id);
                if (idx === -1) { this.openSessions.push(id); } else { this.openSessions.splice(idx, 1); }
            },
            isSessionOpen(id) { return this.openSessions.includes(id); },
            toggleRoom(id) {
                const idx = this.openRooms.indexOf(id);
                if (idx === -1) { this.openRooms.push(id); } else { this.openRooms.splice(idx, 1); }
            },
            isRoomOpen(id) { return this.openRooms.includes(id); },
            async openDetail(representativeId) {
                this.detailLoading = true;
                this.detail = null;
                this.openSessions = [];
                this.openRooms = [];
                this.detailUrl = @js(route('admin.exam-schedules.detail', '_REPLACE_')).replace('_REPLACE_', representativeId);
                try {
                    const resp = await fetch(this.detailUrl, { headers: { 'Accept': 'application/json' } });
                    this.detail = await resp.json();
                    this.$dispatch('open-modal', 'schedule-detail');
                } catch (e) {
                    this.detail = { error: 'Gagal memuat detail.' };
                    this.$dispatch('open-modal', 'schedule-detail');
                } finally {
                    this.detailLoading = false;
                }
            },
            deleteUrl: '',
            deleteDescription: '',
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Jadwal Ujian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Jadwal pelaksanaan ujian pada tanggal
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($examDate)->locale('id')->translatedFormat('l, d F Y') }}</span>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.exam-schedules.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Kembali ke Daftar Tanggal
                </a>
                <a href="{{ route('admin.exam-schedules.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Jadwal
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.exam-schedules.by-date') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <input type="hidden" name="date" value="{{ $examDate }}">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari mata pelajaran..." class="block w-full" />
            </div>
            <div>
                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 lg:w-auto">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('status'))
                    <a href="{{ route('admin.exam-schedules.by-date', ['date' => $examDate]) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Waktu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Durasi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ruangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($groups as $index => $group)
                            @php
                                $subject = $subjects[$group->subject_id] ?? null;
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $groups->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $subject?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::substr($group->earliest_start, 0, 5) }} - {{ \Illuminate\Support\Str::substr($group->latest_end, 0, 5) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $group->duration_minutes }} menit</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $group->room_count }} ruangan</td>
                                <td class="px-4 py-3 text-sm">
                                    <x-badge-status :status="$group->dominant_status" :label="$statuses[$group->dominant_status] ?? $group->dominant_status" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                @click="openDetail({{ $group->representative_id }})"
                                                :disabled="detailLoading"
                                                class="rounded bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                            Detail
                                        </button>
                                        <a href="{{ route('admin.exam-schedules.edit', $group->representative_id) }}" class="rounded-md bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-100 dark:bg-gray-500/10 dark:text-gray-300 dark:hover:bg-gray-500/20">Edit</a>
                                        <button type="button"
                                                @click="
                                                    deleteUrl = '{{ route('admin.exam-schedules.destroy', $group->representative_id) }}';
                                                    deleteDescription = 'Ini akan menghapus {{ $group->room_count }} jadwal untuk {{ $subject?->name ?? '-' }} pada {{ \Carbon\Carbon::parse($examDate)->locale("id")->translatedFormat("d F Y") }}.';
                                                    $dispatch('open-modal', 'confirm-delete');
                                                "
                                                class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada jadwal ujian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $groups->links() }}</div>

        @include('admin.partials.delete-modal')

        {{-- Detail Modal: 3-level accordion (Sesi → Ruangan → Kelas) --}}
        <x-modal name="schedule-detail" maxWidth="2xl">
            <div class="p-6">
                <template x-if="detailLoading">
                    <div class="flex items-center justify-center py-8">
                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600"></div>
                        <span class="ml-3 text-sm text-gray-500">Memuat detail...</span>
                    </div>
                </template>
                <template x-if="!detailLoading && detail">
                    <div>
                        <div class="mb-1">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100" x-text="detail.subject_name"></h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="detail.total_rooms + ' ruangan total'"></span>
                                <template x-if="detail.total_students > 0">
                                    <span><span class="text-gray-400"> · </span><span x-text="detail.total_students + ' siswa total'"></span></span>
                                </template>
                                <template x-if="detail.total_students === 0">
                                    <span><span class="text-gray-400"> · </span>Belum ada siswa ditempatkan</span>
                                </template>
                            </p>
                            <template x-if="detail.name_prefixes && detail.name_prefixes.length > 0">
                                <div>
                                    <div class="my-2 border-t border-gray-100 dark:border-gray-700"></div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="detail.name_prefixes.join(', ') + ' · ' + detail.sessions.length + ' sesi'"></p>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 space-y-4">
                            <template x-for="(session, si) in detail.sessions" :key="si">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                                    {{-- Level 1: Sesi --}}
                                    <div
                                        role="button"
                                        tabindex="0"
                                        @click="toggleSession(si)"
                                        @keydown.enter="toggleSession(si)"
                                        @keydown.space.prevent="toggleSession(si)"
                                        class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    >
                                        <span class="flex min-w-0 flex-1 items-center gap-2">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500"
                                                 :class="isSessionOpen(si) ? 'rotate-180' : ''"
                                                 fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                            <span class="truncate text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="session.label"></span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1.5">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="session.room_count + ' ruang'"></span>
                                            <template x-if="session.is_manual">
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">0 siswa</span>
                                            </template>
                                            <template x-if="!session.is_manual">
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="session.student_count + ' siswa'"></span>
                                            </template>
                                        </span>
                                    </div>

                                    {{-- Level 2 + 3: Ruangan → Kelas --}}
                                    <div x-show="isSessionOpen(si)" x-transition x-cloak
                                         class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                                        <div class="space-y-2">
                                            <template x-for="(room, ri) in session.rooms" :key="ri">
                                                <div class="rounded-md border border-gray-100 dark:border-gray-800">
                                                    {{-- Level 2: Ruangan --}}
                                                    <div
                                                        role="button"
                                                        tabindex="0"
                                                        @click="toggleRoom(si + '-' + ri)"
                                                        @keydown.enter="toggleRoom(si + '-' + ri)"
                                                        @keydown.space.prevent="toggleRoom(si + '-' + ri)"
                                                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left transition hover:bg-gray-50/60 dark:hover:bg-gray-800/30"
                                                    >
                                                        <span class="flex items-center gap-2">
                                                            <svg class="h-3 w-3 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500"
                                                                 :class="isRoomOpen(si + '-' + ri) ? 'rotate-180' : ''"
                                                                 fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400" x-text="room.room_name"></span>
                                                            <template x-if="room.grade_level">
                                                                <span class="shrink-0 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                                                      :class="{
                                                                          'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30': room.grade_level === 'X',
                                                                          'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30': room.grade_level === 'XI',
                                                                          'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/30': room.grade_level === 'XII'
                                                                      }"
                                                                      x-text="room.grade_level"></span>
                                                            </template>
                                                        </span>
                                                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="room.student_count !== null ? room.student_count + ' siswa' : 'Belum ada siswa'"></span>
                                                    </div>

                                                    {{-- Level 3: Kelas --}}
                                                    <div x-show="isRoomOpen(si + '-' + ri)" x-transition x-cloak
                                                         class="border-t border-gray-50 px-3 py-2 dark:border-gray-800/50">
                                                        <div class="flex flex-wrap gap-2">
                                                            <template x-for="(cls, ci) in room.classes" :key="ci">
                                                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                                    <span x-text="cls.name"></span>
                                                                    <span class="text-indigo-400 dark:text-indigo-500" x-text="'(' + cls.count + ')'"></span>
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <x-secondary-button x-on:click="$dispatch('close')">Tutup</x-secondary-button>
                        </div>
                    </div>
                </template>
            </div>
        </x-modal>
    </div>
</x-layouts.admin>
