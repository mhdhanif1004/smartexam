<x-layouts.guru_mapel :title="'Detail Jawaban - '.($studentModel->user?->name ?? 'Siswa')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Detail Jawaban</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $studentModel->user?->name ?? '-' }} &middot; NISN {{ $studentModel->nisn }} &middot;
                    {{ $scheduleModel->subject?->name }} &middot; {{ $scheduleModel->class_name ?? '-' }}
                </p>
            </div>
            <a href="{{ route('guru_mapel.exam-results.schedule', $scheduleModel->id) }}"
               class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Kembali</a>
        </div>

        @include('admin.partials.flash')

        @if ($result !== null)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Skor Total</dt>
                        <dd class="mt-1 text-2xl font-bold {{ $result->is_passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ number_format((float) $result->total_score, 2) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="mt-1 text-lg font-semibold {{ $result->is_passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $result->is_passed ? 'Lulus' : 'Belum Lulus' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Waktu Selesai</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $session?->finished_at?->format('d M Y H:i') ?? '-' }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                    Halaman ini bersifat hanya-baca. Nilai soal essay dikoreksi manual oleh guru/pengawas.
                </p>
            </div>
        @endif

        <div class="space-y-4">
            @forelse ($items as $index => $item)
                @php $question = $item['question']; @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Soal {{ $index + 1 }}. {{ $question->question_text }}
                        </h3>
                        <span class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                            {{ $question->typeLabel() }}
                        </span>
                    </div>

                    @if ($question->imageUrl())
                        <img src="{{ $question->imageUrl() }}" alt="Gambar soal" class="mt-3 max-h-48 rounded-lg border border-gray-200 dark:border-gray-700" />
                    @endif

                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50">
                            <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Jawaban Siswa</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item['student_display'] }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50">
                            <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Kunci Jawaban</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $item['correct_display'] }}</dd>
                        </div>
                    </dl>

                    @if ($item['answer'])
                        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $item['answer']->is_correct === true ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : ($item['answer']->is_correct === false ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400') }}">
                                {{ $item['answer']->is_correct === true ? 'Benar' : ($item['answer']->is_correct === false ? 'Salah' : 'Belum dinilai') }}
                            </span>
                            @if ($item['answer']->score !== null)
                                <span class="text-xs text-gray-500 dark:text-gray-400">Skor: {{ number_format((float) $item['answer']->score, 2) }}</span>
                            @endif
                            @if ($item['answer']->is_doubtful)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Ditandai ragu</span>
                            @endif
                        </div>
                    @else
                        <p class="mt-4 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">Tidak dijawab</p>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada soal pada ujian ini.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.guru_mapel>
