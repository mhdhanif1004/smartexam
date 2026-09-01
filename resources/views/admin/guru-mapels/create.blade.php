<x-layouts.admin title="Tambah Guru Mapel">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tambah Guru Mapel</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Akun pengguna ber-role guru mapel akan dibuat otomatis. Penugasan mapel dikelola setelah guru dibuat, lewat halaman Detail/Penugasan.</p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.guru-mapels.store') }}"
            class="max-w-2xl space-y-6"
            x-data="{
                subject: '',
                fillEmail() {
                    var name = document.getElementById('name');
                    var email = document.getElementById('email');
                    if (!name || !email || email.value.trim() !== '') return;
                    var local = name.value.toLowerCase().replace(/[^a-z0-9]/g, '');
                    if (local) email.value = local + '@gmail.com';
                },
            }"
        >
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Akun Pengguna</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" :value="__('Nama Lengkap')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required autofocus @blur="fillEmail()" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email') }}" placeholder="Diisi otomatis dari nama (bisa diedit)" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Terisi otomatis dari nama; email akhir dibuat unik oleh sistem saat disimpan.</p>
                    </div>
                    <div>
                        <x-input-label for="nip" :value="__('NIP')" />
                        <x-text-input id="nip" name="nip" type="text" class="mt-1 block w-full" value="{{ old('nip') }}" placeholder="Opsional" />
                        <x-input-error :messages="$errors->get('nip')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="password" :value="__('Password')" />
                        <div class="mt-1 flex gap-2">
                            <div class="relative flex-1">
                                <x-text-input id="password" name="password" type="password" class="block w-full pr-10" autocomplete="new-password" placeholder="Kosongkan untuk generate otomatis" />
                                <x-password-toggle />
                            </div>
                            <button type="button" data-password-generator data-target="password" data-confirmation="password_confirmation" class="shrink-0 rounded-md bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">Generate Otomatis</button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opsional. Jika dikosongkan, password akan dibuat otomatis oleh sistem.</p>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="password_confirmation" :value="__('Konfirmasi Password')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    </div>
                </div>
                <label class="mt-5 flex w-fit items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Akun aktif</span>
                </label>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Penugasan Awal (Opsional)</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tentukan mata pelajaran dan kelas yang diampu guru ini. Bisa diubah kapan saja lewat halaman Kelola Mapel. Lewati jika ingin di-assign belakangan.</p>

                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/40">
                    <x-input-label for="subject_id" :value="__('Mata Pelajaran')" />
                    <select id="subject_id" name="subject_id" x-model="subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">-- Pilih Mapel (opsional) --</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                </div>

                <div x-show="subject !== ''" x-cloak class="mt-4">
                    <x-questions.classroom-picker
                        mode="all"
                        :classrooms="$classrooms"
                        :selected="old('classroom_ids', [])"
                        :exclusions-by-subject="$exclusionsBySubject"
                        description="Pilih kelas yang diampu guru ini untuk mapel terpilih. Bisa diubah nanti lewat halaman Kelola Mapel."
                    />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.guru-mapels.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>

    @include('admin.partials.credential-tools')
</x-layouts.admin>
