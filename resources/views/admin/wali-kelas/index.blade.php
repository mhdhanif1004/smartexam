<x-layouts.admin title="Data Wali Kelas">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Data Wali Kelas</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola akun wali kelas dan penugasan kelas yang diampunya (1 wali kelas = 1 kelas).</p>
            </div>
            <a href="{{ route('admin.wali-kelas.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                Tambah Wali Kelas
            </a>
        </div>

        <div
            x-data="{
                deleteUrl: '',
                selected: [],
                sessionKey: 'smartexam_selected_wali_kelas',
                bulkDeleteSuccess: @js(str_contains((string) session('success'), 'data wali kelas berhasil dihapus.')),
                visibleIds: @js($waliKelas->map(fn ($wali) => $wali->id)->values()),
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
                bulkDeleteUrl: @js(route('admin.wali-kelas.bulk-delete')),
            }"
        >
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-800">
                    <form method="GET" action="{{ route('admin.wali-kelas.index') }}" class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                        <x-text-input
                            name="search"
                            type="text"
                            class="sm:w-72"
                            placeholder="Cari nama atau email…"
                            value="{{ request('search') }}"
                        />
                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Cari</button>
                        @if (request('search'))
                            <a href="{{ route('admin.wali-kelas.index') }}" class="text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Reset</a>
                        @endif
                    </form>

                    <div class="flex items-center gap-2">
                        <template x-if="selected.length > 0">
                            <button
                                type="button"
                                @click="deleteUrl = bulkDeleteUrl; $dispatch('open-modal', 'confirm-bulk-delete')"
                                class="inline-flex items-center gap-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                            >
                                Hapus Terpilih ({{ $waliKelas->total() }})
                                <span x-text="selected.length"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="w-12 px-4 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        @change="selectAll()"
                                        :checked="visibleIds.length > 0 && visibleIds.every((id) => selected.includes(id))"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                                    >
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kelas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($waliKelas as $wali)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            @change="toggleSelect({{ $wali->id }})"
                                            :checked="selected.includes({{ $wali->id }})"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $wali->user?->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $wali->user?->email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                            {{ $wali->classroom?->name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($wali->user?->is_active)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Aktif</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.wali-kelas.edit', $wali) }}" class="rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-indigo-400">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </a>
                                            <button
                                                type="button"
                                                @click="deleteUrl = @js(route('admin.wali-kelas.destroy', $wali)); $dispatch('open-modal', 'confirm-delete')"
                                                class="rounded-md p-2 text-gray-500 transition hover:bg-rose-50 hover:text-rose-600 dark:text-gray-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                            >
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada data wali kelas. Klik "Tambah Wali Kelas" untuk membuat yang pertama.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($waliKelas->hasPages())
                    <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                        {{ $waliKelas->links() }}
                    </div>
                @endif
            </div>

            <x-modal name="confirm-delete" :show="false" focusable>
                <form method="POST" :action="deleteUrl" class="p-6">
                    @csrf
                    @method('DELETE')
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Hapus Wali Kelas</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Yakin ingin menghapus wali kelas ini? Akun pengguna terkait juga akan dihapus.</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" x-on:click="$dispatch('close')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</button>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">Hapus</button>
                    </div>
                </form>
            </x-modal>

            <x-modal name="confirm-bulk-delete" :show="false" focusable>
                <form method="POST" :action="bulkDeleteUrl" class="p-6">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Hapus Banyak Wali Kelas</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Yakin ingin menghapus <span x-text="selected.length"></span> wali kelas terpilih? Akun pengguna terkait juga akan dihapus.</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" x-on:click="$dispatch('close')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</button>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">Hapus</button>
                    </div>
                </form>
            </x-modal>
        </div>
    </div>
</x-layouts.admin>