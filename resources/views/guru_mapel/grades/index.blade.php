<x-layouts.guru_mapel title="Nilai">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Nilai</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Input nilai siswa untuk mapel dan kelas yang Anda ampu.</p>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('guru_mapel.grades.index') }}" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                    <select id="subject_id" name="subject_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">-- Pilih Mapel --</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected((string) $subjectId === (string) $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="classroom_id" :value="__('Kelas')" />
                    <select id="classroom_id" name="classroom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected((string) $classroomId === (string) $classroom->id)>{{ $classroom->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Tampilkan Daftar Siswa</button>
                </div>
            </form>
        </div>

        @if (! $validSelection)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pilih mata pelajaran dan kelas yang valid untuk melihat daftar siswa.</p>
            </div>
        @else
            <form method="POST" action="{{ route('guru_mapel.grades.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="subject_id" value="{{ $subjectId }}" />
                <input type="hidden" name="classroom_id" value="{{ $classroomId }}" />

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Input Nilai — {{ $students->count() }} siswa</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Siswa</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nilai (0 - 100)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($students as $student)
                                    @php
                                        $saved = $savedGrades->get($student->id);
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-3 text-sm">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $student->nisn }}</div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="number" name="score[{{ $student->id }}]" min="0" max="100" step="0.01" value="{{ old('score.'.$student->id, $saved?->score) }}" placeholder="0 - 100" required class="w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="text" name="note[{{ $student->id }}]" value="{{ old('note.'.$student->id, $saved?->note) }}" placeholder="Opsional" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada siswa pada kelas ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($students->isNotEmpty())
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('guru_mapel.grades.students', ['subject_id' => $subjectId, 'classroom_id' => $classroomId]) }}"
                               class="inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                Riwayat Nilai Siswa
                            </a>
                            <a href="{{ route('guru_mapel.grades.export-excel', ['subject_id' => $subjectId, 'classroom_id' => $classroomId]) }}"
                               class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
                                Export Excel
                            </a>
                            <a href="{{ route('guru_mapel.grades.export-pdf', ['subject_id' => $subjectId, 'classroom_id' => $classroomId]) }}"
                               class="inline-flex items-center rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
                                Export PDF
                            </a>
                        </div>
                        <x-primary-button>Simpan Nilai</x-primary-button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-layouts.guru_mapel>
