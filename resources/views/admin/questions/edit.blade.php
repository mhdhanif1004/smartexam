<x-layouts.admin title="Edit Soal">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Soal</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui soal dan kunci jawaban.</p>
        </div>

        @php
            $storedOptions = $question->options ?? [];
            $answerKey = $question->answer_key;
            $singleAnswer = old('single_answer', is_string($answerKey) ? $answerKey : null);
            $multipleAnswers = old('multiple_answer', is_array($answerKey) ? $answerKey : []);
            $trueFalseAnswer = old('true_false_answer', $answerKey ? '1' : '0');
            $essayAnswer = old('essay_answer', is_string($answerKey) ? $answerKey : '');
            $pairs = old('matching_left')
                ? collect(old('matching_left'))->map(fn ($left, $index) => ['left' => $left, 'right' => old('matching_right')[$index] ?? ''])->values()->all()
                : ($question->matchingPairs() ?: [['left' => '', 'right' => '']]);
        @endphp

        <form
            method="POST"
            action="{{ route('admin.questions.update', $question) }}"
            enctype="multipart/form-data"
            class="max-w-3xl space-y-6"
            x-data="{ type: @js(old('type', $question->type)), pairs: @js($pairs), img: { preview: '', hasExisting: @js((bool) $question->image_path), existingUrl: @js($question->image_path ? asset('storage/'.$question->image_path) : ''), remove() { this.hasExisting = false; this.preview = ''; } } }"
        >
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                        <select id="subject_id" name="subject_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $question->subject_id) == $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>
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
                        <textarea id="question_text" name="question_text" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" placeholder="Tulis pertanyaan...">{{ old('question_text', $question->question_text) }}</textarea>
                        <x-input-error :messages="$errors->get('question_text')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="score_weight" :value="__('Bobot Nilai (poin)')" />
                        <x-text-input id="score_weight" name="score_weight" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" value="{{ old('score_weight', $question->score_weight) }}" required />
                        <x-input-error :messages="$errors->get('score_weight')" class="mt-2" />
                    </div>
                </div>
            </div>

            {{-- Gambar Soal --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Gambar Soal</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Opsional. Gambar akan ditampilkan di bawah pertanyaan saat ujian berlangsung. Pilih file baru untuk mengganti, atau centang hapus untuk menghapusnya. Format jpg, jpeg, png, atau webp (maks 3 MB).</p>
                <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex-1">
                        <input
                            type="file"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                            @change="img.preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : ''"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20"
                        />
                        <x-input-error :messages="$errors->get('image')" class="mt-2" />
                        <label class="mt-3 flex w-fit items-center gap-2 text-sm font-medium text-rose-700 dark:text-rose-300" x-show="img.hasExisting">
                            <input type="checkbox" name="remove_image" value="1" @change="img.remove()" class="h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800" />
                            Hapus gambar saat ini
                        </label>
                    </div>
                    <div class="flex-1">
                        <img x-show="img.hasExisting && !img.preview" :src="img.existingUrl" class="max-h-48 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700" alt="Gambar saat ini" />
                        <img x-show="img.preview" :src="img.preview" class="max-h-48 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau gambar baru" />
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
                                <input type="radio" name="single_answer" value="{{ $letter }}" @checked($singleAnswer === $letter) class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700 dark:bg-gray-700/60 dark:text-gray-300">{{ $letter }}</span>
                                <x-text-input type="text" name="single_options[{{ $letter }}]" class="block w-full" value="{{ old('single_options.'.$letter, $storedOptions[$letter] ?? '') }}" placeholder="Teks opsi {{ $letter }}" />
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
                                <input type="checkbox" name="multiple_answer[]" value="{{ $letter }}" @checked(in_array($letter, $multipleAnswers)) class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700 dark:bg-gray-700/60 dark:text-gray-300">{{ $letter }}</span>
                                <x-text-input type="text" name="multiple_options[{{ $letter }}]" class="block w-full" value="{{ old('multiple_options.'.$letter, $storedOptions[$letter] ?? '') }}" placeholder="Teks opsi {{ $letter }}" />
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
                            <input type="radio" name="true_false_answer" value="1" @checked($trueFalseAnswer === '1') class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Benar</span>
                        </label>
                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                            <input type="radio" name="true_false_answer" value="0" @checked($trueFalseAnswer === '0') class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
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
                    <p class="mt-1 text-sm text-gray-500">Setiap item kolom kiri dipasangkan dengan item kolom kanan berdasarkan urutan baris. Minimal 2 pasangan.</p>
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
                        <textarea id="essay_answer" name="essay_answer" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" placeholder="Tulis kunci jawaban atau rubrik (opsional)...">{{ $essayAnswer }}</textarea>
                    </div>
                    <x-input-error :messages="$errors->get('essay_answer')" class="mt-2" />
                </div>
            </template>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.questions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
