import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

Alpine.data('pengajuanSewa', () => ({
    step: 1,
    mode: 'pilih',
    searchArmada: '',
    armadaTerpilih: null,
    armadaBaru: {
        nama_perusahaan: '',
        badan_usaha: '',
        no_telepon: '',
        alamat_kantor: '',
        ktp_supir: null,
        ktp_preview: null,
        sim_supir: null,
        sim_preview: null,
        id_skill: [],
        skillBaru: [],
        nama_kendaraan: '',
        plat_nomor: '',
        muatan_maksimal: '',
    },
    skillList: [],
    kategoriTokoList: [],
    jenisBiayaList: [],
    pengajuan: {
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
    dokumenPage: 1,
    dokumenPerPage: 5,
    submitting: false,
    dummyDokumen: [
        { id: 1, nomor_dokumen: 'SJ-001-2026', tipe: 'SJ', tanggal: '2026-08-10', berat: 500, value: 50000000 },
        { id: 2, nomor_dokumen: 'SJ-002-2026', tipe: 'SJ', tanggal: '2026-08-11', berat: 750, value: 75000000 },
        { id: 3, nomor_dokumen: 'TO-ACB-001', tipe: 'TO-ACB', tanggal: '2026-08-12', berat: 1000, value: 100000000 },
        { id: 4, nomor_dokumen: 'SJ-003-2026', tipe: 'SJ', tanggal: '2026-08-13', berat: 600, value: 60000000 },
        { id: 5, nomor_dokumen: 'TO-ACB-2314', tipe: 'TO-ACB', tanggal: '2026-08-23', berat: 250, value: 24000000 },
        { id: 6, nomor_dokumen: 'TO-ACB-2315', tipe: 'TO-ACB', tanggal: '2026-08-24', berat: 300, value: 28000000 },
        { id: 7, nomor_dokumen: 'SJ-004-2026', tipe: 'SJ', tanggal: '2026-08-14', berat: 800, value: 80000000 },
        { id: 8, nomor_dokumen: 'SJ-005-2026', tipe: 'SJ', tanggal: '2026-08-15', berat: 900, value: 90000000 },
        { id: 9, nomor_dokumen: 'TO-ACB-2316', tipe: 'TO-ACB', tanggal: '2026-08-25', berat: 400, value: 35000000 },
        { id: 10, nomor_dokumen: 'SJ-006-2026', tipe: 'SJ', tanggal: '2026-08-16', berat: 700, value: 70000000 },
    ],

    // Dokumen yang ditampilkan di step 3 — filter by tujuan
    get dokumenList() {
        if (this.pengajuan.tujuan_penyewaan === 'PAC') {
            return this.dummyDokumen.filter(d => d.tipe === 'TO-ACB')
        } else if (this.pengajuan.tujuan_penyewaan === 'Toko') {
            return this.dummyDokumen.filter(d => d.tipe === 'SJ')
        }
        return this.dummyDokumen
    },

    get dokumenPaged() {
        const start = (this.dokumenPage - 1) * this.dokumenPerPage
        return this.dokumenList.slice(start, start + this.dokumenPerPage)
    },

    get dokumenTotalPages() {
        return Math.ceil(this.dokumenList.length / this.dokumenPerPage)
    },

    // Total berat dokumen terpilih dalam kg
    get totalBeratDipilih() {
        return this.dokumenDipilih.reduce((sum, docId) => {
            const doc = this.dokumenList.find(d => d.id === docId)
            return sum + (doc ? Number(doc.berat) : 0)
        }, 0)
    },

    // Muatan maksimal armada dalam kg (konversi dari Ton)
    get muatanMaksimalKg() {
        const raw = this.armadaTerpilih?.muatan_raw
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

    async init() {
        // Baca query params kalau ada (dari tombol Pilih di dashboard)
        const params = new URLSearchParams(window.location.search)
        if (params.get('id')) {
            this.armadaTerpilih = {
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
        }

        // Fetch master data paralel
        await Promise.all([
            this.fetchSkillList(),
            this.fetchKategoriTokoList(),
            this.fetchJenisBiayaList(),
        ])

        // Prefill skill checkbox dari armada yang dipilih (dari query param)
        // Pisahkan skill yang match skillList cabang user vs yang tidak (skill baru)
        if (this.armadaTerpilih?.skill) {
            const armadaSkills = this.armadaTerpilih.skill
                .split(',')
                .map(s => s.trim().toUpperCase())
                .filter(s => s !== '')
            const knownSkillNames = this.skillList.map(s => s.id_skill) // id_skill = nama_skill string
            this.pengajuan.id_skill = armadaSkills.filter(s => knownSkillNames.includes(s))
            this.skillBaru = armadaSkills.filter(s => !knownSkillNames.includes(s))
        }

        this.$nextTick(() => createIcons({ icons }))
        this.$watch('step', () => {
            this.$nextTick(() => createIcons({ icons }))
        })
        this.$watch('pengajuan.tujuan_penyewaan', () => {
            this.dokumenDipilih = []
            this.dokumenPage = 1
        })
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

    canGoToStep(target) {
        if (target <= this.step) return true
        if (target === 2) return this.armadaTerpilih !== null
        if (target === 3) return this.armadaTerpilih !== null
            && this.pengajuan.tanggal_pengiriman !== ''
            && this.pengajuan.harga_sewa !== ''
            && this.pengajuan.tujuan_penyewaan !== ''
            && this.skillLocked
            && this.pengajuan.kategoriToko !== ''
        if (target === 4) return this.dokumenDipilih.length > 0 && !this.beratMelebihi
        return false
    },

    goToStep(target) {
        if (this.canGoToStep(target)) this.step = target
    },

    handleKtpUpload(e) {
        const file = e.target.files[0]
        if (!file) return
        this.armadaBaru.ktp_supir = file
        this.armadaBaru.ktp_preview = URL.createObjectURL(file)
    },

    handleSimUpload(e) {
        const file = e.target.files[0]
        if (!file) return
        this.armadaBaru.sim_supir = file
        this.armadaBaru.sim_preview = URL.createObjectURL(file)
    },

    handleDocUpload(e) {
        const file = e.target.files[0]
        if (!file) return
        console.log('Dokumen dipilih:', file.name)
    },

    async submitPengajuan() {
        if (!this.armadaTerpilih || !this.pengajuan.tanggal_pengiriman || 
            !this.pengajuan.harga_sewa || this.dokumenDipilih.length === 0) {
            alert('Data tidak lengkap!')
            return
        }

        this.submitting = true

        // Calculate total value_muatan dari selected dokumen
        const valueMuatan = this.dokumenDipilih.reduce((sum, docId) => {
            const doc = this.dokumenList.find(d => d.id === docId)
            return sum + (doc ? Number(doc.value) : 0)
        }, 0)

        const rasioSewa = (Number(this.pengajuan.harga_sewa) / valueMuatan) * 100
        const kategoriApproval = rasioSewa <= 2.5 ? 'normal' : 'over_threshold'

        try {
            const res = await fetch('/api/pengajuan/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    id_armada: this.armadaTerpilih.id,
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
                })
            })

            const json = await res.json()
            this.submitting = false

            if (!res.ok) {
                alert('Gagal submit: ' + (json.message || 'Error tidak diketahui'))
                return
            }

            alert('✓ Pengajuan berhasil disubmit!\nID: ' + json.id_pengajuan)
            setTimeout(() => window.location.href = '/dashboard/kg', 1500)
        } catch (e) {
            this.submitting = false
            alert('Error: ' + e.message)
        }
    },

    async saveArmada() {
        const formData = new FormData()
        formData.append('nama_perusahaan', this.armadaBaru.nama_perusahaan)
        formData.append('badan_usaha', this.armadaBaru.badan_usaha)
        formData.append('no_telepon', this.armadaBaru.no_telepon)
        formData.append('alamat_kantor', this.armadaBaru.alamat_kantor)

        // Append each skill one-by-one (FormData array handling)
        this.armadaBaru.id_skill.forEach(skill => {
            formData.append('id_skill[]', skill)
        })

        // Also append new skills
        this.armadaBaru.skillBaru.filter(s => s.trim() !== '').forEach(skill => {
            formData.append('id_skill[]', skill)
        })

        formData.append('nama_kendaraan', this.armadaBaru.nama_kendaraan)
        formData.append('plat_nomor', this.armadaBaru.plat_nomor)
        formData.append('muatan_maksimal', this.armadaBaru.muatan_maksimal)
        if (this.armadaBaru.ktp_supir) formData.append('ktp_supir', this.armadaBaru.ktp_supir)
        if (this.armadaBaru.sim_supir) formData.append('sim_supir', this.armadaBaru.sim_supir)

        try {
            const res = await fetch('/api/pengajuan/armada', {
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
            this.armadaTerpilih = json.armada
            this.mode = 'pilih'
            alert('Armada berhasil disimpan!')
            this.goToStep(2)
        } catch (e) {
            alert('Error: ' + e.message)
        }
    },
}))

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons })
})