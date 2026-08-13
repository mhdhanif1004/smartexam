<x-layouts.admin title="Bank Soal">
    <div
        x-data="{
            ...selectionManager({
                sessionKey: 'smartexam_selected_questions',
                visibleIds: [],
                bulkDeleteSuccess: @js(str_contains((string) session('success'), 'soal berhasil dihapus.')),
                bulkDeleteUrl: @js(route('admin.questions.bulk-delete')),
            }),
            deleteUrl: '',
            preview: null,
            expandedIds: @js($hasFilter ? $subjects->pluck('id')->values() : collect()),
            loadingIds: [],
            loadedIds: @js($hasFilter ? $subjects->pluck('id')->values() : collect()),
            listQuestionIds: @js($preloadedQuestionIds),
            subjectIds: @js($subjects->pluck('id')->values()),
            subjectCounts: @js($subjects->mapWithKeys(fn ($subject) => [$subject->id => $subject->questions_count])->all()),
            filterQuery: @js($filterQuery),
            isExpanded(id) { return this.expandedIds.includes(id); },
            isLoading(id) { return this.loadingIds.includes(id); },
            hasList(id) { return this.loadedIds.includes(id); },
            hasNoQuestions(id) { return (this.subjectCounts[id] ?? 0) === 0; },
            toggleAccordion(id) {
                const index = this.expandedIds.indexOf(id);
                if (index === -1) {
                    this.expandedIds.push(id);
                    this.loadList(id);
                } else {
                    this.expandedIds.splice(index, 1);
                }
            },
            loadList(id) {
                if (this.loadedIds.includes(id) || this.loadingIds.includes(id)) return;
                if ((this.subjectCounts[id] ?? 0) === 0) return;
                this.loadingIds.push(id);
                const url = @js(route('admin.questions.by-subject', '__ID__')).replace('__ID__', id)
                    + (this.filterQuery ? '?' + this.filterQuery : '');
                fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } })
                    .then((response) => response.json())
                    .then((data) => {
                        const body = this.$refs['body-' + id];
                        if (body) body.innerHTML = data.html;
                        this.listQuestionIds[id] = data.ids || [];
                        this.loadedIds.push(id);
                        this.$nextTick(() => {
                            if (body) window.Alpine.initTree(body);
                        });
                    })
                    .catch(() => {
                        const body = this.$refs['body-' + id];
                        if (body) {
                            body.innerHTML = '<p class=&quot;px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400&quot;>Gagal memuat soal. Silakan coba lagi.</p>';
                        }
                        this.loadedIds.push(id);
                    })
                    .finally(() => {
                        const index = this.loadingIds.indexOf(id);
                        if (index !== -1) this.loadingIds.splice(index, 1);
                    });
            },
            openAllAccordions() {
                this.expandedIds = [...this.subjectIds];
                this.subjectIds.forEach((id) => this.loadList(id));
            },
            closeAllAccordions() {
                this.expandedIds = [];
            },
            selectAllInSubject(subjectId) {
                const ids = this.listQuestionIds[subjectId] || [];
                if (ids.length === 0) return;
                const allSelected = ids.every((id) => this.selected.includes(id));
                if (allSelected) {
                    this.selected = this.selected.filter((id) => !ids.includes(id));
                } else {
                    ids.forEach((id) => {
                        if (!this.selected.includes(id)) this.selected.push(id);
                    });
                }
                this.persistSelection();
            },
            accordionInit() {
                @if ((string) request('subject_id') !== '')
                this.$nextTick(() => {
                    const el = this.$refs['accordion-' + @js((int) request('subject_id'))];
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                @endif
            },
            exportScope: 'all',
            exportFormat: 'xlsx',
            isCorrectAnswer(letter) {
                if (!this.preview) return false;
                if (this.preview.type === 'single_choice') return this.preview.answer_key === letter;
                if (this.preview.type === 'multiple_choice') return (this.preview.answer_key || []).includes(letter);
                return false;
            },
            openExportModal() {
                if (this.exportScope === 'selected' && this.selected.length === 0) {
                    this.exportScope = 'all';
                }
                this.$dispatch('open-modal', 'export-questions');
            },
            exportUrl() {
                const params = new URLSearchParams();
                params.set('format', this.exportFormat);
                params.set('scope', this.exportScope);
                if (this.exportScope === 'filtered') {
                    @if (request('search'))
                    params.set('search', @js(request('search')));
                    @endif
                    @if (request('subject_id'))
                    params.set('subject_id', @js(request('subject_id')));
                    @endif
                    @if (request('type'))
                    params.set('type', @js(request('type')));
                    @endif
                    @if (request('status'))
                    params.set('status', @js(request('status')));
                    @endif
                }
                if (this.exportScope === 'selected') {
                    this.selected.forEach((id) => params.append('ids[]', id));
                }
                window.location.href = @js(route('admin.questions.export')) + '?' + params.toString();
            },
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
                    return @js(route('admin.questions.import-template', '__TYPE__')).replace('__TYPE__', this.type);
                },
                failedUrl() {
                    return @js(route('admin.questions.import-failed', '__FILE__'))
                        .replace('__FILE__', encodeURIComponent(this.finished?.failed_file ?? ''));
                },
                validate() {
                    if (!this.type) { this.message = 'Pilih jenis soal terlebih dahulu.'; return; }
                    if (!this.file) { this.message = 'Pilih file Excel/CSV terlebih dahulu.'; return; }
                    this.busy = true;
                    this.message = '';
                    const formData = new FormData();
                    formData.append('type', this.type);
                    formData.append('file', this.file);
                    fetch(@js(route('admin.questions.import-validate')), {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
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
                    fetch(@js(route('admin.questions.import-confirm')), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
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
        }"
        x-init="accordionInit()"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Bank Soal</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola soal ujian untuk setiap mata pelajaran.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="importState.reset(); $dispatch('open-modal', 'import-questions')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Impor
                </button>
                <button type="button" @click="openExportModal()" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Ekspor
                </button>
                <a href="{{ route('admin.questions.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Soal
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.questions.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari pertanyaan..." class="block w-full" />
            </div>
            <div>
                <select name="subject_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 lg:w-auto">
                    <option value="">Semua Mata Pelajaran</option>
                    @foreach ($allSubjects as $subject)
                        <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}{{ filled($subject->class_label) ? ' (Kelas '.$subject->class_label.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 lg:w-auto">
                    <option value="">Semua Jenis Soal</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 lg:w-auto">
                    <option value="">Semua Status</option>
                    <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('subject_id') || request('type') || request('status'))
                    <a href="{{ route('admin.questions.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                @endif
            </div>
        </form>

        <div x-show="selected.length > 0" x-transition class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 dark:border-indigo-500/30 dark:bg-indigo-500/10">
            <p class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                <span x-text="selected.length" class="font-bold"></span> soal dipilih <span class="text-xs text-indigo-500 dark:text-indigo-400">(dari semua halaman)</span>
            </p>
            <div class="flex items-center gap-3">
                <button type="button" @click="clearSelection()" class="text-sm font-medium text-gray-600 transition hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Reset Pilihan</button>
                <button type="button" @click="$dispatch('open-modal', 'confirm-bulk-edit')" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Edit Massal
                </button>
                <button type="button" @click="$dispatch('open-modal', 'confirm-bulk-delete')" class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-rose-500">
                    Hapus Massal
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $subjects->count() }} mata pelajaran &middot; {{ $subjects->sum('questions_count') }} soal
            </p>
            <div class="flex items-center gap-2">
                <button type="button" @click="openAllAccordions()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L12 3l9 4.5m-9 4.5v10.5" />
                    </svg>
                    Buka Semua
                </button>
                <button type="button" @click="closeAllAccordions()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L12 3l9 4.5" />
                    </svg>
                    Tutup Semua
                </button>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($subjects as $subject)
                <div x-ref="accordion-{{ $subject->id }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <button
                        type="button"
                        @click="toggleAccordion({{ $subject->id }})"
                        :aria-expanded="isExpanded({{ $subject->id }})"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-gray-50 dark:hover:bg-gray-800/50"
                    >
                        <span class="flex min-w-0 items-center gap-2.5">
                            <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500" :class="isExpanded({{ $subject->id }}) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                            <span class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $subject->name }}</span>
                            @if (filled($subject->class_label))
                                <span class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">Kelas {{ $subject->class_label }}</span>
                            @endif
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $subject->questions_count }} soal</span>
                        </span>
                        <span x-show="isLoading({{ $subject->id }})" class="flex shrink-0 items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <span class="h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600 dark:border-gray-600 dark:border-t-indigo-400"></span>
                            Memuat...
                        </span>
                    </button>

                    <div x-show="isExpanded({{ $subject->id }})" x-transition x-cloak>
                        <div x-show="isLoading({{ $subject->id }})" class="flex items-center justify-center gap-2 border-t border-gray-200 px-4 py-8 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600 dark:border-gray-600 dark:border-t-indigo-400"></span>
                            Memuat soal...
                        </div>
                        <div x-show="!isLoading({{ $subject->id }}) && !hasList({{ $subject->id }}) && hasNoQuestions({{ $subject->id }})" class="border-t border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            Belum ada soal untuk mata pelajaran ini.
                        </div>
                        <div x-show="!isLoading({{ $subject->id }}) && hasList({{ $subject->id }})" class="border-t border-gray-200 dark:border-gray-800">
                            <div x-ref="body-{{ $subject->id }}">
                                {!! $preloadedLists[$subject->id] ?? '' !!}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    Tidak ada mata pelajaran untuk ditampilkan.
                </div>
            @endforelse
        </div>

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
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Yakin ingin menghapus <span x-text="selected.length" class="font-bold"></span> soal yang dipilih? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <form method="POST" :action="bulkDeleteUrl" class="inline">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <x-danger-button>Hapus</x-danger-button>
                    </form>
                </div>
            </div>
        </x-modal>

        <x-modal name="confirm-bulk-edit" maxWidth="sm">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Edit Massal Soal</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Terapkan pengaturan berikut ke <span x-text="selected.length" class="font-bold"></span> soal yang dipilih. Kosongkan kolom yang tidak ingin diubah.</p>

                <form method="POST" :action="@js(route('admin.questions.bulk-edit'))" class="mt-5 space-y-4">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>

                    <div>
                        <label for="bulk-subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mata Pelajaran (ubah)</label>
                        <select id="bulk-subject" name="subject_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Biarkan Tidak Berubah --</option>
                            @foreach ($allSubjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}{{ filled($subject->class_label) ? ' (Kelas '.$subject->class_label.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="bulk-weight" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bobot Poin (ubah)</label>
                        <input id="bulk-weight" type="number" step="0.01" min="0" max="999.99" name="score_weight" placeholder="Biarkan kosong jika tidak diubah" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status Soal (ubah)</span>
                        <div class="mt-2 space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="radio" name="is_active" value="1" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                                Aktif
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="radio" name="is_active" value="0" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                                Nonaktif
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </x-modal>

        <x-modal name="preview-question" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pratinjau Soal</h2>
                    <button type="button" @click="$dispatch('close')" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 space-y-4" x-show="preview">
                    <div>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30" x-text="preview?.subject?.name ?? ''"></span>
                        <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20 dark:bg-gray-700/60 dark:text-gray-300 dark:ring-gray-400/30" x-text="preview?.type_label ?? ''"></span>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="preview?.question_text ?? ''"></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="(preview?.score_weight ? Number(preview.score_weight) + ' poin' : '')"></p>
                    </div>

                    <div x-show="preview?.image_url" class="flex justify-center rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <img :src="preview?.image_url" :alt="'Gambar soal #' + preview?.id" class="max-h-72 w-full max-w-xl rounded-md object-contain" />
                    </div>

                    <div class="space-y-2" x-show="preview?.type === 'single_choice' || preview?.type === 'multiple_choice'">
                        <template x-for="letter in ['A','B','C','D','E']" :key="letter">
                            <label x-show="preview?.options?.[letter]" class="flex cursor-default items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700"
                                :class="isCorrectAnswer(letter) ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-500/50 dark:bg-emerald-500/10' : ''">
                                <input type="checkbox" :checked="isCorrectAnswer(letter)" disabled class="h-4 w-4 rounded border-gray-300 text-indigo-600 dark:border-gray-600 dark:bg-gray-800">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="letter + '. ' + preview?.options?.[letter]"></span>
                                <span x-show="isCorrectAnswer(letter)" class="ml-auto text-xs font-semibold text-emerald-600 dark:text-emerald-400">Kunci</span>
                            </label>
                        </template>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2" x-show="preview?.type === 'true_false'">
                        <template x-for="(label, value) in { true: 'Benar', false: 'Salah' }" :key="value">
                            <label class="flex cursor-default items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700"
                                :class="Boolean(preview?.answer_key) === (value === 'true') ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-500/50 dark:bg-emerald-500/10' : ''">
                                <input type="checkbox" :checked="Boolean(preview?.answer_key) === (value === 'true')" disabled class="h-4 w-4 rounded border-gray-300 text-indigo-600 dark:border-gray-600 dark:bg-gray-800">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="label"></span>
                                <span x-show="Boolean(preview?.answer_key) === (value === 'true')" class="ml-auto text-xs font-semibold text-emerald-600 dark:text-emerald-400">Kunci</span>
                            </label>
                        </template>
                    </div>

                    <div x-show="preview?.type === 'matching'" class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kolom Kiri</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jawaban</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kolom Kanan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                <template x-for="(leftItem, index) in preview?.options?.left ?? []" :key="index">
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100" x-text="['A','B','C','D','E'][index] + '. ' + leftItem"></td>
                                        <td class="px-4 py-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400" x-text="(preview?.options?.right ?? [])[Number(preview?.answer_key?.[['A','B','C','D','E'][index]] ?? '1') - 1] ?? '-'"></td>
                                        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                            <template x-for="(rightItem, rIndex) in preview?.options?.right ?? []" :key="rIndex">
                                                <span><span class="mr-1 inline-block text-xs text-gray-500 dark:text-gray-400" x-text="(rIndex + 1) + '.'"></span><span x-text="rightItem"></span><br></span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="preview?.type === 'essay'">
                        <textarea rows="3" disabled placeholder="Tulis jawaban di sini..." class="block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"></textarea>
                        <div x-show="preview?.answer_key" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800 dark:bg-amber-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Kunci Jawaban</p>
                            <p class="mt-1 text-sm text-gray-800 dark:text-gray-200" x-text="preview?.answer_key"></p>
                        </div>
                    </div>
                </div>
            </div>
        </x-modal>

        <x-modal name="export-questions" maxWidth="sm">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Ekspor Bank Soal</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Unduh bank soal ke file Excel atau CSV.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="export-scope" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ruang Lingkup</label>
                        <select id="export-scope" x-model="exportScope" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="all">Semua Soal</option>
                            <option value="filtered">Soal Sesuai Pencarian/Filter di Atas</option>
                            <option value="selected" :disabled="selected.length === 0" x-text="selected.length > 0 ? 'Hanya Soal yang Dipilih (' + selected.length + ' soal)' : 'Hanya Soal yang Dipilih (Belum ada soal dicentang)'"></option>
                        </select>
                    </div>
                    <div>
                        <label for="export-format" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Format</label>
                        <select id="export-format" x-model="exportFormat" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <button type="button" @click="exportUrl()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download
                    </button>
                </div>
            </div>
        </x-modal>

        <x-modal name="import-questions" maxWidth="2xl">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Impor Bank Soal</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih jenis soal, unduh template sesuai jenis, isi, lalu upload. Setiap jenis memiliki template terpisah.</p>

                <div x-show="importState.message !== ''" x-transition class="mt-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-500/10 dark:text-rose-300">
                    <svg class="h-5 w-5 shrink-0 text-rose-500 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374H4.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0l7.303 13.748zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <p x-text="importState.message"></p>
                </div>

                <template x-if="importState.step === 1">
                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="import-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Soal</label>
                            <select id="import-type" x-model="importState.type" @change="importState.message = ''" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">-- Pilih Jenis Soal --</option>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/60">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <template x-if="importState.type">
                                        <span>Unduh template untuk jenis soal yang dipilih, lalu isi dan simpan sebagai .xlsx/.csv.</span>
                                    </template>
                                    <template x-if="!importState.type">
                                        <span>Pilih jenis soal untuk menampilkan tautan template.</span>
                                    </template>
                                </p>
                                <a :href="importState.templateUrl()" :class="importState.type ? 'text-indigo-600 hover:text-indigo-500 dark:text-indigo-400' : 'pointer-events-none text-gray-400 dark:text-gray-600'" class="shrink-0 text-sm font-medium underline">Unduh Template (.xlsx)</a>
                            </div>
                        </div>

                        <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                @change="importState.onFileChange($event)"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20"
                            />
                            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Format: .xlsx, .xls, atau .csv (maks 5 MB). Baris contoh pada template otomatis dilewati saat impor.</p>
                        </div>
                    </div>
                </template>

                <template x-if="importState.step === 2">
                    <div class="mt-5">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Hasil validasi untuk soal jenis
                            <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="importState.result?.type_label ?? ''"></span>:
                        </p>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800">
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="importState.result?.total ?? 0"></p>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Baris</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-4 text-center dark:bg-emerald-500/10">
                                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300" x-text="importState.result?.valid ?? 0"></p>
                                <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Valid</p>
                            </div>
                            <div class="rounded-lg bg-indigo-50 p-4 text-center dark:bg-indigo-500/10">
                                <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300" x-text="importState.result?.to_create ?? 0"></p>
                                <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Baru (Ditambah)</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 p-4 text-center dark:bg-rose-500/10">
                                <p class="text-2xl font-bold text-rose-700 dark:text-rose-300" x-text="importState.result?.invalid ?? 0"></p>
                                <p class="text-xs font-medium text-rose-600 dark:text-rose-400">Gagal Validasi</p>
                            </div>
                        </div>

                        <div x-show="(importState.result?.invalid ?? 0) > 0" class="mt-4">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Baris yang gagal validasi (<span x-text="importState.result?.invalid ?? 0"></span>)</p>
                            <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-lg bg-rose-50 p-3 dark:bg-rose-500/10">
                                <template x-for="(error, i) in importState.result?.errors ?? []" :key="i">
                                    <li class="text-xs text-rose-800 dark:text-rose-300" x-text="error"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>

                <template x-if="importState.step === 3">
                    <div class="mt-5">
                        <div class="flex items-start gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-500/10">
                            <svg class="h-6 w-6 shrink-0 text-emerald-500 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Import selesai</p>
                                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                                    <span class="font-bold" x-text="importState.finished?.created ?? 0"></span> soal baru ditambahkan untuk jenis
                                    <span class="font-bold" x-text="importState.result?.type_label ?? ''"></span>.
                                </p>
                                <template x-if="(importState.finished?.failed_count ?? 0) > 0">
                                    <p class="mt-2 text-sm text-rose-700 dark:text-rose-400">
                                        <span class="font-bold" x-text="importState.finished?.failed_count ?? 0"></span> baris gagal.
                                        <a :href="importState.failedUrl()" class="font-semibold underline">Unduh daftar baris gagal</a>
                                    </p>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <template x-if="importState.step === 1">
                        <button type="button" @click="importState.validate()" :disabled="importState.busy || !importState.file" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="importState.busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Validasi & Lanjutkan
                        </button>
                    </template>
                    <template x-if="importState.step === 2">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="importState.step = 1; importState.result = null; importState.message = ''" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                Kembali
                            </button>
                            <button type="button" @click="importState.confirm()" :disabled="importState.busy" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="importState.busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Konfirmasi Impor
                            </button>
                        </div>
                    </template>
                    <template x-if="importState.step === 3">
                        <button type="button" @click="window.location.reload()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                            Selesai
                        </button>
                    </template>
                </div>
            </div>
        </x-modal>
    </div>
</x-layouts.admin>
