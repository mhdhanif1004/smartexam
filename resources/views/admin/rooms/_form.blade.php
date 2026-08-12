@php
    $isEdit = isset($room) && $room !== null;
    $action = $isEdit ? route('admin.rooms.update', $room) : route('admin.rooms.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $buttonLabel = $isEdit ? 'Perbarui' : 'Simpan';
    $currentRoomName = $isEdit ? $room->name : '';
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
                <x-text-input id="capacity" name="capacity" type="number" min="0" max="1000" class="mt-1 block w-full" value="{{ old('capacity', $isEdit ? $room->capacity : '') }}" required placeholder="contoh: 20" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Jumlah kursi yang tersedia di ruangan ini.</p>
                <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pengawas Ruangan</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bisa memilih lebih dari satu pengawas. Pengawas yang sedang bertugas di ruangan lain akan dipindahkan ke ruangan ini.</p>

        <p class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Pengawas</p>

        <div class="mt-2 sm:w-72">
            <label for="supervisor-search" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cari Pengawas</label>
            <input type="search" id="supervisor-search" placeholder="Cari nama pengawas..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
        </div>

        <div class="mt-2 grid grid-cols-1 gap-1.5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($supervisors as $supervisor)
                <label class="supervisor-item flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm transition hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10 {{ in_array($supervisor->id, $currentSupervisorIds) ? 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/40 dark:bg-indigo-500/10' : '' }}"
                       data-name="{{ mb_strtolower($supervisor->user?->name ?? '') }}">
                    <input type="checkbox" name="supervisor_ids[]" value="{{ $supervisor->id }}" data-room-name="{{ $supervisor->room?->name ?? '' }}" {{ in_array($supervisor->id, $currentSupervisorIds) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="min-w-0">
                        <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $supervisor->user?->name }}</span>
                        @if ($supervisor->room?->name)
                            <span class="block text-xs text-gray-500 dark:text-gray-400">sekarang di {{ $supervisor->room->name }}</span>
                        @else
                            <span class="block text-xs text-gray-400 dark:text-gray-500">belum ditempatkan</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">Belum ada pengawas terdaftar. Tambahkan pengawas terlebih dahulu.</p>
            @endforelse
        </div>
        <x-input-error :messages="$errors->get('supervisor_ids')" class="mt-2" />

        <div id="supervisor-warning" data-current-room="{{ $currentRoomName }}" class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
            <span class="supervisor-warning-text"></span>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
        <x-primary-button>{{ $buttonLabel }}</x-primary-button>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const supervisorSearch = document.getElementById('supervisor-search');
        const supervisorItems = document.querySelectorAll('.supervisor-item');
        const supervisorCheckboxes = document.querySelectorAll('input[name="supervisor_ids[]"]');
        const warningPanel = document.getElementById('supervisor-warning');
        const warningText = warningPanel.querySelector('.supervisor-warning-text');
        const currentRoomName = warningPanel.dataset.currentRoom;

        function filterSupervisors() {
            const search = supervisorSearch.value.trim().toLowerCase();
            supervisorItems.forEach(function (item) {
                const matchSearch = !search || item.dataset.name.includes(search);
                item.style.display = matchSearch ? '' : 'none';
            });
        }

        function movingSupervisorOrigins() {
            const origins = [];
            supervisorCheckboxes.forEach(function (checkbox) {
                if (checkbox.checked && checkbox.dataset.roomName && checkbox.dataset.roomName !== currentRoomName) {
                    origins.push(checkbox.dataset.roomName);
                }
            });
            return origins;
        }

        function updateSupervisorWarning() {
            const origins = movingSupervisorOrigins();
            if (origins.length > 0) {
                warningText.textContent = 'Pengawas yang sedang bertugas di ruangan lain akan dipindahkan ke ruangan ini: ' + origins.join(', ');
                warningPanel.classList.remove('hidden');
                return true;
            }
            warningText.textContent = '';
            warningPanel.classList.add('hidden');
            return false;
        }

        supervisorSearch.addEventListener('input', filterSupervisors);
        supervisorCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSupervisorWarning);
        });
        document.getElementById('room-form').addEventListener('submit', function (event) {
            if (updateSupervisorWarning()) {
                if (!confirm('Beberapa pengawas sedang bertugas di ruangan lain dan akan dipindahkan ke ruangan ini. Lanjutkan?')) {
                    event.preventDefault();
                }
            }
        });

        filterSupervisors();
        updateSupervisorWarning();
    })();
</script>
@endpush
