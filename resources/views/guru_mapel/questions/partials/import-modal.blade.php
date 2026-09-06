<x-modal name="import-questions" maxWidth="lg">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Import Soal</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Unggah soal dari file Excel/CSV. Baris dengan mapel di luar kelas yang Anda ampu otomatis dianggap gagal dan tidak disimpan.</p>
            </div>
            <button type="button" @click="$dispatch('close')" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="importState.message !== ''" x-transition class="mt-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-500/10 dark:text-rose-300">
            <p x-text="importState.message"></p>
        </div>

        <template x-if="importState.step === 1">
            <div class="mt-5 space-y-5">
                <div>
                    <label for="import-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Soal</label>
                    <select id="import-type" x-model="importState.type" @change="importState.message = ''" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">Pilih jenis soal</option>
                        @foreach (\App\Models\Question::TYPES as $value => $label)
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
                        class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:text-indigo-300 dark:hover:file:bg-indigo-500/20"
                    />
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Format: .xlsx, .xls, atau .csv (maks 5 MB). Baris contoh pada template otomatis dilewati saat impor. Kelas target soal ditentukan otomatis dari cakupan kelas yang di-assign untuk mapel bersangkutan.</p>
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
            <div class="mt-5 space-y-3">
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
                <div x-show="importState.finished?.warning" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                    <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <p x-text="importState.finished?.warning"></p>
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
