{{--
  Large Status Badge Component
  Used in: Detail view header

  @props(['status'])
  @example
    <x-status-badge-large status="pending" />
    <x-status-badge-large status="approved" />
    <x-status-badge-large status="rejected" />
--}}

@props(['status'])

@php
  $statusConfig = match($status ?? 'pending') {
    'pending' => [
      'bg' => 'bg-amber-50',
      'border' => 'border-amber-200',
      'text' => 'text-amber-900',
      'dot' => 'bg-amber-400',
      'label' => 'Menunggu Persetujuan WM',
    ],
    'approved' => [
      'bg' => 'bg-green-50',
      'border' => 'border-green-200',
      'text' => 'text-green-900',
      'dot' => 'bg-green-400',
      'label' => 'Disetujui',
    ],
    'rejected' => [
      'bg' => 'bg-red-50',
      'border' => 'border-red-200',
      'text' => 'text-red-900',
      'dot' => 'bg-red-400',
      'label' => 'Ditolak',
    ],
    default => [
      'bg' => 'bg-gray-50',
      'border' => 'border-gray-200',
      'text' => 'text-gray-900',
      'dot' => 'bg-gray-400',
      'label' => 'Status Tidak Diketahui',
    ],
  };
@endphp

<div class="{{ $statusConfig['bg'] }} border {{ $statusConfig['border'] }} rounded-lg px-4 py-3 flex items-center gap-3">
  <span class="w-3 h-3 rounded-full {{ $statusConfig['dot'] }}"></span>
  <span class="text-sm font-medium {{ $statusConfig['text'] }}">{{ $statusConfig['label'] }}</span>
</div>
