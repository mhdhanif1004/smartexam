<x-layouts.guru_mapel title="Buat Jadwal Ujian">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Buat Jadwal Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Buat jadwal ujian untuk mapel-kelas yang Anda ampu. Ujian berbasis kelas (tanpa ruang fisik),
                dan token otomatis di-generate scheduler ketika waktunya tiba.
            </p>
        </div>

        @if ($examTypes->isEmpty())
            <div class="flex items-start gap-4 rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-500/10">
                <div>
                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-300">Belum ada jenis ujian yang boleh dijadwalkan guru</h3>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        Admin perlu mengaktifkan toggle "Boleh dijadwalkan Guru Mapel" pada minimal satu jenis ujian (menu Admin → Jenis Ujian).
                    </p>
                </div>
            </div>
        @else
            <form
                method="POST"
                action="{{ route('guru_mapel.exam-schedules.store') }}"
                class="max-w-3xl space-y-6"
                x-data="{
                    subjects: @js($classroomsBySubject),
                    get classrooms() {
                        return this.subjects[this.subjectId] ?? [];
                    },
                    subjectId: '{{ old('subject_id', '') }}',
                }"
            >
                @csrf

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Detail Ujian</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="exam_type_id" :value="__('Jenis Ujian')" />
                            <select id="exam_type_id" name="exam_type_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">-- Pilih Jenis --</option>
                                @foreach ($examTypes as $examType)
                                    <option value="{{ $examType->id }}" @selected(old('exam_type_id') == $examType->id)>{{ $examType->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('exam_type_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                            <select id="subject_id" name="subject_id" required x-model="subjectId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">-- Pilih Mapel --</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="classroom_id" :value="__('Kelas')" />
                            <select id="classroom_id" name="classroom_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">-- Pilih Mapel dulu --</option>
                                <template x-for="cls in classrooms" :key="cls.id">
                                    <option :value="cls.id" x-text="cls.name" :selected="'{{ old('classroom_id', '') }}' == cls.id"></option>
                                </template>
                            </select>
                            <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Waktu Ujian</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="exam_date" :value="__('Tanggal')" />
                            <x-text-input id="exam_date" name="exam_date" type="date" class="mt-1 block w-full" value="{{ old('exam_date') }}" required />
                            <x-input-error :messages="$errors->get('exam_date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="start_time" :value="__('Jam Mulai')" />
                            <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full" value="{{ old('start_time') }}" required />
                            <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="duration_minutes" :value="__('Durasi (menit)')" />
                            <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="5" max="600" class="mt-1 block w-full" value="{{ old('duration_minutes', 90) }}" required />
                            <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('guru_mapel.exam-schedules.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                    <x-primary-button>Simpan Jadwal</x-primary-button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.guru_mapel>