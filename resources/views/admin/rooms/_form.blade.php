@php
    $isEdit = isset($room) && $room !== null;
    $action = $isEdit ? route('admin.rooms.update', $room) : route('admin.rooms.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $buttonLabel = $isEdit ? 'Perbarui' : 'Simpan';
    $maxSupervisors = \App\Models\ExamSetting::maxSupervisorsPerRoom();
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
                <x-input-label for="room_number" :value="__('Nomor Ruangan')" />
                <x-text-input id="room_number" name="room_number" type="number" min="1" max="99999" class="mt-1 block w-full" value="{{ old('room_number', $isEdit ? $room->room_number : ($nextRoomNumber ?? 1)) }}" required autofocus />
                <x-input-error :messages="$errors->get('room_number')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="capacity" :value="__('Kapasitas Murid')" />
                <x-text-input id="capacity" name="capacity" type="number" min="1" max="1000" class="mt-1 block w-full" value="{{ old('capacity', $isEdit ? $room->capacity : '') }}" required placeholder="contoh: 20" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Jumlah kursi yang tersedia di ruangan ini.</p>
                <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="supervisor_count" :value="__('Maksimal Pengawas')" />
                <select id="supervisor_count" name="supervisor_count" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    @for ($i = 1; $i <= $maxSupervisors; $i++)
                        <option value="{{ $i }}" {{ old('supervisor_count', $isEdit ? $room->supervisor_count : 1) == $i ? 'selected' : '' }}>{{ $i }} pengawas</option>
                    @endfor
                </select>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Berapa pengawas yang wajib ditugaskan ke ruangan ini pada setiap hari ujian (rotasi).</p>
                <x-input-error :messages="$errors->get('supervisor_count')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
        <x-primary-button>{{ $buttonLabel }}</x-primary-button>
    </div>
</form>
