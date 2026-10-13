{{--
    Kotak pesan error. Tanpa props memakai $errors->first().
    Dipakai di: Setting Approver, edit tarif.
    Props: message (opsional, mengganti pesan dari $errors).
--}}
@props(['message' => null])

@php
  $message = $message ?? (isset($errors) && $errors->any() ? $errors->first() : null);
@endphp

@if($message)
<div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
    {{ $message }}
</div>
@endif
