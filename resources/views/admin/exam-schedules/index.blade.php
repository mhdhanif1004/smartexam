<x-layouts.admin title="Jadwal Ujian">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Jadwal Ujian</h2>
                <p class="mt-1 text-sm text-gray-500">Kelola jadwal pelaksanaan ujian setiap kelas dan mata pelajaran.</p>
            </div>
            <a href="{{ route('admin.exam-schedules.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jadwal
            </a>
        </div>

        @include('admin.partials.flash')

        <form method="GET" action="{{ route('admin.exam-schedules.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex-1">
                <x-text-input type="search" name="search" value="{{ request('search') }}" placeholder="Cari mata pelajaran, kelas, ruangan, atau tanggal..." class="block w-full" />
            </div>
            <div>
                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-auto">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('status'))
                    <a href="{{ route('admin.exam-schedules.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Reset</a>
                @endif
            </div>
        </form>

        <x-table :headers="['No', 'Tanggal', 'Mata Pelajaran', 'Kelas', 'Ruangan', 'Waktu', 'Durasi', 'Status', 'Aksi']">
            @forelse ($schedules as $index => $schedule)
                <tr class="transition hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $schedules->firstItem() + $index }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $schedule->exam_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule->subject?->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule->class_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule->room?->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ \Illuminate\Support\Str::substr($schedule->start_time, 0, 5) }} - {{ \Illuminate\Support\Str::substr($schedule->end_time, 0, 5) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $schedule->duration_minutes }} menit</td>
                    <td class="px-4 py-3 text-sm">
                        <x-badge-status :status="$schedule->status" />
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.exam-schedules.edit', $schedule) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Edit</a>
                            <button type="button" @click="deleteUrl = '{{ route('admin.exam-schedules.destroy', $schedule) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Hapus</button>
                        </div>
                    </td>
                </tr>
            @empty
            @endforelse
        </x-table>

        <div>{{ $schedules->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>
