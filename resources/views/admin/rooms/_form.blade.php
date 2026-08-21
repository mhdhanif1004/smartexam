@php
    $isEdit = isset($room) && $room !== null;
    $action = $isEdit ? route('admin.rooms.update', $room) : route('admin.rooms.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $buttonLabel = $isEdit ? 'Perbarui' : 'Simpan';
    $maxSupervisors = 10;
    $currentRoomId = $isEdit ? $room->id : null;

    // Gabungan: (a) belum ditugaskan ke mana pun + (b) sudah ditugaskan ke ruangan ini
    $availableSupervisors = \App\Models\Supervisor::query()
        ->where(function ($query) use ($currentRoomId) {
            $query->whereNull('room_id')
                ->orWhere('room_id', $currentRoomId);
        })
        ->whereHas('user', fn ($query) => $query->where('is_active', true))
        ->get();
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

<div x-data="{ open: false }" class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:bg-gray-900">
    <button @click="open = !open" type="button" class="flex w-full items-center justify-between py-2 text-left">
        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Daftar Pengawas</span>
        <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition x-cloak class="pt-3">
        @if ($isEdit && $currentRoomId)
            @php
                $assignedIds = \App\Models\Supervisor::where('room_id', $currentRoomId)->pluck('id')->toArray();
            @endphp
        @else
            @php $assignedIds = []; @endphp
        @endif

        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
            @if ($isEdit && count($assignedIds) > 0)
                Centang untuk assign, centang batal untuk unassign. Pengawas tercentang saat ini sudah ditugaskan ke ruangan ini.
            @else
                Centang pengawas yang ingin ditugaskan ke ruangan ini:
            @endif
        </p>
        @if (count($availableSupervisors) > 0)
            <div class="grid gap-1 sm:grid-cols-2">
                @foreach ($availableSupervisors as $supervisor)
                    <label class="flex items-center gap-2 rounded-md px-2 py-1 text-sm transition hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input type="checkbox" name="assign_supervisor_ids[]" value="{{ $supervisor->id }}" {{ in_array($supervisor->id, $assignedIds) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        <span class="text-gray-700 dark:text-gray-200">{{ $supervisor->user?->name }}</span>
                        @if (in_array($supervisor->id, $assignedIds))
                            <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">terassign</span>
                        @endif
                    </label>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Semua pengawas sudah ditugaskan ke ruangan lain.</p>
        @endif
    </div>
</div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.rooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
        <x-primary-button>{{ $buttonLabel }}</x-primary-button>
    </div>
</form>
