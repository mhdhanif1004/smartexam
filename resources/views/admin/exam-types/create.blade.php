<x-layouts.admin title="Tambah Jenis Ujian">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Jenis Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Buat jenis ujian baru yang bisa digunakan untuk sesi ujian.</p>
        </div>

        <form method="POST" action="{{ route('admin.exam-types.store') }}" class="max-w-2xl space-y-6">
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Data Jenis Ujian</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Nama')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required placeholder="contoh: Harian" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="code" :value="__('Kode')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" value="{{ old('code') }}" required placeholder="contoh: harian" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kode unik (huruf/angka/dash/underscore).</p>
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sort_order" :value="__('Urutan Tampil')" />
                        <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" value="{{ old('sort_order', 0) }}" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>
                </div>
                <label class="mt-5 flex w-fit items-center gap-2">
                    <input type="checkbox" name="boleh_dijadwalkan_guru" value="1" @checked(old('boleh_dijadwalkan_guru')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Boleh dijadwalkan oleh Guru Mapel</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-gray-500 dark:text-gray-400">Jika dicentang, guru yang mengampu mapel bisa membuat jadwal ujian sendiri untuk jenis ini.</p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.exam-types.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>