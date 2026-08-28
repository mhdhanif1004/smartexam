<x-layouts.pengawas title="Token Ujian">
    <div class="space-y-6" x-data="tokenApp()" x-init="init()">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Token Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($room !== null)
                    Token masuk peserta pada sesi ujian di ruangan {{ $room->display_name }}.
                @else
                    Anda belum ditugaskan ke ruangan ujian mana pun hari ini.
                @endif
            </p>
        </div>

        @include('admin.partials.flash')

        @if ($period === null)
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {!! $room !== null
                        ? 'Tidak ada sesi ujian yang sedang berlangsung di ruangan Anda.'
                        : 'Belum ada jadwal ujian untuk Anda saat ini.' !!}
                </p>
            </div>
        @else
            @php
                $tokenWindowOpensAt = \Carbon\Carbon::parse($period->exam_date->format('Y-m-d') . ' ' . $period->start_time)->subMinutes(5);
                $tokenWindowOpen = now()->greaterThanOrEqualTo($tokenWindowOpensAt);
                $tokenDelayed = $activeToken === null && $tokenWindowOpen;
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $period->name }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $room->display_name }} &middot;
                            {{ \Illuminate\Support\Str::substr($period->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($period->end_time, 0, 5) }} WIB &middot;
                            {{ $period->exam_date->format('d M Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2.5 w-2.5">
                            <span x-show="tokenActive" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span x-show="tokenActive" class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <span x-show="!tokenActive" class="relative inline-flex h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                        </span>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="tokenActive ? 'Token Aktif' : 'Menunggu Token'"></span>
                    </div>
                </div>

                <div class="mt-5 rounded-xl p-5 text-center transition-colors"
                     :class="tokenActive ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800'">
                    <template x-if="tokenActive">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Token Aktif</p>
                            <p class="mt-2 font-mono text-4xl font-bold tracking-[0.35em] text-indigo-900 dark:text-indigo-300" x-text="tokenCode"></p>
                            <p class="mt-2 text-xs text-indigo-600 dark:text-indigo-400">
                                Rotasi berikutnya dalam: <span class="font-semibold" x-text="formatTime(remainingSeconds)"></span>
                            </p>
                        </div>
                    </template>
                    <template x-if="!tokenActive">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Token akan muncul otomatis saat sesi ujian dimulai.</p>
                    </template>
                </div>
            </div>

            @if ($tokenDelayed)
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Token belum ter-generate</p>
                            <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">Jendela token seharusnya sudah aktif sejak {{ $tokenWindowOpensAt->format('H:i') }} WIB. Kemungkinan scheduler sistem (<code>schedule:work</code>) belum berjalan — hubungi admin atau developer.</p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($rotationHistory->isNotEmpty())
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Token</h4>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($rotationHistory as $rt)
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center justify-center rounded-full px-2.5 py-0.5 text-xs font-bold
                                        {{ $rt->valid_from <= now() && $rt->valid_until > now()
                                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                                        #{{ $rt->rotation_index + 1 }}
                                    </span>
                                    <span class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $rt->token_code }}</span>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $rt->valid_from->format('H:i') }} - {{ $rt->valid_until->format('H:i') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Status Peserta</h4>
                        <div class="flex gap-3">
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['sudah_token'] }}</span> sudah memasukkan token
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-bold text-amber-600 dark:text-amber-400">{{ $stats['belum_token'] }}</span> belum memasukkan token
                            </span>
                        </div>
                    </div>
                </div>

                <x-table :headers="['No', 'NISN', 'Nama Peserta', 'Kelas', 'Status Token']">
                    @foreach ($students as $index => $student)
                        @php($session = $student->examSessions->first())
                        @php($entered = $session && in_array($session->status, [\App\Models\ExamSession::STATUS_IN_PROGRESS, \App\Models\ExamSession::STATUS_COMPLETED, \App\Models\ExamSession::STATUS_TIMED_OUT], true))
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
            </div>
        @endif
    </div>

    <script>
        function tokenApp() {
            return {
                tokenActive: @js($activeToken !== null),
                tokenCode: @js($activeToken?->token_code ?? ''),
                remainingSeconds: @js($activeToken ? max(0, $activeToken->valid_until->getTimestamp() - now()->getTimestamp()) : 0),
                _countdownTimer: null,
                _pollTimer: null,
                init() {
                    this.startCountdown();
                    this.startPolling();
                },
                startCountdown() {
                    this._countdownTimer = setInterval(() => {
                        if (this.remainingSeconds > 0) {
                            this.remainingSeconds--;
                        }
                    }, 1000);
                },
                startPolling() {
                    this._pollTimer = setInterval(async () => {
                        try {
                            const res = await fetch('{{ route('pengawas.tokens.current') }}', {
                                headers: { 'Accept': 'application/json' },
                            });
                            const data = await res.json();
                            if (data.active) {
                                this.tokenActive = true;
                                this.tokenCode = data.token_code;
                                this.remainingSeconds = data.remaining_seconds;
                            } else {
                                this.tokenActive = false;
                                this.tokenCode = '';
                                this.remainingSeconds = 0;
                            }
                        } catch (e) {}
                    }, 10000);
                },
                destroy() {
                    clearInterval(this._countdownTimer);
                    clearInterval(this._pollTimer);
                },
                formatTime(seconds) {
                    if (seconds <= 0) return '00:00';
                    const m = Math.floor(seconds / 60);
                    const s = seconds % 60;
                    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                },
            };
        }
    </script>
</x-layouts.pengawas>
