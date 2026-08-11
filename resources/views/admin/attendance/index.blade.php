<x-layouts.admin title="Absensi">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Absensi Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pantau kehadiran peserta dan pengawas per ruangan untuk sesi ujian hari ini.</p>
        </div>

        @include('admin.partials.flash')

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex-1">
                <label for="room_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Ruangan</label>
                <select name="room_id" id="room_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" onchange="window.location.href = '{{ route('admin.attendance.index') }}?room_id=' + this.value">
                    @foreach ($rooms as $r)
                        <option value="{{ $r->id }}" @selected($room?->id === $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (! $room)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada ruangan.</p>
            </div>
        @elseif (! $schedule)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada jadwal ujian di ruangan <strong>{{ $room->name }}</strong> hari ini.</p>
            </div>
        @else
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Kelas {{ $schedule->class_name }} &middot; {{ $schedule->room?->name }} &middot;
                        {{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB
                    </p>
                </div>
                @php
                    $computedStatus = $schedule->computedStatus();
                @endphp
                <x-badge-status :status="$computedStatus" :label="\App\Models\ExamSchedule::STATUSES[$computedStatus] ?? $computedStatus" />
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Absensi Peserta</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Data real-time dari checklist pengawas di ruangan.</p>
                        </div>
                    </div>
                    <x-table :headers="['No', 'NISN', 'Nama Peserta', 'Kelas', 'Kehadiran']">
                        @forelse ($students as $index => $student)
                            @php
                                $session = $student->examSession;
                                $confirmed = $session?->attendance_confirmed ?? false;
                                $statusText = $confirmed ? 'Hadir' : 'Tidak Hadir';
                                $statusColor = $confirmed ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
                                $bgColor = $confirmed ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-rose-50 dark:bg-rose-500/10';
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->nisn }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->class_name }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $bgColor }} {{ $statusColor }}">
                                        {{ $statusText }}
                                    </span>
                                    @if (! $confirmed)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Belum dicek oleh pengawas</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada peserta di ruangan ini.</td>
                            </tr>
                        @endforelse
                    </x-table>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Absensi Pengawas</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Daftar pengawas yang bertugas di ruangan ini.</p>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $supervisors = \App\Models\Supervisor::query()
                                ->with('user')
                                ->where('room_id', $room->id)
                                ->get();
                        @endphp
                        @forelse ($supervisors as $supervisor)
                            @php
                                $attendance = $supervisorAttendance->get($supervisor->id);
                                $isPresent = $attendance?->status === \App\Models\SupervisorAttendance::STATUS_PRESENT;
                                $statusText = $isPresent ? 'Hadir' : 'Tidak Hadir';
                                $statusColor = $isPresent ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
                                $bgColor = $isPresent ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-rose-50 dark:bg-rose-500/10';
                                $checkInTime = $attendance?->checked_in_at?->format('d M H:i') ?? '-';
                            @endphp
                            <li class="flex items-center gap-4 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $supervisor->user?->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $room->name }} &middot; Check-in: {{ $checkInTime }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $bgColor }} {{ $statusColor }}">
                                        {{ $statusText }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada pengawas yang ditugaskan di ruangan ini.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>