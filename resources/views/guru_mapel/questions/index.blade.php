<x-layouts.guru_mapel title="Soal Saya">
    <div class="space-y-6" x-data="importState()">
        @php($importRouteTemplate = route('guru_mapel.questions.import-template', '__TYPE__'))
        @php($importFailedTemplate = route('guru_mapel.questions.import-failed', '__FILE__'))
        <script>
            function importState() {
                return {
                    importState: {
                        step: 1,
                        type: '',
                        file: null,
                        busy: false,
                        message: '',
                        result: null,
                        finished: null,
                        onFileChange(e) {
                            this.file = e.target.files[0];
                            this.message = '';
                            this.result = null;
                            this.finished = null;
                        },
                        templateUrl() {
                            if (!this.type) return '#';
                            return @json($importRouteTemplate).replace('__TYPE__', this.type);
                        },
                        failedUrl() {
                            return @json($importFailedTemplate).replace('__FILE__', encodeURIComponent(this.finished?.failed_file ?? ''));
                        },
                        validate() {
                            if (!this.type) { this.message = 'Pilih jenis soal terlebih dahulu.'; return; }
                            if (!this.file) { this.message = 'Pilih file Excel/CSV terlebih dahulu.'; return; }
                            this.busy = true;
                            this.message = '';
                            const formData = new FormData();
                            formData.append('type', this.type);
                            formData.append('file', this.file);
                            fetch(@json(route('guru_mapel.questions.import-validate')), {
                                method: 'POST',
                                body: formData,
                                headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                            })
                                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                                .then(({ ok, data }) => {
                                    if (!ok) {
                                        const errors = Object.values(data.errors || {}).flat();
                                        this.message = data.message || (errors.length ? errors.join(' ') : 'Terjadi kesalahan saat memvalidasi file.');
                                        return;
                                    }
                                    this.result = data;
                                    this.step = 2;
                                })
                                .catch(() => { this.message = 'Terjadi kesalahan saat memvalidasi file.'; })
                                .finally(() => { this.busy = false; });
                        },
                        confirm() {
                            this.busy = true;
                            this.message = '';
                            fetch(@json(route('guru_mapel.questions.import-confirm')), {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                            })
                                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                                .then(({ ok, data }) => {
                                    if (!ok) { this.message = data.message || 'Terjadi kesalahan saat mengimpor.'; return; }
                                    this.finished = data;
                                    this.step = 3;
                                })
                                .catch(() => { this.message = 'Terjadi kesalahan saat mengimpor.'; })
                                .finally(() => { this.busy = false; });
                        },
                        reset() {
                            this.step = 1;
                            this.type = '';
                            this.file = null;
                            this.busy = false;
                            this.message = '';
                            this.result = null;
                            this.finished = null;
                        },
                    },
                };
            }
        </script>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Soal</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola soal yang Anda buat. Soal hanya dapat diakses dan diedit oleh Anda, dan hanya untuk mapel yang Anda ampu.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    x-data="{}"
                    @click="$dispatch('open-modal', 'import-questions')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    Import Excel
                </button>
                <a href="{{ route('guru_mapel.questions.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Soal
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('guru_mapel.questions.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari pertanyaan..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                </div>
                <div class="sm:w-64">
                    <select name="subject_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">Semua Mapel</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-900 dark:hover:bg-white">Filter</button>
                <div class="relative" x-data="{ exportOpen: false }">
                    <button type="button" @click="exportOpen = !exportOpen" @click.outside="exportOpen = false" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        Export
                        <svg class="h-4 w-4 transition" :class="exportOpen && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div x-show="exportOpen" x-transition x-cloak class="absolute right-0 z-20 mt-2 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <button type="submit" name="format" value="xlsx" formaction="{{ route('guru_mapel.questions.export') }}" @click="exportOpen = false" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                            Export .xlsx
                        </button>
                        <button type="submit" name="format" value="csv" formaction="{{ route('guru_mapel.questions.export') }}" @click="exportOpen = false" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                            Export .csv
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
             x-data="{
                 selected: [],
                 deleteUrl: '',
                 deleteDescription: '',
                 get allSelected() {
                     const ids = @json($questions->pluck('id')->all());
                     return ids.length > 0 && this.selected.length === ids.length;
                 },
                 toggleAll(e) {
                     this.selected = e.target.checked ? @json($questions->pluck('id')->all()) : [];
                 },
                 toggle(id) {
                     this.selected = this.selected.includes(id)
                         ? this.selected.filter((x) => x !== id)
                         : [...this.selected, id];
                 },
             }">

            <div x-show="selected.length > 0" x-cloak x-transition
                 class="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100 bg-indigo-50 px-4 py-3 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                <p class="text-sm text-indigo-700 dark:text-indigo-300">
                    <span class="font-semibold" x-text="selected.length"></span> soal dipilih
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="selected = []"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        Batal
                    </button>
                    <button type="button" @click="$dispatch('open-modal', 'confirm-bulk-delete')"
                            class="ml-2 inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                        Hapus Terpilih
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="w-12 px-4 py-3">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll($event)" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                <span class="sr-only">Pilih semua</span>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Pertanyaan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Mapel</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Jenis</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Kelas Target</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($questions as $question)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-4">
                                    <input type="checkbox" value="{{ $question->id }}"
                                           :checked="selected.includes({{ $question->id }})"
                                           @change="toggle({{ $question->id }})"
                                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-md text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">{{ Str::limit(strip_tags($question->question_text), 80) }}</div>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>Bobot: {{ $question->score_weight }}</span>
                                        @if ($question->image_path)
                                            <span class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l4.784 4.787M4.5 19.5h15A2.25 2.25 0 0021.75 17.25V6.75A2.25 2.25 0 0019.5 4.5h-15A2.25 2.25 0 002.25 6.75v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                                                Gambar
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $question->subject?->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ \App\Models\Question::TYPES[$question->type] ?? $question->type }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @php($targetParts = \App\Models\Classroom::summarizeTargetParts($question->classrooms->pluck('id')))
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($targetParts as $part)
                                            <span class="inline-flex rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                                                @if ($part['type'] === 'grade')
                                                    {{ $part['label'] }} (Semua Kelas)
                                                @else
                                                    {{ $part['label'] }}
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Belum ada target</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if ($question->is_active)
                                        <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Aktif</span>
                                    @else
                                        <span class="inline-flex rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700/40 dark:text-gray-300">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('guru_mapel.questions.edit', $question) }}" class="rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-indigo-400">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                        </a>
                                        <button type="button"
                                                @click="deleteUrl = @js(route('guru_mapel.questions.destroy', $question)); deleteDescription = @js('Yakin ingin menghapus soal: "' . Str::limit(strip_tags($question->question_text), 60) . '"?'); $dispatch('open-modal', 'confirm-delete')"
                                                class="rounded-md p-2 text-gray-500 transition hover:bg-rose-50 hover:text-rose-600 dark:text-gray-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Belum ada soal.</div>
                                    <a href="{{ route('guru_mapel.questions.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        Buat soal pertama Anda
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($questions->hasPages())
                <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    {{ $questions->links() }}
                </div>
            @endif

            {{-- Modal konfirmasi hapus satu soal (konsisten dengan halaman admin) --}}
            @include('admin.partials.delete-modal')

            <x-modal name="confirm-bulk-delete" maxWidth="sm" focusable>
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-500/10">
                            <svg class="h-5 w-5 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Konfirmasi Hapus Massal</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Yakin ingin menghapus <span x-text="selected.length" class="font-bold"></span> soal yang dipilih? Soal yang sudah pernah dijawab peserta akan dilewati. Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                        <form method="POST" action="{{ route('guru_mapel.questions.bulk-destroy') }}" class="inline">
                            @csrf
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>
                            <x-danger-button>Hapus</x-danger-button>
                        </form>
                    </div>
                </div>
            </x-modal>
        </div>

        @include('guru_mapel.questions.partials.import-modal')
    </div>
</x-layouts.guru_mapel>
