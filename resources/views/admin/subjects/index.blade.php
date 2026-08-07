<x-layouts.admin title="Mata Pelajaran">
    <div
        x-data="{
            deleteUrl: '',
            selected: [],
            sessionKey: 'smartexam_selected_subjects',
            bulkDeleteSuccess: @js(str_contains((string) session('success'), 'mata pelajaran berhasil dihapus.')),
            visibleIds: @js($subjects->map(fn ($subject) => $subject->id)->values()),
            preview: { total: 0, linkedCount: 0 },
            previewing: false,
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
            bulkDeleteUrl: @js(route('admin.subjects.bulk-delete')),
            openBulkDeleteModal() {
                if (this.selected.length === 0 || this.previewing) { return; }
                const formData = new FormData();
                this.selected.forEach((id) => formData.append('ids[]', id));
                this.previewing = true;
                fetch(@js(route('admin.subjects.bulk-delete-preview')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
                    body: formData,
                })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        this.preview = ok
                            ? { total: data.total, linkedCount: data.linked_count }
                            : this.preview;
                        this.$dispatch('open-modal', 'confirm-bulk-delete');
                    })
                    .catch(() => { this.$dispatch('open-modal', 'confirm-bulk-delete'); })
                    .finally(() => { this.previewing = false; });
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Mata Pelajaran</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola daftar mata pelajaran yang diujikan.</p>
            </div>
            <a href="{{ route('admin.subjects.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Mata Pelajaran
            </a>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.subjects.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari kode atau nama mata pelajaran..." class="block w-full" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search'))
                    <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                @endif
            </div>
        </form>

        <div x-show="selected.length > 0" x-transition class="flex items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 dark:border-indigo-500/30 dark:bg-indigo-500/10">
            <p class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                <span x-text="selected.length" class="font-bold"></span> mata pelajaran dipilih <span class="text-xs text-indigo-500 dark:text-indigo-400">(dari semua halaman)</span>
            </p>
            <div class="flex items-center gap-3">
                <button type="button" @click="clearSelection()" class="text-sm font-medium text-gray-600 transition hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Reset Pilihan</button>
                <button type="button" @click="openBulkDeleteModal()" class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-rose-500">
                    Hapus Terpilih
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3">
                                <input type="checkbox" :checked="visibleIds.length > 0 && visibleIds.every((id) => selected.includes(id))" :disabled="visibleIds.length === 0" @change="selectAll()" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kode</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Durasi Default</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah Soal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jadwal Ujian</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($subjects as $index => $subject)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" :checked="selected.includes({{ $subject->id }})" @change="toggleSelect({{ $subject->id }})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $subjects->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $subject->code }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $subject->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $subject->default_duration_minutes }} menit</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $subject->questions_count }} soal</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($subject->exam_schedules_count > 0)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $subject->exam_schedules_count }} jadwal</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.subjects.edit', $subject) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                                        <button type="button" @click="deleteUrl = '{{ route('admin.subjects.destroy', $subject) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada data mata pelajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $subjects->links() }}</div>

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
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Anda akan menghapus <span x-text="selected.length" class="font-bold"></span> mata pelajaran. Tindakan ini tidak dapat dibatalkan.</p>

                        <div x-show="preview.linkedCount > 0" x-transition class="mt-3 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                            <svg class="h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <p>
                                <span x-text="preview.linkedCount" class="font-bold"></span> dari <span x-text="selected.length" class="font-bold"></span> mata pelajaran yang dipilih sudah memiliki soal/jadwal ujian terkait. Menghapusnya akan ikut menghapus data tersebut.
                            </p>
                        </div>
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
    </div>
</x-layouts.admin>
