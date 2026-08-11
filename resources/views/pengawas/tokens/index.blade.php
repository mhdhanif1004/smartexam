<x-layouts.pengawas title="Token Ujian">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Token Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola token masuk peserta pada sesi ujian di ruangan {{ $room->name }}.</p>
        </div>

        @include('admin.partials.flash')

        @if ($schedule === null)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @if ($upcomingSchedules->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada sesi ujian yang sedang dalam jendela token di ruangan Anda.</p>
                @else
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Belum ada sesi ujian dalam jendela token.</p>
                    <ul class="mx-auto mt-3 max-w-lg space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        @foreach ($upcomingSchedules as $item)
                            <li>
                                Token untuk <strong>{{ $item->subject?->name }}</strong> akan tersedia mulai pukul <strong>{{ $item->window_start }}</strong> (5 menit sebelum ujian dimulai).
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @else
            @if ($schedules->count() > 1)
                <form method="GET" action="{{ route('pengawas.tokens.index') }}" class="flex items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex-1">
                        <label for="schedule" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Sesi Ujian</label>
                        <select name="schedule" id="schedule" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" onchange="this.form.submit()">
                            @foreach ($schedules as $item)
                                <option value="{{ $item->id }}" @selected($item->id === $schedule->id)>{{ $item->subject?->name }} ({{ $item->class_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            @endif

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Kelas {{ $schedule->class_name }} &middot; {{ $schedule->room?->name }} &middot;
                            {{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }} WIB
                        </p>
                    </div>
                    <form method="POST" action="{{ route('pengawas.tokens.generate', ['schedule' => $schedule->id]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {{ $token ? 'Perbarui Token' : 'Generate Token' }}
                        </button>
                    </form>
                </div>

                @if ($schedule->computedStatus() === \App\Models\ExamSchedule::STATUS_SCHEDULED)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                        Token tersedia. Ujian resmi dimulai pukul
                        <strong>{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }}</strong>.
                    </div>
                @endif

                @if ($token)
                    <div class="mt-5 rounded-xl bg-indigo-50 p-5 text-center dark:bg-indigo-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Token Aktif</p>
                        <p class="mt-2 font-mono text-4xl font-bold tracking-[0.35em] text-indigo-900 dark:text-indigo-300">{{ $token->token_code }}</p>
                        <p class="mt-2 text-xs text-indigo-600 dark:text-indigo-400">Berlaku sampai {{ $token->valid_until->format('d M Y H:i') }}</p>
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-center dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada token aktif. Klik tombol <strong>Generate Token</strong> untuk membuat token.</p>
                    </div>
                @endif
            </div>

            <x-table :headers="['No', 'NISN', 'Nama Peserta', 'Kelas', 'Status Token']">
                @foreach ($students as $index => $student)
                    @php($session = $student->examSessions->first())
                    @php($entered = $session && in_array($session->status, [\App\Models\ExamSession::STATUS_IN_PROGRESS, \App\Models\ExamSession::STATUS_COMPLETED], true))
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->nisn }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->class_name }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if ($entered)
                                <x-badge-status :status="'aktif'" />
                                <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">Sudah memasukkan token</span>
                            @else
                                <x-badge-status :status="'belum_mulai'" />
                                <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">Belum memasukkan token</span>
                                @if ($session && $session->attendance_confirmed)
                                    <span class="ml-1 text-xs text-emerald-600 dark:text-emerald-400">&middot; sudah diabsen hadir</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-layouts.pengawas>
