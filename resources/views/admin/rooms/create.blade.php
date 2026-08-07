<x-layouts.admin title="Tambah Ruangan">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Tambah Ruangan</h2>
            <p class="mt-1 text-sm text-gray-500">Tambahkan ruangan baru, tetapkan pengawas, dan pilih siswa yang menjadi
                peserta tetap ruangan ini.</p>
        </div>

        @include('admin.rooms._form')
    </div>
</x-layouts.admin>
