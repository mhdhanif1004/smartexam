<x-layouts.admin title="Tahun Ajaran">
    <div x-data="{ deleteUrl: '' }" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tahun Ajaran</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola tahun ajaran akademik. Tahun ajaran dibuat lebih dulu, lalu semester (Ganjil/Genap) di bawahnya.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.academic-years.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Tahun Ajaran
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
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Periode</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah Semester</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($academicYears as $index => $academicYear)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $academicYears->firstItem() + $index }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $academicYear->nama }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    @if ($academicYear->tanggal_mulai || $academicYear->tanggal_selesai)
                                        {{ $academicYear->tanggal_mulai?->translatedFormat('d M Y') ?? '-' }}
                                        s.d.
                                        {{ $academicYear->tanggal_selesai?->translatedFormat('d M Y') ?? '-' }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">{{ $academicYear->semesters_count }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @php
                                        $hasActiveSemester = $academicYear->semesters_count > 0;
                                    @endphp
                                    <x-badge-status :status="$hasActiveSemester ? 'aktif' : 'nonaktif'" label="{{ $hasActiveSemester ? 'Sudah ada semester' : 'Belum ada semester' }}" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.academic-years.edit', $academicYear) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Edit</a>
                                        <button type="button" @click="deleteUrl = '{{ route('admin.academic-years.destroy', $academicYear) }}'; $dispatch('open-modal', 'confirm-delete')" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada data tahun ajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $academicYears->links() }}</div>

        @include('admin.partials.delete-modal')
    </div>
</x-layouts.admin>