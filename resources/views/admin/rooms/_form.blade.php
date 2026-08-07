@php
    $isEdit = isset($room) && $room !== null;
    $action = $isEdit ? route('admin.rooms.update', $room) : route('admin.rooms.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $buttonLabel = $isEdit ? 'Perbarui' : 'Simpan';
    $currentRoomName = $isEdit ? $room->name : '';
    $assignedSet = array_flip($assigned->pluck('id')->all());
    $allStudents = $assigned->concat($available)
        ->sortBy(fn ($student) => $student->class_name.'|'.$student->nisn)
        ->values();
@endphp

<form id="room-form" method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @include('admin.partials.flash')

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Informasi Ruangan</h3>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Nama Ruangan')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $isEdit ? $room->name : '') }}" required placeholder="contoh: Ruang 1" autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="capacity" :value="__('Kapasitas')" />
                <x-text-input id="capacity" name="capacity" type="number" min="0" readonly class="mt-1 block w-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400" value="{{ old('capacity', count($assignedSet)) }}" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Terisi otomatis sesuai jumlah siswa yang dicentang di bawah.</p>
                <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pengawas Ruangan</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Satu pengawas untuk satu ruangan. Pengawas yang sedang bertugas di ruangan lain akan dipindahkan ke ruangan ini.</p>

        <div class="mt-4 max-w-xl">
            <label for="supervisor_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Pengawas</label>
            <select name="supervisor_id" id="supervisor_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                <option value="">-- Pilih Pengawas --</option>
                @foreach ($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}" data-room-name="{{ $supervisor->room?->name ?? '' }}" @selected(old('supervisor_id', $currentSupervisorId) == $supervisor->id)>
                        {{ $supervisor->user?->name }}@if ($supervisor->room?->name) (sekarang di {{ $supervisor->room->name }})@endif
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supervisor_id')" class="mt-2" />
        </div>

        <div id="supervisor-warning" data-current-room="{{ $currentRoomName }}" class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
            <span class="supervisor-warning-text"></span>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Penempatan Siswa</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pilih siswa yang menjadi peserta <strong>tetap</strong> ruangan ini untuk seluruh masa ujian.
                    Siswa yang sudah ditempatkan di ruangan lain tidak ditampilkan.
                </p>
            </div>
            <div class="shrink-0 rounded-lg bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                <span id="student-count">0</span> siswa dipilih
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <div class="sm:w-56">
                <label for="class-filter" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Filter Kelas</label>
                <select id="class-filter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">Semua Kelas</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class }}">{{ $class }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label for="student-search" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cari Nama / NISN</label>
                <input type="search" id="student-search" placeholder="contoh: Budi atau 1234567890..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            </div>
        </div>

        @if ($allStudents->isEmpty())
            <p class="mt-4 rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                Tidak ada siswa yang bisa ditempatkan. Semua siswa sudah punya ruangan.
            </p>
        @else
            <div id="student-checklist" class="mt-4 grid grid-cols-1 gap-1.5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($allStudents as $student)
                    <label class="student-item flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm transition hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10 {{ isset($assignedSet[$student->id]) ? 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/40 dark:bg-indigo-500/10' : '' }}"
                           data-class="{{ $student->class_name }}"
                           data-name="{{ mb_strtolower($student->user?->name ?? '') }}"
                           data-nisn="{{ $student->nisn }}">
                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" {{ isset($assignedSet[$student->id]) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $student->nisn }} &middot; {{ $student->class_name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                Untuk memindahkan siswa dari ruangan lain: buka Edit ruangan asalnya, kosongkan centangnya, simpan, baru siswa tersebut muncul sebagai pilihan di ruangan manapun.
            </p>
        @endif

        <x-input-error :messages="$errors->get('student_ids')" class="mt-2" />

        <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Siswa yang tidak dicentang lagi akan dilepas dari ruangan ini.</p>
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>{{ $buttonLabel }}</x-primary-button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const checklist = document.getElementById('student-checklist');
        const countEl = document.getElementById('student-count');
        const capacityEl = document.getElementById('capacity');
        const classFilter = document.getElementById('class-filter');
        const searchInput = document.getElementById('student-search');
        const supervisorSelect = document.getElementById('supervisor_id');
        const warningPanel = document.getElementById('supervisor-warning');
        const warningText = warningPanel.querySelector('.supervisor-warning-text');
        const currentRoomName = warningPanel.dataset.currentRoom;

        function updateCount() {
            const count = checklist ? checklist.querySelectorAll('input[type="checkbox"]:checked').length : 0;
            countEl.textContent = count;
            capacityEl.value = count;
        }

        function applyFilters() {
            const selectedClass = classFilter.value;
            const search = searchInput.value.trim().toLowerCase();
            checklist.querySelectorAll('.student-item').forEach(function (item) {
                const matchClass = !selectedClass || item.dataset.class === selectedClass;
                const matchSearch = !search
                    || item.dataset.name.includes(search)
                    || item.dataset.nisn.includes(search);
                item.style.display = (matchClass && matchSearch) ? '' : 'none';
            });
        }

        function selectedSupervisorOrigin() {
            const option = supervisorSelect.options[supervisorSelect.selectedIndex];
            return option && option.dataset.roomName ? option.dataset.roomName : '';
        }

        function updateSupervisorWarning() {
            const origin = selectedSupervisorOrigin();
            const moving = origin !== '' && origin !== currentRoomName;
            warningText.textContent = moving
                ? 'Pengawas ini sedang bertugas di Ruang ' + origin + '. Dia akan dipindahkan ke ruangan ini.'
                : '';
            warningPanel.classList.toggle('hidden', !moving);
            return moving;
        }

        if (checklist) {
            checklist.addEventListener('change', updateCount);
        }
        classFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        supervisorSelect.addEventListener('change', updateSupervisorWarning);
        document.getElementById('room-form').addEventListener('submit', function (event) {
            updateCount();
            if (updateSupervisorWarning()) {
                const origin = selectedSupervisorOrigin();
                if (!confirm('Pengawas ini sedang bertugas di Ruang ' + origin + '. Pindahkan dia ke ruangan ini?')) {
                    event.preventDefault();
                }
            }
        });

        updateCount();
        applyFilters();
        updateSupervisorWarning();
    })();
</script>
@endpush
