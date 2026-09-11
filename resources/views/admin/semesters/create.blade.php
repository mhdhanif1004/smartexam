<x-layouts.admin title="Tambah Semester">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Semester</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tambahkan semester akademik dalam tahun ajaran tertentu.</p>
        </div>

        @if ($academicYears->isEmpty())
            <div class="flex items-start gap-4 rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-500/10">
                <svg class="mt-0.5 h-6 w-6 shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-300">Buat Tahun Ajaran terlebih dahulu</h3>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        Belum ada tahun ajaran. Semester harus berada di bawah tahun ajaran —
                        <a href="{{ route('admin.academic-years.create') }}" class="font-semibold underline">buat tahun ajaran</a> terlebih dahulu.
                    </p>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.semesters.store') }}" class="max-w-2xl space-y-6">
                @csrf

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Data Semester</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="academic_year_id" :value="__('Tahun Ajaran')" />
                            <select id="academic_year_id" name="academic_year_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">-- Pilih Tahun Ajaran --</option>
                                @foreach ($academicYears as $academicYear)
                                    <option value="{{ $academicYear->id }}" @selected(old('academic_year_id') == $academicYear->id)>{{ $academicYear->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('academic_year_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="jenis" :value="__('Jenis Semester')" />
                            <select id="jenis" name="jenis" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">-- Pilih Jenis --</option>
                                <option value="ganjil" @selected(old('jenis') === 'ganjil')>Ganjil</option>
                                <option value="genap" @selected(old('jenis') === 'genap')>Genap</option>
                            </select>
                            <x-input-error :messages="$errors->get('jenis')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.semesters.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                    <x-primary-button>Simpan</x-primary-button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.admin>