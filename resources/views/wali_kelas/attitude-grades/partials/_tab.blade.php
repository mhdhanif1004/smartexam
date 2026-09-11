@php
$wali = $wali ?? null;
$students = $students ?? collect();
$aspects = $aspects ?? collect();
$semesters = $semesters ?? collect();
$selectedSemesterId = $selectedSemesterId ?? null;
$existingGrades = $existingGrades ?? collect();
$gradesByStudent = $gradesByStudent ?? [];
@endphp

<div class="space-y-6"
     x-data="{
         showModal: false,
         editMode: false,
         editStudentId: null,
         editStudentName: '',
         editSemesterId: {{ $selectedSemesterId ?? 'null' }},
         formGrades: {
             @foreach ($aspects as $aspect)
                 {{ $aspect->id }}: { score: '', note: '' },
             @endforeach
         },
         allGrades: @js($gradesByStudent),
         openModal(studentId, studentName, hasGrades) {
             this.editMode = hasGrades;
             this.editStudentId = studentId;
             this.editStudentName = studentName;
             const studentData = this.allGrades[studentId] || {};
             const fg = {};
             @foreach ($aspects as $aspect)
                 fg[{{ $aspect->id }}] = {
                     score: (studentData[{{ $aspect->id }}] && studentData[{{ $aspect->id }}].score !== null)
                         ? studentData[{{ $aspect->id }}].score
                         : '',
                     note: (studentData[{{ $aspect->id }}] && studentData[{{ $aspect->id }}].note)
                         ? studentData[{{ $aspect->id }}].note
                         : ''
                 };
             @endforeach
             this.formGrades = fg;
             this.showModal = true;
         }
     }">
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

    {{-- Tabel Nilai Sikap --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Daftar Nilai Sikap Siswa</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Klik tombol edit untuk mengubah semua nilai sikap siswa sekaligus.</p>
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
                                <button
                                    type="button"
                                    @click="openModal({{ $student->id }}, '{{ addslashes($student->user?->name ?? '') }}', {{ $existingGrades->where('student_id', $student->id)->isNotEmpty() ? 'true' : 'false' }})"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 min-w-[84px] whitespace-nowrap"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    {{ $existingGrades->where('student_id', $student->id)->isNotEmpty() ? 'Edit' : 'Isi Nilai' }}
                                </button>
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

    {{-- Modal Edit Nilai Sikap (Bulk) --}}
    <div x-cloak>
        <div x-show="showModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="showModal = false">
            <div x-show="showModal" x-transition class="w-full max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    <span x-text="editMode ? 'Edit Nilai Sikap' : 'Isi Nilai Sikap'"></span> - <span x-text="editStudentName"></span>
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <span x-show="editMode" x-cloak>Ubah nilai untuk semua aspek sikap siswa ini.</span>
                    <span x-show="!editMode" x-cloak>Isi nilai awal untuk semua aspek sikap siswa ini.</span>
                </p>

                <form method="POST" action="{{ route('wali_kelas.attitude-grades.bulk') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="semester_id" :value="editSemesterId">
                    <input type="hidden" name="student_id" :value="editStudentId">

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-800/50 max-h-[60vh] overflow-y-auto">
                        @foreach ($aspects as $index => $aspect)
                            <div class="mb-4 last:mb-0">
                                <input type="hidden" name="grades[{{ $aspect->id }}][aspect_id]" value="{{ $aspect->id }}">
                                <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $aspect->name }}
                                </label>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <input
                                            type="number"
                                            name="grades[{{ $aspect->id }}][score]"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            x-model="formGrades[{{ $aspect->id }}].score"
                                            placeholder="Nilai (0-100)"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        >
                                    </div>
                                    <div>
                                        <input
                                            type="text"
                                            name="grades[{{ $aspect->id }}][note]"
                                            maxlength="500"
                                            x-model="formGrades[{{ $aspect->id }}].note"
                                            placeholder="Catatan (opsional)"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        >
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                            Batal
                        </button>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                            Simpan Semua
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
