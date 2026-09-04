<x-layouts.pengawas title="Absensi Peserta">
    <div
        class="space-y-6"
        x-data="{ _attendanceRefresh: null }"
        x-init="_attendanceRefresh = setInterval(() => { if (document.visibilityState !== 'visible') return; if (document.querySelector('input[disabled]')) return; window.location.reload(); }, 90000)"
    >
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Absensi Peserta</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($room !== null)
                    Catat kehadiran peserta pada sesi ujian di ruangan {{ $room->display_name }}. Absensi berlaku untuk seluruh mata pelajaran dalam sesi ini.
                @else
                    Anda belum ditugaskan ke ruangan ujian mana pun hari ini.
                @endif
            </p>
        </div>

        @include('admin.partials.flash')

        @if ($anchorSchedule === null)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @if ($room === null)
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada jadwal ujian untuk Anda saat ini.</p>
                @elseif ($upcomingSchedules->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada sesi ujian yang sedang dalam jendela absensi di ruangan Anda.</p>
                @else
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Belum ada sesi ujian dalam jendela absensi.</p>
                    <ul class="mx-auto mt-3 max-w-lg space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        @foreach ($upcomingSchedules as $item)
                            <li>
                                Absensi untuk <strong>{{ $item->subject?->name }}</strong> akan aktif mulai pukul <strong>{{ $item->window_start }}</strong> (10 menit sebelum ujian dimulai).
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @else
            @php
                $activeSchedule = $schedules->first();
                $earlyWindow = $activeSchedule !== null
                    && $activeSchedule->isAttendanceWindowOpen()
                    && $activeSchedule->computedStatus() !== \App\Models\ExamSchedule::STATUS_ONGOING;
                $examOver = $activeSchedule !== null
                    && $activeSchedule->computedStatus() === \App\Models\ExamSchedule::STATUS_FINISHED;
            @endphp

            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Absensi Sesi Ujian</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $room->display_name }} &middot;
                        {{ $allSchedules->pluck('subject.name')->filter()->implode(', ') }} &middot;
                        {{ \Illuminate\Support\Str::substr($anchorSchedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($anchorSchedule->end_time, 0, 5) }} WIB
                        @if ($allSchedules->count() > 1)
                            &middot; {{ $allSchedules->count() }} mata pelajaran
                        @endif
                        &middot; {{ $students->count() }} Peserta
                    </p>
                </div>
                <x-badge-status :status="$earlyWindow ? 'belum_mulai' : 'berlangsung'" :label="$earlyWindow ? 'Jendela Absensi' : 'Sedang Berlangsung'" />
            </div>

            @if ($earlyWindow && $examOver)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Waktu ujian telah berakhir. Jendela absensi tetap terbuka
                    <strong>{{ $activeSchedule->attendanceToleranceMinutes() }} menit</strong> setelah selesai untuk
                    absensi ulang peserta yang dinonaktifkan karena pelanggaran.
                </div>
            @elseif ($earlyWindow)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Jendela absensi telah dibuka (10 menit sebelum ujian). Ujian resmi dimulai pukul
                    <strong>{{ \Illuminate\Support\Str::substr($activeSchedule->start_time, 0, 5) }}</strong>.
                </div>
            @endif

            <x-table :headers="['No', 'NISN', 'Nama Peserta', 'Kelas', 'Kehadiran']">
                @foreach ($students as $index => $student)
                    @php
                        $session = $student->examSession;
                        $locked = $session?->locked_by_admin ?? false;
                        $autoDisabled = $session !== null && ! $session->attendance_confirmed && (int) ($session->violations_count ?? 0) > 0;
                    @endphp
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $autoDisabled ? 'bg-amber-50 dark:bg-amber-500/10' : '' }} {{ $locked ? 'bg-gray-100 dark:bg-gray-800' : '' }}">
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->nisn }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->class_name }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if ($locked)
                                <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                    Dikunci oleh Admin &mdash; hubungi Admin untuk membuka kembali
                                </div>
                            @else
                                <div
                                    x-data="{
                                        status: @js($session?->attendance_status),
                                        saving: false,
                                        error: '',
                                        controller: null,
                                        showConfirm: false,
                                        pendingNext: null,
                                        pendingPrev: null,
                                        confirmName: @js($student->user?->name ?? $student->nisn),
                                        async doToggle(next, previous) {
                                            if (this.controller) { try { this.controller.abort(); } catch (_) {} }
                                            const myController = new AbortController();
                                            this.controller = myController;
                                            this.saving = true;
                                            this.error = '';
                                            const url = '{{ route('pengawas.attendance.confirm', $anchorSchedule->id) }}';
                                            const csrfUrl = '{{ route('csrf-token') }}';
                                            const getToken = () => document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content ?? '';
                                            const doFetch = (token) => fetch(url, {
                                                method: 'PATCH',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': token,
                                                },
                                                body: JSON.stringify({ student_id: {{ $student->id }}, status: next }),
                                                signal: myController.signal,
                                            });
                                            try {
                                                let res = await doFetch(getToken());
                                                if (res.status === 419) {
                                                    try {
                                                        const tokRes = await fetch(csrfUrl, { headers: { 'Accept': 'application/json' }, signal: myController.signal });
                                                        const tokData = await tokRes.json().catch(() => ({}));
                                                        const newToken = tokData.csrf_token ?? tokData.token ?? '';
                                                        if (newToken) {
                                                            const meta = document.querySelector('meta[name=&quot;csrf-token&quot;]');
                                                            if (meta) meta.content = newToken;
                                                            res = await doFetch(newToken);
                                                        }
                                                        if (res.status === 419) { window.location.reload(); return; }
                                                    } catch (retryErr) {
                                                        if (retryErr?.name === 'AbortError') return;
                                                        window.location.reload();
                                                        return;
                                                    }
                                                }
                                                if (myController !== this.controller) return;
                                                const data = await res.json().catch(() => ({}));
                                                if (!res.ok) {
                                                    this.status = previous;
                                                    this.error = data.error ?? data.message ?? 'Gagal menyimpan absensi.';
                                                } else if (data.status !== undefined) {
                                                    this.status = data.status;
                                                }
                                            } catch (e) {
                                                if (e?.name === 'AbortError') return;
                                                if (myController !== this.controller) return;
                                                this.status = previous;
                                                this.error = 'Gagal menyimpan absensi. Periksa koneksi Anda.';
                                            } finally {
                                                if (myController === this.controller) this.saving = false;
                                            }
                                        },
                                        async toggle(targetStatus) {
                                            const next = this.status === targetStatus ? null : targetStatus;
                                            if (next === 'tidak_hadir') {
                                                this.pendingNext = next;
                                                this.pendingPrev = this.status;
                                                this.showConfirm = true;
                                                return;
                                            }
                                            const previous = this.status;
                                            this.status = next;
                                            await this.doToggle(next, previous);
                                        },
                                        async confirmAbsent() {
                                            const next = this.pendingNext;
                                            const previous = this.pendingPrev ?? this.status;
                                            this.showConfirm = false;
                                            this.status = next;
                                            await this.doToggle(next, previous);
                                            this.pendingNext = null;
                                            this.pendingPrev = null;
                                        },
                                        cancelConfirm() {
                                            this.showConfirm = false;
                                            this.pendingNext = null;
                                            this.pendingPrev = null;
                                        }
                                    }"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    @if ($autoDisabled)
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30"
                                            title="Absensi sempat aktif lalu dinonaktifkan otomatis oleh sistem karena adanya pelanggaran."
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                            Nonaktif otomatis - ada pelanggaran
                                        </span>
                                    @endif

                                    <label class="inline-flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            :checked="status === 'hadir'"
                                            @change="toggle('hadir')"
                                            :disabled="saving"
                                            class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800"
                                        >
                                        <span class="text-sm">Hadir</span>
                                    </label>

                                    <label class="inline-flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            :checked="status === 'tidak_hadir'"
                                            @change="toggle('tidak_hadir')"
                                            :disabled="saving"
                                            class="h-5 w-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800"
                                        >
                                        <span class="text-sm">Tidak Hadir</span>
                                    </label>

                                    <svg x-show="saving" class="h-4 w-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>

                                    <p x-show="error" x-text="error" class="text-xs font-medium text-rose-600 dark:text-rose-400"></p>

                                    <div x-show="showConfirm" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="cancelConfirm()" @keydown.escape.window="cancelConfirm()">
                                        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900" @click.stop>
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/20">
                                                    <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">Tandai Tidak Hadir?</h4>
                                                    <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-400">Siswa <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="confirmName"></span> akan <span class="font-semibold text-amber-700 dark:text-amber-400">langsung tidak bisa masuk/mengerjakan ujian</span>. Aksi ini mengunci akses di semua mata pelajaran dalam sesi ini.</p>
                                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">Klik lagi checkbox untuk membatalkan (ubah ke Hadir/kosong) jika terjadi kesalahan input. Riwayat tercatat via pencatat absensi.</p>
                                                </div>
                                            </div>
                                            <div class="mt-5 flex justify-end gap-2">
                                                <button type="button" @click="cancelConfirm()" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                                                <button type="button" @click="confirmAbsent()" :disabled="saving" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">Ya, Tandai Tidak Hadir</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-layouts.pengawas>
