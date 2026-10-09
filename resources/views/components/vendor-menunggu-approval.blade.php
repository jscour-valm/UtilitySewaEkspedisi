{{-- Info: vendor pengajuan ini masih vendor baru yang belum disetujui WH, jadi pengajuan belum bisa divalidasi WM. --}}
@props(['vendor' => null])

@if($vendor)
    @php
        $role = auth()->user()?->userUtility?->role;
        $bolehBuka = in_array($role, ['KG', 'WM', 'WH', 'DCI'], true);
    @endphp
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900']) }}>
        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="min-w-0">
            <p>
                Vendor <strong>{{ $vendor->nama_perusahaan }}</strong> masih vendor baru
                (<span class="font-semibold">{{ $vendor->labelStatusPersetujuan() }}</span>).
                Pengajuan ini baru bisa divalidasi WM setelah vendornya disetujui WH.
            </p>
            @if($bolehBuka)
                <a href="{{ route('persetujuan.vendor.show', $vendor->id_perusahaan) }}"
                    class="mt-1 inline-block font-semibold text-amber-800 underline hover:text-amber-950">Lihat pengajuan vendor →</a>
            @endif
        </div>
    </div>
@endif
