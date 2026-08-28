<x-layouts.admin :title="'Penugasan Guru Mapel - '.($guruMapel->user?->name ?? 'Guru Mapel')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Kelola Penugasan Mengajar</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $guruMapel->user?->name }} &middot; tambah atau hapus mapel-kelas yang diampu guru.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.guru-mapels.show', $guruMapel) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Kembali</a>
            </div>
        </div>

        @include('admin.partials.flash')

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Kelola Penugasan per Mapel</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih mapel, lalu centang kelas yang diampu guru ini. Simpan sekali untuk semua kelas sekaligus.</p>

            <form
                method="POST"
                action="{{ route('admin.guru-mapels.assignments.store', $guruMapel) }}"
                class="mt-5 space-y-6"
                x-data="{
                    subject: '',
                    classroomIds: [],
                    bySubject: @js($classroomIdsBySubject),
                    setClassrooms() {
                        this.classroomIds = [...(this.bySubject[this.subject] || [])];
                    },
                }"
            >
                @csrf

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/40">
                    <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                    <select id="subject_id" name="subject_id" x-model="subject" @change="setClassrooms()" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">-- Pilih Mapel --</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                </div>

                @include('admin.questions.partials.classroom-picker', [
                    'classrooms' => $classrooms,
                    'bindTarget' => 'classroomIds',
                    'description' => 'Centang kelas yang diampu guru ini pada mapel terpilih. Kelas yang sudah ter-assign otomatis tercentang — hapus centang untuk melepas dan tambah centang untuk menambah, lalu simpan sekaligus.',
                ])

                <div class="flex items-center justify-end gap-3">
                    <p x-show="subject" class="mr-auto text-xs text-gray-500 dark:text-gray-400" x-text="'Terpilih: ' + classroomIds.length + ' kelas'"></p>
                    <x-primary-button>Simpan Penugasan</x-primary-button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Daftar Penugasan</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Mapel-kelas yang sedang diampu guru ini.</p>
                </div>
                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $assignments->count() }} penugasan</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">No</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mata Pelajaran</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kelas</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($assignments as $index => $assignment)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $assignment->subject?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $assignment->classroom?->name }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <form method="POST" action="{{ route('admin.guru-mapels.assignments.destroy', $assignment) }}" onsubmit="return confirm('Hapus penugasan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada penugasan mapel-kelas untuk guru ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
