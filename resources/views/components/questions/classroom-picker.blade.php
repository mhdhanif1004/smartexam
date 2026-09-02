@props([
    'mode' => 'all',
    'classrooms' => collect(),
    'classroomsBySubject' => [],
    'selected' => [],
    'description' => null,
    'bind' => null,
    'exclusiveNotes' => [],
    'exclusionsBySubject' => [],
])

@php
    $selectedIds = collect($selected)->map(fn ($id) => (int) $id)->values()->all();
    $defaultDescription = $mode === 'scoped'
        ? 'Pilih minimal satu kelas target untuk mapel terpilih. Kelas yang Anda pilih di sini menentukan cakupan kelas untuk Nilai & Absensi Ujian. Soal tanpa kelas target tidak akan muncul di ujian manapun.'
        : 'Pilih kelas yang berhak menerima soal ini. Wajib minimal satu kelas — soal tanpa kelas target tidak akan pernah muncul di ujian manapun.';
    $description = $description ?? $defaultDescription;

    $levelGroups = [];
    if ($mode === 'all') {
        foreach ($classrooms as $classroom) {
            $level = preg_match('/^[A-Z]+/', (string) $classroom->name, $matches) ? $matches[0] : 'Lainnya';
            $levelGroups[$level][] = ['id' => (int) $classroom->id, 'name' => (string) $classroom->name];
        }
    }

    // Untuk mode scoped: subyek => kelas (dengan level) agar UI bisa
    // dikelompokkan per tingkat secara reaktif di sisi klien.
    $scopedGroups = [];
    if ($mode === 'scoped') {
        foreach ($classroomsBySubject as $subjectId => $classrooms) {
            foreach ($classrooms as $classroom) {
                $level = preg_match('/^[A-Z]+/', (string) $classroom['name'], $matches) ? $matches[0] : 'Lainnya';
                $scopedGroups[$subjectId][] = [
                    'id' => (int) $classroom['id'],
                    'name' => (string) $classroom['name'],
                    'level' => $level,
                ];
            }
        }
    }
@endphp

@if ($mode === 'scoped')
    <div
        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        x-data="{
            subjects: @js($scopedGroups),
            groupedScoped() {
                return Object.groupBy ? Object.groupBy(this.subjects[this.subject] ?? [], c => c.level) : this.groupByPolyfill(this.subjects[this.subject] ?? []);
            },
            groupByPolyfill(items) {
                const map = {};
                items.forEach(c => {
                    (map[c.level] = map[c.level] || []).push(c);
                });
                return map;
            },
            groupLevels() {
                return Object.keys(this.groupedScoped() ?? {});
            },
            toggleLevel(level) {
                (this.groupedScoped()[level] ?? []).forEach(c => {
                    if (! this.selected.includes(c.id)) {
                        this.selected.push(c.id);
                    }
                });
            },
            clear() {
                this.selected.splice(0);
            },
        }"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kelas Target</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <template x-for="level in groupLevels()" :key="level">
                    <button type="button" @click="toggleLevel(level)" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/50 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                        Pilih Semua Tingkat<span x-text="' ' + level"></span>
                    </button>
                </template>
                <button type="button" @click="clear()" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Bersihkan</button>
            </div>
        </div>

        <div x-show="subject && subjects[subject]" class="mt-5">
            <template x-for="(items, level) in groupedScoped()" :key="level">
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="'Tingkat ' + level"></p>
                    <div class="mt-2 grid gap-6 sm:grid-cols-3">
                        <template x-for="classroom in items" :key="classroom.id">
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                <input type="checkbox" name="classroom_ids[]" :value="classroom.id" x-model="selected" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="classroom.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
            <p x-show="!subject" class="text-sm text-gray-400 dark:text-gray-500">Pilih mata pelajaran terlebih dahulu untuk melihat kelas target yang dapat dipilih.</p>
            <template x-if="subject && subjects[subject] && subjects[subject].length === 0">
                <p class="text-sm text-amber-600 dark:text-amber-400">Tidak ada kelas yang diampu untuk mapel terpilih.</p>
            </template>
        </div>

        <x-input-error :messages="$errors->get('classroom_ids')" class="mt-3" />
    </div>
