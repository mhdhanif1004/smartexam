<x-layouts.admin title="Edit Siswa">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Edit Siswa</h2>
            <p class="mt-1 text-sm text-gray-500">Perbarui data siswa dan akun penggunanya.</p>
        </div>

        <form method="POST" action="{{ route('admin.students.update', $student) }}" class="max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Akun Pengguna</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" :value="__('Nama Lengkap')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $student->user?->name) }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="username" :value="__('Username (ID Login)')" />
                        <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" value="{{ old('username', $student->user?->username) }}" readonly />
                        <x-input-error :messages="$errors->get('username')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password" :value="__('Password Baru')" />
                        <div class="mt-1 flex gap-2">
                            <div class="relative flex-1">
                                <x-text-input id="password" name="password" type="password" class="block w-full pr-10" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti" />
                                <x-password-toggle />
                            </div>
                            <button type="button" data-password-generator data-target="password" data-confirmation="password_confirmation" class="shrink-0 rounded-md bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Generate Otomatis</button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900">Data Akademik</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="nisn" :value="__('NISN')" />
                        <x-text-input id="nisn" name="nisn" type="text" class="mt-1 block w-full" value="{{ old('nisn', $student->nisn) }}" required placeholder="10 digit angka" />
                        <x-input-error :messages="$errors->get('nisn')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="class_name" :value="__('Kelas')" />
                        <select id="class_name" name="class_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->name }}" @selected(old('class_name', $student->class_name) === $class->name)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
                    </div>
                </div>
                <label class="mt-5 flex w-fit items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $student->user?->is_active)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Akun aktif</span>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.students.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>

    @include('admin.partials.credential-tools')
</x-layouts.admin>
