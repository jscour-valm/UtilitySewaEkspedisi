@props(['col', 'label', 'sortBy', 'sortOrder', 'sortParam', 'orderParam', 'align' => 'left'])

@php
// `page` di-drop dari query: ganti urutan harus balik ke halaman 1, bukan
// nyangkut di halaman N dari urutan sebelumnya (relevan buat tabel ber-pagination).
$baseQuery = collect(request()->query())->except('page');
$isActive = $sortBy === $col;
if (!$isActive) {
    $nextParams = $baseQuery->merge([$sortParam => $col, $orderParam => 'asc'])->all();
} elseif ($sortOrder === 'asc') {
    $nextParams = $baseQuery->merge([$sortParam => $col, $orderParam => 'desc'])->all();
} else {
    // Balik ke default (remove sort params dari query)
    $nextParams = $baseQuery->except([$sortParam, $orderParam])->all();
}
$sortUrl = '?' . http_build_query($nextParams);
$icon = $isActive ? ($sortOrder === 'asc' ? '↑' : '↓') : '⇅';
$iconClass = $isActive ? 'text-gray-900 font-bold' : 'text-gray-400 group-hover:text-gray-600';
@endphp

<th {{ $attributes->merge(['class' => 'overflow-hidden text-ellipsis px-3 py-3 text-' . $align . ' whitespace-nowrap']) }}>
    <a href="{{ $sortUrl }}" title="{{ $label }}" class="group inline-flex max-w-full items-center gap-1.5 select-none hover:text-gray-700 transition">
        <span class="truncate">{{ $label }}</span>
        <span class="shrink-0 text-sm leading-none {{ $iconClass }}">{{ $icon }}</span>
    </a>
</th>
