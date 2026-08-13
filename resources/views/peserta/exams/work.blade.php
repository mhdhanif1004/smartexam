<x-layouts.peserta :title="'Mengerjakan - '.($schedule->subject?->name ?? 'Ujian')">
    <div class="space-y-6"
         x-data="examApp({
             questions: {{ Js::from($questionsData) }},
             answers: {{ Js::from($savedAnswers) }},
             doubtful: {{ Js::from($doubtfulQuestions) }},
             deadline: {{ $deadline }},
             saveUrl: {{ Js::from(route('peserta.exams.save-answer', $schedule->id)) }},
             doubtUrl: {{ Js::from(route('peserta.exams.questions.toggle-doubtful', [$schedule->id, ':question'])) }},
             submitUrl: {{ Js::from(route('peserta.exams.submit', $schedule->id)) }},
             finishedUrl: {{ Js::from(route('peserta.exams.finished', $schedule->id)) }},
             violationUrl: {{ Js::from(route('peserta.exams.violation', $schedule->id)) }},
             statusUrl: {{ Js::from(route('peserta.exams.status', $schedule->id)) }},
             dashboardUrl: {{ Js::from(route('peserta.dashboard')) }},
             csrf: {{ Js::from(csrf_token()) }},
             csrfUrl: {{ Js::from(route('csrf-token')) }},
              loginUrl: {{ Js::from(route('login')) }},
          })"> 


        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Ujian Berlangsung</p>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $schedule->subject?->name }}</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ auth()->user()->name }} &middot; {{ $schedule->class_name }} &middot; {{ $schedule->room?->name }}
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Sisa Waktu</p>
                        <p class="font-mono text-2xl font-bold tabular-nums" :class="remaining < 300 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-gray-100'" x-text="formatTime(remaining)"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Terjawab</p>
                        <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100"><span x-text="answeredCount()"></span><span class="text-base text-gray-400 dark:text-gray-500">/</span><span class="text-base text-gray-400 dark:text-gray-500" x-text="total()"></span></p>
                    </div>
                    <button type="button" @click="submit(false)"
                            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-500"
                            :disabled="submitting">
                        <span x-show="!submitting">Kumpulkan</span>
                        <span x-show="submitting">Mengirim...</span>
                    </button>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-4 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <span x-show="saving" class="text-amber-600 dark:text-amber-400">Menyimpan jawaban...</span>
                <span x-show="!saving && lastSaved" class="text-emerald-600 dark:text-emerald-400">Tersimpan otomatis pukul <span x-text="lastSaved"></span></span>
                <span x-show="!saving && !lastSaved">Jawaban disimpan otomatis saat dipilih.</span>
            </div>
        </div>

        @if ($questionsData->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                </svg>
                <p class="mt-4 text-sm font-semibold text-gray-700 dark:text-gray-200">Belum ada soal yang tersedia untuk ujian ini.</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Silakan hubungi pengawas/admin. Token tidak dapat divalidasi sebelum soal ditambahkan.</p>
                <a href="{{ route('peserta.dashboard') }}" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    Kembali ke Beranda
                </a>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-[1fr_230px]">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <template x-for="(q, index) in questions" :key="q.id">
                        <div x-show="current === index" x-cloak class="p-6 sm:p-8">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-4 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white" x-text="index + 1"></span>
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300" x-text="typeLabel(q.type)"></span>
                                </div>
                                <span class="text-xs font-semibold text-gray-400 dark:text-gray-500" x-text="'Skor ' + q.score_weight"></span>
                            </div>

                            <p class="mt-5 whitespace-pre-line text-base font-medium leading-relaxed text-gray-900 dark:text-gray-100" x-text="q.question_text"></p>

                            <div x-show="q.image_url" class="mt-4 flex justify-center rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                                <img
                                    :src="q.image_url"
                                    :alt="'Gambar soal nomor ' + (index + 1)"
                                    @click="zoomImage = q.image_url"
                                    class="max-h-96 w-full max-w-2xl cursor-zoom-in rounded-md object-contain"
                                    loading="lazy"
                                />
                            </div>

                            <div class="mt-6 space-y-3">
                                <div x-show="q.type === 'single_choice'" class="space-y-2">
                                    <template x-for="(text, letter) in q.options" :key="letter">
                                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
                                            <input type="radio" :name="'q' + q.id" :value="letter" @change="selectValue(q, letter)" :checked="answerFor(q) === letter" class="mt-0.5 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                            <span class="text-sm text-gray-800 dark:text-gray-200" x-text="letter + '. ' + text"></span>
                                        </label>
                                    </template>
                                </div>

                                <div x-show="q.type === 'multiple_choice'" class="space-y-2">
                                    <p class="text-xs text-gray-400">Pilih lebih dari satu jawaban yang benar.</p>
                                    <template x-for="(text, letter) in q.options" :key="letter">
                                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
                                            <input type="checkbox" :value="letter" @change="toggleOption(q, letter)" :checked="(answerFor(q) || []).includes(letter)" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                            <span class="text-sm text-gray-800 dark:text-gray-200" x-text="letter + '. ' + text"></span>
                                        </label>
                                    </template>
                                </div>

                                <div x-show="q.type === 'true_false'" class="grid gap-2 sm:grid-cols-2">
                                    <template x-for="option in ['true', 'false']" :key="option">
                                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
                                            <input type="radio" :name="'q' + q.id" :value="option" @change="selectValue(q, option)" :checked="answerFor(q) === (option === 'true')" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="option === 'true' ? 'Benar' : 'Salah'"></span>
                                        </label>
                                    </template>
                                </div>

                                <div x-show="q.type === 'matching'" class="space-y-3">
                                    <p class="text-xs text-gray-400 dark:text-gray-500">Jodohkan pernyataan kiri dengan pasangan yang tepat di kanan.</p>
                                    <template x-for="(text, index) in (q.options?.left ?? [])" :key="index">
                                        <div class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 sm:flex-row sm:items-center dark:border-gray-700">
                                            <div class="flex flex-1 items-start gap-2">
                                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-700 dark:bg-gray-700/60 dark:text-gray-300" x-text="letter(index)"></span>
                                                <span class="text-sm text-gray-800 dark:text-gray-200" x-text="text"></span>
                                            </div>
                                            <select @change="setMatching(q, letter(index), $event.target.value)" :value="(answerFor(q) || {})[letter(index)] || ''" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                                <option value="">Pilih pasangan</option>
                                                <template x-for="(rightText, rightIndex) in (q.options?.right ?? [])" :key="rightIndex">
                                                    <option :value="String(rightIndex + 1)" x-text="(rightIndex + 1) + '. ' + rightText"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="q.type === 'essay'">
                                    <textarea rows="6" :value="answerFor(q) || ''" @input="selectValue(q, $event.target.value)" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" placeholder="Tulis jawabanmu di sini..."></textarea>
                                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Jawaban essay akan dikoreksi oleh pengawas/guru.</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 p-4 sm:px-8 dark:border-gray-800">
                        <button type="button" @click="prev()" :disabled="current === 0"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            &larr; Sebelumnya
                        </button>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="toggleDoubtful(questions[current])"
                                    :class="isDoubtful(questions[current]) ? 'bg-amber-400 text-amber-950 ring-2 ring-amber-500' : 'border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20'"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold transition">
                                <span x-text="isDoubtful(questions[current]) ? 'Ragu-ragu ✓' : 'Ragu-ragu'"></span>
                            </button>
                            <span class="text-sm text-gray-500 dark:text-gray-400" x-text="(current + 1) + ' / ' + total()"></span>
                        </div>
                        <button type="button" @click="next()" :disabled="current === total() - 1"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                            Berikutnya &rarr;
                        </button>
                    </div>
                </div>

                <div class="h-fit space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:sticky lg:top-6 dark:border-gray-800 dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Navigasi Soal</p>
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            <template x-for="(q, index) in questions" :key="q.id">
                                <button type="button" @click="goTo(index)"
                                        :class="questionClass(q)"
                                        class="relative h-9 rounded-lg text-xs font-bold transition">
                                    <span x-text="index + 1"></span>
                                    <span x-show="isDoubtful(q)" x-cloak class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-amber-400 ring-1 ring-white dark:ring-gray-900"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            Terjawab
                        </span>
                        <span x-text="answeredCount() + '/' + total()"></span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                            Ragu-ragu
                        </span>
                        <span x-text="doubtfulCount() + '/' + total()"></span>
                    </div>

                    <button type="button" @click="submit(false)"
                            class="w-full rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-500"
                            :disabled="submitting">
                        <span x-show="!submitting">Kumpulkan Ujian</span>
                        <span x-show="submitting">Mengirim...</span>
                    </button>
                    <p class="text-center text-[11px] text-gray-400 dark:text-gray-500">Sisa waktu akan otomatis mengumpulkan jawabanmu.</p>
                </div>
            </div>
        @endif

        <div x-show="toastVisible" x-cloak x-transition.opacity class="fixed right-4 top-4 z-50 flex max-w-sm items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-lg dark:border-amber-800 dark:bg-amber-500/10">
        <svg class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <div>
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300" x-text="toast"></p>
            <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">Tetap fokus pada halaman ujian.</p>
        </div>
    </div>

    <div x-show="showConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/50" @click="showConfirm = false"></div>
        <div class="relative w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 dark:ring-1 dark:ring-gray-700">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/10">
                <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374H4.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0l7.303 13.748zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="mt-4 text-lg font-bold text-gray-900 dark:text-gray-100">Kumpulkan ujian?</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pastikan semua jawaban sudah terisi. Jawaban tidak dapat diubah setelah dikumpulkan.</p>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" @click="showConfirm = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</button>
                <button type="button" @click="submit(false)" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-500">Ya, Kumpulkan</button>
            </div>
        </div>
    </div>

    <div x-show="zoomImage" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4" @click="zoomImage = null">
        <img :src="zoomImage" class="max-h-full max-w-full rounded-lg object-contain shadow-2xl" alt="Gambar soal diperbesar" />
        <button type="button" @click="zoomImage = null" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Tutup gambar">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        <p class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-1 text-xs text-white">Klik di mana saja untuk menutup</p>
    </div>
    </div>
</x-layouts.peserta>
