<x-layouts.admin :title="'Pengaturan Kartu Login'">
    @php
        $sample = [
            'nama' => 'Budi Santoso',
            'nisn' => '0123456789',
            'kelas' => 'XI RPL 1',
            'ruangan' => 'Ruang 1',
            'shift' => 'Shift 1',
            'username' => 'budi0123456789',
            'password' => 'rahasia123',
        ];
    @endphp

    <div
        x-data="cardSettingsPreview({
            namaSekolah: @js($setting->nama_sekolah ?? ''),
            tempat: @js($setting->tempat ?? ''),
            namaKepalaSekolah: @js($setting->nama_kepala_sekolah ?? ''),
            jabatanKepalaSekolah: @js($setting->jabatan_kepala_sekolah ?? 'Kepala Sekolah'),
            logoKiriUrl: @js($setting->logoKiriDataUri()),
            logoKananUrl: @js($setting->logoKananDataUri()),
            sample: @js($sample),
            tanggal: @js(now()->translatedFormat('d F Y')),
        })"
        class="space-y-6"
    >
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Pengaturan Kartu Login</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Atur logo, nama sekolah, dan tanda tangan pada kartu login peserta. Isi kartu lainnya (nama, NISN, kelas,
                ruangan, shift, username, password) memakai data siswa secara otomatis.
            </p>
        </div>

        @include('admin.partials.flash')

        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.card-settings.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Header Kartu</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Logo dan nama sekolah tampil di bagian atas kartu.</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <x-input-label for="nama_sekolah" :value="__('Nama Sekolah')" />
                            <x-text-input id="nama_sekolah" name="nama_sekolah" type="text" class="mt-1 block w-full"
                                x-model="namaSekolah" placeholder="contoh: SMA Negeri 1 SmartExam" />
                            <x-input-error :messages="$errors->get('nama_sekolah')" class="mt-2" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="logo-kiri-input" :value="__('Logo Kiri')" />
                                <input id="logo-kiri-input" name="logo_kiri" type="file" accept="image/jpeg,image/png,image/webp"
                                    @change="onLogoKiri($event)"
                                    class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG, atau WEBP maks. 2 MB.</p>
                                <x-input-error :messages="$errors->get('logo_kiri')" class="mt-2" />
                                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" name="hapus_logo_kiri" value="1" x-model="removeLeft" @change="removeLeft && clearKiri()"
                                        class="rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                    Hapus logo kiri
                                </label>
                            </div>

                            <div>
                                <x-input-label for="logo-kanan-input" :value="__('Logo Kanan')" />
                                <input id="logo-kanan-input" name="logo_kanan" type="file" accept="image/jpeg,image/png,image/webp"
                                    @change="onLogoKanan($event)"
                                    class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-500/10 dark:file:text-indigo-300 dark:hover:file:bg-indigo-500/20" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG, atau WEBP maks. 2 MB.</p>
                                <x-input-error :messages="$errors->get('logo_kanan')" class="mt-2" />
                                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" name="hapus_logo_kanan" value="1" x-model="removeRight" @change="removeRight && clearKanan()"
                                        class="rounded border-gray-300 text-rose-600 focus:ring-rose-500 dark:border-gray-600 dark:bg-gray-800">
                                    Hapus logo kanan
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Footer Kartu</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kota &amp; tanggal serta tanda tangan kepala sekolah di bagian bawah kartu.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="tempat" :value="__('Kota')" />
                            <x-text-input id="tempat" name="tempat" type="text" class="mt-1 block w-full" x-model="tempat"
                                placeholder="contoh: Jakarta" />
                            <x-input-error :messages="$errors->get('tempat')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label :value="__('Tanggal')" />
                            <x-text-input type="text" class="mt-1 block w-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                                :value="now()->translatedFormat('d F Y')" readonly />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Terisi otomatis saat kartu dicetak.</p>
                        </div>
                        <div>
                            <x-input-label for="nama_kepala_sekolah" :value="__('Nama Kepala Sekolah')" />
                            <x-text-input id="nama_kepala_sekolah" name="nama_kepala_sekolah" type="text" class="mt-1 block w-full"
                                x-model="namaKepalaSekolah" placeholder="contoh: Drs. Ahmad Fauzi, M.Pd." />
                            <x-input-error :messages="$errors->get('nama_kepala_sekolah')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="jabatan_kepala_sekolah" :value="__('Jabatan')" />
                            <x-text-input id="jabatan_kepala_sekolah" name="jabatan_kepala_sekolah" type="text" class="mt-1 block w-full"
                                x-model="jabatan" placeholder="contoh: Kepala Sekolah" />
                            <x-input-error :messages="$errors->get('jabatan_kepala_sekolah')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.student-cards.index') }}"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        Batal
                    </a>
                    <x-primary-button>Simpan Pengaturan</x-primary-button>
                </div>
            </form>

            <div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Pratinjau Kartu</h3>
                        <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                            Live
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Contoh kartu peserta. Perubahan di form kiri langsung terlihat di sini.</p>

                    <div class="mt-5 flex justify-center">
                        <div class="w-full max-w-sm">
                            <div class="card-preview overflow-hidden rounded-lg border-2 border-dashed border-gray-300 shadow-md dark:border-gray-600">
                                <div class="card-preview-header flex items-center justify-between gap-2 px-3 pt-3">
                                    <template x-if="logoKiriUrl">
                                        <img :src="logoKiriUrl" alt="Logo kiri" class="h-10 w-10 object-contain">
                                    </template>
                                    <template x-if="!logoKiriUrl">
                                        <span class="h-10 w-10"></span>
                                    </template>
                                    <div class="text-center">
                                        <p class="text-xs font-bold leading-tight text-blue-700" x-text="namaSekolah"></p>
                                        <p class="text-[9px] uppercase tracking-wide text-gray-500">Kartu Login Ujian</p>
                                    </div>
                                    <template x-if="logoKananUrl">
                                        <img :src="logoKananUrl" alt="Logo kanan" class="h-10 w-10 object-contain">
                                    </template>
                                    <template x-if="!logoKananUrl">
                                        <span class="h-10 w-10"></span>
                                    </template>
                                </div>

                                <div class="mt-2 space-y-1.5 border-t border-dashed border-blue-200 px-3 py-2 text-[11px]">
                                    <p><span class="inline-block w-16 text-gray-500">Nama</span> <span class="font-bold" x-text="sample.nama"></span></p>
                                    <p><span class="inline-block w-16 text-gray-500">NISN</span> <span class="font-bold" x-text="sample.nisn"></span></p>
                                    <p><span class="inline-block w-16 text-gray-500">Kelas</span> <span class="font-bold" x-text="sample.kelas"></span></p>
                                    <p><span class="inline-block w-16 text-gray-500">Ruangan</span> <span class="font-bold" x-text="sample.ruangan"></span></p>
                                    <p><span class="inline-block w-16 text-gray-500">Shift</span> <span class="font-bold" x-text="sample.shift"></span></p>
                                    <p><span class="inline-block w-16 text-gray-500">Username</span> <span class="font-bold" x-text="sample.username"></span></p>
                                    <p><span class="inline-block w-16 text-gray-500">Password</span>
                                        <span class="rounded bg-indigo-50 px-1 font-mono font-bold" x-text="sample.password"></span>
                                    </p>
                                </div>

                                <div class="mt-2 border-t border-dashed border-blue-200 px-3 pb-3 pt-1 text-[10px]">
                                    <p class="text-right text-gray-500"><span x-text="tempat"></span><template x-if="tempat">, </template><span x-text="tanggal"></span></p>
                                    <div class="mt-2 text-right">
                                        <p class="text-gray-600" x-text="jabatan + ','"></p>
                                        <p class="mt-5 inline-block border-b border-gray-800 font-bold leading-tight" x-text="namaKepalaSekolah"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        Tata letak asli saat dicetak: 6 kartu per halaman A4 lengkap dengan garis potong. Pratinjau di sini
                        disusutkan agar muat di layar.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
