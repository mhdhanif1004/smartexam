<x-layouts.admin title="Edit Jadwal Ujian">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Jadwal Ujian</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui jadwal pelaksanaan ujian.</p>
        </div>

        @include('admin.partials.flash')

        @if ($hasStarted ?? false)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                Sesi ujian sudah mulai dikerjakan siswa — mata pelajaran, ruangan, dan kelas <b>terkunci</b>. Hanya waktu pelaksanaan yang bisa diubah.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.exam-schedules.update', $examSchedule) }}" class="max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                        <select id="subject_id" name="subject_id" required {{ ($hasStarted ?? false) ? 'disabled' : '' }} class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $examSchedule->subject_id) == $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>
                            @endforeach
                        </select>
                        @if ($hasStarted ?? false)
                            <input type="hidden" name="subject_id" value="{{ $examSchedule->subject_id }}">
                        @endif
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="room_id" :value="__('Ruangan Ujian')" />
                        <select id="room_id" name="room_id" required {{ ($hasStarted ?? false) ? 'disabled' : '' }} class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">-- Pilih Ruangan --</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('room_id', $examSchedule->room_id) == $room->id)>{{ $room->display_name }} (kapasitas {{ $room->capacity }})</option>
                            @endforeach
                        </select>
                        @if ($hasStarted ?? false)
                            <input type="hidden" name="room_id" value="{{ $examSchedule->room_id }}">
                        @endif
                        <x-input-error :messages="$errors->get('room_id')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="class_name" :value="__('Kelas')" />
                        <input id="class_name" name="class_name" list="class-list" type="text" required {{ ($hasStarted ?? false) ? 'disabled' : '' }} class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" value="{{ old('class_name', $examSchedule->class_name) }}" placeholder="contoh: XI RPL 1">
                        <datalist id="class-list">
                            @foreach ($classes as $class)
                                <option value="{{ $class }}"></option>
                            @endforeach
                        </datalist>
                        @if ($hasStarted ?? false)
                            <input type="hidden" name="class_name" value="{{ $examSchedule->class_name }}">
                        @endif
                        <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="exam_date" :value="__('Tanggal Ujian')" />
                        <x-text-input id="exam_date" name="exam_date" type="date" class="mt-1 block w-full" value="{{ old('exam_date', $examSchedule->exam_date->format('Y-m-d')) }}" required />
                        <x-input-error :messages="$errors->get('exam_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="start_time" :value="__('Jam Mulai')" />
                        <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full" value="{{ old('start_time', \Illuminate\Support\Str::substr($examSchedule->start_time, 0, 5)) }}" required />
                        <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="duration_minutes" :value="__('Durasi (menit)')" />
                        <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="5" max="600" class="mt-1 block w-full" value="{{ old('duration_minutes', $examSchedule->duration_minutes) }}" required />
                        <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $examSchedule->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50 {{ ($hasStarted ?? false) ? 'opacity-70' : '' }}">
                            <input type="checkbox" name="is_random_question_order" value="1" @checked(old('is_random_question_order', $examSchedule->is_random_question_order)) {{ ($hasStarted ?? false) ? 'disabled' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800">
                            @if ($hasStarted ?? false)
                                <input type="hidden" name="is_random_question_order" value="{{ $examSchedule->is_random_question_order ? '1' : '0' }}">
                            @endif
                            <span>
                                <span class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Acak urutan soal untuk peserta</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    Setiap peserta mendapat urutan soal berbeda, tetap konsisten selama sesi ujian.
                                    @if ($hasStarted ?? false)
                                        Pengaturan ini terkunci karena sesi sudah berjalan.
                                    @endif
                                </span>
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('is_random_question_order')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.exam-schedules.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
