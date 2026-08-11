<x-layouts.admin :title="'Tambah Kelompok - '.$examPeriod->name">
    @php
        $initialSubjects = collect(old('subjects', []))->map(fn ($row) => [
            'subject_id' => (string) ($row['subject_id'] ?? ''),
            'start_time' => (string) ($row['start_time'] ?? ''),
            'duration_minutes' => (string) ($row['duration_minutes'] ?? '60'),
        ])->all();

        $initialRooms = collect(old('rooms', []))->map(fn ($id) => (int) $id)->values()->all();

        $initialClasses = collect(old('class_names', []))->map(fn ($name) => (string) $name)->values()->all();

        $subjectErrors = [];
        foreach ($errors->messages() as $key => $messages) {
            if (str_starts_with($key, 'subjects.')) {
                $subjectErrors[$key] = $messages[0] ?? '';
            }
        }
    @endphp

    <div
        x-data="{
            rooms: @js($initialRooms),
            allRoomIds: @js($rooms->pluck('id')->map(fn ($id) => (int) $id)->all()),
            classes: @js($initialClasses),
            allClassNames: @js($classes->map(fn ($name) => (string) $name)->values()->all()),
            subjects: @js($initialSubjects).map((row, i) => ({ ...row, _id: i })),
            _seq: @js(count($initialSubjects)),
            errors: @js($subjectErrors),
            toggleRoom(id) {
                const i = this.rooms.indexOf(id);
                if (i >= 0) { this.rooms.splice(i, 1); } else { this.rooms.push(id); }
            },
            toggleAllRooms() {
                const allSelected = this.allRoomIds.length > 0 && this.allRoomIds.every((id) => this.rooms.includes(id));
                this.rooms = allSelected ? [] : [...this.allRoomIds];
            },
            toggleClass(name) {
                const i = this.classes.indexOf(name);
                if (i >= 0) { this.classes.splice(i, 1); } else { this.classes.push(name); }
            },
            toggleAllClasses() {
                const allSelected = this.allClassNames.length > 0 && this.allClassNames.every((name) => this.classes.includes(name));
                this.classes = allSelected ? [] : [...this.allClassNames];
            },
            addSubject() {
                this.subjects.push({ _id: this._seq++, subject_id: '', start_time: '', duration_minutes: '60' });
            },
            removeSubject(id) {
                if (this.subjects.length <= 1) return;
                this.subjects.splice(this.subjects.findIndex((row) => row._id === id), 1);
            },
            subjectError(index, field) {
                return this.errors['subjects.' + index + '.' + field] || '';
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Kelompok Ruangan</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sesi <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $examPeriod->name }}</span> &middot;
                    {{ $examPeriod->exam_date->format('d M Y') }} &middot;
                    {{ \Illuminate\Support\Str::substr($examPeriod->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($examPeriod->end_time, 0, 5) }}
                </p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Satu kelompok = satu kelas + beberapa ruangan + daftar mata pelajaran. Jadwal dibuat otomatis untuk semua kombinasi ruangan × mapel.</p>
            </div>
            <a href="{{ route('admin.exam-periods.show', $examPeriod) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
        </div>

        @include('admin.partials.flash')

        <form method="POST" action="{{ route('admin.exam-periods.groups.store', $examPeriod) }}" class="space-y-6">
            @csrf

            <template x-for="(name, index) in classes" :key="name">
                <input type="hidden" :name="'class_names[' + index + ']'" :value="name">
            </template>

            @error('subjects')
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-300">Tidak ada jadwal yang dibuat karena ada bentrok waktu:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-rose-700 dark:text-rose-400">
                        @foreach ($errors->get('subjects') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @enderror

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">1. Kelas <span class="text-xs font-normal text-gray-400">(<span x-text="classes.length"></span> dipilih)</span></h3>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" :checked="allClassNames.length > 0 && allClassNames.every((name) => classes.includes(name))" @change="toggleAllClasses()" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        Pilih Semua
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Siswa ditempatkan otomatis ke ruangan secara berurutan mengikuti urutan kelas yang dicentang, lalu alfabetis nama siswa.</p>
                <x-input-error :messages="$errors->get('class_names')" class="mt-2" />
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($classes as $class)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
                            <input type="checkbox" :checked="classes.includes(@js($class))" @change="toggleClass(@js($class))" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $class }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($classes->isEmpty())
                    <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">Belum ada siswa/kelas. Tambahkan siswa terlebih dahulu.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">2. Ruangan <span class="text-xs font-normal text-gray-400">(<span x-text="rooms.length"></span> dipilih)</span></h3>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" :checked="allRoomIds.length > 0 && allRoomIds.every((id) => rooms.includes(id))" @change="toggleAllRooms()" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        Pilih Semua
                    </label>
                </div>
                <x-input-error :messages="$errors->get('rooms')" class="mt-2" />
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($rooms as $room)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
                            <input type="checkbox" name="rooms[]" :value="{{ $room->id }}" :checked="rooms.includes({{ $room->id }})" @change="toggleRoom({{ $room->id }})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $room->name }}</span>
                            <span class="ml-auto text-xs text-gray-400 dark:text-gray-500">kap. {{ $room->capacity }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($rooms->isEmpty())
                    <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">Belum ada ruangan. Tambahkan ruangan di menu Ruangan terlebih dahulu.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">3. Mata Pelajaran & Waktu</h3>
                    <button type="button" @click="addSubject()" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Tambah Mapel
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Setiap mapel akan dibuat untuk semua ruangan yang dipilih.</p>

                <div class="mt-4 space-y-3">
                    <template x-for="(row, index) in subjects" :key="row._id">
                        <div class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-center dark:border-gray-700">
                            <div>
                                <select :name="'subjects[' + index + '][subject_id]'" x-model="row.subject_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                    <option value="">-- Pilih Mata Pelajaran --</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->code }})</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" x-text="subjectError(index, 'subject_id')"></p>
                            </div>
                            <div>
                                <input type="time" :name="'subjects[' + index + '][start_time]'" x-model="row.start_time" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" x-text="subjectError(index, 'start_time')"></p>
                            </div>
                            <div>
                                <input type="number" min="5" max="600" :name="'subjects[' + index + '][duration_minutes]'" x-model="row.duration_minutes" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" x-text="subjectError(index, 'duration_minutes')"></p>
                            </div>
                            <button type="button" @click="removeSubject(row._id)" :disabled="subjects.length <= 1" class="inline-flex items-center justify-center rounded-md bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
                                Hapus
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.exam-periods.show', $examPeriod) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    Buat Jadwal untuk Semua Ruangan
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
