@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="space-y-16">
    {{-- Card: Pengajuan Sewa --}}
    <div class="rounded-xl">
        <div class="rounded-xl bg-white shadow  mb-4">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-700">Pengajuan Sewa</h2>
                <a href="{{ route('pengajuan.kg') }}"
                class="rounded-lg bg-avian-green px-3 py-1.5 text-xs font-medium text-white hover:bg-avian-green-dark">
                    + Pengajuan Baru
                </a>
            </div>
            <div class="p-6">
                <x-tabel-pengajuan mode="dashboard" />
            </div>
        </div>
    </div>

    {{-- Card: Daftar Armada --}}
    <div class="rounded-xl bg-white shadow">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Daftar Armada</h2>
            <a href="{{ route('armada.idx') }}"
               class="text-xs text-avian-green hover:underline">
                Lihat semua →
            </a>
        </div>
        <div class="p-6">
            <x-tabel-armada mode="dashboard" />
        </div>
    </div>
</div>
@endsection