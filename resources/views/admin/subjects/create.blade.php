<x-layouts.admin title="Tambah Mata Pelajaran">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Mata Pelajaran</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tambahkan mata pelajaran baru ke dalam sistem.</p>
        </div>

        <form method="POST" action="{{ route('admin.subjects.store') }}" class="max-w-2xl space-y-6">
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="code" :value="__('Kode')" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" value="{{ old('code') }}" required placeholder="contoh: MTK" autofocus />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="name" :value="__('Nama Mata Pelajaran')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required placeholder="contoh: Matematika" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="default_duration_minutes" :value="__('Durasi Default (menit)')" />
                        <x-text-input id="default_duration_minutes" name="default_duration_minutes" type="number" min="5" max="600" class="mt-1 block w-full" value="{{ old('default_duration_minutes', 60) }}" required />
                        <x-input-error :messages="$errors->get('default_duration_minutes')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.subjects.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
