<x-layouts.guru_mapel title="Detail Jawaban Siswa">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Detail Jawaban Siswa</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Jawaban siswa bersifat read-only; guru hanya dapat menilai atau mengoreksi skor tiap soal.</p>
            </div>
            <a href="{{ route('guru_mapel.grades.index', ['subject_id' => $subjectId, 'classroom_id' => $classroomId]) }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                &larr; Kembali ke Nilai
            </a>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</h3>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">NISN</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $student->nisn }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Kelas</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $classroom->name }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Mapel</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $subject->name }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Status Ujian</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        @if ($session)
                            {{ $session->examSchedule?->subject?->name ?? $subject->name }} — {{ \App\Models\ExamSession::STATUSES[$session->status] ?? $session->status }}
                        @else
                            Belum ada hasil ujian
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($grade?->is_override)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Nilai saat ini merupakan <strong>koreksi guru</strong>: {{ number_format((float) $grade->score, 2) }}.
                    @if ($grade->note)
                        <span class="text-amber-700 dark:text-amber-200">({{ $grade->note }})</span>
                    @endif
                </div>
            @endif
        </div>

        @if (! $session)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Siswa belum mengerjakan ujian pada mapel dan kelas ini.</p>
            </div>
        @else
            <form method="POST" action="{{ route('guru_mapel.grades.save-scores') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="subject_id" value="{{ $subjectId }}" />
                <input type="hidden" name="classroom_id" value="{{ $classroomId }}" />
                <input type="hidden" name="student_id" value="{{ $student->id }}" />
                <input type="hidden" name="session_id" value="{{ $session->id }}" />

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Jawaban per Soal — {{ $session->examAnswers->count() }} soal</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="w-10 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Soal</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Jawaban Siswa</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Kunci</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Skor Sistem</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Koreksi Guru</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($session->examAnswers as $index => $answer)
                                    @php($question = $answer->question)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="text-gray-900 dark:text-gray-100">{{ $question?->question_text }}</div>
                                            <span class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                {{ $question?->typeLabel() ?? '?' }} · {{ $question?->score_weight ?? 0 }} poin
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                            @if ($question && $question->type === \App\Models\Question::TYPE_ESSAY)
                                                <div class="max-w-xs whitespace-pre-wrap">{{ is_array($answer->student_answer) ? implode(', ', $answer->student_answer) : $answer->student_answer }}</div>
                                            @else
                                                {{ is_array($answer->student_answer) ? implode(', ', $answer->student_answer) : $answer->student_answer }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            @if ($question && $question->type !== \App\Models\Question::TYPE_ESSAY)
                                                {{ is_array($question->answer_key) ? implode(', ', array_map(static fn ($item) => is_bool($item) ? ($item ? 'Benar' : 'Salah') : (string) $item, (array) $question->answer_key)) : $question->answer_key }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($answer->score === null)
                                                @if ($question && $question->type === \App\Models\Question::TYPE_ESSAY)
                                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Belum dinilai</span>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500">Dikosongkan</span>
                                                @endif
                                            @else
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $answer->score, 2) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="scores[{{ $answer->id }}]" min="0" max="{{ (float) $question?->score_weight ?? 0 }}" step="0.01"
                                                   value="{{ old('scores.'.$answer->id, $answer->score) }}"
                                                   placeholder="0 - {{ (float) $question?->score_weight ?? 0 }}"
                                                   class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                                            @error('scores.'.$answer->id)
                                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada jawaban yang tercatat pada sesi ujian ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <x-input-label for="note" :value="__('Catatan (opsional)')" />
                    <input type="text" id="note" name="note" value="{{ old('note', $grade?->note) }}" maxlength="255"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                    <div class="mt-4 flex items-center gap-3">
                        <x-primary-button>Simpan Skor & Nilai</x-primary-button>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Total dihitung ulang dari seluruh skor per soal.</span>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-layouts.guru_mapel>