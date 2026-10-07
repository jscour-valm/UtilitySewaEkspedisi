import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

// Pengganti alert()/confirm() native: modal SweetAlert2 dengan warna design system
// (avian-green = aksi utama, merah = destruktif). Diekspos ke window supaya bisa
// dipanggil dari <script>/x-data inline di Blade.
window.Swal = Swal;

window.notify = function (message, type = 'success', title = null) {
    const defaultTitle = { success: 'Berhasil', error: 'Gagal', warning: 'Perhatian', info: 'Info' };
    return Swal.fire({
        icon: type,
        title: title ?? defaultTitle[type] ?? 'Info',
        text: message,
        confirmButtonColor: type === 'error' ? '#dc2626' : '#1B7A43',
    });
};

// Detail pengajuan: pengaju membatalkan pengajuan pending (alasan wajib).
window.batalkanPengajuan = async function (id) {
    const { value: alasan, isConfirmed } = await Swal.fire({
        title: 'Batalkan pengajuan?',
        text: 'Pengajuan yang dibatalkan tidak bisa diproses lagi. Dokumen SJ/TO-ACB-nya bisa dipakai pengajuan lain.',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Alasan pembatalan',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonText: 'Batalkan Pengajuan',
        cancelButtonText: 'Kembali',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
        inputValidator: v => (!v || !v.trim()) ? 'Alasan pembatalan wajib diisi' : undefined,
    })
    if (!isConfirmed) return

    try {
        const res = await fetch(`/api/pengajuan/${id}/batalkan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ alasan: alasan.trim() }),
        })
        const json = await res.json()
        if (!res.ok || !json.success) {
            window.notify(json.message || 'Gagal membatalkan pengajuan', 'error')
            return
        }
        await window.notify('Pengajuan dibatalkan', 'success')
        window.location.reload()
    } catch (e) {
        window.notify('Gagal membatalkan pengajuan', 'error')
    }
}

// Pengganti confirm() — return Promise<boolean> (resolve true kalau user klik "Ya").
window.confirmDialog = function (message, { danger = false, confirmText = 'Ya', cancelText = 'Batal', title = 'Yakin?', icon = 'warning' } = {}) {
    return Swal.fire({
        icon,
        title,
        text: message,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: danger ? '#dc2626' : '#1B7A43',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    }).then((r) => r.isConfirmed);
};

// Pemantau sesi, hanya di halaman ber-layout app (meta sesi-lifetime & sesi-pengguna):
// - Session habis (idle melebihi SESSION_LIFETIME, atau server balas 401/419) → popup
//   blocking lalu login ulang. Aktivitas = respons server terakhir (muat halaman / fetch),
//   dibagi antar-tab lewat localStorage supaya tab yang diam tidak me-logout tab yang aktif.
// - Satu browser hanya punya satu session: kalau tab lain login dengan akun berbeda, tab ini
//   diblokir (fetch & submit form ditahan) sampai dimuat ulang dengan akun yang aktif.
const sesiMenit = Number(document.querySelector('meta[name="sesi-lifetime"]')?.content);
const sesiPengguna = document.querySelector('meta[name="sesi-pengguna"]')?.content;
if (sesiMenit > 0 && sesiPengguna) {
    const KUNCI_AKTIF = 'sesi:aktifTerakhir';
    const KUNCI_PENGGUNA = 'sesi:pengguna';
    const batasMs = sesiMenit * 60 * 1000;
    const fireAsli = Swal.fire.bind(Swal);
    const fetchAsli = window.fetch.bind(window);
    let aktifLokal = Date.now();
    let terblokir = false;
    let timer = null;

    const baca = (kunci) => {
        try {
            return localStorage.getItem(kunci);
        } catch {
            return null;
        }
    };
    const tulis = (kunci, nilai) => {
        try {
            localStorage.setItem(kunci, nilai);
        } catch {}
    };

    // Popup yang tidak bisa ditutup; setelah tampil, modal lain (notify gagal, dsb.) dimatikan.
    const blokir = (opsi, lanjut) => {
        if (terblokir) return;
        terblokir = true;
        clearTimeout(timer);
        fireAsli({
            icon: 'warning',
            confirmButtonColor: '#1B7A43',
            allowOutsideClick: false,
            allowEscapeKey: false,
            ...opsi,
        }).then(lanjut);
        Swal.fire = () => new Promise(() => {});
    };

    window.tampilkanSesiHabis = function () {
        const kembali = encodeURIComponent(location.pathname + location.search);
        blokir({
            title: 'Sesi habis',
            text: 'Sesi Anda sudah berakhir. Silakan login ulang untuk melanjutkan.',
            confirmButtonText: 'Login Ulang',
        }, () => { location.href = `/sesi-habis?kembali=${kembali}`; });
    };

    const tampilkanGantiAkun = () => blokir({
        title: 'Akun berganti',
        text: 'Tab lain sudah login dengan akun berbeda. Satu browser hanya bisa memakai satu akun; halaman ini akan dibuka ulang dengan akun yang sedang aktif.',
        confirmButtonText: 'Buka Ulang',
    }, () => { location.href = '/'; });

    const akunMasihSama = () => {
        const aktif = baca(KUNCI_PENGGUNA);
        if (aktif && aktif !== sesiPengguna) {
            tampilkanGantiAkun();
            return false;
        }
        return true;
    };

    // Dicek tiap ≤1 menit (bukan satu timeout 2 jam) supaya tetap jalan setelah laptop sleep.
    const cekSesi = () => {
        clearTimeout(timer);
        if (terblokir || !akunMasihSama()) return;
        const aktifTerakhir = Math.max(aktifLokal, Number(baca(KUNCI_AKTIF)) || 0);
        const sisa = aktifTerakhir + batasMs - Date.now();
        if (sisa <= 0) {
            window.tampilkanSesiHabis();
            return;
        }
        timer = setTimeout(cekSesi, Math.min(sisa, 60 * 1000));
    };

    const catatAktif = () => {
        aktifLokal = Date.now();
        tulis(KUNCI_AKTIF, String(aktifLokal));
        cekSesi();
    };

    window.fetch = async (...args) => {
        if (terblokir || !akunMasihSama()) return new Promise(() => {});
        const res = await fetchAsli(...args);
        const keLogin = res.redirected && new URL(res.url).pathname === '/login';
        if (res.status === 401 || res.status === 419 || keLogin) {
            window.tampilkanSesiHabis();
        } else {
            catatAktif();
        }
        return res;
    };

    document.addEventListener('submit', (e) => {
        if (terblokir || !akunMasihSama()) e.preventDefault();
    }, true);

    window.addEventListener('storage', (e) => {
        if (e.key === KUNCI_PENGGUNA && e.newValue && e.newValue !== sesiPengguna) tampilkanGantiAkun();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') cekSesi();
    });

    // Halaman ini dimuat dengan akun ini → jadi akun aktif untuk semua tab.
    tulis(KUNCI_PENGGUNA, sesiPengguna);
    catatAktif();
}

// Format nomor pakai titik ribuan (format Indonesia) — dipakai buat semua
// input angka besar (harga, muatan, dst) di seluruh app, bukan cuma wizard
// Pengajuan Sewa. Diekspos global (window) supaya bisa dipanggil dari Alpine
// component manapun (mis. komponen edit tarif/kendaraan di Kelola Tarif),
// nggak cuma dari dalam Alpine.data('pengajuanSewa', ...) di bawah.
window.formatRibuan = function (val) {
    if (val === '' || val === null || val === undefined) return ''
    const num = String(val).replace(/\D/g, '')
    return num === '' ? '' : Number(num).toLocaleString('id-ID')
}

window.parseRibuan = function (str) {
    const digits = String(str).replace(/\D/g, '')
    return digits === '' ? '' : Number(digits)
}

// Guard "perubahan belum disimpan" — dipasang di halaman mana pun yang punya
// elemen [data-dirty-root] (edit Sewa Truk/Kiriman Rutin). Delegasi input/change
// dari container itu nangkep perubahan dari SEMUA x-data terpisah di dalamnya
// (Profil Perusahaan, Kendaraan, Skill/Harga) tanpa perlu diubah satu-satu.
let __formDirty = false
window.markDirty = () => { __formDirty = true }
window.clearDirty = () => { __formDirty = false }

window.addEventListener('beforeunload', (e) => {
    if (!__formDirty) return
    e.preventDefault()
    e.returnValue = ''
})

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-dirty-root]')
    if (!root) return
    root.addEventListener('input', () => window.markDirty())
    root.addEventListener('change', () => window.markDirty())
    // Submit form tarif utama ("Simpan Perubahan"/"Simpan Semua Perubahan") itu
    // navigasi POST/PUT beneran (bukan AJAX) — beforeunload tetap kepicu pas
    // submit, jadi harus di-clear dulu di sini biar nggak nge-confirm ke diri
    // sendiri pas user justru lagi nyimpan.
    root.addEventListener('submit', () => window.clearDirty())
})

// Naikkan versi tiap struktur draft berubah; draft versi lain dibuang saat dibaca.
const PENGAJUAN_DRAFT_VERSION = 6
const DRAFT_TTL_MS = 48 * 60 * 60 * 1000

Alpine.data('pengajuanSewa', () => ({
    step: 1, // 1-4=wizard steps (jenis pengajuan dipilih via toggle persisten)
    editId: null,
    dataAwalEdit: null, // ringkasanEdit() saat halaman edit dibuka, pembanding penanda "Diubah" di review
    barisAwalEdit: [], // baris Daftar Barang tersimpan saat halaman edit dibuka (harganya terkunci)
    searchKendaraan: '',
    searchPerusahaan: '',
    kendaraanTerpilih: null,
    subStepKendaraan: 1, // 1=Perusahaan, 2=Kendaraan, 3=Ringkasan
    perusahaanList: [],
    loadingPerusahaan: false, // beda "masih fetch" vs "udah selesai & beneran kosong" (Jo, 30 Sept)
    perusahaanStep: 'pilih', // 'pilih' | 'form_baru'
    perusahaanTerpilih: null, // { id_perusahaan, nama_perusahaan, ... }
    kendaraanByPerusahaan: [],
    loadingKendaraanByPerusahaan: false,
    showFormKendaraanBaru: false,
    kendaraanBaru: {
        // Perusahaan (sub-step 1)
        perusahaan_mode: 'pilih', // 'pilih' | 'baru'
        perusahaan_id: null, // jika pilih existing
        nama_perusahaan: '',
        badan_usaha: '',
        no_telepon: '',
        alamat_kantor: '',
        identitas_owner_files: [], // array File objects
        identitas_owner_previews: [], // array preview URLs (blob URLs)
        // Kendaraan (sub-step 2)
        id_skill: [],
        skillBaru: [],
        jenis_kendaraan: '',
        id_jenis_kendaraan: '', // master sesi_master_jenis_kendaraan — pilih ini, muatan_maksimal auto-fill (lihat onJenisKendaraanChange())
        plat_nomor_truk: '',
        muatan_maksimal: '',
    },
    skillList: [],
    loadingSkillList: false, // beda "masih fetch" vs "udah selesai & beneran kosong" (Jo, 30 Sept)
    jenisKendaraanList: [], // [{id_jenis_kendaraan, nama_jenis, muatan_maksimal_ton}] — dropdown "Jenis Kendaraan"
    cabangList: [], // [{Code, Name}] — dropdown "Cabang Tujuan" (PAC)
    kategoriTokoList: [],
    jenisBiayaList: [],
    pengajuan: {
        jenis_pengajuan: 'sewa_truk', // 'sewa_truk' | 'pengiriman_rutin'
        tanggal_pengiriman: '',
        tujuan_penyewaan: '',
        id_cabang_tujuan: '', // PAC only — cabang tujuan barang
        id_skill: [],
        kategoriToko: '',
        harga_sewa: '',
        value_muatan: '',
        catatan: '',
        usulan_status: null, // status keputusan usulan sewa_truk (mode edit): pending/approved/rejected/null
        usulan_harga_sewa: false, // Part B — usul harga_sewa jadi harga master baru (sewa_truk only)
    },
    biayaTambahan: [],
    skillBaru: [],
    skillLocked: false,
    dokumenDipilih: [],
    dokumenSearch: '',
    dokumenPage: 1,
    dokumenPerPage: 5,
    ringkasanDokumenExpanded: false, // toggle "Lihat semua" di ringkasan dokumen terpilih (step3/step4)
    submitting: false,
    savingKendaraan: false, // guard submit ganda di saveKendaraan()
    dummyDokumen: [],  // Will be populated by fetchDokumenList()
    loadingDokumen: false, // beda "masih fetch" vs "udah selesai & beneran kosong" (Jo, 30 Sept)

    // Kiriman Rutin specific state
    detailKirimanRutin: [], // [{id_jenis_barang, id_tarif_kiriman_rutin, jenis_barang, quantity, harga_satuan, subtotal, tarif_baru}]
    tarifKirimanRutinList: [], // [{id_tarif, id_jenis_barang, jenis_barang, biaya_per_unit}] — tarif yg SUDAH terdaftar utk vendor terpilih
    rateCardVendorSkillIds: [], // array id_vendor_skill (sesi_perusahaan_skill) utk vendor+cabang — Kiriman Rutin
    rateCardAreas: [], // [{id_vendor_skill, id_skill, nama_skill}] area milik vendor di cabang user
    areaBaruInput: '', // input teks area baru (Kiriman Rutin, ministep 2)
    detailKirimanArea: null, // areaRutinKey saat Daftar Barang terakhir diisi
    adaDraft: false, // ada draft tersimpan untuk user ini (banner "Hapus Draft")
    loadingRateCard: false,
    jenisBarangList: [], // [{id_jenis_barang, nama_barang}] — semua master jenis barang (selalu ditampilkan di dropdown)
    loadingTarif: false,

    // Master list dokumen (difilter tujuan SAJA, TANPA search) — dipakai buat resolve
    // dokumen yang SUDAH DIPILIH (total/ringkasan/rasio). Beda dari dokumenList di bawah
    // (buat picker table) yang juga kena filter search — dulu semua total/ringkasan salah
    // pakai dokumenList utk resolve, jadi dokumen terpilih yang lagi ga match search term
    // seolah "hilang" dari hitungan meski dokumenDipilih sendiri ga berubah. Lihat Batch Fix 12.
    get dokumenMaster() {
        if (this.pengajuan.tujuan_penyewaan === 'PAC') {
            return this.dummyDokumen.filter(d => d.tipe === 'TO-ACB')
        }
        if (this.pengajuan.tujuan_penyewaan === 'Toko') {
            return this.dummyDokumen.filter(d => d.tipe === 'SJ')
        }
        return this.dummyDokumen
    },

    // Dokumen yang ditampilkan di step 3 — filter by tujuan, lalu by search term
    get dokumenList() {
        let filtered = this.dokumenMaster

        // Filter by search term
        if (this.dokumenSearch.trim() !== '') {
            const searchTerm = this.dokumenSearch.toLowerCase()
            filtered = filtered.filter(d =>
                (d.nomor_dokumen && d.nomor_dokumen.toLowerCase().includes(searchTerm)) ||
                (d.nama_customer && d.nama_customer.toLowerCase().includes(searchTerm)) ||
                (d.alamat && d.alamat.toLowerCase().includes(searchTerm)) ||
                (d.kota && d.kota.toLowerCase().includes(searchTerm)) ||
                (d.last_shipment_no && d.last_shipment_no.toLowerCase().includes(searchTerm))
            )
        }

        return filtered
    },

    // Dokumen terpilih, sudah di-resolve jadi objek lengkap (via dokumenMaster, bukan
    // dokumenList) — dipakai ringkasan step3/step4 supaya gak berubah-ubah pas search aktif.
    get dokumenDipilihResolved() {
        return this.dokumenDipilih
            .map(id => this.dokumenMaster.find(d => d.id === id))
            .filter(Boolean)
    },

    // Total value muatan dari dokumen terpilih (dipakai step3/step4 & rasio)
    get valueMuatanDipilih() {
        return this.dokumenDipilihResolved.reduce((sum, doc) => sum + Number(doc.value), 0)
    },

    // Estimasi rasio sewa (%) = (harga sewa + biaya tambahan) / value muatan * 100 —
    // replikasi client-side dari PengajuanSewa::hitungRasio() di server.
    get rasioSewaEstimasi() {
        if (this.valueMuatanDipilih <= 0) return null
        return (this.totalDenganBiayaTambahan / this.valueMuatanDipilih) * 100
    },

    // Batas rasio sewa dari server (RasioSewa aktif).
    get rasioMaks() {
        return Number(window.__rasioMaks ?? 2.5)
    },

    // Rasio dipakai Sewa Truk dan Kiriman Rutin tujuan PAC (sama dengan PengajuanSewa::pakaiRasioUntuk()).
    get pakaiRasio() {
        return this.pengajuan.jenis_pengajuan !== 'pengiriman_rutin' || this.pengajuan.tujuan_penyewaan === 'PAC'
    },

    get rasioDiAtasBatas() {
        return this.pakaiRasio
            && this.rasioSewaEstimasi !== null && this.rasioSewaEstimasi > this.rasioMaks
    },

    // Pratinjau alur approval, sama dengan PengajuanSewa::hitungAlur().
    get alurApprovalEstimasi() {
        if (this.rasioDiAtasBatas) return ['WM', 'WH']
        return this.pengajuan.tujuan_penyewaan === 'PAC' ? ['WM', 'WC'] : ['WM']
    },

    get adaAreaBaru() {
        return this.skillBaru.some(s => String(s).trim() !== '')
    },

    get dokumenPaged() {
        const start = (this.dokumenPage - 1) * this.dokumenPerPage
        return this.dokumenList.slice(start, start + this.dokumenPerPage)
    },

    get dokumenTotalPages() {
        return Math.ceil(this.dokumenList.length / this.dokumenPerPage)
    },

    // Nomor halaman yang ditampilin di pagination dokumen: kalau totalnya banyak
    // (puluhan halaman), render semua tombol bikin baris pagination meluber ke
    // kanan - jadi di-windowing kayak pagination Laravel standar (halaman pertama,
    // halaman terakhir, current±1, sisanya "…"). Lihat Batch Fix 16.
    get dokumenPageWindow() {
        const total = this.dokumenTotalPages
        const current = this.dokumenPage
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)

        const pages = new Set([1, total, current, current - 1, current + 1])
        const sorted = [...pages].filter(p => p >= 1 && p <= total).sort((a, b) => a - b)

        const result = []
        let prev = null
        for (const p of sorted) {
            if (prev !== null && p - prev > 1) result.push('…')
            result.push(p)
            prev = p
        }
        return result
    },

    // Total berat dokumen terpilih dalam kg
    get totalBeratDipilih() {
        return this.dokumenDipilihResolved.reduce((sum, doc) => sum + Number(doc.berat), 0)
    },

    // Muatan maksimal kendaraan dalam kg (konversi dari Ton)
    get muatanMaksimalKg() {
        const raw = this.kendaraanTerpilih?.muatan_raw
        if (!raw) return null
        return Number(raw) * 1000
    },

    // Apakah berat melebihi kapasitas
    get beratMelebihi() {
        if (!this.muatanMaksimalKg || this.dokumenDipilih.length === 0) return false
        return this.totalBeratDipilih > this.muatanMaksimalKg
    },

    // Persentase kapasitas muatan terpakai (untuk width progress bar)
    get persentaseMuatan() {
        if (!this.muatanMaksimalKg) return 0
        return Math.min((this.totalBeratDipilih / this.muatanMaksimalKg) * 100, 100)
    },

    // Total biaya tambahan
    get totalBiayaTambahan() {
        return this.biayaTambahan.reduce((sum, b) => sum + (Number(b.nominal) || 0), 0)
    },

    // Total harga sewa + biaya tambahan
    get totalDenganBiayaTambahan() {
        return (Number(this.pengajuan.harga_sewa) || 0) + this.totalBiayaTambahan
    },

    // Jumlah toko dari dokumen yang dipilih:
    // - Toko (SJ): jumlah Sell-to Customer unik (customer_no) di antara SJ terpilih
    // - PAC (TO-ACB): jumlah cabang tujuan unik
    get jumlahTokoDipilih() {
        if (this.pengajuan.tujuan_penyewaan === 'Toko') {
            const customerSet = new Set(
                this.dokumenDipilihResolved
                    .filter(d => d.customer_no)
                    .map(d => d.customer_no)
            )
            return customerSet.size
        }
        if (this.pengajuan.tujuan_penyewaan !== 'PAC') return null
        const cabangSet = new Set(
            this.dokumenDipilihResolved
                .filter(d => d.cabang_tujuan)
                .map(d => d.cabang_tujuan)
        )
        return cabangSet.size
    },

    // Skill gabungan versi label: id_skill -> nama_skill (via skillList), + skillBaru.
    // Dipakai buat tampilan (Preview Kalkulasi dsb).
    get skillGabunganLabel() {
        const existing = (this.pengajuan.id_skill || [])
            .map(s => this.skillList.find(sk => String(sk.id_skill) === String(s))?.nama_skill ?? s)
        const baru = this.skillBaru.filter(s => s.trim() !== '')
        return [...existing, ...baru]
    },

    // Cek ada minimal 1 skill terpilih (existing atau baru)
    get adaSkillTerpilih() {
        return this.pengajuan.id_skill.length > 0 || this.skillBaru.filter(s => s.trim() !== '').length > 0
    },

    // Total harga agregasi dari detail kiriman rutin (Σ quantity × harga_satuan)
    get totalHargaKirimanRutin() {
        return this.detailKirimanRutin.reduce((sum, d) => sum + (Number(d.quantity) || 0) * (Number(d.harga_satuan) || 0), 0)
    },

    // Validasi Daftar Barang (Kiriman Rutin): minimal 1 baris TERISI (qty > 0) & valid
    // (jenis barang + harga). Baris yang qty-nya masih kosong (mis. sisa prefill dari
    // tarif terdaftar yang nggak jadi diajukan) DIABAIKAN, bukan ikut ngeblok validasi
    // (Jo, 1 Okt 2026 — biarin aja baris kosong, nggak usah dihapus manual).
    get detailKirimanRutinValid() {
        if (this.pengajuan.jenis_pengajuan !== 'pengiriman_rutin') return true
        const terisi = this.detailKirimanRutin.filter(d => Number(d.quantity) > 0)
        if (terisi.length === 0) return false
        return terisi.every(d =>
            d.id_jenis_barang !== '' && d.id_jenis_barang !== null
            && (d.id_tarif_kiriman_rutin || d.tarif_baru)
            && Number(d.harga_satuan) > 0
        )
    },

    // Konversi kg → ton buat keterangan "(kalkulasi dlm ton)" di step 3/4, mis. 1370.302 → "1,37"
    formatTon(kg) {
        return (Number(kg || 0) / 1000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 3 })
    },

    // Format tanggal ke format Indonesia (d M Y) — match Carbon translatedFormat('d M Y')
    formatTanggalID(dateStr) {
        if (!dateStr) return '—'
        const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
        const d = new Date(dateStr + 'T00:00:00')
        if (isNaN(d)) return dateStr
        return `${String(d.getDate()).padStart(2, '0')} ${bulan[d.getMonth()]} ${d.getFullYear()}`
    },

    // Format/parse nomor Rupiah dengan titik ribuan — delegasi ke fungsi
    // global (window.formatRibuan/parseRibuan, didefinisikan di atas file
    // ini) biar 1 sumber definisi dipakai bareng komponen Alpine lain juga.
    formatRibuan(val) {
        return window.formatRibuan(val)
    },

    parseRibuan(str) {
        return window.parseRibuan(str)
    },

    async init() {
        // Default id_cabang dari cabang user yang login (dipakai buat fetchDokumenList).
        // Mode edit & prefill kendaraan dari dashboard akan override ini pakai data mereka sendiri.
        this.pengajuan.id_cabang = window.__userCabang || ''
        try { localStorage.removeItem('pengajuan_draft') } catch (e) { /* key lama sebelum draft per user */ }

        // Fetch master data paralel dulu sebelum prefill
        await Promise.all([
            this.fetchSkillList(),
            this.fetchKategoriTokoList(),
            this.fetchJenisBiayaList(),
            this.fetchPerusahaanList(),
            this.fetchJenisBarangList(),
            this.fetchJenisKendaraanList(),
            this.fetchCabangList(),
        ])

        // Fetch dokumen list (SJ + TO-ACB) - called after master data loaded
        // Note: fetchDokumenList depends on pengajuan.id_cabang & tujuan_penyewaan
        // so it will be called again after those are set

        // Check edit mode (dari window.__editPengajuan)
        if (window.__editPengajuan) {
            const data = window.__editPengajuan
            this.editId = data.id_pengajuan_sewa
            this.pengajuan.jenis_pengajuan = data.jenis_pengajuan || 'sewa_truk'
            this.pengajuan.tanggal_pengiriman = data.tanggal_pengiriman
            this.pengajuan.harga_sewa = data.harga_sewa
            this.pengajuan.tujuan_penyewaan = data.tujuan_penyewaan
            this.pengajuan.id_cabang_tujuan = data.id_cabang_tujuan || ''
            this.pengajuan.kategoriToko = data.kategori_toko
            this.pengajuan.catatan = data.catatan_pengajuan
            this.pengajuan.value_muatan = data.value_muatan // Prefill value_muatan dari database
            this.pengajuan.usulan_harga_sewa = data.usulan_harga_sewa ?? false // sewa_truk; false kalau sudah terkunci
            this.pengajuan.usulan_status = data.usulan_status ?? null
            this.pengajuan.id_cabang = data.id_cabang // Needed for fetchDokumenList
            this.biayaTambahan = data.biaya_tambahan || []
            this.dokumenDipilih = data.dokumen_dipilih || [] // Prefill dokumen nomor (dari junction table)

            // Prefill skill: split existing skill (numeric id, match skillList) vs
            // yang ga match (leftover lama blm ke-convert, jadi skill_baru)
            const knownSkillIds = this.skillList.map(s => String(s.id_skill))
            this.pengajuan.id_skill = data.id_skill.filter(s => knownSkillIds.includes(String(s)))
            this.skillBaru = data.id_skill.filter(s => !knownSkillIds.includes(String(s)))

            // Branch: prefill berdasarkan jenis_pengajuan
            if (data.jenis_pengajuan === 'sewa_truk') {
                this.kendaraanTerpilih = data.kendaraan
            } else { // pengiriman_rutin
                this.perusahaanTerpilih = data.perusahaan
                // Area tersimpan selalu id_skill terdaftar (1 area).
                this.pengajuan.id_skill = data.id_skill.map(String).slice(0, 1)
                this.skillBaru = []
                this.detailKirimanRutin = (data.detail_kiriman_dipilih || []).map(d => ({ ...d, tersimpan: true }))
                this.barisAwalEdit = this.detailKirimanRutin.map(d => ({ ...d }))
                this.detailKirimanArea = this.areaRutinKey
                this.resolveRateCardForVendor()
            }

            // Skill udah pernah dikunci sebelumnya
            this.skillLocked = true
            this.step = 2
            this.dataAwalEdit = this.ringkasanEdit()
            if (this.step3Dikunci) this.dokumenDipilih = []

            // Fetch dokumen (SJ/TO-ACB) — tujuan_penyewaan & id_cabang di atas di-assign
            // langsung (bukan lewat watcher), dan watcher yang biasanya trigger ini baru
            // didaftarkan SETELAH blok if/else ini selesai, jadi perlu dipanggil manual.
            if (this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) {
                this.fetchDokumenList()
            }
        } else {
            // Mode create: baca query params kalau ada (dari tombol Pilih di dashboard)
            const params = new URLSearchParams(window.location.search)
            if (params.get('id')) {
                // Shortcut "Pilih" kendaraan dari dashboard: isian draft lain tetap dipulihkan,
                // kendaraan & step diambil dari URL.
                await this.loadDraft()
                // Jalur ini selalu Sewa Truk (kendaraan dipilih dari dashboard) — pastikan tidak
                // ke-override jadi 'pengiriman_rutin' kalau draft yang direstore sebelumnya
                // adalah draft Kiriman Rutin.
                this.pengajuan.jenis_pengajuan = 'sewa_truk'

                this.kendaraanTerpilih = {
                    id:        parseInt(params.get('id')),
                    nama:      params.get('nama') ?? '',
                    kendaraan: params.get('kendaraan') ?? '',
                    muatan:    params.get('muatan') ?? '',
                    muatan_raw: params.get('muatan_raw') ?? '',
                    harga:     params.get('harga') ?? '',
                    updated_at: params.get('updated_at') ?? '',
                    skill:     params.get('skill') ?? '',
                }
                this.step = 2

                // Prefill skill checkbox dari kendaraan yang dipilih (dari query param).
                // 'skill' = id_skill numeric comma-separated (sesi_unit_kendaraan) —
                // token yang cocok ke skillList (by id, numeric) masuk pengajuan.id_skill,
                // sisanya (leftover lama yg blm ke-convert, misal "DKAH") jadi skill_baru.
                if (this.kendaraanTerpilih?.skill) {
                    const kendaraanSkillTokens = this.kendaraanTerpilih.skill
                        .split(',')
                        .map(s => s.trim())
                        .filter(s => s !== '')
                    const knownSkillIds = this.skillList.map(s => String(s.id_skill))
                    this.pengajuan.id_skill = kendaraanSkillTokens.filter(s => knownSkillIds.includes(s))
                    this.skillBaru = kendaraanSkillTokens.filter(s => !knownSkillIds.includes(s))
                }

                // Bersihkan query string setelah prefill selesai — kalau tetap ada di URL, F5
                // akan mengulang blok ini dari awal (loadDraft() + override kendaraanTerpilih),
                // menimpa balik perubahan yang sudah dibuat user setelah masuk (mis. ganti
                // kendaraan/perusahaan lain). saveDraft() dipanggil manual krn $watch belum
                // terdaftar di titik ini, jadi reload berikutnya baca draft, bukan URL basi.
                history.replaceState(null, '', window.location.pathname)
                this.saveDraft()
            } else if (params.get('perusahaan_id')) {
                // Shortcut dari baris tarif di Detail Perusahaan: mulai pengajuan baru untuk
                // perusahaan itu (draft lama dibuang), langsung ke ministep 2.
                this.hapusDraft()
                const jenis = params.get('jenis') === 'pengiriman_rutin' ? 'pengiriman_rutin' : 'sewa_truk'
                this.pengajuan.jenis_pengajuan = jenis

                this.perusahaanTerpilih = {
                    id_perusahaan:   parseInt(params.get('perusahaan_id')),
                    nama_perusahaan: params.get('nama_perusahaan') ?? '',
                    badan_usaha:     params.get('badan_usaha') ?? '',
                    no_telepon:      params.get('no_telepon') ?? '',
                    alamat_kantor:   params.get('alamat_kantor') ?? '',
                }
                // Perusahaan ini belum tentu ada di perusahaanList (di-fetch di awal init(), sebelum
                // blok ini jalan) — push manual biar <x-tabel-perusahaan> di sub-step "Perusahaan"
                // bisa nge-render & nge-highlight barisnya (sama pola kayak di loadDraft()).
                if (!this.perusahaanList.some(p => String(p.id_perusahaan) === String(this.perusahaanTerpilih.id_perusahaan))) {
                    this.perusahaanList.push(this.perusahaanTerpilih)
                }
                this.subStepKendaraan = 2

                if (jenis === 'sewa_truk') {
                    this.fetchKendaraanByPerusahaan(this.perusahaanTerpilih.id_perusahaan)

                    // Pre-centang skill (area kirim) di form "tambah kendaraan baru" — form itu
                    // otomatis kebuka kalau perusahaan ini belum punya kendaraan (lihat
                    // fetchKendaraanByPerusahaan).
                    const skillParam = params.get('skill')
                    if (skillParam && this.skillList.some(s => String(s.id_skill) === skillParam)) {
                        this.kendaraanBaru.id_skill = [skillParam]
                    }
                    // Harga tarif ini cuma starting point (tetap bisa diedit manual di step
                    // berikutnya, sama kayak harga referensi dari shortcut kendaraan).
                    if (params.get('harga')) {
                        this.pengajuan.harga_sewa = params.get('harga')
                    }
                } else {
                    // Baris tarif yang diklik = 1 area kirim vendor → langsung terpilih kalau area itu
                    // memang milik vendor di cabang user atau terdaftar di cabang user.
                    const skillParam = params.get('skill')
                    await this.resolveRateCardForVendor()
                    if (skillParam && (this.rateCardAreas.some(a => String(a.id_skill) === skillParam)
                        || this.skillList.some(s => String(s.id_skill) === skillParam))) {
                        this.pilihAreaRutin(skillParam)
                    }
                }

                // Sama seperti jalur ?id= di atas — bersihkan URL supaya F5 tidak mengulang
                // prefill dari data perusahaan/harga yang sudah basi (lihat komentar di sana).
                history.replaceState(null, '', window.location.pathname)
                this.saveDraft()
            } else {
                const draft = this.bacaDraft()
                if (draft) {
                    const disimpan = new Date(draft.savedAt).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
                    const jenis = draft.pengajuan?.jenis_pengajuan === 'pengiriman_rutin' ? 'Kiriman Rutin' : 'Sewa Truk'
                    const vendor = draft.perusahaanTerpilih?.nama_perusahaan || draft.kendaraanTerpilih?.nama || '-'
                    const lanjut = await confirmDialog(
                        `Ada draft pengajuan ${jenis} (${vendor}), step ${draft.step}, disimpan ${disimpan}. Lanjutkan draft ini?`,
                        { title: 'Draft tersimpan', icon: 'question', confirmText: 'Lanjutkan', cancelText: 'Mulai baru' }
                    )
                    if (lanjut) await this.loadDraft(draft)
                    else this.hapusDraft()
                }
            }
        }

        this.$nextTick(() => createIcons({ icons }))
        this.$watch('step', () => {
            this.$nextTick(() => createIcons({ icons }))
            this.saveDraft()
        })
        this.$watch('pengajuan.tujuan_penyewaan', () => {
            this.dokumenDipilih = []
            this.dokumenPage = 1
            // Fetch dokumen list saat tujuan_penyewaan berubah
            if (this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) {
                this.fetchDokumenList()
            }
        })

        this.$watch('step3Dikunci', dikunci => {
            if (dikunci) this.dokumenDipilih = []
        })
        if (this.step3Dikunci && this.step === 3) this.step = 2

        this.$watch('pengajuan.id_cabang_tujuan', (baru, lama) => {
            if (this.pengajuan.tujuan_penyewaan === 'PAC') this.centangToAcbCabangTujuan(lama)
        })

        this.$watch('pengajuan.id_cabang', () => {
            // Fetch dokumen list saat cabang berubah
            if (this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) {
                this.fetchDokumenList()
            }
        })

        // Auto-save draft: watch state dan simpan ke localStorage (debounce 500ms untuk pengajuan)
        let saveDraftTimer = null
        this.$watch('pengajuan', () => {
            clearTimeout(saveDraftTimer)
            saveDraftTimer = setTimeout(() => this.saveDraft(), 500)
        }, { deep: true })
        this.$watch('skillBaru', () => { this.$nextTick(() => createIcons({ icons })); this.saveDraft() }, { deep: true })
        this.$watch('biayaTambahan', () => { this.$nextTick(() => createIcons({ icons })); this.saveDraft() }, { deep: true })
        this.$watch('dokumenDipilih', () => this.saveDraft(), { deep: true })
        this.$watch('kendaraanTerpilih', () => this.saveDraft())
        this.$watch('perusahaanTerpilih', () => this.saveDraft())
        this.$watch('kendaraanBaru', () => this.saveDraft(), { deep: true })

        // perusahaanTerpilih berubah → Kiriman Rutin fetch tarif; kedua jenis fetch kendaraan
        // terdaftar vendor itu (Sewa Truk buat pilih, Kiriman Rutin cuma info di Ringkasan).
        this.$watch('perusahaanTerpilih', (baru, lama) => {
            // Ganti / batal pilih vendor → area & Daftar Barang vendor sebelumnya tidak berlaku lagi.
            if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin'
                && String(lama?.id_perusahaan ?? '') !== String(baru?.id_perusahaan ?? '')) {
                this.pengajuan.id_skill = []
                this.skillBaru = []
                this.detailKirimanRutin = []
                this.detailKirimanArea = null
            }
            if (!this.pengajuanIdPerusahaanEkspedisi) return
            if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') {
                this.resolveRateCardForVendor()
            }
            this.fetchKendaraanByPerusahaan(this.pengajuanIdPerusahaanEkspedisi)
        })

        // Daftar vendor difilter beda per jenis pengajuan (Kiriman Rutin: hanya
        // yang punya tarif barang) — refetch tiap jenis berubah lewat toggle.
        this.$watch('pengajuan.jenis_pengajuan', () => {
            this.fetchPerusahaanList()
        })

        this.$watch('detailKirimanRutin', () => {
            // Auto-sync total harga kiriman rutin ke pengajuan.harga_sewa
            if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') {
                this.pengajuan.harga_sewa = this.totalHargaKirimanRutin
            }
            this.saveDraft()
        }, { deep: true })
    },

    async fetchSkillList() {
        this.loadingSkillList = true
        try {
            const res = await fetch('/api/pengajuan/skill-list')
            const json = await res.json()
            // safeQuery returns { data: [...], error: null }
            this.skillList = json.data ?? json
        } catch (e) {
            console.error('Gagal fetch skill list:', e)
        } finally {
            this.loadingSkillList = false
        }
    },

    async fetchJenisKendaraanList() {
        try {
            const res = await fetch('/api/jenis-kendaraan')
            const json = await res.json()
            this.jenisKendaraanList = json.data ?? []
        } catch (e) {
            console.error('Gagal fetch jenis kendaraan list:', e)
        }
    },

    // Begitu KaGud pilih jenis kendaraan di dropdown, muatan_maksimal auto-fill dari
    // master (readonly di form) — biar nggak bisa salah isi manual (review mentor item 4).
    onJenisKendaraanChange(target, idJenisKendaraan) {
        const found = this.jenisKendaraanList.find(j => String(j.id_jenis_kendaraan) === String(idJenisKendaraan))
        target.muatan_maksimal = found ? found.muatan_maksimal_ton : ''
    },

    // Dropdown "Cabang Tujuan" (PAC) — cabang satu area dengan cabang sendiri.
    async fetchCabangList() {
        try {
            const res = await fetch('/api/pengajuan/cabang-list')
            const json = await res.json()
            this.cabangList = json.data ?? []
        } catch (e) {
            console.error('Gagal fetch cabang list:', e)
        }
    },

    async fetchKategoriTokoList() {
        try {
            const res = await fetch('/api/pengajuan/kategori-toko-list')
            const json = await res.json()
            this.kategoriTokoList = json.data ?? json
        } catch (e) {
            console.error('Gagal fetch kategori toko:', e)
        }
    },

    async fetchJenisBiayaList() {
        try {
            const res = await fetch('/api/pengajuan/jenis-biaya')
            const json = await res.json()
            this.jenisBiayaList = json.data ?? json
        } catch (e) {
            console.error('Gagal fetch jenis biaya:', e)
        }
    },

    // Revisi 8: hasil sekarang dibatasi 5 data TERCOCOK di server (proyeksi dashboard
    // ala tab Semua /perusahaan) — `searchPerusahaan` (state Alpine yang di-bind ke
    // input search di tabel-perusahaan.blade.php) dikirim sbg query param `search`,
    // bukan cuma filter array di client lagi (percuma kalau hasil server udah dibatasi 5).
    async fetchPerusahaanList() {
        this.loadingPerusahaan = true
        try {
            const params = new URLSearchParams({ jenis: this.pengajuan.jenis_pengajuan })
            if (this.searchPerusahaan) params.set('search', this.searchPerusahaan)
            const res = await fetch(`/api/pengajuan/perusahaan-list?${params}`)
            const json = await res.json()
            this.perusahaanList = json.data ?? json

            // Vendor baru Kiriman Rutin belum punya tarif -> ga ikut hasil fetch.
            // Selama sesi wizard, jaga vendor yang lagi dipilih tetap tampil di tabel.
            if (this.perusahaanTerpilih &&
                !this.perusahaanList.some(p => String(p.id_perusahaan) === String(this.perusahaanTerpilih.id_perusahaan))) {
                this.perusahaanList.push(this.perusahaanTerpilih)
            }
        } catch (e) {
            console.error('Gagal fetch perusahaan list:', e)
        } finally {
            this.loadingPerusahaan = false
        }
    },

    // Kiriman Rutin: area milik vendor terpilih di cabang user + tarif barangnya.
    async resolveRateCardForVendor() {
        const vid = this.pengajuanIdPerusahaanEkspedisi
        if (!vid) {
            this.rateCardVendorSkillIds = []
            this.rateCardAreas = []
            this.tarifKirimanRutinList = []
            return
        }
        this.loadingRateCard = true
        try {
            const res = await fetch(`/api/pengajuan/rate-card?id_perusahaan_ekspedisi=${vid}`)
            const json = await res.json()
            this.rateCardVendorSkillIds = json.id_vendor_skill ?? []
            this.rateCardAreas = json.areas ?? []
            if (this.rateCardVendorSkillIds.length) await this.fetchTarifKirimanRutin(this.rateCardVendorSkillIds)
            else this.tarifKirimanRutinList = []
        } catch (e) {
            console.error('Gagal resolve rate-card:', e)
            this.rateCardVendorSkillIds = []
            this.rateCardAreas = []
            this.tarifKirimanRutinList = []
        } finally {
            this.loadingRateCard = false
        }
    },

    // Tarif barang milik 1 area (id_vendor_skill).
    tarifUntukArea(idVendorSkill) {
        return this.tarifKirimanRutinList.filter(t => String(t.id_vendor_skill) === String(idVendorSkill))
    },

    // Kiriman Rutin: tepat 1 area — area terdaftar (id_skill) atau area baru (skillBaru).
    pilihAreaRutin(idSkill) {
        this.pengajuan.id_skill = [String(idSkill)]
        this.skillBaru = []
        this.areaBaruInput = ''
    },

    pilihAreaRutinBaru(nama) {
        const bersih = String(nama || '').trim().toUpperCase()
        if (!bersih) return
        const terdaftar = this.skillList.find(s => String(s.nama_skill).toUpperCase() === bersih)
        if (terdaftar) return this.pilihAreaRutin(terdaftar.id_skill)
        this.pengajuan.id_skill = []
        this.skillBaru = [bersih]
        this.areaBaruInput = ''
    },

    get areaRutinKey() {
        if (this.pengajuan.id_skill.length) return 'id:' + this.pengajuan.id_skill[0]
        const baru = this.skillBaru.find(s => String(s).trim() !== '')
        return baru ? 'baru:' + baru : null
    },

    get namaAreaRutin() {
        const key = this.areaRutinKey
        if (!key) return ''
        if (key.startsWith('baru:')) return key.slice(5)
        const id = key.slice(3)
        return this.rateCardAreas.find(a => String(a.id_skill) === id)?.nama_skill
            ?? this.skillList.find(s => String(s.id_skill) === id)?.nama_skill
            ?? id
    },

    // Tarif milik area Kiriman Rutin yang dipilih ([] kalau area belum dimiliki vendor).
    get tarifAreaTerpilih() {
        const key = this.areaRutinKey
        if (!key || !key.startsWith('id:')) return []
        const area = this.rateCardAreas.find(a => String(a.id_skill) === key.slice(3))
        return area ? this.tarifUntukArea(area.id_vendor_skill) : []
    },

    // Area cabang user yang belum dimiliki vendor terpilih.
    get areaCabangBukanVendor() {
        const milikVendor = this.rateCardAreas.map(a => String(a.id_skill))
        return this.skillList.filter(s => !milikVendor.includes(String(s.id_skill)))
    },

    // rateCardAreas, tapi area yang udah punya tarif ditaruh duluan (lebih relevan buat
    // dipilih) — dalam grup yang sama urutan alfabetis dari backend tetap kejaga (stable sort).
    get rateCardAreasSorted() {
        return this.rateCardAreas.slice().sort((a, b) =>
            (this.tarifUntukArea(b.id_vendor_skill).length > 0) - (this.tarifUntukArea(a.id_vendor_skill).length > 0)
        )
    },

    async fetchJenisBarangList() {
        try {
            const res = await fetch('/api/jenis-barang-kiriman')
            const json = await res.json()
            this.jenisBarangList = json.barang ?? []
        } catch (e) {
            console.error('Gagal fetch jenis barang:', e)
        }
    },

    async fetchKendaraanByPerusahaan(perusahaanId) {
        this.loadingKendaraanByPerusahaan = true
        this.kendaraanByPerusahaan = []
        try {
            const res = await fetch(`/api/pengajuan/kendaraan-by-perusahaan?perusahaan_id=${perusahaanId}`)
            const json = await res.json()
            this.kendaraanByPerusahaan = json.data ?? []
            // Kalau perusahaan belum punya kendaraan terdaftar, langsung buka form tambah baru
            this.showFormKendaraanBaru = this.kendaraanByPerusahaan.length === 0
        } catch (e) {
            console.error('Gagal fetch kendaraan by perusahaan:', e)
        } finally {
            this.loadingKendaraanByPerusahaan = false
        }
    },

    async fetchDokumenList() {
        this.loadingDokumen = true
        try {
            // Fetch dokumen dari backend (SJ + TO-ACB) berdasarkan tujuan & cabang.
            // Skill dikirim juga (bukan cuma cabang) biar backend nandain "skill cocok"
            // ke skill yang beneran dipilih di step2 utk pengajuan ini, bukan ke semua
            // skill yang dilayani cabang - lihat Batch Fix 16.
            const cabang = this.pengajuan.id_cabang || 'default'
            const tujuan = this.pengajuan.tujuan_penyewaan || 'Umum'
            const skill = encodeURIComponent(this.skillGabunganLabel.join(','))
            const kecuali = this.editId || ''
            const res = await fetch(`/api/dokumen/list?tujuan_penyewaan=${tujuan}&cabang=${cabang}&skill=${skill}&kecuali=${kecuali}`)
            const json = await res.json()

            // Map dokumen dengan ID unik (gunakan nomor_dokumen + tipe sebagai ID)
            this.dummyDokumen = (json.dokumen || []).map((d, idx) => ({
                id: d.nomor_dokumen,  // Use nomor_dokumen as unique ID
                ...d,
            }))
            if (this.pengajuan.tujuan_penyewaan === 'PAC' && this.dokumenDipilih.length === 0) {
                this.centangToAcbCabangTujuan()
            }
        } catch (e) {
            console.error('Gagal fetch dokumen list:', e)
            this.dummyDokumen = []
        } finally {
            this.loadingDokumen = false
        }
    },

    // PAC: centang TO-ACB yang Code-nya = cabang tujuan; lepas centang milik cabang tujuan sebelumnya.
    centangToAcbCabangTujuan(tujuanLama = null) {
        const tujuan = this.pengajuan.id_cabang_tujuan
        const idsLama = tujuanLama
            ? this.dummyDokumen.filter(d => d.tipe === 'TO-ACB' && d.cabang_tujuan === tujuanLama).map(d => d.id)
            : []
        const idsBaru = tujuan
            ? this.dummyDokumen.filter(d => d.tipe === 'TO-ACB' && d.cabang_tujuan === tujuan).map(d => d.id)
            : []
        const sisa = this.dokumenDipilih.filter(id => !idsLama.includes(id))
        this.dokumenDipilih = [...sisa, ...idsBaru.filter(id => !sisa.includes(id))]
    },

    async fetchLinkedDocumen(pengajuanId) {
        try {
            // Fetch linked dokumen dari junction table
            const res = await fetch(`/api/pengajuan/${pengajuanId}/documents`)
            const json = await res.json()

            if (!json.success) {
                console.error('Gagal fetch linked dokumen:', json)
                return
            }

            // Map linked dokumen IDs back to dokumenDipilih
            const linkedNomor = [
                ...(json.data.surat_jalans || []),
                ...(json.data.transfer_antar_cabang || [])
            ]

            // Find matching dokumen in dummyDokumen dan set as dipilih
            this.dokumenDipilih = this.dummyDokumen
                .filter(d => linkedNomor.includes(d.nomor_dokumen))
                .map(d => d.id)
        } catch (e) {
            console.error('Error fetching linked dokumen:', e)
        }
    },

    // Handle identitas owner file upload (multi-file, max 3) — form "perusahaan baru" step 1.
    handleIdentitasOwnerUpload(e) {
        const files = Array.from(e.target.files || [])
        if (files.length > 3) {
            notify('Maksimal 3 file saja!', 'warning')
            e.target.value = ''
            return
        }
        this.kendaraanBaru.identitas_owner_files = files
        this.kendaraanBaru.identitas_owner_previews = files.map(f => URL.createObjectURL(f))
    },

    // Draft wizard di localStorage, terpisah per user.
    get draftKey() {
        return 'pengajuan_draft:' + (window.__userId ?? 'anon')
    },

    // Draft yang masih berlaku, atau null (draft korup / versi lama / kedaluwarsa dihapus).
    bacaDraft() {
        let draft = null
        try {
            const raw = localStorage.getItem(this.draftKey)
            draft = raw ? JSON.parse(raw) : null
        } catch (e) {
            draft = null
        }
        const umur = draft ? Date.now() - Date.parse(draft.savedAt) : Infinity
        if (!draft || draft.version !== PENGAJUAN_DRAFT_VERSION || !(umur <= DRAFT_TTL_MS)) {
            this.hapusDraft()
            return null
        }
        return draft
    },

    hapusDraft() {
        try {
            localStorage.removeItem(this.draftKey)
        } catch (e) { /* storage tidak tersedia */ }
        this.adaDraft = false
    },

    saveDraft() {
        if (this.editId) return
        const draft = {
            version: PENGAJUAN_DRAFT_VERSION,
            step: this.step,
            subStepKendaraan: this.subStepKendaraan,
            kendaraanTerpilih: this.kendaraanTerpilih,
            perusahaanTerpilih: this.perusahaanTerpilih,
            pengajuan: this.pengajuan,
            skillBaru: this.skillBaru,
            biayaTambahan: this.biayaTambahan,
            dokumenDipilih: this.dokumenDipilih,
            detailKirimanRutin: this.detailKirimanRutin,
            detailKirimanArea: this.detailKirimanArea,
            kendaraanBaru: {
                perusahaan_mode: this.kendaraanBaru.perusahaan_mode,
                perusahaan_id: this.kendaraanBaru.perusahaan_id,
                nama_perusahaan: this.kendaraanBaru.nama_perusahaan,
                badan_usaha: this.kendaraanBaru.badan_usaha,
                no_telepon: this.kendaraanBaru.no_telepon,
                alamat_kantor: this.kendaraanBaru.alamat_kantor,
                id_skill: this.kendaraanBaru.id_skill,
                skillBaru: this.kendaraanBaru.skillBaru,
                jenis_kendaraan: this.kendaraanBaru.jenis_kendaraan,
                id_jenis_kendaraan: this.kendaraanBaru.id_jenis_kendaraan,
                plat_nomor_truk: this.kendaraanBaru.plat_nomor_truk,
                muatan_maksimal: this.kendaraanBaru.muatan_maksimal,
                // File upload (identitas owner) tidak bisa disimpan di draft.
            },
            savedAt: new Date().toISOString(),
        }
        try {
            localStorage.setItem(this.draftKey, JSON.stringify(draft))
            this.adaDraft = true
        } catch (e) {
            console.warn('Gagal simpan draft:', e)
        }
    },

    // Pulihkan draft, lalu buang pilihan yang sudah tidak valid di server.
    async loadDraft(draft = this.bacaDraft()) {
        if (!draft) return false

        this.step = draft.step >= 1 ? draft.step : 1
        this.kendaraanTerpilih = draft.kendaraanTerpilih ?? null
        this.perusahaanTerpilih = draft.perusahaanTerpilih ?? null
        this.subStepKendaraan = draft.subStepKendaraan ?? 1
        this.pengajuan = { ...this.pengajuan, ...draft.pengajuan, id_cabang: window.__userCabang || '' }
        this.skillBaru = draft.skillBaru ?? []
        this.biayaTambahan = draft.biayaTambahan ?? []
        this.dokumenDipilih = draft.dokumenDipilih ?? []
        this.detailKirimanRutin = draft.detailKirimanRutin ?? []
        this.detailKirimanArea = draft.detailKirimanArea ?? null
        if (draft.kendaraanBaru) {
            this.kendaraanBaru = { ...this.kendaraanBaru, ...draft.kendaraanBaru }
        }
        if (this.perusahaanTerpilih &&
            !this.perusahaanList.some(p => String(p.id_perusahaan) === String(this.perusahaanTerpilih.id_perusahaan))) {
            this.perusahaanList.push(this.perusahaanTerpilih)
        }

        const muat = []
        if (this.perusahaanTerpilih) muat.push(this.fetchKendaraanByPerusahaan(this.perusahaanTerpilih.id_perusahaan))
        if (this.pengajuanIdPerusahaanEkspedisi && this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') muat.push(this.resolveRateCardForVendor())
        if (this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) muat.push(this.fetchDokumenList())
        await Promise.all(muat)

        if (this.perusahaanTerpilih && this.kendaraanTerpilih && !this.kendaraanByPerusahaan.some(k => String(k.id) === String(this.kendaraanTerpilih.id))) {
            this.kendaraanTerpilih = null
            if (this.step > 1) this.step = 1
            this.subStepKendaraan = this.perusahaanTerpilih ? 2 : 1
        }
        if (this.pengajuan.tujuan_penyewaan) {
            this.dokumenDipilih = this.dokumenDipilih.filter(id => this.dummyDokumen.some(d => d.id === id))
        }
        const key = this.areaRutinKey
        if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin' && key && key.startsWith('id:')) {
            const id = key.slice(3)
            const valid = this.rateCardAreas.some(a => String(a.id_skill) === id) || this.skillList.some(s => String(s.id_skill) === id)
            if (!valid) {
                this.pengajuan.id_skill = []
                if (this.step > 1) this.step = 1
                this.subStepKendaraan = 2
            }
        }
        this.adaDraft = true
        return true
    },

    // Hapus draft dan mulai ulang form.
    clearDraft() {
        this.hapusDraft()
        window.location.reload()
    },

    // Tooltip untuk tombol Lanjut di step 1
    get tooltipStep1() {
        return this.kendaraanTerpilih ? '' : 'Pilih atau tambahkan kendaraan terlebih dahulu'
    },

    // Tooltip untuk tombol Lanjut di step 2
    // Derived state: id perusahaan ekspedisi dari perusahaanTerpilih (single source of truth)
    get pengajuanIdPerusahaanEkspedisi() {
        return this.perusahaanTerpilih?.id_perusahaan ?? null
    },

    // Mini-stepper step 1: apakah sub-step 2 sudah "beres".
    // Kiriman Rutin cuma butuh vendor; Sewa Truk butuh kendaraan.
    get step2Done() {
        return this.pengajuan.jenis_pengajuan === 'pengiriman_rutin'
            ? this.perusahaanTerpilih !== null && this.areaRutinKey !== null
            : this.kendaraanTerpilih !== null
    },

    get tooltipStep2() {
        const missing = []
        if (!this.pengajuan.tanggal_pengiriman) missing.push('Tanggal Pengiriman')
        if (this.pengajuan.jenis_pengajuan === 'sewa_truk' && !this.pengajuan.harga_sewa) missing.push('Harga Sewa')
        if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin' && !this.detailKirimanRutinValid) missing.push('Daftar Barang (pilih jenis barang & isi qty/harga tiap baris)')
        if (!this.pengajuan.tujuan_penyewaan) missing.push('Tujuan Penyewaan')
        if (this.pengajuan.tujuan_penyewaan === 'PAC' && !this.pengajuan.id_cabang_tujuan) missing.push('Cabang Tujuan')
        if (!this.adaSkillTerpilih) missing.push('Skill/Area (minimal 1)')
        if (!this.pengajuan.kategoriToko) missing.push('Kategori Toko')
        return missing.length ? 'Lengkapi dulu: ' + missing.join(', ') : ''
    },

    // Tooltip untuk tombol Lanjut di step 3
    get tooltipStep3() {
        if (this.beratMelebihi) return 'Berat muatan melebihi kapasitas kendaraan'
        if (this.dokumenDipilih.length === 0 && !(this.editId && this.pengajuan.value_muatan)) {
            return 'Pilih minimal 1 dokumen'
        }
        return ''
    },

    // Kiriman Rutin tujuan Toko tidak memakai dokumen: step 3 dikunci, step 2 langsung ke 4.
    get step3Dikunci() {
        return this.pengajuan.jenis_pengajuan === 'pengiriman_rutin' && this.pengajuan.tujuan_penyewaan === 'Toko'
    },

    get step2Lengkap() {
        const hasVendor = this.pengajuan.jenis_pengajuan === 'sewa_truk'
            ? this.kendaraanTerpilih !== null
            : this.perusahaanTerpilih !== null
        return hasVendor
            && this.pengajuan.tanggal_pengiriman !== ''
            && this.pengajuan.harga_sewa !== ''
            && this.pengajuan.tujuan_penyewaan !== ''
            && (this.pengajuan.tujuan_penyewaan !== 'PAC' || !!this.pengajuan.id_cabang_tujuan)
            && this.adaSkillTerpilih
            && this.pengajuan.kategoriToko !== ''
            && this.detailKirimanRutinValid
    },

    canGoToStep(target) {
        // Pas edit mode, nggak boleh balik ke step 1 (vendor/kendaraan dikunci, harga nempel ke vendor)
        if (this.editId && target === 1) return false
        if (target <= this.step) return true
        // Kiriman Rutin: Daftar Barang harus sudah diisi ulang untuk area yang sekarang dipilih.
        if (target >= 3 && this.pengajuan.jenis_pengajuan === 'pengiriman_rutin'
            && this.detailKirimanArea !== this.areaRutinKey) return false
        if (target === 2) {
            // Sewa Truk: kendaraan terpilih; Kiriman Rutin: vendor + 1 area terpilih
            if (this.pengajuan.jenis_pengajuan === 'sewa_truk') {
                return this.kendaraanTerpilih !== null
            } else {
                return this.perusahaanTerpilih !== null && this.areaRutinKey !== null
            }
        }
        if (target === 3) {
            return !this.step3Dikunci && this.step2Lengkap
        }
        if (target === 4) {
            if (this.step3Dikunci) return this.step2Lengkap
            // Edit mode: bisa skip dokumen selection (gunakan value_muatan dari database)
            if (this.editId) return true
            // Create mode: harus pilih dokumen
            return this.dokumenDipilih.length > 0 && !this.beratMelebihi
        }
        return false
    },

    async goToStep(target) {
        if (!this.canGoToStep(target)) return
        // Kiriman Rutin: Daftar Barang diisi dari tarif area terpilih; diisi ulang kalau area berubah.
        if (target === 2 && this.pengajuan.jenis_pengajuan === 'pengiriman_rutin'
            && this.detailKirimanArea !== this.areaRutinKey) {
            if (this.loadingRateCard || this.loadingTarif) {
                notify('Tarif area masih dimuat, coba lagi sebentar.', 'info')
                return
            }
            const adaQty = this.detailKirimanRutin.some(d => Number(d.quantity) > 0)
            if (adaQty && !(await confirmDialog('Area kirim berubah. Daftar Barang akan diisi ulang sesuai tarif area baru. Lanjut?'))) return
            this.prefillDetailKirimanFromTarif()
        }
        // Step 3: refetch dokumen supaya penanda "skill cocok" memakai area terbaru.
        if (target === 3 && this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) {
            this.fetchDokumenList()
        }
        this.step = target
    },

    canGoToSubStep(n) {
        // Mini-stepper step 1: 1=Perusahaan, 2=Kendaraan/Area, 3=Ringkasan
        if (n <= 1) return true
        if (this.perusahaanTerpilih === null) return false
        // Kiriman Rutin: ringkasan butuh area terpilih
        if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') return n === 2 || this.areaRutinKey !== null
        if (n === 2) return true
        if (n === 3) return this.kendaraanTerpilih !== null
        return false
    },

    // Pilih / batal pilih perusahaan di tabel step 1. Kalau jenis tarif perusahaan tidak
    // cocok dengan form aktif, tawarkan pindah form (perusahaan tetap terpilih).
    async pilihPerusahaan(p) {
        if (String(this.perusahaanTerpilih?.id_perusahaan) === String(p.id_perusahaan)) {
            this.kendaraanBaru.perusahaan_id = null
            this.perusahaanTerpilih = null
            this.kendaraanTerpilih = null
            return
        }

        const label = { sewa_truk: 'Sewa Truk', pengiriman_rutin: 'Kiriman Rutin' }
        const aktif = this.pengajuan.jenis_pengajuan
        const jenisTarif = p.has_sewa && !p.has_kiriman ? 'sewa_truk'
            : (p.has_kiriman && !p.has_sewa ? 'pengiriman_rutin' : null)

        if (!this.editId && jenisTarif && jenisTarif !== aktif) {
            const pindah = await confirmDialog(
                `Perusahaan ${p.nama_perusahaan} punya tarif ${label[jenisTarif]}, tapi kamu sedang di form ${label[aktif]}.`,
                { title: 'Jenis tarif tidak cocok', confirmText: `Pindah ke ${label[jenisTarif]}`, cancelText: `Tetap di ${label[aktif]}` }
            )
            if (pindah) return this.gantiJenis(jenisTarif, { tanpaKonfirmasi: true, perusahaan: p })
        }

        this.kendaraanTerpilih = null
        this.kendaraanBaru.perusahaan_id = p.id_perusahaan
        this.perusahaanTerpilih = p
    },

    // Ganti jenis pengajuan. Data yang khusus satu jenis (kendaraan/vendor/area/daftar barang/harga)
    // direset; `perusahaan` dipilih ulang setelah reset kalau diisi.
    async gantiJenis(newJenis, { tanpaKonfirmasi = false, perusahaan = null } = {}) {
        if (this.editId || newJenis === this.pengajuan.jenis_pengajuan) return
        const dirty = this.kendaraanTerpilih || this.perusahaanTerpilih
            || this.detailKirimanRutin.length > 0 || this.kendaraanBaru.perusahaan_id
        if (!tanpaKonfirmasi && dirty && !(await confirmDialog('Ganti jenis pengajuan akan mereset data kendaraan/vendor/daftar barang. Lanjut?'))) return

        this.pengajuan.jenis_pengajuan = newJenis
        this.pengajuan.id_skill = []
        this.skillBaru = []
        this.detailKirimanArea = null
        this.rateCardAreas = []
        this.kendaraanTerpilih = null
        this.perusahaanTerpilih = null
        this.kendaraanByPerusahaan = []
        this.detailKirimanRutin = []
        this.tarifKirimanRutinList = []
        this.rateCardVendorSkillIds = []
        this.pengajuan.harga_sewa = ''
        this.subStepKendaraan = 1
        this.showFormKendaraanBaru = false
        this.kendaraanBaru = {
            perusahaan_mode: 'pilih', perusahaan_id: null, nama_perusahaan: '',
            badan_usaha: '', no_telepon: '', alamat_kantor: '',
            identitas_owner_files: [], identitas_owner_previews: [],
            id_skill: [], skillBaru: [], jenis_kendaraan: '', plat_nomor_truk: '', muatan_maksimal: '',
        }
        if (this.step > 1) this.step = 1
        if (perusahaan) {
            this.kendaraanBaru.perusahaan_id = perusahaan.id_perusahaan
            this.perusahaanTerpilih = perusahaan
        }
        this.fetchPerusahaanList()
        this.saveDraft()
    },

    // Kiriman Rutin: tarif barang untuk satu atau beberapa id_vendor_skill.
    async fetchTarifKirimanRutin(idVendorSkill) {
        const ids = Array.isArray(idVendorSkill) ? idVendorSkill : [idVendorSkill]
        if (!ids.length || ids.every(id => !id)) { this.tarifKirimanRutinList = []; return }
        this.loadingTarif = true
        try {
            const qs = ids.map(id => `id_vendor_skill[]=${id}`).join('&')
            const res = await fetch(`/api/tarif-kiriman-rutin?${qs}`)
            const json = await res.json()
            this.tarifKirimanRutinList = json.tarif || []
        } catch (e) {
            console.error('Gagal fetch tarif:', e)
            this.tarifKirimanRutinList = []
        } finally {
            this.loadingTarif = false
        }
    },

    // Kiriman Rutin: Daftar Barang awal = tarif area terpilih, KG tinggal isi qty.
    prefillDetailKirimanFromTarif() {
        this.detailKirimanArea = this.areaRutinKey
        this.detailKirimanRutin = this.tarifAreaTerpilih.map(t => ({
            id_jenis_barang: t.id_jenis_barang,
            id_tarif_kiriman_rutin: t.id_tarif,
            jenis_barang: t.nama_barang,
            quantity: '',
            harga_satuan: t.biaya_per_unit,
            subtotal: '',
            tarif_baru: false,
            usulan_update_master: false,
            harga_custom: false,
        }))
    },

    // Kiriman Rutin: Tambah detail item (line item di tabel)
    tambahDetailItem() {
        this.detailKirimanRutin.push({
            id_jenis_barang: '',
            id_tarif_kiriman_rutin: null,
            jenis_barang: '',
            quantity: '',
            harga_satuan: '',
            subtotal: '',
            tarif_baru: false, // true kalau vendor ini belum punya tarif utk jenis barang ini
            usulan_update_master: false, // Part B — usul biaya_per_unit baris ini jadi harga master
            harga_custom: false, // mode edit: pertahankan/isi harga sendiri (bukan harga master)
        })
    },

    // Pilihan jenis barang per baris. Mode edit: baris baru tidak boleh memilih jenis
    // yang sudah ada di baris lain.
    opsiJenisBarang(idx) {
        const detail = this.detailKirimanRutin[idx]
        if (!this.editId || !detail || detail.tersimpan) return this.jenisBarangList
        const dipakai = this.detailKirimanRutin
            .filter((d, i) => i !== idx && d.id_jenis_barang)
            .map(d => String(d.id_jenis_barang))
        return this.jenisBarangList.filter(b => !dipakai.includes(String(b.id_jenis_barang)))
    },

    // Mode edit: harga baris yang sudah tersimpan tidak bisa diubah.
    hargaTerkunci(detail) {
        return !!(this.editId && detail.tersimpan)
    },

    // Data pembanding mode edit, dikelompokkan per kartu di step 4.
    ringkasanEdit() {
        const urut = arr => arr.map(String).sort()
        return {
            tanggal: String(this.pengajuan.tanggal_pengiriman || ''),
            tujuan: JSON.stringify([
                this.pengajuan.tujuan_penyewaan || '',
                this.pengajuan.tujuan_penyewaan === 'PAC' ? (this.pengajuan.id_cabang_tujuan || '') : '',
                urut([...this.pengajuan.id_skill, ...this.skillBaru]),
                this.pengajuan.kategoriToko || '',
            ]),
            barang: JSON.stringify(urut(this.detailKirimanRutin
                .filter(d => d.id_jenis_barang && Number(d.quantity) > 0)
                .map(d => d.id_jenis_barang + ':' + Number(d.quantity)))),
            biaya: JSON.stringify(urut(this.biayaTambahan.map(b => b.id_jenis_biaya + ':' + Number(b.nominal || 0)))),
            dokumen: JSON.stringify(urut(this.dokumenDipilih)),
            catatan: String(this.pengajuan.catatan || '').trim(),
        }
    },

    // Bagian yang berbeda dari data saat halaman edit dibuka.
    get perubahanEdit() {
        if (!this.editId || !this.dataAwalEdit) return []
        const label = {
            tanggal: 'Tanggal pengiriman',
            tujuan: 'Tujuan & area',
            barang: 'Daftar barang',
            biaya: 'Biaya tambahan',
            dokumen: 'Dokumen',
            catatan: 'Catatan',
        }
        const sekarang = this.ringkasanEdit()
        return Object.keys(label)
            .filter(k => sekarang[k] !== this.dataAwalEdit[k])
            .map(k => ({ bagian: k, label: label[k] }))
    },

    berubah(...bagian) {
        return this.perubahanEdit.some(p => bagian.includes(p.bagian))
    },

    // Kiriman Rutin: dipanggil saat user pilih jenis barang di 1 baris Daftar Barang.
    // Tarif vendor ada → harga master; tidak ada → user isi harga dan baris jadi usulan harga master.
    pilihJenisBarangDetail(detail) {
        // Mode edit: jenis yang pernah tersimpan (baris dihapus lalu ditambah lagi) pakai harga lamanya
        const awal = this.editId && this.barisAwalEdit.find(b => String(b.id_jenis_barang) === String(detail.id_jenis_barang))
        if (awal) {
            Object.assign(detail, { ...awal, quantity: detail.quantity })
            detail.subtotal = Number(detail.quantity || 0) * Number(detail.harga_satuan || 0)
            return
        }
        const tarif = this.tarifAreaTerpilih.find(t => t.id_jenis_barang == detail.id_jenis_barang)
        if (tarif) {
            detail.id_tarif_kiriman_rutin = tarif.id_tarif
            detail.jenis_barang = tarif.nama_barang
            detail.harga_satuan = tarif.biaya_per_unit
            detail.tarif_baru = false
            detail.usulan_update_master = false
        } else {
            const barang = this.jenisBarangList.find(b => b.id_jenis_barang == detail.id_jenis_barang)
            detail.id_tarif_kiriman_rutin = null
            detail.jenis_barang = barang?.nama_barang ?? ''
            detail.harga_satuan = ''
            detail.tarif_baru = true
            // Barang tanpa tarif master: harganya selalu diajukan jadi harga master
            detail.usulan_update_master = true
        }
        detail.subtotal = Number(detail.quantity || 0) * Number(detail.harga_satuan || 0)
    },

    // Kiriman Rutin (relevan pas edit pengajuan lama): cek apakah harga yang
    // di-snapshot di baris ini (harga_satuan, dikunci saat submit) beda dari
    // tarif resmi vendor SAAT INI. Tarif resmi bisa berubah setelah pengajuan
    // dibuat (dikelola manual lewat Kelola Tarif), jadi bisa aja beda.
    // Return harga tarif saat ini kalau beda, atau null kalau sama/tidak relevan.
    // Dipakai juga utk alert selisih saat KG mengetik harga usulan (beda dari master).
    cekSelisihTarif(detail) {
        if (detail.tarif_baru || !detail.id_tarif_kiriman_rutin) return null
        const tarifSaatIni = this.tarifAreaTerpilih.find(t => t.id_jenis_barang == detail.id_jenis_barang)
        if (!tarifSaatIni) return null
        if (Number(tarifSaatIni.biaya_per_unit) === Number(detail.harga_satuan)) return null
        return Number(tarifSaatIni.biaya_per_unit)
    },

    // Kiriman Rutin: centang/un-centang "ajukan sbg harga master". Un-centang di baris
    // yg punya master → harga balik ke harga master (input dikunci lagi).
    toggleUsulanHarga(detail) {
        if (detail.usulan_update_master || detail.tarif_baru || !detail.id_tarif_kiriman_rutin) return
        const tarif = this.tarifAreaTerpilih.find(t => t.id_jenis_barang == detail.id_jenis_barang)
        if (!tarif) return
        detail.harga_custom = false
        detail.harga_satuan = tarif.biaya_per_unit
        detail.subtotal = Number(detail.quantity || 0) * Number(detail.harga_satuan || 0)
    },

    // Usulan harga master yang sudah diputuskan WM/WH tidak bisa diubah lagi saat edit
    usulanTerkunci(status) {
        return status === 'approved' || status === 'rejected'
    },

    // Kiriman Rutin: Hapus detail item
    hapusDetailItem(idx) {
        this.detailKirimanRutin.splice(idx, 1)
    },

    async submitPengajuan() {
        // Validation: untuk create mode, dokumen harus dipilih
        if (!this.editId && this.dokumenDipilih.length === 0) {
            notify('Mohon pilih dokumen terlebih dahulu!', 'warning')
            return
        }

        // Branch-specific validation
        if (this.pengajuan.jenis_pengajuan === 'sewa_truk') {
            if (!this.kendaraanTerpilih || !this.pengajuan.tanggal_pengiriman ||
                !this.pengajuan.harga_sewa) {
                notify('Data tidak lengkap! Pastikan kendaraan, tanggal, dan harga sewa sudah diisi.', 'warning')
                return
            }
        } else { // pengiriman_rutin
            if (!this.perusahaanTerpilih || !this.pengajuan.tanggal_pengiriman ||
                !this.detailKirimanRutinValid) {
                notify('Data tidak lengkap! Pastikan vendor, tanggal, dan tiap baris Daftar Barang sudah pilih jenis barang & isi qty/harga.', 'warning')
                return
            }
        }

        this.submitting = true

        // Calculate total value_muatan
        let valueMuatan
        if (this.step3Dikunci) {
            this.dokumenDipilih = []
            valueMuatan = null
        } else if (this.editId && this.dokumenDipilih.length === 0) {
            // Edit mode tanpa re-select dokumen: gunakan value_muatan dari prefill
            valueMuatan = this.pengajuan.value_muatan
        } else {
            // Create mode atau edit mode dengan dokumen baru: hitung dari dokumen yang dipilih
            valueMuatan = this.valueMuatanDipilih
        }

        const payload = {
            jenis_pengajuan: this.pengajuan.jenis_pengajuan,
            tanggal_pengiriman: this.pengajuan.tanggal_pengiriman,
            harga_sewa: this.pengajuan.harga_sewa,
            value_muatan: valueMuatan,
            tujuan_penyewaan: this.pengajuan.tujuan_penyewaan,
            id_cabang_tujuan: this.pengajuan.tujuan_penyewaan === 'PAC' ? this.pengajuan.id_cabang_tujuan : null,
            id_skill: this.pengajuan.id_skill,
            skill_baru: this.skillBaru,
            kategori_toko: this.pengajuan.kategoriToko,
            catatan: this.pengajuan.catatan,
            biaya_tambahan: this.biayaTambahan.map(b => ({
                id_jenis_biaya: b.id_jenis_biaya,
                nominal: b.nominal,
            })),
            dokumen_dipilih: this.dokumenDipilih,
        }

        // Branch: add jenis-specific fields
        if (this.pengajuan.jenis_pengajuan === 'sewa_truk') {
            payload.id_kendaraan = this.kendaraanTerpilih.id
            // Part B — usul harga_sewa jadi harga master baru (independen dari approve/
            // reject pengajuan ini, diputuskan WM/WH di halaman approval).
            payload.usulan_harga_sewa = this.pengajuan.usulan_harga_sewa
        } else { // pengiriman_rutin
            payload.id_perusahaan_ekspedisi = this.pengajuanIdPerusahaanEkspedisi
            // Baris yang qty-nya masih kosong (prefill yang nggak jadi diajukan) nggak ikut dikirim.
            payload.detail_kiriman = this.detailKirimanRutin.filter(d => Number(d.quantity) > 0).map(d => ({
                id_jenis_barang: d.id_jenis_barang,
                quantity: d.quantity,
                // biaya_per_unit dipakai backend kalau tarif (vendor, jenis_barang) belum ada
                // (tarif baru) ATAU KG mencentang usulan harga master (harga usulan dipakai)
                biaya_per_unit: (d.tarif_baru || d.usulan_update_master || d.harga_custom) ? d.harga_satuan : null,
                harga_custom: !!d.harga_custom,
                // Part B — usul per baris (independen, bukan all-or-nothing per pengajuan).
                usulan_update_master: !!(d.usulan_update_master || d.tarif_baru),
            }))
        }

        const url = this.editId ? `/api/pengajuan/${this.editId}` : '/api/pengajuan/submit'
        const method = this.editId ? 'PUT' : 'POST'

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload)
            })

            const json = await res.json()
            this.submitting = false

            if (!res.ok) {
                notify('Gagal ' + (this.editId ? 'update' : 'submit') + ': ' + (json.message || 'Error tidak diketahui'), 'error')
                return
            }

            const msg = this.editId ? 'Pengajuan berhasil diperbarui!' : 'Pengajuan berhasil disubmit!'
            const redirectTo = this.editId ? `/pengajuan/${json.id_pengajuan}` : '/dashboard/kg'

            // Link dokumen ke pengajuan via junction table
            if (this.dokumenDipilih.length > 0) {
                await this.linkDokumenToPengajuan(json.id_pengajuan)
            }

            // Email notifikasi ke WM — dipicu SETELAH dokumen ter-link supaya daftar SJ di email terisi.
            // Gagal kirim notifikasi tidak boleh mengganggu alur submit.
            try {
                await fetch(`/api/pengajuan/${json.id_pengajuan}/notifikasi-baru`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ ulang: !!this.editId }),
                })
            } catch (e) {
                console.error('Gagal memicu notifikasi email:', e)
            }

            // Bersihkan draft setelah submit sukses
            this.hapusDraft()
            // Redirect begitu user nutup dialognya sendiri (bukan timer 1.5 detik
            // yang dulu ngikutin blocking-nya alert() native — SweetAlert2 non-blocking).
            await notify(msg + '\nID: ' + json.id_pengajuan, 'success')
            window.location.href = redirectTo
        } catch (e) {
            this.submitting = false
            notify('Error: ' + e.message, 'error')
        }
    },

    async linkDokumenToPengajuan(pengajuanId) {
        // Link each selected dokumen ke pengajuan via junction table
        try {
            for (const docId of this.dokumenDipilih) {
                const doc = this.dokumenMaster.find(d => d.id === docId)
                if (!doc) continue

                const endpoint = doc.tipe === 'SJ'
                    ? `/api/pengajuan/${pengajuanId}/surat-jalan/link`
                    : `/api/pengajuan/${pengajuanId}/transfer-antar-cabang/link`

                const payload = doc.tipe === 'SJ'
                    ? { id_surat_jalan: doc.nomor_dokumen }
                    : { id_to_acb: doc.nomor_dokumen }

                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload)
                })

                if (!res.ok) {
                    console.error(`Gagal link dokumen ${doc.nomor_dokumen}:`, await res.json())
                }
            }
        } catch (e) {
            console.error('Error linking dokumen:', e)
        }
    },

    async savePerusahaanBaru() {
        // Validasi: identitas_owner wajib
        if (this.kendaraanBaru.identitas_owner_files.length === 0) {
            notify('Mohon upload minimal 1 file identitas owner (KTP/NPWP/SIM)!', 'warning')
            return
        }

        const formData = new FormData()
        formData.append('nama_perusahaan', this.kendaraanBaru.nama_perusahaan)
        formData.append('badan_usaha', this.kendaraanBaru.badan_usaha)
        formData.append('no_telepon', this.kendaraanBaru.no_telepon)
        formData.append('alamat_kantor', this.kendaraanBaru.alamat_kantor)

        // Append identitas owner files
        this.kendaraanBaru.identitas_owner_files.forEach(file => {
            formData.append('identitas_owner[]', file)
        })

        try {
            const res = await fetch('/api/pengajuan/perusahaan', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            })
            const json = await res.json()
            if (!res.ok) {
                if (json.errors) {
                    const errMsg = Object.values(json.errors).flat().join('\n')
                    notify('Validation error:\n' + errMsg, 'error')
                } else {
                    notify('Gagal menyimpan perusahaan: ' + (json.message || 'Error tidak diketahui'), 'error')
                }
                return
            }
            // Perusahaan berhasil dibuat, set as terpilih
            this.perusahaanTerpilih = json.perusahaan
            this.kendaraanBaru.perusahaan_id = json.perusahaan.id_perusahaan
            this.perusahaanStep = 'pilih' // kembali ke mode pilih
            this.kendaraanBaru.perusahaan_mode = 'pilih'

            if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') {
                // Vendor-skill placeholder sudah dibuat backend — pakai buat fetch tarif (kosong dulu)
                this.rateCardVendorSkillIds = json.id_vendor_skill_list ?? []
                if (this.rateCardVendorSkillIds.length) this.fetchTarifKirimanRutin(this.rateCardVendorSkillIds)
            }

            // Refresh perusahaan list sehingga data baru muncul di tabel & Alpine state.
            // fetchPerusahaanList() otomatis nge-push vendor terpilih kalau ga ikut hasil fetch
            // (vendor baru Kiriman Rutin belum punya tarif).
            await this.fetchPerusahaanList()
            // Fetch kendaraan by perusahaan (Sewa Truk saja — perusahaan baru pasti kosong)
            if (this.pengajuan.jenis_pengajuan === 'sewa_truk') {
                this.fetchKendaraanByPerusahaan(json.perusahaan.id_perusahaan)
            }
            notify('Perusahaan berhasil disimpan!', 'success')
        } catch (e) {
            notify('Error: ' + e.message, 'error')
        }
    },

    async saveKendaraan() {
        // Validasi: perusahaan harus dipilih/dibuat dulu
        if (this.kendaraanBaru.perusahaan_mode === 'pilih' && !this.kendaraanBaru.perusahaan_id) {
            notify('Mohon pilih perusahaan terlebih dahulu!', 'warning')
            return
        }
        if (this.savingKendaraan) return // guard submit ganda / double-click
        this.savingKendaraan = true

        const formData = new FormData()
        formData.append('perusahaan_id', this.kendaraanBaru.perusahaan_id)

        // id_skill = area existing (numerik, dari checkbox) — skill_baru = nama area baru
        // (teks bebas). Dikirim TERPISAH — jangan digabung, storeKendaraan() meresolve
        // skill_baru by NAME dan id_skill langsung dipakai apa adanya (sudah numeric id).
        this.kendaraanBaru.id_skill.forEach(skill => {
            formData.append('id_skill[]', skill)
        })
        this.kendaraanBaru.skillBaru.filter(s => s.trim() !== '').forEach(skill => {
            formData.append('skill_baru[]', skill)
        })

        formData.append('jenis_kendaraan', this.kendaraanBaru.jenis_kendaraan || '')
        formData.append('id_jenis_kendaraan', this.kendaraanBaru.id_jenis_kendaraan || '')
        formData.append('plat_nomor_truk', this.kendaraanBaru.plat_nomor_truk || '')
        formData.append('muatan_maksimal', this.kendaraanBaru.muatan_maksimal)

        try {
            const res = await fetch('/api/pengajuan/kendaraan', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            })
            const json = await res.json()
            if (!res.ok) {
                if (json.errors) {
                    const errMsg = Object.values(json.errors).flat().join('\n')
                    notify('Validation error:\n' + errMsg, 'error')
                } else {
                    notify('Gagal menyimpan: ' + (json.message || 'Error tidak diketahui'), 'error')
                }
                return
            }
            this.kendaraanTerpilih = json.kendaraan
            // Reset form tambah kendaraan biar ga ke-resubmit dgn data yang sama
            this.showFormKendaraanBaru = false
            this.kendaraanBaru.jenis_kendaraan = ''
            this.kendaraanBaru.id_jenis_kendaraan = ''
            this.kendaraanBaru.plat_nomor_truk = ''
            this.kendaraanBaru.muatan_maksimal = ''
            this.kendaraanBaru.id_skill = []
            this.kendaraanBaru.skillBaru = []
            // Refresh daftar kendaraan existing perusahaan ini biar truk baru ikut kelihatan
            if (this.kendaraanBaru.perusahaan_id) {
                this.fetchKendaraanByPerusahaan(this.kendaraanBaru.perusahaan_id)
            }
            notify('Kendaraan berhasil disimpan!', 'success')
            this.subStepKendaraan = 3
        } catch (e) {
            notify('Error: ' + e.message, 'error')
        } finally {
            this.savingKendaraan = false
        }
    },
}))

// Filter tanggal dashboard: rentang maks `maksHari`, tanggal tidak boleh melewati hari ini.
// Filter periode dashboard (components/filter-tanggal-dashboard). Tanggal = string 'YYYY-MM-DD'.
// Klik pertama = tanggal mulai, akhir otomatis mulai + (maksHari - 1) dibatasi hari ini;
// klik kedua di dalam rentang itu = tanggal akhir.
Alpine.data('filterTanggalDashboard', (dari, sampai, maksHari) => {
    const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    const BULAN_PANJANG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    const keStr = (d) => d.toLocaleDateString('en-CA')
    const geser = (tanggal, hari) => {
        const d = new Date(tanggal + 'T00:00:00')
        d.setDate(d.getDate() + hari)
        return keStr(d)
    }
    const hariIni = keStr(new Date())
    const minimal = (a, b) => (a < b ? a : b)

    const hariDalamMinggu = (new Date(hariIni + 'T00:00:00').getDay() + 6) % 7 // Senin = 0
    const seninIni = geser(hariIni, -hariDalamMinggu)
    const preset = [
        { nama: 'Hari ini', dari: hariIni, sampai: hariIni },
        { nama: 'Kemarin', dari: geser(hariIni, -1), sampai: geser(hariIni, -1) },
        { nama: `${maksHari} hari terakhir`, dari: geser(hariIni, -(maksHari - 1)), sampai: hariIni },
        { nama: 'Minggu ini', dari: seninIni, sampai: hariIni },
        { nama: 'Minggu lalu', dari: geser(seninIni, -7), sampai: geser(seninIni, -1) },
    ]

    return {
        dari,
        sampai,
        hariIni,
        preset,
        buka: false,
        pilihDari: dari,
        pilihSampai: sampai,
        menungguAkhir: false,
        tahun: 0,
        bulan: 0,

        toggle() {
            if (this.buka) {
                this.buka = false
                return
            }
            this.pilihDari = this.dari
            this.pilihSampai = this.sampai
            this.menungguAkhir = false
            this.tampilkanBulan(this.sampai)
            this.buka = true
        },

        tampilkanBulan(tanggal) {
            this.tahun = Number(tanggal.slice(0, 4))
            this.bulan = Number(tanggal.slice(5, 7)) - 1
        },

        geserBulan(n) {
            const d = new Date(this.tahun, this.bulan + n, 1)
            this.tahun = d.getFullYear()
            this.bulan = d.getMonth()
        },

        get bisaMaju() {
            return keStr(new Date(this.tahun, this.bulan + 1, 1)) <= hariIni
        },

        get judulBulan() {
            return `${BULAN_PANJANG[this.bulan]} ${this.tahun}`
        },

        // Sel kalender minggu penuh (Senin di kolom pertama); tanggal bulan sebelum/sesudah
        // ikut tampil (luar: true) supaya sorotan periode lintas bulan tetap menyambung.
        get hariBulan() {
            const mulai = -((new Date(this.tahun, this.bulan, 1).getDay() + 6) % 7)
            const jumlah = new Date(this.tahun, this.bulan + 1, 0).getDate()
            const total = Math.ceil((jumlah - mulai) / 7) * 7
            const sel = []
            for (let i = 0; i < total; i++) {
                const d = new Date(this.tahun, this.bulan, mulai + i + 1)
                sel.push({ tgl: keStr(d), hari: d.getDate(), luar: d.getMonth() !== this.bulan })
            }
            return sel
        },

        klikTanggal(t) {
            if (t > hariIni) return
            if (this.menungguAkhir && t >= this.pilihDari && t <= geser(this.pilihDari, maksHari - 1)) {
                this.pilihSampai = t
                this.menungguAkhir = false
                return
            }
            this.pilihDari = t
            this.pilihSampai = minimal(geser(t, maksHari - 1), hariIni)
            this.menungguAkhir = true
        },

        pilihPreset(p) {
            this.pilihDari = p.dari
            this.pilihSampai = p.sampai
            this.menungguAkhir = false
            this.tampilkanBulan(p.sampai)
        },

        terapkan() {
            this.dari = this.pilihDari
            this.sampai = this.pilihSampai
            this.buka = false
            this.$nextTick(() => this.$root.submit())
        },

        kelasTanggal(sel) {
            const t = sel.tgl
            if (t === this.pilihDari || t === this.pilihSampai) return 'bg-avian-green font-semibold text-white'
            if (t > this.pilihDari && t < this.pilihSampai) return sel.luar ? 'text-avian-green/60' : 'font-medium text-avian-green'
            if (sel.luar) return 'text-gray-400 hover:bg-gray-100'
            return t === hariIni ? 'font-semibold text-avian-green ring-1 ring-avian-green/40 hover:bg-gray-100' : 'text-gray-700 hover:bg-gray-100'
        },

        // Pita rentang di belakang tombol tanggal; ujung mulai/akhir dari tengah lingkaran.
        kelasRentang(t) {
            if (t < this.pilihDari || t > this.pilihSampai || this.pilihDari === this.pilihSampai) return ''
            if (t === this.pilihDari) return 'bg-linear-to-r from-transparent from-50% to-avian-green-light to-50%'
            if (t === this.pilihSampai) return 'bg-linear-to-r from-avian-green-light from-50% to-transparent to-50%'
            return 'bg-avian-green-light'
        },

        namaPreset(a, b) {
            return preset.find((p) => p.dari === a && p.sampai === b)?.nama ?? null
        },

        label(a, b) {
            const [ta, ba, ha] = a.split('-').map(Number)
            const [tb, bb, hb] = b.split('-').map(Number)
            const akhir = `${hb} ${BULAN[bb - 1]} ${tb}`
            if (a === b) return akhir
            if (ta !== tb) return `${ha} ${BULAN[ba - 1]} ${ta} – ${akhir}`
            if (ba !== bb) return `${ha} ${BULAN[ba - 1]} – ${akhir}`
            return `${ha} – ${akhir}`
        },

        jumlahHari(a, b) {
            return Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000) + 1
        },
    }
})

// Ikon duluan, baru Alpine.start() — createIcons() gak butuh Alpine selesai
// hydrate, dan halaman yang lagi hydrate banyak komponen x-data (mis. /perusahaan
// tab Semua dgn banyak baris) bikin Alpine.start() lumayan makan waktu di thread
// yang sama; kalau createIcons() nunggu di belakang, ikon (termasuk sidebar)
// kelihatan kosong lebih lama/lebih kentara di halaman yang "ramai" itu (Jo, 30
// Sept 2026). Dua-duanya independen, jadi urutan dibalik + gak perlu nunggu
// DOMContentLoaded sama sekali (module script Vite udah jalan setelah DOM ke-parse).
createIcons({ icons })

window.Alpine = Alpine;
Alpine.start();