<x-layouts.admin title="Edit Semester">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Semester</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui data semester.</p>
        </div>

        <form method="POST" action="{{ route('admin.semesters.update', $semester) }}" class="max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Data Semester</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="academic_year_id" :value="__('Tahun Ajaran')" />
                        <select id="academic_year_id" name="academic_year_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            @foreach ($academicYears as $academicYear)
                                <option value="{{ $academicYear->id }}" @selected(old('academic_year_id', $semester->academic_year_id) == $academicYear->id)>{{ $academicYear->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('academic_year_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="jenis" :value="__('Jenis Semester')" />
                        <select id="jenis" name="jenis" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Jenis --</option>
                            <option value="ganjil" @selected(old('jenis', $semester->jenis) === 'ganjil')>Ganjil</option>
                            <option value="genap" @selected(old('jenis', $semester->jenis) === 'genap')>Genap</option>
                        </select>
                        <x-input-error :messages="$errors->get('jenis')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.semesters.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>