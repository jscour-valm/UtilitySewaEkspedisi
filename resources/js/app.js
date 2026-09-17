import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

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

// Naikkan versi ini tiap kali struktur draft (field di dalam pengajuan,
// biayaTambahan, atau detailKirimanRutin) berubah — draft lama dgn versi
// beda otomatis dibuang saat load, biar ga ke-restore setengah-setengah
// (misal field baru jadi kosong/ga ke-mapping ke dropdown).
const PENGAJUAN_DRAFT_VERSION = 4

Alpine.data('pengajuanSewa', () => ({
    step: 1, // 1-4=wizard steps (jenis pengajuan dipilih via toggle persisten)
    editId: null,
    searchKendaraan: '',
    searchPerusahaan: '',
    previewImageUrl: null,
    kendaraanTerpilih: null,
    subStepKendaraan: 1, // 1=Perusahaan, 2=Kendaraan, 3=Ringkasan
    perusahaanList: [],
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
        plat_nomor_truk: '',
        muatan_maksimal: '',
    },
    skillList: [],
    kategoriTokoList: [],
    jenisBiayaList: [],
    pengajuan: {
        jenis_pengajuan: 'sewa_truk', // 'sewa_truk' | 'pengiriman_rutin'
        tanggal_pengiriman: '',
        tujuan_penyewaan: '',
        id_skill: [],
        kategoriToko: '',
        harga_sewa: '',
        value_muatan: '',
        catatan: '',
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

    // Kiriman Rutin specific state
    detailKirimanRutin: [], // [{id_jenis_barang, id_tarif_kiriman_rutin, jenis_barang, quantity, harga_satuan, subtotal, tarif_baru}]
    tarifKirimanRutinList: [], // [{id_tarif, id_jenis_barang, jenis_barang, biaya_per_unit}] — tarif yg SUDAH terdaftar utk vendor terpilih
    rateCardVendorSkillIds: [], // array id_vendor_skill (sesi_perusahaan_skill) utk vendor+cabang — Kiriman Rutin
    jenisBarangList: [], // [{id_jenis_barang, nama_barang}] — semua master jenis barang (selalu ditampilkan di dropdown)
    loadingTarif: false,

    // Kiriman Rutin: form "Lengkapi Data Vendor" di step 2 (badan_usaha/telepon/alamat/identitas).
    // Prefill dari perusahaanTerpilih; kalau dirty → PATCH sesi_perusahaan_ekspedisi saat submit.
    vendorEdit: {
        badan_usaha: '', no_telepon: '', alamat_kantor: '',
        identitas_owner_files: [], identitas_owner_previews: [], dirty: false,
    },

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

    // Validasi Daftar Barang (Kiriman Rutin): tiap baris harus punya jenis barang,
    // qty > 0, dan harga (dari tarif existing ATAU input manual utk tarif baru)
    get detailKirimanRutinValid() {
        if (this.pengajuan.jenis_pengajuan !== 'pengiriman_rutin') return true
        if (this.detailKirimanRutin.length === 0) return false
        return this.detailKirimanRutin.every(d =>
            d.id_jenis_barang !== '' && d.id_jenis_barang !== null
            && Number(d.quantity) > 0
            && (d.id_tarif_kiriman_rutin || (d.tarif_baru && Number(d.harga_satuan) > 0))
        )
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

        // Fetch master data paralel dulu sebelum prefill
        await Promise.all([
            this.fetchSkillList(),
            this.fetchKategoriTokoList(),
            this.fetchJenisBiayaList(),
            this.fetchPerusahaanList(),
            this.fetchJenisBarangList(),
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
            this.pengajuan.kategoriToko = data.kategori_toko
            this.pengajuan.catatan = data.catatan_pengajuan
            this.pengajuan.value_muatan = data.value_muatan // Prefill value_muatan dari database
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
                // id_perusahaan_ekspedisi derived dari perusahaanTerpilih via getter
                this.detailKirimanRutin = data.detail_kiriman_dipilih || []
                // Fetch tarif list untuk vendor-skill ini (by id_vendor_skill[])
                this.rateCardVendorSkillIds = data.id_vendor_skill_list ?? []
                if (this.rateCardVendorSkillIds.length) {
                    this.fetchTarifKirimanRutin(this.rateCardVendorSkillIds)
                } else {
                    this.resolveRateCardForVendor()
                }
            }

            // Skill udah pernah dikunci sebelumnya
            this.skillLocked = true
            this.step = 2
            // Hapus draft lama supaya tidak nyasar ke sesi create berikutnya
            localStorage.removeItem('pengajuan_draft')

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
                // Restore draft lama dulu (biayaTambahan, skillBaru, detailKirimanRutin, dll) supaya
                // kerjaan yang belum sempat disubmit gak hilang diam-diam gara-gara masuk lewat ?id=.
                // kendaraanTerpilih & step di bawah ini tetap override draft, karena itu intent eksplisit
                // dari tombol "Pilih" kendaraan di dashboard.
                this.loadDraft()
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
            } else {
                // Jalur create murni tanpa query param — coba restore draft lama
                this.loadDraft()
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
        this.$watch('vendorEdit', () => this.saveDraft(), { deep: true })

        // perusahaanTerpilih berubah → prefill form Data Vendor (kedua jenis pengajuan,
        // sejak dipindah ke mini-stepper step1 sub-step 2); Kiriman Rutin juga fetch tarif.
        this.$watch('perusahaanTerpilih', () => {
            if (!this.pengajuanIdPerusahaanEkspedisi) return
            if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') {
                this.resolveRateCardForVendor()
            }
            this.prefillVendorEdit()
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
        try {
            const res = await fetch('/api/pengajuan/skill-list')
            const json = await res.json()
            // safeQuery returns { data: [...], error: null }
            this.skillList = json.data ?? json
        } catch (e) {
            console.error('Gagal fetch skill list:', e)
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

    async fetchPerusahaanList() {
        try {
            const res = await fetch(`/api/pengajuan/perusahaan-list?jenis=${this.pengajuan.jenis_pengajuan}`)
            const json = await res.json()
            this.perusahaanList = json.data ?? json

            // Vendor baru Kiriman Rutin belum punya tarif -> ga ikut hasil fetch.
            // Selama sesi wizard, jaga vendor yang lagi dipilih tetap tampil di tabel.
            if (this.perusahaanTerpilih &&
                !this.perusahaanList.some(p => p.id_perusahaan === this.perusahaanTerpilih.id_perusahaan)) {
                this.perusahaanList.push(this.perusahaanTerpilih)
            }
        } catch (e) {
            console.error('Gagal fetch perusahaan list:', e)
        }
    },

    // Resolve vendor-skill (id_vendor_skill[]) utk vendor terpilih di cabang
    // user, lalu fetch tarif barang yang sudah terdaftar. Dipakai Kiriman Rutin.
    async resolveRateCardForVendor() {
        const vid = this.pengajuanIdPerusahaanEkspedisi
        if (!vid) {
            this.rateCardVendorSkillIds = []
            this.tarifKirimanRutinList = []
            return
        }
        try {
            const res = await fetch(`/api/pengajuan/rate-card?id_perusahaan_ekspedisi=${vid}`)
            const json = await res.json()
            this.rateCardVendorSkillIds = json.id_vendor_skill ?? []
        } catch (e) {
            console.error('Gagal resolve rate-card:', e)
            this.rateCardVendorSkillIds = []
        }
        if (this.rateCardVendorSkillIds.length) this.fetchTarifKirimanRutin(this.rateCardVendorSkillIds)
        else this.tarifKirimanRutinList = []
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
        try {
            // Fetch dokumen dari backend (SJ + TO-ACB) berdasarkan tujuan & cabang.
            // Skill dikirim juga (bukan cuma cabang) biar backend nandain "skill cocok"
            // ke skill yang beneran dipilih di step2 utk pengajuan ini, bukan ke semua
            // skill yang dilayani cabang - lihat Batch Fix 16.
            const cabang = this.pengajuan.id_cabang || 'default'
            const tujuan = this.pengajuan.tujuan_penyewaan || 'Umum'
            const skill = encodeURIComponent(this.skillGabunganLabel.join(','))
            const res = await fetch(`/api/dokumen/list?tujuan_penyewaan=${tujuan}&cabang=${cabang}&skill=${skill}`)
            const json = await res.json()

            // Map dokumen dengan ID unik (gunakan nomor_dokumen + tipe sebagai ID)
            this.dummyDokumen = (json.dokumen || []).map((d, idx) => ({
                id: d.nomor_dokumen,  // Use nomor_dokumen as unique ID
                ...d,
            }))
        } catch (e) {
            console.error('Gagal fetch dokumen list:', e)
            this.dummyDokumen = []
        }
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

    // Handle identitas owner file upload (multi-file, max 3).
    // target: 'kendaraanBaru' (default, form perusahaan baru step 1) atau 'vendorEdit' (form Data Vendor step 2)
    handleIdentitasOwnerUpload(e, target = 'kendaraanBaru') {
        const files = Array.from(e.target.files || [])
        if (files.length > 3) {
            alert('Maksimal 3 file saja!')
            e.target.value = ''
            return
        }
        const bucket = target === 'vendorEdit' ? this.vendorEdit : this.kendaraanBaru
        bucket.identitas_owner_files = files
        bucket.identitas_owner_previews = files.map(f => URL.createObjectURL(f))
        if (target === 'vendorEdit') this.vendorEdit.dirty = true
    },

    // Kiriman Rutin: prefill form "Lengkapi Data Vendor" dari vendor terpilih
    prefillVendorEdit() {
        const p = this.perusahaanTerpilih
        this.vendorEdit = {
            badan_usaha: p?.badan_usaha ?? '',
            no_telepon: p?.no_telepon ?? '',
            alamat_kantor: p?.alamat_kantor ?? '',
            identitas_owner_files: [],
            identitas_owner_previews: [],
            dirty: false,
        }
    },

    // Save wizard state to localStorage
    saveDraft() {
        if (this.editId) return // Jangan simpan draft saat edit mode
        const draft = {
            version: PENGAJUAN_DRAFT_VERSION,
            step: this.step, // Save step untuk restore (UX: user refresh mid-form → balik ke step terakhir)
            subStepKendaraan: this.subStepKendaraan,
            kendaraanTerpilih: this.kendaraanTerpilih,
            perusahaanTerpilih: this.perusahaanTerpilih,
            pengajuan: this.pengajuan,
            skillBaru: this.skillBaru,
            biayaTambahan: this.biayaTambahan,
            dokumenDipilih: this.dokumenDipilih,
            detailKirimanRutin: this.detailKirimanRutin, // Kiriman Rutin line items
            rateCardVendorSkillIds: this.rateCardVendorSkillIds,
            vendorEdit: {
                badan_usaha: this.vendorEdit.badan_usaha,
                no_telepon: this.vendorEdit.no_telepon,
                alamat_kantor: this.vendorEdit.alamat_kantor,
                dirty: this.vendorEdit.dirty,
                // EXCLUDE: identitas_owner_files / previews (File & blob URL)
            },
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
                plat_nomor_truk: this.kendaraanBaru.plat_nomor_truk,
                muatan_maksimal: this.kendaraanBaru.muatan_maksimal,
                // EXCLUDE: identitas_owner_files, identitas_owner_previews (File objects & blob URLs)
            },
            savedAt: new Date().toISOString(),
        }
        try {
            localStorage.setItem('pengajuan_draft', JSON.stringify(draft))
        } catch (e) {
            console.warn('Gagal simpan draft:', e)
        }
    },

    // Load wizard state from localStorage
    loadDraft() {
        const raw = localStorage.getItem('pengajuan_draft')
        if (!raw) return false
        try {
            const draft = JSON.parse(raw)
            // Draft dari versi struktur lama (sebelum field2 baru ditambahkan) dibuang
            // total — restore parsial bikin dropdown ga ke-mapping (field baru kosong).
            if (draft.version !== PENGAJUAN_DRAFT_VERSION) {
                localStorage.removeItem('pengajuan_draft')
                return false
            }
            if (draft.step && draft.step >= 1) {
                this.step = draft.step
            }
            this.kendaraanTerpilih = draft.kendaraanTerpilih ?? null
            this.perusahaanTerpilih = draft.perusahaanTerpilih ?? null
            // Restore subStepKendaraan, fallback ke estimasi dari data kalau draft lama (pre-migrasi)
            this.subStepKendaraan = draft.subStepKendaraan ?? (this.kendaraanTerpilih ? 3 : (this.perusahaanTerpilih ? 2 : 1))
            this.pengajuan = { ...this.pengajuan, ...draft.pengajuan }
            this.skillBaru = draft.skillBaru ?? []
            this.biayaTambahan = draft.biayaTambahan ?? []
            this.dokumenDipilih = draft.dokumenDipilih ?? []
            this.detailKirimanRutin = draft.detailKirimanRutin ?? [] // Kiriman Rutin
            this.rateCardVendorSkillIds = draft.rateCardVendorSkillIds ?? []
            if (draft.kendaraanBaru) {
                this.kendaraanBaru = { ...this.kendaraanBaru, ...draft.kendaraanBaru }
            }
            // draft.vendorEdit HAMPIR SELALU ada (state selalu ke-save walau masih default
            // kosong) — jadi cuma percaya isinya kalau memang udah pernah diubah user
            // (dirty=true). Kalau belum pernah disentuh, selalu prefill ulang dari
            // perusahaanTerpilih yang fresh (bukan draft lama yang mungkin masih kosong).
            if (draft.vendorEdit?.dirty) {
                this.vendorEdit = { ...this.vendorEdit, ...draft.vendorEdit, identitas_owner_files: [], identitas_owner_previews: [] }
            } else if (this.perusahaanTerpilih) {
                this.prefillVendorEdit()
            }
            // Vendor baru (tanpa tarif) ga ikut fetch list — jaga tetap tampil di tabel
            if (this.perusahaanTerpilih &&
                !this.perusahaanList.some(p => p.id_perusahaan === this.perusahaanTerpilih.id_perusahaan)) {
                this.perusahaanList.push(this.perusahaanTerpilih)
            }
            // Refetch kendaraan kalau perusahaan sudah terpilih (Sewa Truk)
            if (this.perusahaanTerpilih && this.pengajuan.jenis_pengajuan === 'sewa_truk') {
                this.fetchKendaraanByPerusahaan(this.perusahaanTerpilih.id_perusahaan)
            }
            // Refetch tarif kalau vendor sudah terpilih (Kiriman Rutin)
            if (this.pengajuanIdPerusahaanEkspedisi && this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') {
                if (this.rateCardVendorSkillIds.length) this.fetchTarifKirimanRutin(this.rateCardVendorSkillIds)
                else this.resolveRateCardForVendor()
            }
            // Refetch dokumen (SJ/TO-ACB) kalau tujuan_penyewaan & id_cabang sudah ada dari draft —
            // watcher pengajuan.tujuan_penyewaan/id_cabang yang biasanya trigger ini baru didaftarkan
            // SETELAH loadDraft() selesai, jadi restore lewat assignment bulk di atas ga otomatis kepicu.
            if (this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) {
                this.fetchDokumenList()
            }
            return true
        } catch (e) {
            console.warn('Draft korup, diabaikan:', e)
            localStorage.removeItem('pengajuan_draft')
            return false
        }
    },

    // Clear draft dan reload halaman
    clearDraft() {
        localStorage.removeItem('pengajuan_draft')
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
            ? this.perusahaanTerpilih !== null
            : this.kendaraanTerpilih !== null
    },

    get tooltipStep2() {
        const missing = []
        if (!this.pengajuan.tanggal_pengiriman) missing.push('Tanggal Pengiriman')
        if (this.pengajuan.jenis_pengajuan === 'sewa_truk' && !this.pengajuan.harga_sewa) missing.push('Harga Sewa')
        if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin' && !this.detailKirimanRutinValid) missing.push('Daftar Barang (pilih jenis barang & isi qty/harga tiap baris)')
        if (!this.pengajuan.tujuan_penyewaan) missing.push('Tujuan Penyewaan')
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

    canGoToStep(target) {
        if (target <= this.step) return true
        // Pas edit mode, nggak boleh balik ke step 1 (kendaraan dikunci)
        if (this.editId && target === 1) return false
        if (target === 2) {
            // Sewa Truk: need kendaraanTerpilih; Kiriman Rutin: need perusahaanTerpilih
            if (this.pengajuan.jenis_pengajuan === 'sewa_truk') {
                return this.kendaraanTerpilih !== null
            } else {
                return this.perusahaanTerpilih !== null
            }
        }
        if (target === 3) {
            const hasVendor = this.pengajuan.jenis_pengajuan === 'sewa_truk'
                ? this.kendaraanTerpilih !== null
                : this.perusahaanTerpilih !== null
            return hasVendor
            && this.pengajuan.tanggal_pengiriman !== ''
            && this.pengajuan.harga_sewa !== ''
            && this.pengajuan.tujuan_penyewaan !== ''
            && this.adaSkillTerpilih
            && this.pengajuan.kategoriToko !== ''
            && this.detailKirimanRutinValid
        }
        if (target === 4) {
            // Edit mode: bisa skip dokumen selection (gunakan value_muatan dari database)
            if (this.editId) return true
            // Create mode: harus pilih dokumen
            return this.dokumenDipilih.length > 0 && !this.beratMelebihi
        }
        return false
    },

    goToStep(target) {
        if (!this.canGoToStep(target)) return
        // Masuk ke step3: refetch dokumen supaya label "skill cocok" pakai skill
        // TERBARU yang dipilih di step2 (fetch awal - dipicu watcher tujuan_penyewaan -
        // bisa aja kejadian sebelum user selesai centang skill). Lihat Batch Fix 16.
        if (target === 3 && this.pengajuan.tujuan_penyewaan && this.pengajuan.id_cabang) {
            this.fetchDokumenList()
        }
        this.step = target
    },

    canGoToSubStep(n) {
        // Mini-stepper step 1: 1=Perusahaan, 2=Kendaraan/Area, 3=Ringkasan
        if (n <= 1) return true
        if (this.perusahaanTerpilih === null) return false
        // Kiriman Rutin: vendor cukup (rate-card/tarif diisi di step 2)
        if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin') return true
        if (n === 2) return true
        if (n === 3) return this.kendaraanTerpilih !== null
        return false
    },

    // Ganti jenis pengajuan via toggle persisten. Reset data yang cross-jenis
    // (kendaraan/vendor/daftar barang/harga), pertahankan field bersama.
    gantiJenis(newJenis) {
        if (this.editId || newJenis === this.pengajuan.jenis_pengajuan) return
        const dirty = this.kendaraanTerpilih || this.perusahaanTerpilih
            || this.detailKirimanRutin.length > 0 || this.kendaraanBaru.perusahaan_id
        if (dirty && !confirm('Ganti jenis pengajuan akan mereset data kendaraan/vendor/daftar barang. Lanjut?')) return

        this.pengajuan.jenis_pengajuan = newJenis
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
        this.vendorEdit = { badan_usaha: '', no_telepon: '', alamat_kantor: '', identitas_owner_files: [], identitas_owner_previews: [], dirty: false }
        if (this.step > 1) this.step = 1
        this.fetchPerusahaanList()
        this.saveDraft()
    },

    handleDocUpload(e) {
        const file = e.target.files[0]
        if (!file) return
        console.log('Dokumen dipilih:', file.name)
    },

    // Kiriman Rutin: Fetch tarif list untuk vendor-skill tertentu (by
    // id_vendor_skill) — terima 1 id atau array id (1 cabang bisa punya
    // beberapa skill sekaligus, masing2 1 baris sesi_perusahaan_skill).
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
        })
    },

    // Kiriman Rutin: dipanggil saat user pilih jenis barang di 1 baris Daftar Barang.
    // Cari tarif existing utk (vendor terpilih, jenis barang ini); kalau ga ketemu,
    // biarkan user isi harga manual (akan didaftarkan sbg tarif baru saat submit).
    pilihJenisBarangDetail(detail) {
        const tarif = this.tarifKirimanRutinList.find(t => t.id_jenis_barang == detail.id_jenis_barang)
        if (tarif) {
            detail.id_tarif_kiriman_rutin = tarif.id_tarif
            detail.jenis_barang = tarif.nama_barang
            detail.harga_satuan = tarif.biaya_per_unit
            detail.tarif_baru = false
        } else {
            const barang = this.jenisBarangList.find(b => b.id_jenis_barang == detail.id_jenis_barang)
            detail.id_tarif_kiriman_rutin = null
            detail.jenis_barang = barang?.nama_barang ?? ''
            detail.harga_satuan = ''
            detail.tarif_baru = true
        }
        detail.subtotal = Number(detail.quantity || 0) * Number(detail.harga_satuan || 0)
    },

    // Kiriman Rutin (relevan pas edit pengajuan lama): cek apakah harga yang
    // di-snapshot di baris ini (harga_satuan, dikunci saat submit) beda dari
    // tarif resmi vendor SAAT INI. Tarif resmi bisa berubah setelah pengajuan
    // dibuat (dikelola manual lewat Kelola Tarif), jadi bisa aja beda.
    // Return harga tarif saat ini kalau beda, atau null kalau sama/tidak relevan.
    cekSelisihTarif(detail) {
        if (detail.tarif_baru || !detail.id_tarif_kiriman_rutin) return null
        const tarifSaatIni = this.tarifKirimanRutinList.find(t => t.id_jenis_barang == detail.id_jenis_barang)
        if (!tarifSaatIni) return null
        if (Number(tarifSaatIni.biaya_per_unit) === Number(detail.harga_satuan)) return null
        return Number(tarifSaatIni.biaya_per_unit)
    },

    // Kiriman Rutin: Hapus detail item
    hapusDetailItem(idx) {
        this.detailKirimanRutin.splice(idx, 1)
    },

    async submitPengajuan() {
        // Validation: untuk create mode, dokumen harus dipilih
        if (!this.editId && this.dokumenDipilih.length === 0) {
            alert('Mohon pilih dokumen terlebih dahulu!')
            return
        }

        // Branch-specific validation
        if (this.pengajuan.jenis_pengajuan === 'sewa_truk') {
            if (!this.kendaraanTerpilih || !this.pengajuan.tanggal_pengiriman ||
                !this.pengajuan.harga_sewa) {
                alert('Data tidak lengkap! Pastikan kendaraan, tanggal, dan harga sewa sudah diisi.')
                return
            }
        } else { // pengiriman_rutin
            if (!this.perusahaanTerpilih || !this.pengajuan.tanggal_pengiriman ||
                !this.detailKirimanRutinValid) {
                alert('Data tidak lengkap! Pastikan vendor, tanggal, dan tiap baris Daftar Barang sudah pilih jenis barang & isi qty/harga.')
                return
            }
        }

        this.submitting = true

        // Kiriman Rutin: kalau form "Lengkapi Data Vendor" diubah, PATCH vendor dulu
        // (master data independen — di luar transaksi pengajuan).
        if (this.pengajuan.jenis_pengajuan === 'pengiriman_rutin' && this.vendorEdit.dirty) {
            try {
                const fd = new FormData()
                fd.append('_method', 'PATCH')
                fd.append('badan_usaha', this.vendorEdit.badan_usaha)
                fd.append('no_telepon', this.vendorEdit.no_telepon)
                fd.append('alamat_kantor', this.vendorEdit.alamat_kantor)
                this.vendorEdit.identitas_owner_files.forEach(f => fd.append('identitas_owner[]', f))
                const vres = await fetch(`/api/pengajuan/perusahaan/${this.pengajuanIdPerusahaanEkspedisi}`, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                })
                const vjson = await vres.json().catch(() => ({}))
                if (!vres.ok) {
                    this.submitting = false
                    const msg = vjson.message || Object.values(vjson.errors || {}).flat().join('\n') || 'Error tidak diketahui'
                    alert('Gagal memperbarui data vendor:\n' + msg)
                    return
                }
                if (vjson.perusahaan) {
                    this.perusahaanTerpilih = { ...this.perusahaanTerpilih, ...vjson.perusahaan }
                    this.vendorEdit.dirty = false
                }
            } catch (e) {
                this.submitting = false
                alert('Gagal memperbarui data vendor: ' + e.message)
                return
            }
        }

        // Calculate total value_muatan
        let valueMuatan
        if (this.editId && this.dokumenDipilih.length === 0) {
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
        } else { // pengiriman_rutin
            payload.id_perusahaan_ekspedisi = this.pengajuanIdPerusahaanEkspedisi
            payload.detail_kiriman = this.detailKirimanRutin.map(d => ({
                id_jenis_barang: d.id_jenis_barang,
                quantity: d.quantity,
                // biaya_per_unit cuma dipakai backend kalau tarif utk (vendor, jenis_barang)
                // ini belum ada — dipakai buat register tarif baru
                biaya_per_unit: d.tarif_baru ? d.harga_satuan : null,
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
                alert('Gagal ' + (this.editId ? 'update' : 'submit') + ': ' + (json.message || 'Error tidak diketahui'))
                return
            }

            const msg = this.editId ? '✓ Pengajuan berhasil diperbarui!' : '✓ Pengajuan berhasil disubmit!'
            const redirectTo = this.editId ? `/pengajuan/${json.id_pengajuan}` : '/dashboard/kg'
            alert(msg + '\nID: ' + json.id_pengajuan)

            // Link dokumen ke pengajuan via junction table
            if (this.dokumenDipilih.length > 0) {
                await this.linkDokumenToPengajuan(json.id_pengajuan)
            }

            // Bersihkan draft setelah submit sukses
            localStorage.removeItem('pengajuan_draft')
            setTimeout(() => window.location.href = redirectTo, 1500)
        } catch (e) {
            this.submitting = false
            alert('Error: ' + e.message)
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
            alert('Mohon upload minimal 1 file identitas owner (KTP/NPWP/SIM)!')
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
                    alert('Validation error:\n' + errMsg)
                } else {
                    alert('Gagal menyimpan perusahaan: ' + (json.message || 'Error tidak diketahui'))
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
            alert('Perusahaan berhasil disimpan!')
        } catch (e) {
            alert('Error: ' + e.message)
        }
    },

    async saveKendaraan() {
        // Validasi: perusahaan harus dipilih/dibuat dulu
        if (this.kendaraanBaru.perusahaan_mode === 'pilih' && !this.kendaraanBaru.perusahaan_id) {
            alert('Mohon pilih perusahaan terlebih dahulu!')
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
                    alert('Validation error:\n' + errMsg)
                } else {
                    alert('Gagal menyimpan: ' + (json.message || 'Error tidak diketahui'))
                }
                return
            }
            this.kendaraanTerpilih = json.kendaraan
            // Reset form tambah kendaraan biar ga ke-resubmit dgn data yang sama
            this.showFormKendaraanBaru = false
            this.kendaraanBaru.jenis_kendaraan = ''
            this.kendaraanBaru.plat_nomor_truk = ''
            this.kendaraanBaru.muatan_maksimal = ''
            this.kendaraanBaru.id_skill = []
            this.kendaraanBaru.skillBaru = []
            // Refresh daftar kendaraan existing perusahaan ini biar truk baru ikut kelihatan
            if (this.kendaraanBaru.perusahaan_id) {
                this.fetchKendaraanByPerusahaan(this.kendaraanBaru.perusahaan_id)
            }
            alert('Kendaraan berhasil disimpan!')
            this.subStepKendaraan = 3
        } catch (e) {
            alert('Error: ' + e.message)
        } finally {
            this.savingKendaraan = false
        }
    },
}))

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons })
})