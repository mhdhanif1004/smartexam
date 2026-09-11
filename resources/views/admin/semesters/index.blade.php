<x-layouts.admin title="Data Semester">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Data Semester</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola semester akademik dan tentukan semester aktif.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.semesters.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Semester
                </a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Semester</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($semesters as $index => $semester)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $semesters->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $semester->nama_lengkap }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <x-badge-status :status="$semester->is_active ? 'aktif' : 'nonaktif'" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        @if (! $semester->is_active)
                                            <form method="POST" action="{{ route('admin.semesters.make-active', $semester) }}" x-data
                                                  x-on:submit.prevent="if (confirm('Jadikan {{ $semester->nama_lengkap }} sebagai semester aktif? Ini akan mempengaruhi seluruh sistem.')) $el.submit()">
                                                @csrf
                                                <button type="submit" class="rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">Aktifkan</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.semesters.edit', $semester) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                                        <button type="button" @click="deleteUrl = '{{ route('admin.semesters.destroy', $semester) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada data semester.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $semesters->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>