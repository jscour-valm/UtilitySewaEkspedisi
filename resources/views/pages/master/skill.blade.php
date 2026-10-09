@extends('layouts.app')

@section('title', 'Master Skill')

@section('content')
<div class="flex flex-col gap-4 pb-2" x-data="masterSkill(@js($cabangList->count() === 1 ? $cabangList->first()->Code : ''))">
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Master Skill</h1>
                <p class="text-sm text-gray-600">Area kirim yang terdaftar per cabang — dipakai di tarif, kendaraan, dan pengajuan sewa.</p>
            </div>
            <button type="button" @click="bukaTambah()"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">+ Tambah Area</button>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama area…"
                class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            @if($cabangList->count() > 1)
                <select name="cabang" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                    <option value="">Semua cabang</option>
                    @foreach($cabangList as $c)
                        <option value="{{ $c->Code }}" @selected($cabang === $c->Code)>{{ $c->Code }} — {{ $c->Name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cari</button>
            @if($search !== '' || $cabang !== '')
                <a href="{{ route('master.skill') }}" class="text-sm text-gray-500 hover:underline">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full min-w-[36rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3 text-left">Cabang</th>
                        <th class="px-4 py-3 text-left">Area</th>
                        <th class="px-4 py-3 text-left">Didaftarkan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $r->cabang_code }} — {{ $r->nama_cabang ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $r->nama_skill }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->translatedFormat('d M Y') : '—' }}</td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" @click="bukaEdit({{ (int) $r->id_skill }}, @js($r->nama_skill))"
                                    class="text-xs font-medium text-avian-green hover:underline">Ganti Nama</button>
                                @if($isDci)
                                    <button type="button" @click="hapus({{ (int) $r->id_skill }}, @js($r->cabang_code), @js($r->nama_skill))"
                                        class="text-xs font-medium text-red-600 hover:underline">Hapus</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 text-center text-sm text-gray-400">Belum ada area{{ $search !== '' ? ' yang cocok' : '' }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    </div>

    {{-- Modal tambah / ganti nama --}}
    <div x-show="buka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="buka = false">
        <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-[17px] font-bold text-gray-900" x-text="idEdit ? 'Ganti Nama Area' : 'Tambah Area'"></h3>
            <p class="mb-4 mt-1 text-sm text-gray-500" x-show="idEdit">Nama area berlaku di semua cabang yang memakainya.</p>
            <div class="space-y-3">
                <div x-show="!idEdit">
                    <x-form-label required>Cabang</x-form-label>
                    <select x-model="form.cabang_code"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        <option value="">-- Pilih --</option>
                        @foreach($cabangList as $c)
                            <option value="{{ $c->Code }}">{{ $c->Code }} — {{ $c->Name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-form-label required>Nama Area</x-form-label>
                    <input type="text" x-model="form.nama_skill" maxlength="100" @keydown.enter.prevent="simpan()"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-avian-green focus:outline-none">
                </div>
            </div>
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
@endsection
