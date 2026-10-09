@extends('layouts.app')

@section('title', 'Detail Perusahaan')

@section('content')
@php
    $role = auth()->user()?->userUtility?->role;
    $isDci = $role === 'DCI';
    $isKg = $role === 'KG';
    // Riwayat harga: semua role kecuali KG. Kendaraan milik cabang lain hanya bisa diedit user global.
    $lihatRiwayat = ! $isKg;
    $isGlobal = auth()->user()?->isGlobalAccess();

    $docs = $perusahaan->identitas_owner ?? [];
    $docSrcs = collect($docs)->map(fn ($item) => \App\Helpers\FormatHelper::identitasOwnerSrc($item))->values();

    $sectionLabel = 'text-[11px] font-semibold tracking-[0.09em] text-gray-500';
    $editBtn = 'rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50';
    $namaLengkap = trim(($perusahaan->badan_usaha && $perusahaan->badan_usaha !== '-' ? $perusahaan->badan_usaha . ' ' : '') . $perusahaan->nama_perusahaan);
@endphp

<div class="space-y-4 pb-4">
    <x-alert-success />

    @if($perusahaan->status_approval && ! $perusahaan->sudahDisetujui())
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p>
                Vendor baru — <strong>{{ $perusahaan->labelStatusPersetujuan() }}</strong>.
                Pengajuan sewa yang memakai vendor ini baru bisa divalidasi WM setelah vendor disetujui WH.
            </p>
            @if(in_array($role, ['KG', 'WM', 'WH', 'DCI'], true))
                <a href="{{ route('persetujuan.vendor.show', $perusahaan->id_perusahaan) }}"
                    class="font-semibold text-amber-800 underline hover:text-amber-950">Lihat proses vendor →</a>
            @endif
        </div>
    @endif

    {{-- ==================== HERO + INFO PERUSAHAAN + DOKUMEN ==================== --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="h-1 w-full bg-avian-green"></div>
        <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-5 lg:grid-cols-[1.3fr_1px_1fr]">

            <div class="min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h1 class="text-[22px] font-bold tracking-tight text-gray-900">{{ $namaLengkap }}</h1>
                    @if($bolehKelola)
                        <button type="button" @click="$dispatch('edit-profil-vendor')" class="{{ $editBtn }}">Edit Profil</button>
                    @endif
                </div>
                <p class="mt-1 text-[13px] text-gray-500">
                    {{ $cabangCount }} cabang &middot; {{ $areaCount }} area &middot; {{ count($kendaraan) }} unit kendaraan
                </p>

                <p class="{{ $sectionLabel }} mt-4 mb-1">INFORMASI PERUSAHAAN</p>
                <div class="flex justify-between gap-4 border-b border-gray-50 py-2">
                    <span class="text-sm text-gray-500">Badan Usaha</span>
                    <span class="text-sm font-semibold text-gray-900">{{ $perusahaan->badan_usaha ?: '—' }}</span>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-50 py-2">
                    <span class="text-sm text-gray-500">No. Telepon</span>
                    @if($perusahaan->no_telepon && $perusahaan->no_telepon !== '-')
                        <a href="tel:{{ $perusahaan->no_telepon }}" class="text-sm font-semibold text-green-800 hover:underline">{{ $perusahaan->no_telepon }}</a>
                    @else
                        <span class="text-sm text-gray-400">—</span>
                    @endif
                </div>
                <div class="flex justify-between gap-4 py-2">
                    <span class="text-sm text-gray-500">Alamat Kantor</span>
                    <span class="text-right text-sm font-semibold text-gray-900">{{ $perusahaan->alamat_kantor && $perusahaan->alamat_kantor !== '-' ? $perusahaan->alamat_kantor : '—' }}</span>
                </div>
            </div>

            <div class="hidden bg-gray-100 lg:block"></div>

            <div class="min-w-0">
                <p class="{{ $sectionLabel }}">DOKUMEN IDENTITAS</p>
                <p class="mb-3 mt-0.5 text-xs text-gray-400">Klik untuk memperbesar</p>
                @if(count($docs))
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($docSrcs as $i => $src)
                            <div @click="openLightbox(@js($docSrcs), {{ $i }})"
                                class="h-24 cursor-zoom-in overflow-hidden rounded-lg border border-gray-200 bg-gray-50 transition hover:opacity-80">
                                <img src="{{ $src }}" alt="Dokumen Identitas #{{ $i + 1 }}" class="h-full w-full object-cover">
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex h-24 items-center justify-center rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400">
                        Belum ada dokumen identitas
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ==================== KENDARAAN ==================== --}}
    {{-- Section disembunyikan total kalau kosong (bukan cuma nunjukin "Belum ada...") —
    konsisten sama pola yang udah dipakai di section "Area Terdaftar, Belum Ada Tarif"
    di bawah, biar Detail Perusahaan nggak makan tempat percuma buat data yang nggak ada. --}}
    @if(count($kendaraan) > 0 || $bolehKelola)
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"
        @if($bolehKelola) x-data="kelolaKendaraan({{ (int) $perusahaan->id_perusahaan }}, @js($jenisKendaraanList), @js($cabangKelola->count() === 1 ? $cabangKelola->first()->Code : ''))" @endif>
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="{{ $sectionLabel }}">KENDARAAN</p>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400">{{ count($kendaraan) }} unit</span>
                @if($bolehKelola)
                    <button type="button" @click="bukaTambah()"
                        class="rounded-lg bg-avian-green px-2.5 py-1 text-xs font-medium text-white hover:bg-avian-green-dark">+ Tambah Kendaraan</button>
                @endif
            </div>
        </div>
        @if(count($kendaraan) > 0)
        <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                        <th class="px-3.5 py-2.5 text-left">CABANG</th>
                        <th class="px-3.5 py-2.5 text-left">JENIS KENDARAAN</th>
                        <th class="px-3.5 py-2.5 text-left">PLAT NOMOR</th>
                        <th class="px-3.5 py-2.5 text-right">MUATAN</th>
                        <th class="px-3.5 py-2.5 text-left">AREA / SKILL</th>
                        @if($isKg || $bolehKelola)
                            <th class="px-3.5 py-2.5 text-right">AKSI</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($kendaraan as $k)
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-2.5 text-gray-600">{{ $k['cabang'] }}</td>
                            <td class="px-3.5 py-2.5 font-medium text-gray-800">{{ $k['jenis'] ?: '—' }}</td>
                            <td class="px-3.5 py-2.5 font-mono text-gray-700">
                                @if($k['plat']) {{ $k['plat'] }} @else <span class="font-sans text-gray-400">tanpa plat</span> @endif
                            </td>
                            <td class="px-3.5 py-2.5 text-right tabular-nums text-gray-700">{{ $k['muatan'] }}</td>
                            <td class="px-3.5 py-2.5 text-gray-600">{{ $k['skills'] ? implode(', ', $k['skills']) : '—' }}</td>
                            @if($isKg)
                                <td class="px-3.5 py-2.5 text-right">
                                    @if($k['pengajuan_url'])
                                        <a href="{{ $k['pengajuan_url'] }}"
                                            class="rounded-lg bg-avian-green px-2.5 py-1 text-xs font-medium text-white hover:bg-avian-green-dark">
                                            Buat Pengajuan
                                        </a>
                                    @endif
                                </td>
                            @elseif($bolehKelola)
                                <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                    @if($isGlobal || $k['mine'])
                                        <button type="button" class="{{ $editBtn }}" @click="bukaEdit(@js($k))">Edit</button>
                                    @endif
                                    @if($isDci)
                                        <button type="button" class="ml-1 rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                            @click="hapus({{ (int) $k['id'] }})">Hapus</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 py-6 text-center text-xs text-gray-400">Belum ada unit kendaraan.</p>
        @endif

        @if($bolehKelola)
            @include('pages.perusahaan.partials.modal-kendaraan')
        @endif
    </div>
    @endif

    {{-- ==================== TARIF SEWA TRUK ==================== --}}
    @if($tarifSewa->isNotEmpty())
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <p class="{{ $sectionLabel }}">TARIF SEWA TRUK</p>
            <span class="text-xs text-gray-400">{{ $tarifSewa->count() }} area</span>
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                        <th class="px-3.5 py-2.5 text-left">CABANG</th>
                        <th class="px-3.5 py-2.5 text-left">AREA KIRIM</th>
                        <th class="px-3.5 py-2.5 text-right">HARGA SEWA</th>
                        <th class="px-3.5 py-2.5 text-left">DIUPDATE</th>
                        <th class="px-3.5 py-2.5 text-right">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tarifSewa as $t)
                        @php
                            $diupdate = $t->update_date_source ?? $t->updated_at;
                            $usulanRow = $usulanBerjalan->get('sewa_truk|' . $t->cabang_code . '|' . $t->id_skill . '|');
                        @endphp
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-2.5 text-gray-600">{{ $t->cabang_code }} &mdash; {{ $t->nama_cabang ?? '—' }}</td>
                            <td class="px-3.5 py-2.5 font-medium text-gray-800">{{ $t->nama_skill }}</td>
                            <td class="px-3.5 py-2.5 text-right font-semibold tabular-nums text-gray-900">
                                {{ \App\Helpers\FormatHelper::rupiah($t->harga_sewa) }}
                                <x-penanda-usulan-harga :usulan="$usulanRow" />
                            </td>
                            <td class="px-3.5 py-2.5 text-gray-500">{{ $diupdate ? \Carbon\Carbon::parse($diupdate)->translatedFormat('d M Y') : '—' }}</td>
                                <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                    @if($lihatRiwayat)
                                        <button type="button" class="{{ $editBtn }}"
                                            @click="$dispatch('riwayat-harga', @js(['url' => route('perusahaan.riwayat-harga', ['sewa-truk', $t->id_vendor_skill]), 'judul' => 'Sewa Truk · '.$t->nama_skill.' · Cab. '.$t->cabang_code, 'barang' => false]))">Riwayat</button>
                                    @endif
                                    @if($isDci)
                                        <a href="{{ route('perusahaan.sewa-truk.edit', $t->id_vendor_skill) }}" class="{{ $editBtn }}">Edit</a>
                                    @endif
                                    @if($isKg && ! $usulanRow)
                                        <button type="button" class="{{ $editBtn }}"
                                            @click="$dispatch('usulkan-harga', @js(['jenis' => 'sewa_truk', 'id_skill' => (int) $t->id_skill, 'cabang_code' => $t->cabang_code, 'label' => $t->nama_skill . ' · Cab. ' . $t->cabang_code, 'harga_sekarang' => (float) $t->harga_sewa]))">Usulkan Harga</button>
                                    @endif
                                    @if($t->pengajuan_url)
                                        <a href="{{ $t->pengajuan_url }}" class="whitespace-nowrap rounded-lg bg-avian-green px-2.5 py-1 text-xs font-medium text-white hover:bg-avian-green-dark">Buat Pengajuan</a>
                                    @endif
                                </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ==================== TARIF KIRIMAN RUTIN ==================== --}}
    @if($tarifKiriman->isNotEmpty())
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <p class="{{ $sectionLabel }}">TARIF KIRIMAN RUTIN</p>
            <span class="text-xs text-gray-400">{{ $tarifKiriman->count() }} area</span>
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                        <th class="px-3.5 py-2.5 text-left whitespace-nowrap">CABANG</th>
                        <th class="px-3.5 py-2.5 text-left whitespace-nowrap">AREA KIRIM</th>
                        @foreach($jenisBarangCols as $jb)
                            <th class="px-3.5 py-2.5 text-right whitespace-nowrap uppercase" title="{{ $jb->nama_barang }}">{{ $jb->nama_barang }}</th>
                        @endforeach
                        <th class="px-3.5 py-2.5 text-right">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tarifKiriman as $t)
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-2.5 whitespace-nowrap text-gray-600">{{ $t->cabang_code }} &mdash; {{ $t->nama_cabang ?? '—' }}</td>
                            <td class="px-3.5 py-2.5 whitespace-nowrap font-medium text-gray-800">{{ $t->nama_skill }}</td>
                            @foreach($jenisBarangCols as $jb)
                                <td class="px-3.5 py-2.5 text-right tabular-nums text-gray-700">
                                    {{ isset($hargaByVs[$t->id_vendor_skill][$jb->id_jenis_barang]) ? number_format($hargaByVs[$t->id_vendor_skill][$jb->id_jenis_barang], 0, ',', '.') : '—' }}
                                    <x-penanda-usulan-harga :usulan="$usulanBerjalan->get('pengiriman_rutin|' . $t->cabang_code . '|' . $t->id_skill . '|' . $jb->id_jenis_barang)" />
                                </td>
                            @endforeach
                                <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                    @if($lihatRiwayat)
                                        <button type="button" class="{{ $editBtn }}"
                                            @click="$dispatch('riwayat-harga', @js(['url' => route('perusahaan.riwayat-harga', ['kiriman-rutin', $t->id_vendor_skill]), 'judul' => 'Kiriman Rutin · '.$t->nama_skill.' · Cab. '.$t->cabang_code, 'barang' => true]))">Riwayat</button>
                                    @endif
                                    @if($isDci)
                                        <a href="{{ route('perusahaan.kiriman-rutin.edit', $t->id_vendor_skill) }}" class="{{ $editBtn }}">Edit</a>
                                    @endif
                                    @if($isKg)
                                        <button type="button" class="{{ $editBtn }}"
                                            @click="$dispatch('usulkan-harga', @js(['jenis' => 'pengiriman_rutin', 'id_skill' => (int) $t->id_skill, 'cabang_code' => $t->cabang_code, 'label' => $t->nama_skill . ' · Cab. ' . $t->cabang_code, 'harga_per_barang' => (object) ($hargaByVs[$t->id_vendor_skill] ?? [])]))">Usulkan Harga</button>
                                    @endif
                                    @if($t->pengajuan_url)
                                        <a href="{{ $t->pengajuan_url }}" class="whitespace-nowrap rounded-lg bg-avian-green px-2.5 py-1 text-xs font-medium text-white hover:bg-avian-green-dark">Buat Pengajuan</a>
                                    @endif
                                </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ==================== AREA TERDAFTAR, BELUM ADA TARIF ==================== --}}
    @if($belumAdaTarif->isNotEmpty())
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="{{ $sectionLabel }} mb-1">AREA TERDAFTAR, BELUM ADA TARIF</p>
            <p class="mb-3 text-xs text-gray-400">Area yang sudah terhubung ke perusahaan ini tapi belum punya harga sewa maupun tarif kiriman rutin.</p>
            <div class="flex flex-wrap gap-2">
                @foreach($belumAdaTarif as $b)
                    <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700">
                        <span class="font-medium">{{ $b->nama_skill }}</span>
                        <span class="text-gray-400">Cab. {{ $b->cabang_code }}</span>
                        @if($isDci)
                            <a href="{{ route('perusahaan.sewa-truk.edit', $b->id_vendor_skill) }}" class="text-avian-green hover:underline">Isi Sewa Truk</a>
                            <a href="{{ route('perusahaan.kiriman-rutin.edit', $b->id_vendor_skill) }}" class="text-avian-green hover:underline">Isi Kiriman Rutin</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($isKg)
        <x-modal-usulan-harga :perusahaan="$perusahaan" :jenis-barang="$jenisBarangSemua" />
    @endif

    @if($bolehKelola)
        {{-- Edit profil vendor: nama perusahaan hanya bisa diubah DCI --}}
        <div x-data="{ buka: false }" @edit-profil-vendor.window="buka = true" @keydown.escape.window="buka = false">
            <div x-show="buka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>
                <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                    x-data="formVendor(@js(route('pengajuan.update-vendor', $perusahaan->id_perusahaan)), @js($perusahaan->only(['nama_perusahaan', 'badan_usaha', 'no_telepon', 'alamat_kantor'])))">
                    <h3 class="mb-4 text-[17px] font-bold text-gray-900">Edit Profil Vendor</h3>
                    <x-form-vendor :foto-lama="$docSrcs->all()" :nama-terkunci="! $isDci" />
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="buka = false"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                        <button type="button" @click="simpan()" :disabled="menyimpan"
                            class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">
                            <span x-text="menyimpan ? 'Menyimpan…' : 'Simpan'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($lihatRiwayat)
        @include('pages.perusahaan.partials.modal-riwayat-harga')
    @endif
</div>
@endsection
