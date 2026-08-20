<x-layouts.admin title="Tambah Sesi Ujian">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Sesi Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tentukan blok waktu ujian terlebih dahulu. Setelah itu Anda bisa membuat kelompok ruangan & mata pelajaran untuk sesi ini.</p>
        </div>

        @include('admin.partials.flash')

        <form method="POST" action="{{ route('admin.exam-periods.store') }}" class="max-w-2xl space-y-6">
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" :value="__('Nama Sesi')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" placeholder="contoh: UAS Ganjil" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="grade_level" :value="__('Tingkat')" />
                        <select id="grade_level" name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" required>
                            <option value="" @selected(old('grade_level') === '')>Pilih Tingkat</option>
                            <option value="X" @selected(old('grade_level') === 'X')">X</option>
                            <option value="XI" @selected(old('grade_level') === 'XI')">XI</option>
                            <option value="XII" @selected(old('grade_level') === 'XII')">XII</option>
                        </select>
                        <x-input-error :messages="$errors->get('grade_level')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="session_number_display" :value="__('Nomor Sesi')" />
                        <x-text-input id="session_number_display" type="text" class="mt-1 block w-full bg-gray-100 dark:bg-gray-800" value="{{ $nextSessionNumber }}" readonly />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Ditentukan otomatis berdasarkan sesi yang sudah ada.</p>
                    </div>
                    <div>
                        <x-input-label for="exam_date" :value="__('Tanggal')" />
                        <x-text-input id="exam_date" name="exam_date" type="date" class="mt-1 block w-full" value="{{ old('exam_date') }}" required />
                        <x-input-error :messages="$errors->get('exam_date')" class="mt-2" />
                    </div>
                    <div class="grid grid-cols-2 gap-4 sm:col-span-2">
                        <div>
                            <x-input-label for="start_time" :value="__('Jam Mulai')" />
                            <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full" value="{{ old('start_time') }}" required />
                            <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="end_time" :value="__('Jam Selesai')" />
                            <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full" value="{{ old('end_time') }}" required />
                            <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.exam-periods.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
