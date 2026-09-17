@extends('layouts.app')

@section('title', 'Pengajuan Sewa')

@section('content')
@if(isset($editPengajuan))
<script>window.__editPengajuan = @json($editPengajuan);</script>
@endif
<script>window.__userCabang = @json(auth()->user()->getCabangId());</script>
<div
    class="flex flex-col gap-4 pb-2"
    x-data="pengajuanSewa">

    {{-- Clear Draft Button & Info Banner --}}
    <div x-show="!editId && localStorage.getItem('pengajuan_draft')" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
        <p class="mb-1"><span class="font-medium">Draft otomatis tersimpan.</span> File KTP/SIM perlu diupload ulang jika Anda menambah kendaraan baru.</p>
        <button type="button" @click="if(confirm('Hapus draft yang tersimpan?')) clearDraft()"
            class="text-amber-700 hover:text-amber-900 font-medium underline">
            Hapus Draft
        </button>
    </div>

    {{-- Toggle Jenis Pengajuan (persisten, di atas stepper) --}}
    <div class="rounded-xl bg-white shadow-sm px-8 py-4">
        <div class="flex items-center gap-3">
            <span class="text-xs font-medium text-gray-500">Jenis Pengajuan Sewa</span>
            <div class="inline-flex rounded-lg border border-gray-300 p-0.5">
                <button type="button" @click="gantiJenis('sewa_truk')" :disabled="editId"
                    :class="pengajuan.jenis_pengajuan === 'sewa_truk' ? 'bg-avian-green text-white' : 'text-gray-600 hover:bg-gray-50'"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Sewa Truk
                </button>
                <button type="button" @click="gantiJenis('pengiriman_rutin')" :disabled="editId"
                    :class="pengajuan.jenis_pengajuan === 'pengiriman_rutin' ? 'bg-avian-green text-white' : 'text-gray-600 hover:bg-gray-50'"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Kiriman Rutin
                </button>
            </div>
        </div>
    </div>

    {{-- Stepper --}}
    <div class="rounded-xl bg-white shadow-sm px-8 py-6">
        <div class="flex items-center justify-between">

            @foreach ([
                [1, 'truck',        'step1'],
                [2, 'file-text',    'Data Pengajuan'],
                [3, 'file-search',  'Pilih Dokumen'],
                [4, 'check-circle', 'Review & Submit'],
            ] as [$n, $icon, $label])

                {{-- Step bubble --}}
                <div class="flex flex-col items-center gap-2 cursor-pointer"
                    @click="goToStep({{ $n }})">
                    <div
                        class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-200"
                        :class="{
                            'bg-avian-green border-avian-green text-white': step === {{ $n }},
                            'bg-avian-green-light border-avian-green text-avian-green': step > {{ $n }},
                            'bg-white border-gray-300 text-gray-400': step < {{ $n }} && !canGoToStep({{ $n }}),
                            'bg-white border-avian-green text-avian-green cursor-pointer': step < {{ $n }} && canGoToStep({{ $n }}),
                        }">
                        <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
                    </div>
                    <span
                        class="text-xs font-medium"
                        :class="{
                            'text-avian-green': step >= {{ $n }},
                            'text-gray-400': step < {{ $n }} && !canGoToStep({{ $n }}),
                            'text-avian-green': step < {{ $n }} && canGoToStep({{ $n }}),
                        }"
                        @if($label === 'step1') x-text="pengajuan.jenis_pengajuan === 'pengiriman_rutin' ? 'Pilih Ekspedisi' : 'Pilih Kendaraan'" @endif>
                        @if($label !== 'step1'){{ $label }}@endif
                    </span>
                </div>

                {{-- Garis penghubung --}}
                @if ($n < 4)
                <div class="flex-1 h-0.5 mx-2 mt-[-18px]"
                    :class="step > {{ $n }} ? 'bg-avian-green' : 'bg-gray-200'">
                </div>
                @endif

            @endforeach

        </div>
    </div>

    {{-- Content Area --}}
    <div class="rounded-xl bg-white shadow-sm p-6">
        <div x-show="step === 1">@include('pages.pengajuan.step1')</div>
        <div x-show="step === 2">@include('pages.pengajuan.step2')</div>
        <div x-show="step === 3">@include('pages.pengajuan.step3')</div>
        <div x-show="step === 4">@include('pages.pengajuan.step4')</div>
    </div>
</div>
@endsection