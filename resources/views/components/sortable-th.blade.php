@props(['col', 'label', 'sortBy', 'sortOrder', 'sortParam', 'orderParam', 'align' => 'left'])

@php
$isActive = $sortBy === $col;
if (!$isActive) {
    $nextParams = array_merge(request()->query(), [$sortParam => $col, $orderParam => 'asc']);
} elseif ($sortOrder === 'asc') {
    $nextParams = array_merge(request()->query(), [$sortParam => $col, $orderParam => 'desc']);
} else {
    // Balik ke default (remove sort params dari query)
    $nextParams = collect(request()->query())->except([$sortParam, $orderParam])->all();
}
$sortUrl = '?' . http_build_query($nextParams);
$icon = $isActive ? ($sortOrder === 'asc' ? '↑' : '↓') : '⇅';
$iconClass = $isActive ? 'text-gray-900 font-bold' : 'text-gray-400 group-hover:text-gray-600';
@endphp

<th class="px-3 py-3 text-{{ $align }} whitespace-nowrap">
    <a href="{{ $sortUrl }}" class="group inline-flex items-center gap-1.5 select-none hover:text-gray-700 transition">
        <span>{{ $label }}</span>
        <span class="text-sm leading-none {{ $iconClass }}">{{ $icon }}</span>
    </a>
</th>
