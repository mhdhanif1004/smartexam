<x-layouts.admin title="Edit Pengawas">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Edit Pengawas</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui data akun pengawas. Penugasan ruangan dikelola lewat halaman Tambah/Edit Ruangan.</p>
        </div>

        <form method="POST" action="{{ route('admin.supervisors.update', $supervisor) }}" class="max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Akun Pengguna</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" :value="__('Nama Lengkap')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $supervisor->user?->name) }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $supervisor->user?->email) }}" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password" :value="__('Password Baru')" />
                        <div class="mt-1 flex gap-2">
                            <div class="relative flex-1">
                                <x-text-input id="password" name="password" type="password" class="block w-full pr-10" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti" />
                                <x-password-toggle />
                            </div>
                            <button type="button" data-password-generator data-target="password" data-confirmation="password_confirmation" class="shrink-0 rounded-md bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Generate Otomatis</button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    </div>
                </div>
                <label class="mt-5 flex w-fit items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supervisor->user?->is_active)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Akun aktif</span>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.supervisors.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>

    @include('admin.partials.credential-tools')
</x-layouts.admin>
