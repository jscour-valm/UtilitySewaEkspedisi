import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

Alpine.data('pengajuanSewa', () => ({
    step: 1,
    mode: 'pilih',
    editId: null,
    searchArmada: '',
    armadaTerpilih: null,
    subStepArmada: 1, // 1=Perusahaan, 2=Kendaraan, 3=Ringkasan
    perusahaanList: [],
    perusahaanStep: 'pilih', // 'pilih' | 'form_baru'
    perusahaanTerpilih: null, // { id_perusahaan, nama_perusahaan, ... }
    armadaByPerusahaan: [],
    loadingArmadaByPerusahaan: false,
    showFormKendaraanBaru: false,
    armadaBaru: {
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
    submitting: false,
    dummyDokumen: [
        { id: 1, nomor_dokumen: 'SJ-001-2026', tipe: 'SJ', tanggal: '2026-08-10', berat: 500, value: 50000000, cabang_tujuan: null },
        { id: 2, nomor_dokumen: 'SJ-002-2026', tipe: 'SJ', tanggal: '2026-08-11', berat: 750, value: 75000000, cabang_tujuan: null },
        { id: 3, nomor_dokumen: 'TO-ACB-001', tipe: 'TO-ACB', tanggal: '2026-08-12', berat: 1000, value: 100000000, cabang_tujuan: 'CBG-JKT01' },
        { id: 4, nomor_dokumen: 'SJ-003-2026', tipe: 'SJ', tanggal: '2026-08-13', berat: 600, value: 60000000, cabang_tujuan: null },
        { id: 5, nomor_dokumen: 'TO-ACB-2314', tipe: 'TO-ACB', tanggal: '2026-08-23', berat: 250, value: 24000000, cabang_tujuan: 'CBG-BDG01' },
        { id: 6, nomor_dokumen: 'TO-ACB-2315', tipe: 'TO-ACB', tanggal: '2026-08-24', berat: 300, value: 28000000, cabang_tujuan: 'CBG-JKT01' },
        { id: 7, nomor_dokumen: 'SJ-004-2026', tipe: 'SJ', tanggal: '2026-08-14', berat: 800, value: 80000000, cabang_tujuan: null },
        { id: 8, nomor_dokumen: 'SJ-005-2026', tipe: 'SJ', tanggal: '2026-08-15', berat: 900, value: 90000000, cabang_tujuan: null },
        { id: 9, nomor_dokumen: 'TO-ACB-2316', tipe: 'TO-ACB', tanggal: '2026-08-25', berat: 400, value: 35000000, cabang_tujuan: 'CBG-SBY01' },
        { id: 10, nomor_dokumen: 'SJ-006-2026', tipe: 'SJ', tanggal: '2026-08-16', berat: 700, value: 70000000, cabang_tujuan: null },
    ],

    // Dokumen yang ditampilkan di step 3 — filter by tujuan, lalu by search term
    get dokumenList() {
        let filtered = this.dummyDokumen

        // Filter by tujuan penyewaan
        if (this.pengajuan.tujuan_penyewaan === 'PAC') {
            filtered = filtered.filter(d => d.tipe === 'TO-ACB')
        } else if (this.pengajuan.tujuan_penyewaan === 'Toko') {
            filtered = filtered.filter(d => d.tipe === 'SJ')
        }

        // Filter by search term
        if (this.dokumenSearch.trim() !== '') {
            const searchTerm = this.dokumenSearch.toLowerCase()
            filtered = filtered.filter(d =>
                (d.nomor_dokumen && d.nomor_dokumen.toLowerCase().includes(searchTerm)) ||
                (d.tipe && d.tipe.toLowerCase().includes(searchTerm)) ||
                (d.skill_nama && d.skill_nama.toLowerCase().includes(searchTerm)) ||
                (d.id_skill && d.id_skill.toLowerCase().includes(searchTerm)) ||
                (d.kategoriToko && d.kategoriToko.toLowerCase().includes(searchTerm))
            )
        }

        return filtered
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

    // Total biaya tambahan
    get totalBiayaTambahan() {
        return this.biayaTambahan.reduce((sum, b) => sum + (Number(b.nominal) || 0), 0)
    },

    // Total harga sewa + biaya tambahan
    get totalDenganBiayaTambahan() {
        return (Number(this.pengajuan.harga_sewa) || 0) + this.totalBiayaTambahan
    },

    // Jumlah toko (cabang tujuan unik) dari dokumen TO-ACB yang dipilih
    get jumlahTokoDipilih() {
        if (this.pengajuan.tujuan_penyewaan !== 'PAC') return null // SJ: struktur data belum tersedia
        const cabangSet = new Set(
            this.dokumenDipilih
                .map(id => this.dokumenList.find(d => d.id === id))
                .filter(Boolean)
                .filter(d => d.cabang_tujuan)
                .map(d => d.cabang_tujuan)
        )
        return cabangSet.size
    },

    // Skill gabungan: existing id_skill + skillBaru
    get skillGabungan() {
        const existing = this.pengajuan.id_skill || []
        const baru = this.skillBaru.filter(s => s.trim() !== '')
        return [...existing, ...baru]
    },

    // Cek ada minimal 1 skill terpilih (existing atau baru)
    get adaSkillTerpilih() {
        return this.pengajuan.id_skill.length > 0 || this.skillBaru.filter(s => s.trim() !== '').length > 0
    },

    // Format tanggal ke format Indonesia (d M Y) — match Carbon translatedFormat('d M Y')
    formatTanggalID(dateStr) {
        if (!dateStr) return '—'
        const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
        const d = new Date(dateStr + 'T00:00:00')
        if (isNaN(d)) return dateStr
        return `${String(d.getDate()).padStart(2, '0')} ${bulan[d.getMonth()]} ${d.getFullYear()}`
    },

    async init() {
        // Fetch master data paralel dulu sebelum prefill
        await Promise.all([
            this.fetchSkillList(),
            this.fetchKategoriTokoList(),
            this.fetchJenisBiayaList(),
            this.fetchPerusahaanList(),
        ])

        // Check edit mode (dari window.__editPengajuan)
        if (window.__editPengajuan) {
            const data = window.__editPengajuan
            this.editId = data.id_pengajuan_sewa
            this.armadaTerpilih = data.armada
            this.pengajuan.tanggal_pengiriman = data.tanggal_pengiriman
            this.pengajuan.harga_sewa = data.harga_sewa
            this.pengajuan.tujuan_penyewaan = data.tujuan_penyewaan
            this.pengajuan.kategoriToko = data.kategori_toko
            this.pengajuan.catatan = data.catatan_pengajuan
            this.pengajuan.value_muatan = data.value_muatan // Prefill value_muatan dari database
            this.biayaTambahan = data.biaya_tambahan || []
            this.dokumenDipilih = data.dokumen_dipilih || [] // Prefill dokumen yang dipilih sebelumnya

            // Prefill skill: split existing skill vs yang tidak match skillList (skill baru)
            const knownSkillNames = this.skillList.map(s => s.id_skill)
            this.pengajuan.id_skill = data.id_skill.filter(s => knownSkillNames.includes(s))
            this.skillBaru = data.id_skill.filter(s => !knownSkillNames.includes(s))

            // Skill udah pernah dikunci sebelumnya
            this.skillLocked = true
            this.step = 2
            // Hapus draft lama supaya tidak nyasar ke sesi create berikutnya
            localStorage.removeItem('pengajuan_draft')
        } else {
            // Mode create: baca query params kalau ada (dari tombol Pilih di dashboard)
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

                // Prefill skill checkbox dari armada yang dipilih (dari query param)
                // Pisahkan skill yang match skillList cabang user vs yang tidak (skill baru)
                if (this.armadaTerpilih?.skill) {
                    const armadaSkills = this.armadaTerpilih.skill
                        .split(',')
                        .map(s => s.trim().toUpperCase())
                        .filter(s => s !== '')
                    const knownSkillNames = this.skillList.map(s => s.id_skill)
                    this.pengajuan.id_skill = armadaSkills.filter(s => knownSkillNames.includes(s))
                    this.skillBaru = armadaSkills.filter(s => !knownSkillNames.includes(s))
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
        })

        // Auto-save draft: watch state dan simpan ke localStorage (debounce 500ms untuk pengajuan)
        let saveDraftTimer = null
        this.$watch('pengajuan', () => {
            clearTimeout(saveDraftTimer)
            saveDraftTimer = setTimeout(() => this.saveDraft(), 500)
        }, { deep: true })
        this.$watch('skillBaru', () => this.saveDraft(), { deep: true })
        this.$watch('biayaTambahan', () => this.saveDraft(), { deep: true })
        this.$watch('dokumenDipilih', () => this.saveDraft(), { deep: true })
        this.$watch('armadaTerpilih', () => this.saveDraft())
        this.$watch('mode', () => this.saveDraft())
        this.$watch('perusahaanTerpilih', () => this.saveDraft())
        this.$watch('armadaBaru', () => this.saveDraft(), { deep: true })
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
            const res = await fetch('/api/pengajuan/perusahaan-list')
            const json = await res.json()
            this.perusahaanList = json.data ?? json
        } catch (e) {
            console.error('Gagal fetch perusahaan list:', e)
        }
    },

    async fetchArmadaByPerusahaan(perusahaanId) {
        this.loadingArmadaByPerusahaan = true
        this.armadaByPerusahaan = []
        try {
            const res = await fetch(`/api/pengajuan/armada-by-perusahaan?perusahaan_id=${perusahaanId}`)
            const json = await res.json()
            this.armadaByPerusahaan = json.data ?? []
            // Kalau perusahaan belum punya armada terdaftar, langsung buka form tambah baru
            this.showFormKendaraanBaru = this.armadaByPerusahaan.length === 0
        } catch (e) {
            console.error('Gagal fetch armada by perusahaan:', e)
        } finally {
            this.loadingArmadaByPerusahaan = false
        }
    },

    // Handle identitas owner file upload (multi-file, max 3)
    handleIdentitasOwnerUpload(e) {
        const files = Array.from(e.target.files || [])
        if (files.length > 3) {
            alert('Maksimal 3 file saja!')
            e.target.value = ''
            return
        }
        this.armadaBaru.identitas_owner_files = files
        // Generate preview URLs
        this.armadaBaru.identitas_owner_previews = files.map(f => URL.createObjectURL(f))
    },

    // Save wizard state to localStorage
    saveDraft() {
        if (this.editId) return // Jangan simpan draft saat edit mode
        const draft = {
            step: this.step,
            mode: this.mode,
            subStepArmada: this.subStepArmada,
            armadaTerpilih: this.armadaTerpilih,
            perusahaanTerpilih: this.perusahaanTerpilih,
            pengajuan: this.pengajuan,
            skillBaru: this.skillBaru,
            biayaTambahan: this.biayaTambahan,
            dokumenDipilih: this.dokumenDipilih,
            armadaBaru: {
                perusahaan_mode: this.armadaBaru.perusahaan_mode,
                perusahaan_id: this.armadaBaru.perusahaan_id,
                nama_perusahaan: this.armadaBaru.nama_perusahaan,
                badan_usaha: this.armadaBaru.badan_usaha,
                no_telepon: this.armadaBaru.no_telepon,
                alamat_kantor: this.armadaBaru.alamat_kantor,
                id_skill: this.armadaBaru.id_skill,
                skillBaru: this.armadaBaru.skillBaru,
                jenis_kendaraan: this.armadaBaru.jenis_kendaraan,
                plat_nomor_truk: this.armadaBaru.plat_nomor_truk,
                muatan_maksimal: this.armadaBaru.muatan_maksimal,
                // EXCLUDE: ktp_supir, sim_supir, identitas_owner_files, identitas_owner_previews (File objects & blob URLs)
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
            this.step = draft.step ?? 1
            this.mode = draft.mode ?? 'pilih'
            this.armadaTerpilih = draft.armadaTerpilih ?? null
            this.perusahaanTerpilih = draft.perusahaanTerpilih ?? null
            // Restore subStepArmada, fallback ke estimasi dari data kalau draft lama (pre-migrasi)
            this.subStepArmada = draft.subStepArmada ?? (this.armadaTerpilih ? 3 : (this.perusahaanTerpilih ? 2 : 1))
            this.pengajuan = { ...this.pengajuan, ...draft.pengajuan }
            this.skillBaru = draft.skillBaru ?? []
            this.biayaTambahan = draft.biayaTambahan ?? []
            this.dokumenDipilih = draft.dokumenDipilih ?? []
            if (draft.armadaBaru) {
                this.armadaBaru = { ...this.armadaBaru, ...draft.armadaBaru }
            }
            // Refetch armada kalau perusahaan sudah terpilih
            if (this.perusahaanTerpilih) {
                this.fetchArmadaByPerusahaan(this.perusahaanTerpilih.id_perusahaan)
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
        return this.armadaTerpilih ? '' : 'Pilih atau tambahkan armada terlebih dahulu'
    },

    // Tooltip untuk tombol Lanjut di step 2
    get tooltipStep2() {
        const missing = []
        if (!this.pengajuan.tanggal_pengiriman) missing.push('Tanggal Pengiriman')
        if (!this.pengajuan.harga_sewa) missing.push('Harga Sewa')
        if (!this.pengajuan.tujuan_penyewaan) missing.push('Tujuan Penyewaan')
        if (!this.adaSkillTerpilih) missing.push('Skill/Area (minimal 1)')
        if (!this.pengajuan.kategoriToko) missing.push('Kategori Toko')
        return missing.length ? 'Lengkapi dulu: ' + missing.join(', ') : ''
    },

    // Tooltip untuk tombol Lanjut di step 3
    get tooltipStep3() {
        if (this.beratMelebihi) return 'Berat muatan melebihi kapasitas armada'
        if (this.dokumenDipilih.length === 0 && !(this.editId && this.pengajuan.value_muatan)) {
            return 'Pilih minimal 1 dokumen'
        }
        return ''
    },

    canGoToStep(target) {
        if (target <= this.step) return true
        // Pas edit mode, nggak boleh balik ke step 1 (armada dikunci)
        if (this.editId && target === 1) return false
        if (target === 2) return this.armadaTerpilih !== null
        if (target === 3) return this.armadaTerpilih !== null
            && this.pengajuan.tanggal_pengiriman !== ''
            && this.pengajuan.harga_sewa !== ''
            && this.pengajuan.tujuan_penyewaan !== ''
            && this.adaSkillTerpilih
            && this.pengajuan.kategoriToko !== ''
        if (target === 4) {
            // Edit mode: bisa skip dokumen selection (gunakan value_muatan dari database)
            if (this.editId) return true
            // Create mode: harus pilih dokumen
            return this.dokumenDipilih.length > 0 && !this.beratMelebihi
        }
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
        // Validation: untuk create mode, dokumen harus dipilih
        if (!this.editId && this.dokumenDipilih.length === 0) {
            alert('Mohon pilih dokumen terlebih dahulu!')
            return
        }

        if (!this.armadaTerpilih || !this.pengajuan.tanggal_pengiriman ||
            !this.pengajuan.harga_sewa) {
            alert('Data tidak lengkap!')
            return
        }

        this.submitting = true

        // Calculate total value_muatan
        let valueMuatan
        if (this.editId && this.dokumenDipilih.length === 0) {
            // Edit mode tanpa re-select dokumen: gunakan value_muatan dari prefill
            valueMuatan = this.pengajuan.value_muatan
        } else {
            // Create mode atau edit mode dengan dokumen baru: hitung dari dokumen yang dipilih
            valueMuatan = this.dokumenDipilih.reduce((sum, docId) => {
                const doc = this.dokumenList.find(d => d.id === docId)
                return sum + (doc ? Number(doc.value) : 0)
            }, 0)
        }

        const payload = {
            id_kendaraan: this.armadaTerpilih.id,
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
            // Bersihkan draft setelah submit sukses
            localStorage.removeItem('pengajuan_draft')
            setTimeout(() => window.location.href = redirectTo, 1500)
        } catch (e) {
            this.submitting = false
            alert('Error: ' + e.message)
        }
    },

    async savePerusahaanBaru() {
        // Validasi: identitas_owner wajib
        if (this.armadaBaru.identitas_owner_files.length === 0) {
            alert('Mohon upload minimal 1 file identitas owner (KTP/NPWP/SIM)!')
            return
        }

        const formData = new FormData()
        formData.append('nama_perusahaan', this.armadaBaru.nama_perusahaan)
        formData.append('badan_usaha', this.armadaBaru.badan_usaha)
        formData.append('no_telepon', this.armadaBaru.no_telepon)
        formData.append('alamat_kantor', this.armadaBaru.alamat_kantor)

        // Append identitas owner files
        this.armadaBaru.identitas_owner_files.forEach(file => {
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
            this.armadaBaru.perusahaan_id = json.perusahaan.id_perusahaan
            this.perusahaanStep = 'pilih' // kembali ke mode pilih
            this.armadaBaru.perusahaan_mode = 'pilih'
            // Fetch armada by perusahaan (perusahaan baru pasti kosong)
            this.fetchArmadaByPerusahaan(json.perusahaan.id_perusahaan)
            alert('Perusahaan berhasil disimpan!')
        } catch (e) {
            alert('Error: ' + e.message)
        }
    },

    async saveArmada() {
        // Validasi: perusahaan harus dipilih/dibuat dulu
        if (this.armadaBaru.perusahaan_mode === 'pilih' && !this.armadaBaru.perusahaan_id) {
            alert('Mohon pilih perusahaan terlebih dahulu!')
            return
        }

        const formData = new FormData()
        formData.append('perusahaan_id', this.armadaBaru.perusahaan_id)

        // Append each skill one-by-one (FormData array handling)
        this.armadaBaru.id_skill.forEach(skill => {
            formData.append('id_skill[]', skill)
        })

        // Also append new skills
        this.armadaBaru.skillBaru.filter(s => s.trim() !== '').forEach(skill => {
            formData.append('id_skill[]', skill)
        })

        formData.append('jenis_kendaraan', this.armadaBaru.jenis_kendaraan || '')
        formData.append('plat_nomor_truk', this.armadaBaru.plat_nomor_truk || '')
        formData.append('muatan_maksimal', this.armadaBaru.muatan_maksimal)

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
            this.subStepArmada = 3
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