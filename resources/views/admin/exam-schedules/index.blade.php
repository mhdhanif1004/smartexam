<x-layouts.admin title="Jadwal Ujian">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Jadwal Ujian</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola jadwal pelaksanaan ujian setiap kelas dan mata pelajaran.</p>
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
                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 lg:w-auto">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">Cari</button>
                @if (request('search') || request('status'))
                    <a href="{{ route('admin.exam-schedules.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah Jadwal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($dates as $index => $date)
                            @php
                                $filters = array_filter([
                                    'search' => request('search'),
                                    'status' => request('status'),
                                ], fn ($value) => filled($value));
                                $dateLink = route('admin.exam-schedules.by-date', array_merge(['date' => $date->exam_date->format('Y-m-d')], $filters));
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $dates->firstItem() + $index }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $dateLink }}" class="text-sm font-semibold text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">
                                        {{ $date->exam_date->locale('id')->translatedFormat('l, d F Y') }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $date->total }} jadwal</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ $dateLink }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Lihat Jadwal</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada jadwal ujian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $dates->links() }}</div>
    </div>
</x-layouts.admin>
