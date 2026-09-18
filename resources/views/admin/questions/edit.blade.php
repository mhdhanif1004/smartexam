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
            $existingOptionImages = collect($storedOptions)
                ->map(fn ($option) => is_array($option) && filled($option['image'] ?? null) ? ['has' => true, 'url' => asset('storage/'.$option['image']), 'path' => $option['image']] : ['has' => false, 'url' => '', 'path' => ''])
                ->all();
        @endphp

        <form
            method="POST"
            action="{{ route('admin.questions.update', $question) }}"
            enctype="multipart/form-data"
            class="max-w-3xl space-y-6"
            x-data="{ type: @js(old('type', $question->type)), guru: @js((string) old('creator_user_id', $question->created_by_user_id ?? '')), subject: @js((string) old('subject_id', $question->subject_id)), selected: @js(old('classroom_ids', $question->classrooms->pluck('id')->all())), pairs: @js($pairs), img: { preview: '', hasExisting: @js((bool) $question->image_path), existingUrl: @js($question->image_path ? asset('storage/'.$question->image_path) : ''), remove() { this.hasExisting = false; this.preview = ''; } }, opt: {}, optImg(e, key) { this.opt[key] = e.target.files[0] ? URL.createObjectURL(e.target.files[0]) : ''; }, weight: @js((string) old('score_weight', $question->score_weight)) }"
        >
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                        <select id="subject_id" name="subject_id" required x-model="subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
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
                    <div>
                        <x-input-label for="teacher_guru_mapel_id" :value="__('Pemilik Guru Mapel (opsional)')" />
                        <select id="teacher_guru_mapel_id" name="teacher_guru_mapel_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Belum Ada Guru --</option>
                            @foreach ($gurus as $guruItem)
                                <option value="{{ $guruItem->id }}" @selected(old('teacher_guru_mapel_id', $question->teacher_guru_mapel_id) == $guruItem->id)>{{ $guruItem->user?->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Menentukan cabang "Guru" pada hierarki Bank Soal. Kosongkan untuk menyimpan di bucket "Belum Ada Guru".</p>
                        <x-input-error :messages="$errors->get('teacher_guru_mapel_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="exam_type_id" :value="__('Jenis Ujian (opsional)')" />
                        <select id="exam_type_id" name="exam_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Belum Ditentukan --</option>
                            @foreach ($examTypes as $examType)
                                <option value="{{ $examType->id }}" @selected(old('exam_type_id', $question->exam_type_id) == $examType->id)>{{ $examType->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Label kategori soal (Harian/UTS/UAS). Tidak memengaruhi bobot atau penjadwalan ujian.</p>
                        <x-input-error :messages="$errors->get('exam_type_id')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="creator_user_id" :value="__('Atas Nama Guru (opsional)')" />
                        <select id="creator_user_id" name="creator_user_id" x-model="guru" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Milik Admin (bukan guru) --</option>
                            @foreach ($gurus as $guruItem)
                                <option value="{{ $guruItem->user_id }}" @selected(old('creator_user_id', $question->created_by_user_id) == $guruItem->user_id)>{{ $guruItem->user?->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Jika dipilih, soal akan muncul di halaman "Soal" guru tersebut dan kelas target dibatasi ke penugasan guru.</p>
                        <x-input-error :messages="$errors->get('creator_user_id')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="question_text" :value="__('Pertanyaan')" />
                        <textarea id="question_text" name="question_text" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" placeholder="Tulis pertanyaan...">{{ old('question_text', $question->question_text) }}</textarea>
                        <x-input-error :messages="$errors->get('question_text')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="score_weight" :value="__('Bobot Nilai (poin)')" />
                        <x-text-input id="score_weight" name="score_weight" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" x-model="weight" value="{{ old('score_weight', $question->score_weight) }}" required />
                        <p class="mt-1.5 text-xs" :class="(parseFloat(weight) || 0) > 100 ? 'text-rose-600 dark:text-rose-400' : ((parseFloat(weight) || 0) <= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400')" x-text="(parseFloat(weight) || 0) > 100 ? 'Bobot melebihi 100 — periksa total bobot per kelas.' : ((parseFloat(weight) || 0) <= 0 ? 'Bobot minimal > 0.' : 'Bobot akan dijumlah per kelas target (total harus 100).')"></p>
                        <x-input-error :messages="$errors->get('score_weight')" class="mt-2" />
                    </div>
                </div>
            </div>

            {{-- Kelas Target --}}
            <template x-if="guru === ''">
                <x-questions.classroom-picker
                    mode="all"
                    :classrooms="$classrooms"
                    :selected="old('classroom_ids', $question->classrooms->pluck('id')->all())"
                />
            </template>
            <template x-if="guru !== ''">
                <x-questions.classroom-picker
                    mode="scoped"
                    :allGuruClassroomsBySubject="$guruClassroomsBySubject"
                    :selected="old('classroom_ids', $question->classrooms->pluck('id')->all())"
                />
            </template>

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
                        <img x-show="img.hasExisting && !img.preview" :src="img.existingUrl" class="max-h-48 w-full cursor-zoom-in rounded-lg border border-gray-200 object-contain dark:border-gray-700" alt="Gambar saat ini" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)" />
                        <img x-show="img.preview" :src="img.preview" class="max-h-48 w-full cursor-zoom-in rounded-lg border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau gambar baru" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)" />
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
                                <x-text-input type="text" name="single_options[{{ $letter }}]" class="block w-full" value="{{ old('single_options.'.$letter, $question->optionText($storedOptions[$letter] ?? '')) }}" placeholder="Teks opsi {{ $letter }}" />
                            </div>
                            <div class="ml-11 flex items-center gap-3">
                                <input type="file" name="single_options_image[{{ $letter }}]" accept="image/jpeg,image/png,image/webp" @change="optImg($event, 'single_{{ $letter }}')" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20">
                                <input type="hidden" name="existing_single_options_image[{{ $letter }}]" value="{{ $existingOptionImages[$letter]['path'] ?? '' }}">
                                <span class="text-xs text-gray-400 dark:text-gray-500">Gambar opsi</span>
                                @if ($existingOptionImages[$letter]['has'] ?? false)
                                    <img src="{{ $existingOptionImages[$letter]['url'] }}" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Opsi {{ $letter }} saat ini" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)">
                                    <label class="flex items-center gap-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                                        <input type="checkbox" name="remove_single_options_image[{{ $letter }}]" value="1" class="h-3.5 w-3.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                        Hapus
                                    </label>
                                @endif
                                <img x-show="opt['single_{{ $letter }}']" :src="opt['single_{{ $letter }}']" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau opsi {{ $letter }}" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)" />
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
                                <x-text-input type="text" name="multiple_options[{{ $letter }}]" class="block w-full" value="{{ old('multiple_options.'.$letter, $question->optionText($storedOptions[$letter] ?? '')) }}" placeholder="Teks opsi {{ $letter }}" />
                            </div>
                            <div class="ml-11 flex items-center gap-3">
                                <input type="file" name="multiple_options_image[{{ $letter }}]" accept="image/jpeg,image/png,image/webp" @change="optImg($event, 'multi_{{ $letter }}')" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20">
                                <input type="hidden" name="existing_multiple_options_image[{{ $letter }}]" value="{{ $existingOptionImages[$letter]['path'] ?? '' }}">
                                <span class="text-xs text-gray-400 dark:text-gray-500">Gambar opsi</span>
                                @if ($existingOptionImages[$letter]['has'] ?? false)
                                    <img src="{{ $existingOptionImages[$letter]['url'] }}" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Opsi {{ $letter }} saat ini" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)">
                                    <label class="flex items-center gap-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                                        <input type="checkbox" name="remove_multiple_options_image[{{ $letter }}]" value="1" class="h-3.5 w-3.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                        Hapus
                                    </label>
                                @endif
                                <img x-show="opt['multi_{{ $letter }}']" :src="opt['multi_{{ $letter }}']" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau opsi {{ $letter }}" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)" />
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
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @php($tfTrue = $existingOptionImages['true'] ?? ['has' => false, 'url' => '', 'path' => ''])
                        @php($tfFalse = $existingOptionImages['false'] ?? ['has' => false, 'url' => '', 'path' => ''])
                        <div class="flex items-center gap-3">
                            <input type="file" name="true_false_image[true]" accept="image/jpeg,image/png,image/webp" @change="optImg($event, 'tf_true')" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20">
                            <input type="hidden" name="existing_true_false_image[true]" value="{{ $tfTrue['path'] }}">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Gambar "Benar"</span>
                            @if ($tfTrue['has'])
                                <img src="{{ $tfTrue['url'] }}" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Gambar Benar saat ini" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)">
                                <label class="flex items-center gap-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                                    <input type="checkbox" name="remove_true_false_image[true]" value="1" class="h-3.5 w-3.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                    Hapus
                                </label>
                            @endif
                            <img x-show="opt['tf_true']" :src="opt['tf_true']" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau Benar" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)" />
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="file" name="true_false_image[false]" accept="image/jpeg,image/png,image/webp" @change="optImg($event, 'tf_false')" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20">
                            <input type="hidden" name="existing_true_false_image[false]" value="{{ $tfFalse['path'] }}">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Gambar "Salah"</span>
                            @if ($tfFalse['has'])
                                <img src="{{ $tfFalse['url'] }}" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Gambar Salah saat ini" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)">
                                <label class="flex items-center gap-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                                    <input type="checkbox" name="remove_true_false_image[false]" value="1" class="h-3.5 w-3.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                    Hapus
                                </label>
                            @endif
                            <img x-show="opt['tf_false']" :src="opt['tf_false']" class="h-12 w-12 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Pratinjau Salah" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)" />
                        </div>
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
                                <input type="file" name="matching_left_image[]" accept="image/jpeg,image/png,image/webp" @change="optImg($event, 'ml_'+index)" class="w-32 text-xs text-gray-500 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-[10px] file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10" />
                                <input type="hidden" :name="'existing_matching_left_image['+index+']'" :value="pair.left_image || ''" />
                                <span class="text-gray-400 dark:text-gray-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </span>
                                <x-text-input type="text" name="matching_right[]" x-model="pair.right" class="block w-full" placeholder="Kolom kanan" />
                                <input type="file" name="matching_right_image[]" accept="image/jpeg,image/png,image/webp" @change="optImg($event, 'mr_'+index)" class="w-32 text-xs text-gray-500 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-[10px] file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10" />
                                <input type="hidden" :name="'existing_matching_right_image['+index+']'" :value="pair.right_image || ''" />
                                <img x-show="pair.left_image && !opt['ml_'+index]" :src="'/storage/'+pair.left_image" class="h-8 w-8 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Kiri {{ $question->id }}" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)">
                                <img x-show="pair.right_image && !opt['mr_'+index]" :src="'/storage/'+pair.right_image" class="h-8 w-8 cursor-zoom-in rounded border border-gray-200 object-contain dark:border-gray-700" alt="Kanan {{ $question->id }}" title="Perbesar gambar" @click="$dispatch('image-zoom', $event.currentTarget.src)">
                                <button type="button" @click="pairs.splice(index, 1)" class="rounded-md p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 dark:text-gray-500 dark:hover:bg-rose-500/20 dark:hover:text-rose-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <button type="button" @click="pair.left_image = ''; pair.right_image = ''" class="rounded-md p-1 text-xs text-rose-500 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10" title="Hapus gambar pasangan ini">
                                    Hapus gambar
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="pairs.push({ left: '', right: '', left_image: '', right_image: '' })" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-600 transition hover:bg-indigo-50 dark:border-indigo-500/50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
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

    <x-image-lightbox />
</x-layouts.admin>
