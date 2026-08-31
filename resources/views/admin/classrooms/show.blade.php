<x-layouts.admin :title="'Detail Kelas - '.$classroom->name">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Detail Kelas</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informasi kelas {{ $classroom->name }} beserta mapel, guru pengampu, dan siswa.</p>
            </div>
            <a href="{{ route('admin.classrooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Kembali</a>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Nama Kelas</dt>
                    <dd class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $classroom->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Jumlah Siswa</dt>
                    <dd class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $students->count() }} siswa</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Mapel Pengampu</dt>
                    <dd class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $assignments->pluck('subject.id')->filter()->unique()->count() }} mapel</dd>
                </div>
            </dl>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Mata Pelajaran &amp; Guru Pengampu</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Guru yang membuat soal untuk kelas ini (kelas target soal). Bersifat read-only.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Guru Pengampu</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">NIP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($assignments as $index => $assignment)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment['subject']?->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment['guru']?->user?->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment['guru']?->nip ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada guru yang membuat soal untuk kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Daftar Siswa</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $students->count() }} siswa terdaftar pada kelas ini.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">NISN</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nama</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($students as $index => $student)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $student->nisn }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada siswa pada kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
