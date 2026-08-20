<x-layouts.admin :title="'Pengaturan Ujian'">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Pengaturan Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Atur batas maksimal pengawas per ruangan. Nilai ini menjadi default untuk semua ruangan baru.
            </p>
        </div>

        @include('admin.partials.flash')

        <form method="POST" action="{{ route('admin.exam-settings.update') }}" class="max-w-xl">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pengawas</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Batas maksimal jumlah pengawas yang dapat ditugaskan di setiap ruangan per hari ujian.
                </p>

                <div class="mt-4">
                    <x-input-label for="max_supervisors_per_room" :value="__('Maksimal Pengawas per Ruangan')" />
                    <x-text-input id="max_supervisors_per_room" name="max_supervisors_per_room" type="number"
                        min="1" max="10" class="mt-1 block w-full"
                        value="{{ old('max_supervisors_per_room', $setting->max_supervisors_per_room ?? 3) }}" />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Default: 3. Nilai ini bisa di-override per ruangan saat membuat ruangan baru.
                    </p>
                    <x-input-error :messages="$errors->get('max_supervisors_per_room')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    Batal
                </a>
                <x-primary-button>Simpan Pengaturan</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
