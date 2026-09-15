<x-layouts.guru_mapel title="Edit Jadwal Ujian">
    @php
        $firstSchedule = $examPeriod->schedules->first();
        $currentScores = [
            'subject_id' => $firstSchedule?->subject_id,
            'classroom_id' => $firstSchedule?->classroom_id,
            'class_name' => $firstSchedule?->class_name,
            'exam_date' => $examPeriod->exam_date->format('Y-m-d'),
            'start_time' => \Illuminate\Support\Str::substr((string) $examPeriod->start_time, 0, 5),
            'duration_minutes' => $firstSchedule?->duration_minutes,
        ];
    @endphp
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Jadwal Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui jadwal ujian untuk mapel-kelas yang Anda ampu.</p>
        </div>

        @include('admin.partials.flash')

        @if ($hasStarted)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                Sesi ujian sudah mulai dikerjakan siswa — jenis ujian, mapel, dan kelas <b>terkunci</b>. Hanya waktu pelaksanaan yang bisa diubah.
            </div>
        @elseif ($hasConfirmedAttendance)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                Sudah ada absensi yang dikonfirmasi untuk sesi ini. Mengubah jenis ujian, mapel, atau kelas <b>akan menghapus data absensi yang sudah dikonfirmasi</b> — wajib mencentang konfirmasi di bawah.
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('guru_mapel.exam-schedules.update', $examPeriod) }}"
            class="max-w-3xl space-y-6"
            x-data="{
                subjects: @js($classroomsBySubject),
                get classrooms() {
                    return this.subjects[this.subjectId] ?? [];
                },
                subjectId: '{{ old('subject_id', $currentScores['subject_id']) }}',
            }"
        >
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Detail Ujian</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="exam_type_id" :value="__('Jenis Ujian')" />
                        <select id="exam_type_id" name="exam_type_id" required {{ $hasStarted ? 'disabled' : '' }} class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Jenis --</option>
                            @foreach ($examTypes as $examType)
                                <option value="{{ $examType->id }}" @selected(old('exam_type_id', $examPeriod->exam_type_id) == $examType->id)>{{ $examType->name }}</option>
                            @endforeach
                        </select>
                        @if ($hasStarted)
                            <input type="hidden" name="exam_type_id" value="{{ $examPeriod->exam_type_id }}">
                        @endif
                        <x-input-error :messages="$errors->get('exam_type_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                        <select id="subject_id" name="subject_id" required {{ $hasStarted ? 'disabled' : '' }} x-model="subjectId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Mapel --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $currentScores['subject_id']) == $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        @if ($hasStarted)
                            <input type="hidden" name="subject_id" value="{{ $currentScores['subject_id'] }}">
                        @endif
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="classroom_id" :value="__('Kelas')" />
                        <select id="classroom_id" name="classroom_id" required {{ $hasStarted ? 'disabled' : '' }} class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Mapel dulu --</option>
                            <template x-for="cls in classrooms" :key="cls.id">
                                <option :value="cls.id" x-text="cls.name" :selected="'{{ old('classroom_id', $currentScores['classroom_id']) }}' == cls.id"></option>
                            </template>
                        </select>
                        @if ($hasStarted)
                            <input type="hidden" name="classroom_id" value="{{ $currentScores['classroom_id'] }}">
                        @endif
                        <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                    </div>
                </div>

                @if (! $hasStarted && $hasConfirmedAttendance)
                    <label class="mt-5 flex w-fit items-start gap-2">
                        <input type="checkbox" name="confirm_attendance_reset" value="1" @checked(old('confirm_attendance_reset')) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Saya paham: mengubah jenis ujian/mapel/kelas <b>akan menghapus data absensi yang sudah dikonfirmasi</b> untuk sesi ini.
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('confirm_attendance_reset')" class="mt-2" />
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Waktu Ujian</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="exam_date" :value="__('Tanggal')" />
                        <x-text-input id="exam_date" name="exam_date" type="date" class="mt-1 block w-full" value="{{ old('exam_date', $currentScores['exam_date']) }}" required />
                        <x-input-error :messages="$errors->get('exam_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="start_time" :value="__('Jam Mulai')" />
                        <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full" value="{{ old('start_time', $currentScores['start_time']) }}" required />
                        <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="duration_minutes" :value="__('Durasi (menit)')" />
                        <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="5" max="600" class="mt-1 block w-full" value="{{ old('duration_minutes', $currentScores['duration_minutes']) }}" required />
                        <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('guru_mapel.exam-schedules.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.guru_mapel>