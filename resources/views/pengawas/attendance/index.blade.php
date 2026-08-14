<x-layouts.pengawas title="Absensi Peserta">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Absensi Peserta</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Catat kehadiran peserta pada sesi ujian yang sedang berlangsung di ruangan {{ $room->display_name }}.</p>
        </div>

        @include('admin.partials.flash')

        @if ($schedule === null)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @if ($upcomingSchedules->isEmpty())
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
            @if ($schedules->count() > 1)
                <form method="GET" action="{{ route('pengawas.attendance.index') }}" class="flex items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex-1">
                        <label for="schedule" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Mata Pelajaran</label>
                        <select name="schedule" id="schedule" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" onchange="this.form.submit()">
                            @foreach ($schedules as $item)
                                <option value="{{ $item->id }}" @selected($item->id === $schedule->id)>{{ $item->subject?->name }} ({{ $item->class_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            @endif

            @php
                $earlyWindow = $schedule->isAttendanceWindowOpen()
                    && $schedule->computedStatus() !== \App\Models\ExamSchedule::STATUS_ONGOING;
                $examOver = $schedule->computedStatus() === \App\Models\ExamSchedule::STATUS_FINISHED;
            @endphp

            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Kelas {{ $schedule->class_name }} &middot; {{ $schedule->room?->display_name }} &middot;
                        {{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB
                    </p>
                </div>
                <x-badge-status :status="$earlyWindow ? 'belum_mulai' : 'berlangsung'" :label="$earlyWindow ? 'Jendela Absensi' : 'Sedang Berlangsung'" />
            </div>

            @if ($earlyWindow && $examOver)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Waktu ujian telah berakhir. Jendela absensi tetap terbuka
                    <strong>{{ $schedule->attendanceToleranceMinutes() }} menit</strong> setelah selesai untuk
                    absensi ulang peserta yang dinonaktifkan karena pelanggaran.
                </div>
            @elseif ($earlyWindow)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Jendela absensi telah dibuka (10 menit sebelum ujian). Ujian resmi dimulai pukul
                    <strong>{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }}</strong>.
                </div>
            @endif

            <x-table :headers="['No', 'NISN', 'Nama Peserta', 'Kelas', 'Kehadiran']">
                @foreach ($students as $index => $student)
                    @php
                        $session = $student->examSession;
                        $locked = $session->locked_by_admin;
                        $autoDisabled = ! $session->attendance_confirmed && (int) $session->violations_count > 0;
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
                                        confirmed: @js($session->attendance_confirmed),
                                        saving: false,
                                        error: '',
                                        async toggle(target) {
                                            this.saving = true;
                                            this.error = '';
                                            try {
                                                const res = await fetch('{{ route('pengawas.attendance.confirm', $schedule->id) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'Accept': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                    },
                                                    body: JSON.stringify({ student_id: {{ $student->id }}, confirmed: target }),
                                                });
                                                if (res.status === 419) {
                                                    window.location.reload();
                                                    return;
                                                }
                                                const data = await res.json().catch(() => ({}));
                                                if (!res.ok) {
                                                    this.confirmed = !target;
                                                    this.error = data.error ?? 'Gagal menyimpan absensi.';
                                                }
                                            } catch (e) {
                                                this.confirmed = !target;
                                                this.error = 'Gagal menyimpan absensi.';
                                            } finally {
                                                this.saving = false;
                                            }
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
                                            x-model="confirmed"
                                            @change="toggle($event.target.checked)"
                                            :disabled="saving"
                                            class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800"
                                        >
                                        <span class="text-sm" x-text="confirmed ? 'Hadir' : 'Tidak Hadir'"></span>
                                    </label>

                                    <svg x-show="saving" class="h-4 w-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>

                                    <p x-show="error" x-text="error" class="text-xs font-medium text-rose-600 dark:text-rose-400"></p>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-layouts.pengawas>
