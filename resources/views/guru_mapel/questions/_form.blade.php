@php
    $question ??= null;
    $selectedSubjectId = old('subject_id', $question?->subject_id);
    $matchingPairs = old('matching_left')
        ? collect(old('matching_left'))
            ->map(fn ($left, $index) => ['left' => $left, 'right' => old('matching_right')[$index] ?? ''])
            ->filter(fn ($pair) => $pair['left'] !== '' || $pair['right'] !== '')
            ->values()
            ->all()
        : ($question?->matchingPairs() ?? []);
    if (empty($matchingPairs)) {
        $matchingPairs = [['left' => '', 'right' => '']];
    }
@endphp

<form
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    class="max-w-3xl space-y-6"
    x-data="{
        type: @js(old('type', $question?->type ?? \App\Models\Question::TYPE_SINGLE_CHOICE)),
        subject: @js((string) $selectedSubjectId),
        selected: @js(array_map('intval', old('classroom_ids', $question?->classrooms?->pluck('id')->all() ?? []))),
        groups: @js($classroomsBySubject),
        pairs: @js($matchingPairs),
        img: { preview: '' },
    }"
>
    @csrf
    @if (isset($method) && $method === 'PUT')
        @method('PUT')
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                <select id="subject_id" name="subject_id" required x-model="subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->code }})</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="type" :value="__('Jenis Soal')" />
                <select id="type" name="type" required x-model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="question_text" :value="__('Pertanyaan')" />
                <textarea id="question_text" name="question_text" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" placeholder="Tulis pertanyaan...">{{ old('question_text', $question?->question_text) }}</textarea>
                <x-input-error :messages="$errors->get('question_text')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="score_weight" :value="__('Bobot Nilai (poin)')" />
                <x-text-input id="score_weight" name="score_weight" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" value="{{ old('score_weight', $question?->score_weight ?? 10) }}" required />
                <x-input-error :messages="$errors->get('score_weight')" class="mt-2" />
            </div>
        </div>
    </div>

    {{-- Kelas Target (hanya kelas yang diampu untuk mapel terpilih) --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kelas Target</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih minimal satu kelas yang Anda ampu. Soal tanpa kelas target tidak akan muncul di ujian manapun.</p>
            </div>
            <button type="button" @click="selected = []" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Bersihkan</button>
        </div>
        <div x-show="subject && groups[subject]" class="mt-5 grid gap-6">
            <template x-for="classroom in groups[subject]" :key="classroom.id">
                <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800" x-show="true" style="display: block">
                    <input type="checkbox" name="classroom_ids[]" :value="classroom.id" x-model="selected" class="mr-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="classroom.name"></span>
                </label>
            </template>
        </div>
        <p x-show="!subject" class="mt-5 text-sm text-gray-400 dark:text-gray-500">Pilih mata pelajaran terlebih dahulu untuk melihat kelas target yang dapat dipilih.</p>
        <template x-if="subject && groups[subject] && groups[subject].length === 0">
            <p class="mt-5 text-sm text-amber-600 dark:text-amber-400">Tidak ada kelas yang diampu untuk mapel terpilih.</p>
        </template>
        <x-input-error :messages="$errors->get('classroom_ids')" class="mt-3" />
    </div>

    {{-- Gambar Soal --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Gambar Soal</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Opsional. Format jpg, jpeg, png, atau webp (maks 3 MB).</p>
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="flex-1">
                <input
                    type="file"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    @change="img.preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : ''"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20"
                />
                @if (isset($question) && $question->image_path)
                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        Hapus gambar yang sudah ada
                    </label>
                @endif
                <x-input-error :messages="$errors->get('image')" class="mt-2" />
            </div>
            <div class="flex-1">
                @if (isset($question) && $question->imageUrl())
                    <img src="{{ $question->imageUrl() }}" class="max-h-48 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700" alt="Gambar soal saat ini" />
                @endif
                <img x-show="img.preview" :src="img.preview" class="max-h-48 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau gambar" />
            </div>
        </div>
    </div>

    {{-- Pilihan Ganda (satu jawaban) --}}
    <template x-if="type === 'single_choice'">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Opsi Jawaban (Pilihan Ganda - Satu Jawaban)</h3>
            <p class="mt-1 text-sm text-gray-500">Isi minimal 2 opsi dan tandai satu jawaban yang benar.</p>
            <div class="mt-5 space-y-3">
                @foreach ($letters as $letter)
                    <div class="flex items-center gap-3">
                        <input type="radio" name="single_answer" value="{{ $letter }}" @checked(old('single_answer', isset($question) ? $question->answer_key : null) === $letter) class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700 dark:bg-gray-700/60 dark:text-gray-300">{{ $letter }}</span>
                        <x-text-input type="text" name="single_options[{{ $letter }}]" class="block w-full" value="{{ old('single_options.'.$letter, isset($question) && isset($question->options[$letter]) ? $question->options[$letter] : '') }}" placeholder="Teks opsi {{ $letter }}" />
                    </div>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('single_answer')" class="mt-2" />
            <x-input-error :messages="$errors->get('single_options')" class="mt-2" />
        </div>
    </template>

    {{-- Pilihan Ganda (banyak jawaban) --}}
    <template x-if="type === 'multiple_choice'">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Opsi Jawaban (Pilihan Ganda - Banyak Jawaban)</h3>
            <p class="mt-1 text-sm text-gray-500">Isi minimal 2 opsi dan centang semua jawaban yang benar.</p>
            <div class="mt-5 space-y-3">
                @foreach ($letters as $letter)
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="multiple_answer[]" value="{{ $letter }}" @checked(in_array($letter, old('multiple_answer', isset($question) && is_array($question->answer_key) ? $question->answer_key : []))) class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700 dark:bg-gray-700/60 dark:text-gray-300">{{ $letter }}</span>
                        <x-text-input type="text" name="multiple_options[{{ $letter }}]" class="block w-full" value="{{ old('multiple_options.'.$letter, isset($question) && isset($question->options[$letter]) ? $question->options[$letter] : '') }}" placeholder="Teks opsi {{ $letter }}" />
                    </div>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('multiple_answer')" class="mt-2" />
            <x-input-error :messages="$errors->get('multiple_options')" class="mt-2" />
        </div>
    </template>

    {{-- Benar / Salah --}}
    <template x-if="type === 'true_false'">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kunci Jawaban (Benar / Salah)</h3>
            <p class="mt-1 text-sm text-gray-500">Pilih salah satu kunci jawaban yang benar.</p>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                    <input type="radio" name="true_false_answer" value="1" @checked(old('true_false_answer', isset($question) ? (string) (int) $question->answer_key : '1') === '1') class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Benar</span>
                </label>
                <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                    <input type="radio" name="true_false_answer" value="0" @checked(old('true_false_answer') === '0') class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Salah</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('true_false_answer')" class="mt-2" />
        </div>
    </template>

    {{-- Menjodohkan --}}
    <template x-if="type === 'matching'">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Pasangan Menjodohkan (Kiri - Kanan)</h3>
            <p class="mt-1 text-sm text-gray-500">Setiap item kolom kiri dipasangkan dengan kolom kanan berdasarkan urutan baris. Minimal 2 pasangan.</p>
            <div class="mt-5 space-y-3">
                <template x-for="(pair, index) in pairs" :key="index">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700 dark:bg-gray-700/60 dark:text-gray-300" x-text="String.fromCharCode(65 + index)"></span>
                        <x-text-input type="text" name="matching_left[]" x-model="pair.left" class="block w-full" placeholder="Kolom kiri" />
                        <span class="text-gray-400 dark:text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                        <x-text-input type="text" name="matching_right[]" x-model="pair.right" class="block w-full" placeholder="Kolom kanan" />
                        <button type="button" @click="pairs.splice(index, 1)" class="rounded-md p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 dark:text-gray-500 dark:hover:bg-rose-500/20 dark:hover:text-rose-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </template>
                <button type="button" @click="pairs.push({ left: '', right: '' })" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50 dark:border-indigo-500/50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                    + Tambah Pasangan
                </button>
            </div>
            <x-input-error :messages="$errors->get('matching_left')" class="mt-2" />
            <x-input-error :messages="$errors->get('matching_right')" class="mt-2" />
        </div>
    </template>

    {{-- Essay --}}
    <template x-if="type === 'essay'">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kunci Jawaban / Rubrik Penilaian (Essay)</h3>
            <p class="mt-1 text-sm text-gray-500">Opsional. Tulis kunci jawaban atau rubrik sebagai referensi koreksi manual nanti.</p>
            <div class="mt-5">
                <textarea id="essay_answer" name="essay_answer" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" placeholder="Tulis kunci jawaban atau rubrik (opsional)...">{{ old('essay_answer', isset($question) && is_string($question->answer_key) ? $question->answer_key : '') }}</textarea>
            </div>
            <x-input-error :messages="$errors->get('essay_answer')" class="mt-2" />
        </div>
    </template>

    <div class="flex justify-end gap-3">
        <a href="{{ route('guru_mapel.questions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
        <x-primary-button>{{ $submitLabel ?? 'Simpan' }}</x-primary-button>
    </div>
</form>
