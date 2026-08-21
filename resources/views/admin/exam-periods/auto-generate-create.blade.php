<x-layouts.admin title="Buat Sesi Otomatis">
    @php
        $initialSubjects = collect(old('subjects', []))->map(fn ($row) => [
            'subject_id' => (string) ($row['subject_id'] ?? ''),
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
            defaultDurations: @js($subjects->pluck('default_duration_minutes', 'id')->map(fn ($d) => (int) $d)->all()),
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
                this.subjects.push({ _id: this._seq++, subject_id: '', duration_minutes: '60' });
            },
            removeSubject(id) {
                if (this.subjects.length <= 1) return;
                this.subjects.splice(this.subjects.findIndex((row) => row._id === id), 1);
            },
            subjectChanged(row, event) {
                row.subject_id = event.target.value;
                const duration = this.defaultDurations[event.target.value];
                if (duration) row.duration_minutes = String(duration);
            },
            subjectError(index, field) {
                return this.errors['subjects.' + index + '.' + field] || '';
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Buat Sesi Otomatis</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi satu form ini, sistem menghitung sendiri berapa gelombang sesi yang dibutuhkan sampai semua siswa terpilih tertampung.</p>
            </div>
            <a href="{{ route('admin.exam-periods.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
        </div>

        @include('admin.partials.flash')

        <form method="POST" action="{{ route('admin.exam-periods.auto-generate.store') }}" class="space-y-6">
            @csrf

            <template x-for="(name, index) in classes" :key="name">
                <input type="hidden" :name="'class_names[' + index + ']'" :value="name">
            </template>

            @error('subjects')
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-300">Tidak ada sesi yang dibuat karena ada bentrok waktu:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-rose-700 dark:text-rose-400">
                        @foreach ($errors->get('subjects') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @enderror

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Nama Ujian')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" placeholder="contoh: UAS Ganjil Kelas 12" required />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Dipakai sebagai prefix nama sesi: "{{ old('name', 'UAS Ganjil Kelas 12') }} - Sesi 1", dst.</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="exam_date" :value="__('Tanggal Ujian')" />
                        <x-text-input id="exam_date" name="exam_date" type="date" class="mt-1 block w-full" value="{{ old('exam_date') }}" required />
                        <x-input-error :messages="$errors->get('exam_date')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">1. Kelas <span class="text-xs font-normal text-gray-400">(<span x-text="classes.length"></span> dipilih)</span></h3>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" :checked="allClassNames.length > 0 && allClassNames.every((name) => classes.includes(name))" @change="toggleAllClasses()" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        Pilih Semua
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Siswa ditempatkan otomatis secara berurutan mengikuti urutan kelas yang dicentang, lalu alfabetis nama siswa di dalam tiap kelas.</p>
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
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Semua gelombang sesi memakai ruangan-ruangan yang sama; kapasitas dijumlahkan untuk menghitung jumlah sesi.</p>
                <x-input-error :messages="$errors->get('rooms')" class="mt-2" />
<div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($rooms as $room)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-300 dark:hover:bg-indigo-500/10">
                            <input type="checkbox" name="rooms[]" :value="{{ $room->id }}" :checked="rooms.includes({{ $room->id }})" @change="toggleRoom({{ $room->id }})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                            <div class="min-w-0 flex-1">
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $room->display_name }}</span>
                                <p class="text-xs text-gray-400 dark:text-gray-500">kap. {{ $room->capacity }} &middot; Maks. {{ $room->supervisor_count }} pengawas</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @if ($rooms->isEmpty())
                    <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">Belum ada ruangan. Tambahkan ruangan di menu Ruangan terlebih dahulu.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">3. Mata Pelajaran & Durasi</h3>
                    <button type="button" @click="addSubject()" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Tambah Mapel
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Urutan baris = urutan mapel diujikan (back-to-back tanpa jeda). Durasi terisi otomatis dari mapel, tapi bisa diubah manual. Semua mapel diujikan di semua ruangan terpilih.</p>

                <div class="mt-4 space-y-3">
                    <template x-for="(row, index) in subjects" :key="row._id">
                        <div class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-center dark:border-gray-700">
                            <div>
                                <select :name="'subjects[' + index + '][subject_id]'" x-model="row.subject_id" @change="subjectChanged(row, $event)" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                    <option value="">-- Pilih Mata Pelajaran --</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->code }})</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" x-text="subjectError(index, 'subject_id')"></p>
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

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="start_time" :value="__('Jam Mulai Sesi Pertama')" />
                        <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full" value="{{ old('start_time', '07:30') }}" required />
                        <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gap_minutes" :value="__('Jeda Antar Sesi (menit)')" />
                        <x-text-input id="gap_minutes" name="gap_minutes" type="number" min="0" max="600" class="mt-1 block w-full" value="{{ old('gap_minutes', 15) }}" required />
                        <x-input-error :messages="$errors->get('gap_minutes')" class="mt-2" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Waktu tiap sesi dihitung otomatis: sesi berikutnya dimulai setelah sesi sebelumnya selesai + jeda ini.</p>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.exam-periods.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    Generate Semua Sesi Sekaligus
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
