<x-layouts.admin title="Edit Wali Kelas">
    <div class="max-w-3xl space-y-6">
        <div>
            <a href="{{ route('admin.wali-kelas.index') }}" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-500 dark:text-indigo-400">&larr; Kembali ke data wali kelas</a>
            <h2 class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">Edit Wali Kelas</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ubah profil atau pindahkan wali kelas ke kelas lain (hanya kelas yang tidak terpakai yang tersedia).</p>
        </div>

        <form method="POST" action="{{ route('admin.wali-kelas.update', $waliKelas) }}" class="space-y-6 overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Nama Lengkap" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $waliKelas->user?->name) }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $waliKelas->user?->email) }}" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="classroom_id" value="Kelas yang Diampu" />
                    <select id="classroom_id" name="classroom_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                        @foreach ($classrooms as $classroom)
                            <option
                                value="{{ $classroom->id }}"
                                {{ old('classroom_id', $waliKelas->classroom_id) == $classroom->id ? 'selected' : '' }}
                                {{ $classroom->wali_kelas_exists && $classroom->id !== $waliKelas->classroom_id ? 'disabled' : '' }}
                            >
                                {{ $classroom->name }}{{ $classroom->wali_kelas_exists && $classroom->id !== $waliKelas->classroom_id ? ' (sudah ada wali kelas)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('classroom_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" value="Password Baru" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" placeholder="Kosongkan jika tidak diubah" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Konfirmasi Password Baru" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800" {{ old('is_active', $waliKelas->user?->is_active) ? 'checked' : '' }}>
                <x-input-label for="is_active" value="Akun aktif (bisa login)" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-5 dark:border-gray-800">
                <a href="{{ route('admin.wali-kelas.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</x-layouts.admin>