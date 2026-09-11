<x-layouts.admin title="Tambah Tahun Ajaran">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Tahun Ajaran</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Buat tahun ajaran baru, lalu tambahkan semester Ganjil/Genap di bawahnya.</p>
        </div>

        <form method="POST" action="{{ route('admin.academic-years.store') }}" class="max-w-2xl space-y-6">
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Data Tahun Ajaran</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="nama" :value="__('Nama Tahun Ajaran')" />
                        <x-text-input id="nama" name="nama" type="text" class="mt-1 block w-full" value="{{ old('nama') }}" required placeholder="contoh: 2025/2026" />
                        <x-input-error :messages="$errors->get('nama')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="tanggal_mulai" :value="__('Tanggal Mulai')" />
                        <x-text-input id="tanggal_mulai" name="tanggal_mulai" type="date" class="mt-1 block w-full" value="{{ old('tanggal_mulai') }}" />
                        <x-input-error :messages="$errors->get('tanggal_mulai')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="tanggal_selesai" :value="__('Tanggal Selesai')" />
                        <x-text-input id="tanggal_selesai" name="tanggal_selesai" type="date" class="mt-1 block w-full" value="{{ old('tanggal_selesai') }}" />
                        <x-input-error :messages="$errors->get('tanggal_selesai')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.academic-years.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>