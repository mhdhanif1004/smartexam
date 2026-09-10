<x-layouts.guru_mapel title="Nilai">
    <div class="space-y-6" x-data="{ tab: 'rekap' }">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Nilai</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nilai CBT terisi otomatis dari hasil ujian. Guru menginput nilai harian/UTS/UAS manual dan kehadiran KBM.</p>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('guru_mapel.grades.index') }}" class="grid gap-4 sm:grid-cols-3">
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
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pilih mapel, lalu klik "Tampilkan" untuk melihat kelas yang Anda ampu.</p>
                </div>
                <div>
                    <x-input-label for="semester_id" :value="__('Semester')" />
                    <select id="semester_id" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        @foreach ($semesters as $semester)
                            <option value="{{ $semester->id }}" @selected((string) $semesterId === (string) $semester->id)>
                                {{ $semester->year }} — Semester {{ $semester->semester }} {{ $semester->is_active ? '(Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Tampilkan Daftar Siswa</button>
                </div>
            </form>
        </div>

        @if (! $validSelection)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pilih mata pelajaran dan kelas yang valid untuk melihat daftar siswa.</p>
            </div>
        @else
            {{-- Tab Navigation --}}
            <div class="border-b border-gray-200 dark:border-gray-800">
                <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                    <button type="button" @click="tab = 'rekap'" :class="tab === 'rekap' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition">
                        Rekap Nilai
                    </button>
                    <button type="button" @click="tab = 'manual'" :class="tab === 'manual' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition">
                        Input Manual
                    </button>
                    <button type="button" @click="tab = 'kehadiran'" :class="tab === 'kehadiran' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'" class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition">
                        Kehadiran KBM
                    </button>
                </nav>
            </div>

            {{-- Tab 1: Rekap --}}
            <div x-show="tab === 'rekap'" x-cloak class="space-y-6">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Rekap Nilai — {{ $students->count() }} siswa</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Kategori dikosongkan bila belum ada data. Nilai Akhir = rata-rata per kategori yang ada.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Siswa</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Harian</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">UTS</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">UAS</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Kehadiran</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nilai Akhir</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($rows as $row)
                                    @php
                                        $student = $row['student'];
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $student->nisn }}</div>
                                        </td>
                                        @foreach ($examTypes as $type)
                                            @php
                                                $cat = $row['breakdown'][$type->code] ?? null;
                                            @endphp
                                            @if ($type->code === 'harian')
                                                <td class="px-4 py-3 text-center align-top">
                                                    @if ($cat !== null)
                                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($cat['average'], 2) }}</span>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ count($cat['entries']) }} entri</div>
                                                        @foreach ($cat['entries'] as $entry)
                                                            <div class="text-xs text-gray-400 italic" title="{{ $entry['note'] }}">{{ $entry['title'] }}: {{ number_format($entry['score'], 0) }}</div>
                                                        @endforeach
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                                    @endif
                                                </td>
                                            @else
                                                <td class="px-4 py-3 text-center align-top">
                                                    @if ($cat !== null)
                                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($cat['average'], 2) }}</span>
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                                    @endif
                                                </td>
                                            @endif
                                        @endforeach
                                        <td class="px-4 py-3 text-center align-top">
                                            @if ($row['score'] !== null)
                                                <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-sm font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                    {{ number_format($row['score'], 2) }}
                                                </span>
                                                @if ($row['note'])
                                                    <div class="mt-1 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $row['note'] }}">{{ $row['note'] }}</div>
                                                @endif
                                            @else
                                                <span class="text-sm text-gray-400 dark:text-gray-500">Belum ada nilai</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('guru_mapel.grades.detail', ['subject_id' => $subjectId, 'classroom_id' => $classroomId, 'student_id' => $student->id]) }}"
                                                   class="inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                                    Lihat Jawaban
                                                </a>
                                                <a href="{{ route('guru_mapel.grades.student-history', ['subject_id' => $subjectId, 'classroom_id' => $classroomId, 'student_id' => $student->id]) }}"
                                                   class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Riwayat
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 5 + count($examTypes) }}" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada siswa pada kelas ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($students->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('guru_mapel.grades.export-excel', ['subject_id' => $subjectId, 'classroom_id' => $classroomId, 'semester_id' => $semesterId]) }}"
                           class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
                            Export Excel
                        </a>
                        <a href="{{ route('guru_mapel.grades.export-pdf', ['subject_id' => $subjectId, 'classroom_id' => $classroomId, 'semester_id' => $semesterId]) }}"
                           class="inline-flex items-center rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
                            Export PDF
                        </a>
                    </div>
                @endif
            </div>

            {{-- Tab 2: Input Manual --}}
            <div x-show="tab === 'manual'" x-cloak class="space-y-6">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Input Nilai Manual</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            UTS &amp; UAS: satu nilai per semester. Harian: judul otomatis "UH n" (bisa diedit) untuk menambah entri baru per siswa.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('guru_mapel.grades.store') }}" class="p-6">
                        @csrf
                        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                        <input type="hidden" name="classroom_id" value="{{ $classroomId }}">
                        <input type="hidden" name="semester_id" value="{{ $semesterId }}">

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Siswa</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Harian (judul + nilai)</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">UTS</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">UAS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    @forelse ($students as $student)
                                        @php
                                            $sr = $rowsByStudent->get($student->id);
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-3 text-sm align-top">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $student->nisn }}</div>
                                            </td>
                                            <td class="px-3 py-3 align-top">
                                                <div class="flex gap-2">
                                                    <input type="text"
                                                           name="entries[{{ $student->id }}][harian][title]"
                                                           placeholder="UH {{ ($sr['harianCount'] ?? 0) + 1 }}"
                                                           class="block w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                                    <input type="number"
                                                           name="entries[{{ $student->id }}][harian][score]"
                                                           min="0" max="100" step="0.01" placeholder="Nilai"
                                                           class="block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                                </div>
                                                <input type="text"
                                                       name="entries[{{ $student->id }}][harian][note]"
                                                       placeholder="Catatan (opsional)"
                                                       class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 max-w-sm">
                                            </td>
                                            <td class="px-3 py-3 align-top">
                                                <input type="number"
                                                       name="entries[{{ $student->id }}][uts][score]"
                                                       min="0" max="100" step="0.01"
                                                       class="block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                                <input type="text"
                                                       name="entries[{{ $student->id }}][uts][note]"
                                                       placeholder="Catatan"
                                                       class="mt-2 block w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                            </td>
                                            <td class="px-3 py-3 align-top">
                                                <input type="number"
                                                       name="entries[{{ $student->id }}][uas][score]"
                                                       min="0" max="100" step="0.01"
                                                       class="block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                                <input type="text"
                                                       name="entries[{{ $student->id }}][uas][note]"
                                                       placeholder="Catatan"
                                                       class="mt-2 block w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada siswa pada kelas ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($students->isNotEmpty())
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                    Simpan Nilai Manual
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Tab 3: Kehadiran KBM --}}
            <div x-show="tab === 'kehadiran'" x-cloak class="space-y-6">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kehadiran KBM</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Skor kehadiran = hadir ÷ (hadir + tidak hadir) × 100. Kosongkan semua angka untuk menghapus data kehadiran siswa.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('guru_mapel.grades.store') }}" class="p-6">
                        @csrf
                        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                        <input type="hidden" name="classroom_id" value="{{ $classroomId }}">
                        <input type="hidden" name="semester_id" value="{{ $semesterId }}">

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Siswa</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Hari</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Hadir</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tidak Hadir</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Skor Otomatis</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    @forelse ($rows as $row)
                                        @php
                                            $student = $row['student'];
                                            $att = $row['breakdown']['kehadiran'] ?? null;
                                            $days = $row['attendanceDays'];
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-3 text-sm">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $student->nisn }}</div>
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <input type="number" name="attendance[{{ $student->id }}][total_days]" min="0" max="32767"
                                                       value="{{ $days['total_days'] ?? '' }}"
                                                       class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <input type="number" name="attendance[{{ $student->id }}][present_days]" min="0" max="32767"
                                                       value="{{ $days['present_days'] ?? '' }}"
                                                       class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <input type="number" name="attendance[{{ $student->id }}][absent_days]" min="0" max="32767"
                                                       value="{{ $days['absent_days'] ?? '' }}"
                                                       class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                            </td>
                                            <td class="px-3 py-3 text-center text-sm">
                                                @if ($att !== null)
                                                    <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-sm font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                        {{ number_format($att['average'], 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-600">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada siswa pada kelas ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($students->isNotEmpty())
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                    Simpan &amp; Hitung Kehadiran
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.guru_mapel>