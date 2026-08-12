<x-layouts.admin :title="$type === 'pengawas' ? 'Kartu Login Pengawas' : 'Kartu Login Peserta'">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Kartu Login</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $type === 'pengawas'
                    ? 'Cetak kartu berisi akun login (email & password) untuk dibagikan kepada pengawas ujian.'
                    : 'Cetak kartu berisi akun login (username & password) untuk dibagikan kepada peserta ujian.' }}
            </p>
        </div>

        @include('admin.partials.flash')

        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <a href="{{ route('admin.student-cards.index', ['type' => 'peserta']) }}"
                class="rounded-md px-4 py-2 text-sm font-semibold transition {{ $type === 'peserta' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' }}">
                Peserta
            </a>
            <a href="{{ route('admin.student-cards.index', ['type' => 'pengawas']) }}"
                class="rounded-md px-4 py-2 text-sm font-semibold transition {{ $type === 'pengawas' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' }}">
                Pengawas
            </a>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10">
                    <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                </span>
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Kartu {{ $type }} dicetak dengan desain bawaan SmartExam: maksimal 6 kartu per halaman A4.
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Atur logo, nama sekolah, dan kepala sekolah pada halaman Pengaturan Kartu.
                    </p>
                </div>
            </div>
            <a href="{{ route('admin.card-settings.edit') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Pengaturan Kartu
            </a>
        </div>

        @if ($type === 'pengawas')
            <form method="GET" action="{{ route('admin.student-cards.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <input type="hidden" name="type" value="pengawas">
                <div class="flex-1">
                    <label for="room" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Filter Ruangan</label>
                    <select name="room" id="room" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">Semua Ruangan</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected($selectedRoom === $room->id)>{{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Tampilkan Pengawas</button>
                </div>
            </form>

            <div x-data="{ selectAll: false }" class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Menampilkan <strong>{{ $supervisors->count() }}</strong> pengawas. Centang pengawas tertentu, atau biarkan kosong untuk mencetak semua yang tampil.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="selectAll" @change="document.querySelectorAll('[data-supervisor-id]').forEach(cb => cb.checked = $event.target.checked)" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            Pilih Semua
                        </label>
                        <button type="submit" form="card-form" formaction="{{ route('admin.student-cards.preview') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Pratinjau
                        </button>
                        <button type="submit" form="card-form" formaction="{{ route('admin.student-cards.print') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18v-.008z" />
                            </svg>
                            Cetak PDF
                        </button>
                    </div>
                </div>

                <form id="card-form" method="POST" x-on:submit="$refs.supervisorIds.value = [...document.querySelectorAll('#card-form [data-supervisor-id]:checked')].map(cb => cb.value).join(',')" class="divide-y divide-gray-100 dark:divide-gray-800">
                    @csrf
                    <input type="hidden" name="type" value="pengawas">
                    <input type="hidden" name="room" value="{{ $selectedRoom ?? '' }}">
                    <input type="hidden" name="supervisor_ids" x-ref="supervisorIds" value="">
                    @forelse ($supervisors as $supervisor)
                        <label class="flex cursor-pointer items-center gap-4 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <input type="checkbox" data-supervisor-id value="{{ $supervisor->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $supervisor->user?->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $supervisor->user?->email }}</p>
                            </div>
                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700/60 dark:text-gray-400">{{ $supervisor->room?->name ?? 'Belum ditugaskan' }}</span>
                        </label>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada pengawas.</p>
                    @endforelse
                </form>
            </div>
        @else
            <form method="GET" action="{{ route('admin.student-cards.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <input type="hidden" name="type" value="peserta">
                <div class="flex-1">
                    <label for="class" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Kelas</label>
                    <select name="class" id="class" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="" @selected($selectedClass === '' || $selectedClass === 'all')>Semua Kelas</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class }}" @selected($selectedClass === $class)>{{ $class }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Tampilkan Siswa</button>
                </div>
            </form>

            @if ($selectedClass)
                @if ($students->isEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada siswa di kelas <strong>{{ $selectedClass }}</strong>.</p>
                    </div>
                @else
                    <div x-data="{ selectAll: false }" class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
<p class="text-sm text-gray-600 dark:text-gray-400">
                            Menampilkan <strong>{{ $students->count() }}</strong> siswa {{ $selectedClass === 'all' ? 'dari semua kelas' : 'kelas <strong>' . $selectedClass . '</strong>' }}. Centang siswa tertentu, atau biarkan kosong untuk mencetak semua yang tampil.
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="selectAll" @change="document.querySelectorAll('[data-student-id]').forEach(cb => cb.checked = $event.target.checked)" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            Pilih Semua
                        </label>
                                <button type="submit" form="card-form" formaction="{{ route('admin.student-cards.preview') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Pratinjau
                                </button>
                                <button type="submit" form="card-form" formaction="{{ route('admin.student-cards.print') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18v-.008z" />
                                    </svg>
                                    Cetak PDF
                                </button>
                            </div>
                        </div>

                        <form id="card-form" method="POST" x-on:submit="$refs.studentIds.value = [...document.querySelectorAll('#card-form [data-student-id]:checked')].map(cb => cb.value).join(',')" class="divide-y divide-gray-100 dark:divide-gray-800">
                            @csrf
                            <input type="hidden" name="type" value="peserta">
                            <input type="hidden" name="class" value="{{ $selectedClass === 'all' ? '' : $selectedClass }}">
                            <input type="hidden" name="student_ids" x-ref="studentIds" value="">
                            @forelse ($students as $student)
                                <label class="flex cursor-pointer items-center gap-4 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <input type="checkbox" data-student-id value="{{ $student->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">NISN {{ $student->nisn }} &middot; {{ $student->user?->username }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700/60 dark:text-gray-400">{{ $student->class_name }}</span>
                                        @php
                                            $assignments = $roomAssignments[$student->id] ?? collect();
                                            $assignmentRoom = $assignments->isNotEmpty()
                                                ? $assignments->first()->room?->name
                                                : null;
                                            $assignmentSessions = $assignments->isNotEmpty()
                                                ? $assignments->map(fn ($a) => $a->examPeriod?->name)->filter()->unique()->values()->implode(', ')
                                                : null;
                                        @endphp
                                        @if ($assignmentRoom)
                                            <span class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">{{ $assignmentRoom }}</span>
                                        @endif
                                        @if ($assignmentSessions)
                                            <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $assignmentSessions }}</span>
                                        @endif
                                    </div>
                                </label>
                            @empty
                                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada siswa yang dipilih.</p>
                            @endforelse
                        </form>
                    </div>
                @endif
            @else
                <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Silakan pilih kelas terlebih dahulu untuk melihat daftar siswa.</p>
                </div>
            @endif
        @endif
    </div>
</x-layouts.admin>
