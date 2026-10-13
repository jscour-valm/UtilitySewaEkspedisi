{{--
    Kotak pesan sukses. Tanpa props memakai session('success').
    Dipakai di: Perusahaan, detail perusahaan, Pengaturan, Setting Approver.
    Props: message (opsional, mengganti pesan dari session).
--}}
@props(['message' => null])

@php
  $message = $message ?? session('success');
@endphp

@if($message)
<div class="rounded-xl bg-avian-green-light border border-avian-green/30 text-avian-green px-4 py-3 text-sm">
    {{ $message }}
</div>
@endif
