{{-- Lightbox zoom gambar soal/opsi.
     Mendengarkan event window 'image-zoom' via Alpine $dispatch.
     Pakai pada img: @click="$dispatch('image-zoom', $event.currentTarget.src)" + class cursor-zoom-in.
     Visual mengikuti pola lightbox peserta (peserta/exams/work.blade.php): overlay gelap,
     gambar object-contain, tombol tutup, ESC & klik overlay untuk menutup. --}}
<div
    x-data="{ zoomUrl: null }"
    @image-zoom.window="zoomUrl = $event.detail"
    @keydown.escape.window="zoomUrl = null"
>
    <div x-show="zoomUrl" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4" @click="zoomUrl = null">
        <img :src="zoomUrl" class="max-h-full max-w-full rounded-lg object-contain shadow-2xl" alt="Gambar soal diperbesar" />
        <button type="button" @click="zoomUrl = null" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Tutup gambar">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>