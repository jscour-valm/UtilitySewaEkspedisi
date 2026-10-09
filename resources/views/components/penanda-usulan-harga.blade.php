{{-- Penanda kecil di sel tarif: ada usulan harga master yang masih berjalan untuk tarif ini. --}}
@props(['usulan' => null])

@if($usulan)
    @php $bolehBuka = in_array(auth()->user()?->userUtility?->role, ['KG', 'WM', 'WH', 'DCI'], true); @endphp
    <{{ $bolehBuka ? 'a' : 'span' }} @if($bolehBuka) href="{{ route('persetujuan.harga.show', $usulan->id_usulan_harga) }}" @endif
        class="mt-0.5 block whitespace-nowrap text-[11px] font-medium text-amber-700 {{ $bolehBuka ? 'hover:underline' : '' }}"
        title="{{ $usulan->labelStatusPersetujuan() }}">
        Usulan {{ \App\Helpers\FormatHelper::rupiah($usulan->harga_usulan) }} · {{ $usulan->giliranPersetujuan() === 'WM' ? 'menunggu WM' : 'menunggu WH' }}
    </{{ $bolehBuka ? 'a' : 'span' }}>
@endif
