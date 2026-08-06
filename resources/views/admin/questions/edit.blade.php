<x-layouts.admin title="Edit Soal">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Edit Soal</h2>
            <p class="mt-1 text-sm text-gray-500">Perbarui soal dan kunci jawaban.</p>
        </div>

        @php
            $storedOptions = $question->options ?? [];
            $pairs = old('matching_left')
                ? collect(old('matching_left'))->map(fn ($left, $index) => ['left' => $left, 'right' => old('matching_right')[$index] ?? ''])->values()->all()
                : (collect($storedOptions['left'] ?? [])->map(fn ($left, $index) => ['left' => $left, 'right' => $storedOptions['right'][$index] ?? ''])->values()->all() ?: [['left' => '', 'right' => '']]);
        @endphp

        <form
            method="POST"
            action="{{ route('admin.questions.update', $question) }}"
            class="max-w-3xl space-y-6"
            x-data="{ type: @js(old('type', $question->type)), pairs: @js($pairs) }"
        >
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                        <select id="subject_id" name="subject_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $question->subject_id) == $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="type" :value="__('Jenis Soal')" />
                        <select id="type" name="type" required x-model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="question_text" :value="__('Pertanyaan')" />
                        <textarea id="question_text" name="question_text" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Tulis pertanyaan...">{{ old('question_text', $question->question_text) }}</textarea>
                        <x-input-error :messages="$errors->get('question_text')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="score_weight" :value="__('Bobot Nilai (poin)')" />
                        <x-text-input id="score_weight" name="score_weight" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" value="{{ old('score_weight', $question->score_weight) }}" required />
                        <x-input-error :messages="$errors->get('score_weight')" class="mt-2" />
                    </div>
                </div>
            </div>

            {{-- Pilihan Ganda (satu jawaban) --}}
            <div x-show="type === 'single_choice'" x-cloak class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Opsi Jawaban (Pilih Ganda - Satu Jawaban)</h3>
                <p class="mt-1 text-sm text-gray-500">Isi minimal 2 opsi dan tandai satu jawaban yang benar.</p>
                <div class="mt-5 space-y-3">
                    @foreach ($letters as $letter)
                        <div class="flex items-center gap-3">
                            <input type="radio" name="single_answer" value="{{ $letter }}" @checked(old('single_answer', $question->answer_key) === $letter) class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700">{{ $letter }}</span>
                            <x-text-input type="text" name="single_options[{{ $letter }}]" class="block w-full" value="{{ old('single_options.'.$letter, $storedOptions[$letter] ?? '') }}" placeholder="Teks opsi {{ $letter }}" />
                        </div>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('single_answer')" class="mt-2" />
                <x-input-error :messages="$errors->get('options')" class="mt-2" />
            </div>

            {{-- Pilihan Ganda (banyak jawaban) --}}
            <div x-show="type === 'multiple_choice'" x-cloak class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Opsi Jawaban (Pilih Ganda - Banyak Jawaban)</h3>
                <p class="mt-1 text-sm text-gray-500">Isi minimal 2 opsi dan centang semua jawaban yang benar.</p>
                <div class="mt-5 space-y-3">
                    @foreach ($letters as $letter)
                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="multiple_answer[]" value="{{ $letter }}" @checked(in_array($letter, old('multiple_answer', $question->answer_key ?? []))) class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700">{{ $letter }}</span>
                            <x-text-input type="text" name="multiple_options[{{ $letter }}]" class="block w-full" value="{{ old('multiple_options.'.$letter, $storedOptions[$letter] ?? '') }}" placeholder="Teks opsi {{ $letter }}" />
                        </div>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('multiple_answer')" class="mt-2" />
                <x-input-error :messages="$errors->get('options')" class="mt-2" />
            </div>

            {{-- Benar / Salah --}}
            <div x-show="type === 'true_false'" x-cloak class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Kunci Jawaban (Benar / Salah)</h3>
                <p class="mt-1 text-sm text-gray-500">Pilih salah satu kunci jawaban yang benar.</p>
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3">
                        <input type="radio" name="true_false_answer" value="1" @checked(old('true_false_answer', $question->answer_key ? '1' : '0') === '1') class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-800">Benar</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3">
                        <input type="radio" name="true_false_answer" value="0" @checked(old('true_false_answer', $question->answer_key ? '1' : '0') === '0') class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-800">Salah</span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('true_false_answer')" class="mt-2" />
            </div>

            {{-- Menjodohkan --}}
            <div x-show="type === 'matching'" x-cloak class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Pasangan Menjodohkan (Kiri - Kanan)</h3>
                <p class="mt-1 text-sm text-gray-500">Setiap item kolom kiri dipasangkan dengan item kolom kanan berdasarkan urutan baris.</p>
                <div class="mt-5 space-y-3">
                    <template x-for="(pair, index) in pairs" :key="index">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm font-bold text-gray-700" x-text="String.fromCharCode(65 + index)"></span>
                            <x-text-input type="text" name="matching_left[]" x-model="pair.left" class="block w-full" placeholder="Kolom kiri" />
                            <span class="text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </span>
                            <x-text-input type="text" name="matching_right[]" x-model="pair.right" class="block w-full" placeholder="Kolom kanan" />
                            <button type="button" @click="pairs.splice(index, 1)" class="rounded-md p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="pairs.push({ left: '', right: '' })" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50">
                        + Tambah Pasangan
                    </button>
                </div>
                <x-input-error :messages="$errors->get('matching_left')" class="mt-2" />
                <x-input-error :messages="$errors->get('matching_right')" class="mt-2" />
            </div>

            {{-- Essay --}}
            <div x-show="type === 'essay'" x-cloak class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Kunci Jawaban (Essay)</h3>
                <p class="mt-1 text-sm text-gray-500">Tulis kunci jawaban berupa teks untuk referensi penilaian.</p>
                <div class="mt-5">
                    <textarea id="essay_answer" name="essay_answer" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Tulis kunci jawaban...">{{ old('essay_answer', $question->answer_key) }}</textarea>
                </div>
                <x-input-error :messages="$errors->get('essay_answer')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.questions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
