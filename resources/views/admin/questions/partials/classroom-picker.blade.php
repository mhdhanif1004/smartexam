@php
    $selectedIds = array_map('intval', $selected ?? []);
    $groups = [];
    foreach ($classrooms as $classroom) {
        $level = preg_match('/^[A-Z]+/', (string) $classroom->name, $matches) ? $matches[0] : 'Lainnya';
        $groups[$level][] = ['id' => (int) $classroom->id, 'name' => (string) $classroom->name];
    }
    $bindTarget = $bind ?? null;
    $description = $description ?? 'Pilih kelas yang berhak menerima soal ini. Wajib minimal satu kelas — soal tanpa kelas target tidak akan pernah muncul di ujian manapun.';
@endphp

<div
    @if ($bindTarget)
        x-data="{
            groups: @js($groups),
            toggleLevel(ids) {
                ids.forEach(id => {
                    if (! {{ $bindTarget }}.includes(id)) {
                        {{ $bindTarget }}.push(id);
                    }
                });
            },
            clear() {
                {{ $bindTarget }}.splice(0);
            },
        }"
    @else
        x-data="{
            selected: @js($selectedIds),
            groups: @js($groups),
            toggleLevel(ids) {
                ids.forEach(id => {
                    if (! this.selected.includes(id)) {
                        this.selected.push(id);
                    }
                });
            },
            clear() {
                this.selected = [];
            },
        }"
    @endif
    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kelas Target</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="(items, level) in groups" :key="level">
                <button type="button" @click="toggleLevel(ids = items.map(item => item.id))" class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/50 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                    Pilih Semua Tingkat<span x-text="' ' + level"></span>
                </button>
            </template>
            <button type="button" @click="clear()" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                Bersihkan
            </button>
        </div>
    </div>

    <div class="mt-5 grid gap-6 sm:grid-cols-3">
        <template x-for="(items, level) in groups" :key="level">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="'Tingkat ' + level"></p>
                <div class="mt-2 space-y-2">
                    <template x-for="classroom in items" :key="classroom.id">
                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <input type="checkbox" name="classroom_ids[]" :value="classroom.id" x-model="{{ $bindTarget ?? 'selected' }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" />
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="classroom.name"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <x-input-error :messages="$errors->get('classroom_ids')" class="mt-3" />
</div>