@else
    @php
        // Bila $bind diisi (masuk ke mode 'all'), toggling & kliring dilakarkan
        // pada array Alpine eksternal (mis. importState.classroomIds untuk
        // modal import AJAX) alih-alih 'selected' internal. Tanpa $bind,
        // perilaku tetap memakai state 'selected' milik komponen sendiri.
        $bound = $bind !== null && $bind !== '';

        // Dua sumber data kelas-eksklusif:
        //  1. static $exclusiveNotes  → per-picker (halaman Kelola Mapel, satu
        //     picker per mapel; map classroom_id => nama guru pemilik).
        //  2. static $exclusionsBySubject → preload per-subject (halaman Tambah
        //     Guru Mapel; map subject_id => [classroom_id => nama guru pemilik]).
        //     Nilai "subject" di-resolve reaktif dari scope induk (X-model pada
        //     dropdown mapel) sehingga exclusion berubah otomatis saat ganti mapel.
        $preloaded = is_array($exclusionsBySubject) && count($exclusionsBySubject) > 0;
    @endphp
    <div
        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        x-data="{
            @if ($bound)
                target: {{ $bind }},
            @else
                target: @js($selectedIds),
            @endif
            groups: @js($levelGroups),
            notes: @js($exclusiveNotes),
            excl: @js($exclusionsBySubject),
            // Sumber data kelas-eksklusif berdasarkan mode server.
            // Metode bersama untuk noteFor() dan toggleLevel() — tidak ada lagi
            // penyuntikan ekspresi Blade dari PHP ke atribut Alpine.
            takenMapFor() {
                return @js($preloaded)
                    ? ((this.excl && this.excl[this.subject]) || {})
                    : ((this.notes && typeof this.notes === 'object') ? this.notes : {});
            },
            // Nama pemilik kelas yang diambil guru lain, atau string kosong bila
            // tidak ada. Dijamin TIDAK pernah mengembalikan/merender literal
            // undefined — string kosong membuat x-show sembunyi dan x-text tidak
            // menampilkan label.
            noteFor(id) {
                const map = this.takenMapFor();
                const holder = map ? map[id] : undefined;
                return typeof holder === 'string' && holder !== '' ? holder : '';
            },
            toggleLevel(ids) {
                const takenMap = this.takenMapFor();
                ids.forEach(id => {
                    if (! this.target.includes(id) && ! (takenMap || {})[id]) {
                        this.target.push(id);
                    }
                });
            },
            clear() {
                this.target.splice(0);
            },
        }"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kelas Target</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <template x-for="(items, level) in groups" :key="level">
                    <button type="button" @click="toggleLevel(items.map(item => item.id))" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/50 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                        Pilih Semua Tingkat<span x-text="' ' + level"></span>
                    </button>
                </template>
                <button type="button" @click="clear()" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Bersihkan</button>
            </div>
        </div>

        <div class="mt-5 grid gap-6 sm:grid-cols-3">
            <template x-for="(items, level) in groups" :key="level">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="'Tingkat ' + level"></p>
                    <div class="mt-2 space-y-2">
                        <template x-for="classroom in items" :key="classroom.id">
                            <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                <span class="flex items-center gap-3">
                                    <input type="checkbox" name="classroom_ids[]" :value="classroom.id" x-model="target" :disabled="noteFor(classroom.id) !== ''" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800" />
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="classroom.name"></span>
                                </span>
                                <span x-show="noteFor(classroom.id) !== ''" class="text-xs italic text-amber-600 dark:text-amber-400" x-text="'Sudah diampu ' + noteFor(classroom.id)"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <x-input-error :messages="$errors->get('classroom_ids')" class="mt-3" />
    </div>
@endif
