{{--
  Stat Card Component
  Used in: Dashboard WH, Dashboard DCI

  @props(['label', 'count', 'subtitle', 'color' => 'orange'])
  @example
    <x-stat-card label="PERLU DISETUJI WH" :count="3" subtitle="Menunggu keputusan Anda" color="orange" />
--}}

@props(['label', 'count', 'subtitle', 'color' => 'orange'])

@php
  $colorClasses = match($color) {
    'orange' => 'text-orange-500',
    'yellow' => 'text-yellow-600',
    'green' => 'text-green-600',
    'red' => 'text-red-600',
    'gray' => 'text-gray-600',
    default => 'text-orange-500',
  };
@endphp

<div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $label }}</p>
  <p class="text-4xl font-bold {{ $colorClasses }} mt-2">{{ $count }}</p>
  <p class="text-sm text-gray-600 mt-1">{{ $subtitle }}</p>
</div>
