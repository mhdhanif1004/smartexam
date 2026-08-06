<x-layouts.admin title="Edit Kelas">
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Edit Kelas</h2>
            <p class="mt-1 text-sm text-gray-500">Perbaiki nama kelas. Siswa yang sudah memakai kelas ini akan ikut diperbarui otomatis.</p>
        </div>

        <form method="POST" action="{{ route('admin.classrooms.update', $classroom) }}" class="max-w-2xl space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div>
                    <x-input-label for="name" :value="__('Nama Kelas')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $classroom->name) }}" required placeholder="contoh: XI RPL 1" autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.classrooms.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Batal</a>
                <x-primary-button>Perbarui</x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.admin>
