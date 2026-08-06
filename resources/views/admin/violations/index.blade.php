<x-layouts.admin title="Riwayat Pelanggaran">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Riwayat Pelanggaran</h2>
            <p class="mt-1 text-sm text-gray-500">Daftar pelanggaran peserta selama ujian berlangsung.</p>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.violations.index') }}" class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="date_from" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Selesai</label>
                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="room_id" class="mb-1 block text-sm font-medium text-gray-700">Ruangan</label>
                <select name="room_id" id="room_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Semua Ruangan</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room_id'] === $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="violation_type" class="mb-1 block text-sm font-medium text-gray-700">Jenis Pelanggaran</label>
                <select name="violation_type" id="violation_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Semua Jenis</option>
                    @foreach ($violationTypes as $type)
                        <option value="{{ $type }}" @selected($filters['violation_type'] === $type)>{{ \App\Models\Violation::typeLabel($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Terapkan</button>
                @if ($filters['date_from'] || $filters['date_to'] || $filters['room_id'] || $filters['violation_type'])
                    <a href="{{ route('admin.violations.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                @endif
            </div>
        </form>

        <x-table :headers="['No', 'Waktu', 'Siswa', 'Kelas', 'Ruangan', 'Mata Pelajaran', 'Jenis Pelanggaran', 'Checklist Aktif', 'Hentikan Paksa', 'Dilaporkan Oleh']">
            @forelse ($violations as $index => $violation)
                @php
                    $student = $violation->examSession?->student;
                    $schedule = $violation->examSession?->examSchedule;
                    $session = $violation->examSession;
                @endphp
                <tr class="transition hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $violations->firstItem() + $index }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $violation->occurred_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                        {{ $student?->user?->name ?? '-' }}
                        <span class="block text-xs font-normal text-gray-500">{{ $student?->nisn ?? '' }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $student?->class_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule?->room?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule?->subject?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <x-badge-status :status="'dilaporkan'" />
                        {{ \App\Models\Violation::typeLabel($violation->violation_type) }}
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-0.5">
                                <span class="inline-block h-3 w-3 rounded-sm {{ $session?->violation_flag_1 ? 'bg-rose-500' : 'bg-gray-200' }}"></span>
                                <span class="inline-block h-3 w-3 rounded-sm {{ $session?->violation_flag_2 ? 'bg-rose-500' : 'bg-gray-200' }}"></span>
                                <span class="inline-block h-3 w-3 rounded-sm {{ $session?->violation_flag_3 ? 'bg-rose-500' : 'bg-gray-200' }}"></span>
                            </span>
                            <span class="text-xs font-semibold {{ ($session?->activeViolationFlags() ?? 0) > 0 ? 'text-rose-600' : 'text-gray-500' }}">
                                {{ $session?->activeViolationFlags() ?? 0 }} dari 3
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm"
                        x-data="{
                            locked: @js((bool) ($session?->locked_by_admin ?? false)),
                            busy: false,
                            async toggle() {
                                if (this.busy) return;
                                this.busy = true;
                                try {
                                    const res = await fetch(@js($session ? route('admin.violations.lock', $session->id) : '#'), {
                                        method: 'PATCH',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': @js(csrf_token()),
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                        body: JSON.stringify({ locked: !this.locked }),
                                    });
                                    if (res.ok) {
                                        this.locked = (await res.json()).locked;
                                    } else {
                                        alert('Gagal mengubah status kunci.');
                                    }
                                } finally {
                                    this.busy = false;
                                }
                            }
                        }">
                        <button type="button" @click="toggle()" :disabled="busy"
                                :class="locked ? 'bg-rose-600 text-white shadow-sm' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                                class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50">
                            <span class="relative inline-flex h-4 w-8 items-center rounded-full transition" :class="locked ? 'bg-rose-500' : 'bg-gray-300'">
                                <span class="inline-block h-3 w-3 transform rounded-full bg-white transition" :class="locked ? 'translate-x-4' : 'translate-x-0.5'"></span>
                            </span>
                            <span x-text="locked ? 'Dikunci' : 'Aktif'"></span>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $violation->reportedBy?->name ?? '-' }}</td>
                </tr>
            @empty
            @endforelse
        </x-table>

        <div>{{ $violations->links() }}</div>
    </div>
</x-layouts.admin>
