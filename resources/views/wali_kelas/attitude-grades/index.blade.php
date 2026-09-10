<x-layouts.wali_kelas title="Nilai Sikap">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Nilai Sikap / Perilaku</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Kelola nilai sikap siswa di kelas <span class="font-semibold">{{ $wali->classroom?->name ?? '-' }}</span>.
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

        {{-- Pilih Semester --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <label for="semester_select" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Semester</label>
            <select
                id="semester_select"
                class="mt-1 block w-full max-w-xs rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                onchange="window.location.href='{{ route('wali_kelas.attitude-grades.index') }}?semester_id=' + this.value"
            >
                @foreach ($semesters as $semester)
                    <option value="{{ $semester->id }}" {{ $semester->id == $selectedSemesterId ? 'selected' : '' }}>
                        {{ $semester->year }} — Semester {{ $semester->semester }} {{ $semester->is_active ? '(Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Tabel Nilai Sikap --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Daftar Nilai Sikap Siswa</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Klik tombol edit untuk mengubah nilai, atau gunakan form di bawah untuk menambah nilai baru.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NISN / Nama Siswa</th>
                            @foreach ($aspects as $aspect)
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $aspect->name }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($students as $index => $student)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $student->user?->name ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">NISN: {{ $student->nisn ?? '-' }}</div>
                                </td>
                                @foreach ($aspects as $aspect)
                                    @php
                                        $key = $student->id.'_'.$aspect->id;
                                        $grade = $existingGrades->get($key);
                                    @endphp
                                    <td class="px-4 py-3 text-center text-sm">
                                        @if ($grade)
                                            <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-sm font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                {{ number_format($grade->score, 0) }}
                                            </span>
                                            @if ($grade->note)
                                                <p class="mt-1 text-xs text-gray-500 italic">{{ Str::limit($grade->note, 30) }}</p>
                                            @endif
                                        @else
                                            <span class="text-gray-400 italic">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-1">
                                        @foreach ($aspects as $aspect)
                                            @php
                                                $key = $student->id.'_'.$aspect->id;
                                                $grade = $existingGrades->get($key);
                                            @endphp
                                            <button
                                                type="button"
                                                @click="
                                                    editMode = true;
                                                    editId = {{ $grade?->id ?? 'null' }};
                                                    editStudentId = {{ $student->id }};
                                                    editAspectId = {{ $aspect->id }};
                                                    editScore = '{{ $grade?->score ?? '' }}';
                                                    editNote = '{{ addslashes($grade?->note ?? '') }}';
                                                    showModal = true;
                                                "
                                                class="inline-flex items-center justify-center rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-gray-800 dark:hover:text-indigo-400"
                                                title="Edit {{ $aspect->name }} — {{ $student->user?->name }}"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </button>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + count($aspects) }}" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada siswa di kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Form Tambah Nilai Sikap --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900" x-data="{ showAddForm: false }">
            <button
                type="button"
                @click="showAddForm = !showAddForm"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Nilai Sikap
            </button>

            <div x-show="showAddForm" x-cloak x-transition class="mt-4">
                <form method="POST" action="{{ route('wali_kelas.attitude-grades.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $selectedSemesterId }}">

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="add_student_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Siswa</label>
                            <select name="student_id" id="add_student_id" required class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">— Pilih Siswa —</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}">{{ $student->user?->name }} ({{ $student->nisn }})</option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="add_aspect_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aspek Sikap</label>
                            <select name="attitude_aspect_id" id="add_aspect_id" required class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">— Pilih Aspek —</option>
                                @foreach ($aspects as $aspect)
                                    <option value="{{ $aspect->id }}">{{ $aspect->name }}</option>
                                @endforeach
                            </select>
                            @error('attitude_aspect_id')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="add_score" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nilai (0-100)</label>
                            <input type="number" name="score" id="add_score" min="0" max="100" step="0.01" required
                                class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            @error('score')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="add_note" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan (Opsional)</label>
                        <textarea name="note" id="add_note" rows="2" maxlength="500"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                            placeholder="Catatan tentang penilaian sikap siswa..."></textarea>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit --}}
    <div
        x-data="{
            showModal: false,
            editMode: false,
            editId: null,
            editStudentId: null,
            editAspectId: null,
            editScore: '',
            editNote: ''
        }"
        x-cloak
    >
        <div x-show="showModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="showModal = false">
            <div x-show="showModal" x-transition class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Edit Nilai Sikap</h3>
                <form method="POST" :action="editMode ? `/wali_kelas/attitude-grades/${editId}` : ''" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="semester_id" value="{{ $selectedSemesterId }}">
                    <input type="hidden" name="student_id" :value="editStudentId">
                    <input type="hidden" name="attitude_aspect_id" :value="editAspectId">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Siswa</label>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="
                            ({{ $students->pluck('id', 'id')->toJson() }})[editStudentId]
                            || 'Siswa #' + editStudentId
                        "></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aspek</label>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="
                            ({{ $aspects->mapWithKeys(fn($a) => [$a->id => $a->name])->toJson() }})[editAspectId]
                            || 'Aspek #' + editAspectId
                        "></p>
                    </div>

                    <div>
                        <label for="edit_score" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nilai (0-100)</label>
                        <input type="number" name="score" id="edit_score" min="0" max="100" step="0.01" required x-model="editScore"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    </div>

                    <div>
                        <label for="edit_note" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                        <textarea name="note" id="edit_note" rows="2" maxlength="500" x-model="editNote"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            Batal
                        </button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>

                {{-- Form Hapus --}}
                <form method="POST" :action="editMode ? `/wali_kelas/attitude-grades/${editId}` : ''" class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        onclick="return confirm('Yakin ingin menghapus data nilai sikap ini?')"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-500/10">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        Hapus Data Ini
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.wali_kelas>
