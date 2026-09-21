{{--
  Success Alert Box Component
  Used in: Halaman index/edit setelah action sukses (flash session)

  @props(['message' => null])
  @example
    Tanpa props: auto pakai session('success')
      <x-alert-success />
    Override manual:
      <x-alert-success message="Data disimpan." />
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
