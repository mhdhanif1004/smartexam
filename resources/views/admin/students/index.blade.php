<x-layouts.admin title="Data Siswa">
    <div
        x-data="{
            deleteUrl: '',
            selected: [],
            sessionKey: 'smartexam_selected_students',
            bulkDeleteSuccess: @js(str_contains((string) session('success'), 'data siswa berhasil dihapus.')),
            visibleIds: @js($students->map(fn ($student) => $student->id)->values()),
            init() {
                if (this.bulkDeleteSuccess) {
                    this.clearSelection();
                } else {
                    this.selected = this.loadSelection();
                }
            },
            loadSelection() {
                try {
                    const parsed = JSON.parse(window.sessionStorage.getItem(this.sessionKey) || '[]');
                    return Array.isArray(parsed)
                        ? [...new Set(parsed.filter((id) => Number.isInteger(id)))]
                        : [];
                } catch (e) {
                    return [];
                }
            },
            persistSelection() {
                try {
                    window.sessionStorage.setItem(this.sessionKey, JSON.stringify(this.selected));
                } catch (e) {
                }
            },
            clearSelection() {
                this.selected = [];
                try {
                    window.sessionStorage.removeItem(this.sessionKey);
                } catch (e) {
                }
            },
            toggleSelect(id) {
                const index = this.selected.indexOf(id);
                if (index === -1) { this.selected.push(id); } else { this.selected.splice(index, 1); }
                this.persistSelection();
            },
            selectAll() {
                const allVisibleSelected = this.visibleIds.length > 0
                    && this.visibleIds.every((id) => this.selected.includes(id));
                if (allVisibleSelected) {
                    this.selected = this.selected.filter((id) => !this.visibleIds.includes(id));
                } else {
                    this.visibleIds.forEach((id) => {
                        if (!this.selected.includes(id)) { this.selected.push(id); }
                    });
                }
                this.persistSelection();
            },
            bulkDeleteUrl: @js(route('admin.students.bulk-delete')),
            exportScope: 'all',
            exportFormat: 'xlsx',
            openExportModal() {
                if (this.exportScope === 'selected' && this.selected.length === 0) {
                    this.exportScope = 'all';
                }
                this.$dispatch('open-modal', 'export-students');
            },
            exportUrl() {
                const params = new URLSearchParams();
                params.set('format', this.exportFormat);
                params.set('scope', this.exportScope);
                if (this.exportScope === 'filtered') {
                    @if (request('search'))
                    params.set('search', @js(request('search')));
                    @endif
                    @if (request('class'))
                    params.set('class', @js(request('class')));
                    @endif
                }
                if (this.exportScope === 'selected') {
                    this.selected.forEach((id) => params.append('ids[]', id));
                }
                window.location.href = @js(route('admin.students.export')) + '?' + params.toString();
            },
            importState: {
                step: 1,
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
                failedUrl() {
                    return @js(route('admin.students.import-failed', '__FILE__'))
                        .replace('__FILE__', encodeURIComponent(this.finished?.failed_file ?? ''));
                },
                validate() {
                    if (!this.file) { this.message = 'Pilih file Excel/CSV terlebih dahulu.'; return; }
                    this.busy = true;
                    this.message = '';
                    const formData = new FormData();
                    formData.append('file', this.file);
                    fetch(@js(route('admin.students.import-validate')), {
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
                    fetch(@js(route('admin.students.import-confirm')), {
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
                    this.file = null;
                    this.busy = false;
                    this.message = '';
                    this.result = null;
                    this.finished = null;
                },
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Data Siswa</h2>
                <p class="mt-1 text-sm text-gray-500">Kelola data siswa beserta akun penggunanya.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="importState.reset(); $dispatch('open-modal', 'import-students')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Impor
                </button>
                <button type="button" @click="openExportModal()" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Ekspor
                </button>
                <a href="{{ route('admin.students.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Siswa
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.students.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama, username, atau NISN..." class="block w-full" />
            </div>
            <div>
                <select name="class" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-auto">
                    <option value="">Semua Kelas</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class }}" @selected(request('class') === $class)>{{ $class }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('class'))
                    <a href="{{ route('admin.students.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                @endif
            </div>
        </form>

        <div x-show="selected.length > 0" x-transition class="flex items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
            <p class="text-sm font-medium text-indigo-800">
                <span x-text="selected.length" class="font-bold"></span> siswa dipilih <span class="text-xs text-indigo-500">(dari semua halaman)</span>
            </p>
            <div class="flex items-center gap-3">
                <button type="button" @click="clearSelection()" class="text-sm font-medium text-gray-600 transition hover:text-gray-800">Reset Pilihan</button>
                <button type="button" @click="$dispatch('open-modal', 'confirm-bulk-delete')" class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-rose-500">
                    Hapus Terpilih
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3">
                                <input type="checkbox" :checked="visibleIds.length > 0 && visibleIds.every((id) => selected.includes(id))" :disabled="visibleIds.length === 0" @change="selectAll()" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">NISN</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nama</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Username</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Password</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kelas</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ruangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($students as $index => $student)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" :checked="selected.includes({{ $student->id }})" @change="toggleSelect({{ $student->id }})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $students->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $student->nisn }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $student->user?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $student->user?->username }}</td>
                                @include('admin.partials.password-cell', ['user' => $student->user])
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $student->class_name }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($student->room)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">{{ $student->room->name }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <x-badge-status :status="$student->user?->is_active ? 'aktif' : 'nonaktif'" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.students.edit', $student) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Edit</a>
                                        <button type="button" @click="deleteUrl = '{{ route('admin.students.destroy', $student) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada data siswa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $students->links() }}</div>

        @include('admin.partials.delete-modal')

        <x-modal name="confirm-bulk-delete" maxWidth="sm" focusable>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Konfirmasi Hapus Massal</h2>
                        <p class="mt-1 text-sm text-gray-500">Yakin ingin menghapus <span x-text="selected.length" class="font-bold"></span> data siswa beserta akunnya? Tindakan ini tidak dapat dibatalkan.</p>
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

        <x-modal name="export-students" maxWidth="sm">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Ekspor Data Siswa</h2>
                <p class="mt-1 text-sm text-gray-500">Unduh data siswa ke file Excel atau CSV.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="export-scope" class="block text-sm font-medium text-gray-700">Ruang Lingkup</label>
                        <select id="export-scope" x-model="exportScope" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">Semua Data Siswa</option>
                            <option value="filtered">Data Sesuai Pencarian/Filter Kelas di Atas</option>
                            <option value="selected" :disabled="selected.length === 0" x-text="selected.length > 0 ? 'Hanya Siswa yang Dipilih (' + selected.length + ' siswa)' : 'Hanya Siswa yang Dipilih (Belum ada siswa dicentang)'"></option>
                        </select>
                    </div>
                    <div>
                        <label for="export-format" class="block text-sm font-medium text-gray-700">Format</label>
                        <select id="export-format" x-model="exportFormat" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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

        <x-modal name="import-students" maxWidth="2xl">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Impor Data Siswa</h2>
                <p class="mt-1 text-sm text-gray-500">Upload file Excel/CSV berisi kolom NISN, Nama, dan Kelas.</p>

                <div x-show="importState.message !== ''" x-transition class="mt-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <svg class="h-5 w-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c-.866 1.5.217 3.374 1.948 3.374H4.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0l7.303 13.748zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <p x-text="importState.message"></p>
                </div>

                <template x-if="importState.step === 1">
                    <div class="mt-5">
                        <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center">
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                @change="importState.onFileChange($event)"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <p class="mt-3 text-xs text-gray-400">Format: .xlsx, .xls, atau .csv (maks 5 MB). Unduh template terlebih dahulu jika perlu.</p>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('admin.students.import-template') }}" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-500">Unduh Template Impor (.xlsx)</a>
                        </div>
                    </div>
                </template>

                <template x-if="importState.step === 2">
                    <div class="mt-5">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            <div class="rounded-lg bg-gray-50 p-4 text-center">
                                <p class="text-2xl font-bold text-gray-900" x-text="importState.result?.total ?? 0"></p>
                                <p class="text-xs font-medium text-gray-500">Total Baris</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-4 text-center">
                                <p class="text-2xl font-bold text-emerald-700" x-text="importState.result?.valid ?? 0"></p>
                                <p class="text-xs font-medium text-emerald-600">Valid</p>
                            </div>
                            <div class="rounded-lg bg-indigo-50 p-4 text-center">
                                <p class="text-2xl font-bold text-indigo-700" x-text="importState.result?.to_create ?? 0"></p>
                                <p class="text-xs font-medium text-indigo-600">Baru (Ditambah)</p>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-4 text-center">
                                <p class="text-2xl font-bold text-amber-700" x-text="importState.result?.to_update ?? 0"></p>
                                <p class="text-xs font-medium text-amber-600">Update</p>
                            </div>
                            <div class="rounded-lg bg-sky-50 p-4 text-center">
                                <p class="text-2xl font-bold text-sky-700" x-text="importState.result?.new_classes_count ?? 0"></p>
                                <p class="text-xs font-medium text-sky-600">Kelas Baru (Ditambah)</p>
                            </div>
                        </div>

                        <div x-show="(importState.result?.new_classes_count ?? 0) > 0" class="mt-4 flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                            <svg class="h-5 w-5 shrink-0 text-sky-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <p>
                                <span class="font-bold" x-text="importState.result?.new_classes_count ?? 0"></span> kelas baru akan ditambahkan:
                                <span x-text="(importState.result?.new_classes ?? []).join(', ')"></span>.
                            </p>
                        </div>

                        <div x-show="(importState.result?.invalid ?? 0) > 0" class="mt-4">
                            <p class="text-sm font-semibold text-gray-900">Baris yang gagal validasi (<span x-text="importState.result?.invalid ?? 0"></span>)</p>
                            <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-lg bg-rose-50 p-3">
                                <template x-for="(error, i) in importState.result?.errors ?? []" :key="i">
                                    <li class="text-xs text-rose-800" x-text="error"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>

                <template x-if="importState.step === 3">
                    <div class="mt-5">
                        <div class="flex items-start gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <svg class="h-6 w-6 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-emerald-800">Import selesai</p>
                                <p class="mt-1 text-sm text-emerald-700">
                                    <span class="font-bold" x-text="importState.finished?.created ?? 0"></span> siswa baru ditambahkan,
                                    <span class="font-bold" x-text="importState.finished?.updated ?? 0"></span> siswa diperbarui.
                                </p>
                                <template x-if="(importState.finished?.new_classes_created ?? 0) > 0">
                                    <p class="mt-2 text-sm text-sky-700">
                                        <span class="font-bold" x-text="importState.finished?.new_classes_created ?? 0"></span> kelas baru ditambahkan
                                        (<span x-text="(importState.finished?.new_classes ?? []).join(', ')"></span>).
                                    </p>
                                </template>
                                <template x-if="(importState.finished?.failed_count ?? 0) > 0">
                                    <p class="mt-2 text-sm text-rose-700">
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
                        <button type="button" @click="importState.confirm()" :disabled="importState.busy" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="importState.busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Konfirmasi Impor
                        </button>
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
