export function cardSettingsPreview(config) {
    return {
        namaSekolah: config.namaSekolah,
        tempat: config.tempat,
        namaKepalaSekolah: config.namaKepalaSekolah,
        jabatan: config.jabatanKepalaSekolah,
        logoKiriUrl: config.logoKiriUrl || null,
        logoKananUrl: config.logoKananUrl || null,
        sample: config.sample,
        tanggal: config.tanggal,
        removeLeft: false,
        removeRight: false,

        onLogoKiri(event) {
            const file = event.target.files[0];
            if (!file) {
                return;
            }

            this.logoKiriUrl = URL.createObjectURL(file);
            this.removeLeft = false;
        },

        onLogoKanan(event) {
            const file = event.target.files[0];
            if (!file) {
                return;
            }

            this.logoKananUrl = URL.createObjectURL(file);
            this.removeRight = false;
        },

        clearKiri() {
            this.logoKiriUrl = null;
            this.removeLeft = true;
            const input = document.getElementById('logo-kiri-input');
            if (input) {
                input.value = '';
            }
        },

        clearKanan() {
            this.logoKananUrl = null;
            this.removeRight = true;
            const input = document.getElementById('logo-kanan-input');
            if (input) {
                input.value = '';
            }
        },
    };
}
