@php
$wali = $wali ?? null;
$students = $students ?? collect();
$semesters = $semesters ?? collect();
$selectedSemesterId = $selectedSemesterId ?? null;
$selectedStudentId = $selectedStudentId ?? null;
$notes = $notes ?? collect();
@endphp

<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Catatan Wali Kelas</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Tulis dan lihat catatan pembinaan siswa di kelas <span class="font-semibold">{{ $wali->classroom?->name ?? '-' }}</span>.
            Setiap entri bersifat kronologis (append-only).
        </p>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filter Semester + Siswa --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="semester_select" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Semester</label>
                <select
                    id="semester_select"
                    class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    onchange="window.location.href='{{ route('wali_kelas.dashboard', ['tab' => 'catatan']) }}?semester_id=' + this.value + '&student_id=' + (document.getElementById('student_select').value || '')"
                >
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester->id }}" {{ $semester->id == $selectedSemesterId ? 'selected' : '' }}>
                            {{ $semester->year }} — Semester {{ $semester->semester }} {{ $semester->is_active ? '(Aktif)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="student_select" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Filter Siswa (opsional)</label>
                <select
                    id="student_select"
                    class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    onchange="window.location.href='{{ route('wali_kelas.dashboard', ['tab' => 'catatan']) }}?semester_id={{ $selectedSemesterId }}&student_id=' + this.value"
                >
                    <option value="">Semua Siswa</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" {{ $student->id == $selectedStudentId ? 'selected' : '' }}>
                            {{ $student->user?->name ?? '-' }} ({{ $student->nisn ?? '-' }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Form Tambah Catatan Baru --}}
    <div x-data="{ showForm: false }" class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800 flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Tambah Catatan Baru</h3>
            <button
                type="button"
                @click="showForm = !showForm"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span x-text="showForm ? 'Tutup' : 'Tambah Catatan'"></span>
            </button>
        </div>

        <div x-show="showForm" x-transition x-cloak class="p-6">
            <form method="POST" action="{{ route('wali_kelas.catatan.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="semester_id" value="{{ $selectedSemesterId }}">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="form_student_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Siswa *</label>
                        <select
                            id="form_student_id"
                            name="student_id"
                            required
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option value="">Pilih siswa...</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" {{ $selectedStudentId && $selectedStudentId == $student->id ? 'selected' : '' }}>
                                    {{ $student->user?->name ?? '-' }} ({{ $student->nisn ?? '-' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="form_tipe" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Tipe Catatan</label>
                        <select
                            id="form_tipe"
                            name="tipe"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option value="">— Pilih tipe —</option>
                            @foreach (\App\Models\WaliKelasNote::TIPE_OPTIONS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="form_catatan" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Isi Catatan *</label>
                    <textarea
                        id="form_catatan"
                        name="catatan"
                        required
                        maxlength="2000"
                        rows="3"
                        placeholder="Tulis catatan pembinaan siswa..."
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Maks. 2000 karakter</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Simpan Catatan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Daftar Catatan per Siswa --}}
    <div class="space-y-4">
        @forelse ($students as $student)
            @php
                $studentNotes = $notes->get($student->id, collect());
            @endphp

            {{-- Sembunyikan siswa tanpa catatan + kalau filter aktif, tampilkan hanya siswa itu --}}
            @if ($selectedStudentId && $selectedStudentId != $student->id)
                @continue
            @endif

            {{-- Tampilkan semua siswa (termasuk yang belum punya catatan) --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                            {{ $student->user?->name ?? '-' }}
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">NISN: {{ $student->nisn ?? '-' }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        {{ $studentNotes->count() }} catatan
                    </span>
                </div>

                @if ($studentNotes->isEmpty())
                    <div class="px-6 py-8 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada catatan untuk siswa ini di semester ini.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($studentNotes as $note)
                            <div class="px-6 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            @if ($note->tipe)
                                                @php
                                                    $tipeColors = [
                                                        'observasi' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                                        'pelanggaran' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                                        'prestasi' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                                        'lainnya' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                                    ];
                                                    $colorClass = $tipeColors[$note->tipe] ?? $tipeColors['lainnya'];
                                                @endphp
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $colorClass }}">
                                                    {{ $note->tipeLabel() }}
                                                </span>
                                            @endif
                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ $note->created_at->translatedFormat('d M Y, H:i') }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $note->catatan }}</p>
                                    </div>
                                    @if ($note->waliKelas?->user)
                                        <p class="shrink-0 text-xs text-gray-400 dark:text-gray-500 italic">
                                            — {{ $note->waliKelas->user->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Belum ada siswa</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelas ini belum memiliki siswa yang terdaftar.</p>
            </div>
        @endforelse
    </div>
</div>
