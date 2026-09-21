{{--
  Error Alert Box Component
  Used in: Halaman index/edit setelah validation error ($errors bag)

  @props(['message' => null])
  @example
    Tanpa props: auto pakai $errors->first()
      <x-alert-error />
    Override manual:
      <x-alert-error message="Kode sudah dipakai." />
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
