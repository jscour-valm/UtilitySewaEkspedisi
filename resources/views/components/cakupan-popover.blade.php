{{--
  Cakupan Popover Component
  Used in: /perusahaan tab Semua (server), tabel pemilihan perusahaan Step 1
  Pengajuan Sewa (alpine), preview tabel Perusahaan di dashboard KG (alpine)
--}}

@props(['mode' => 'alpine', 'row' => null, 'jsVar' => 'row', 'padding' => 'px-4 py-3', 'onlyArea' => false])

@if($mode === 'server')
    @php
        $cabangCount = $row->cabang_count ?? 0;
        $areaCount = $row->area_count ?? 0;
        $cabangList = $row->cabang_list ?? [];
        $areaList = $row->area_list ?? [];
        $showCount = $onlyArea ? $areaCount : ($cabangCount || $areaCount);
    @endphp
    <td class="{{ $padding }} text-gray-600 whitespace-nowrap"
        x-data="{
            open: false, pos: '', left: 0, _id: null,
            reposition() {
                const rect = $el.getBoundingClientRect();
                this.left = Math.max(8, Math.min(rect.left, window.innerWidth - 336));
                this.pos = (rect.bottom + 300 > window.innerHeight) ? 'bottom:' + (window.innerHeight - rect.top) + 'px' : 'top:' + rect.bottom + 'px';
            },
            show() {
                this._id = this._id || Math.random();
                this.reposition();
                this.open = true;
                window.dispatchEvent(new CustomEvent('cakupan:open', { detail: this._id }));
            }
        }"
        @mouseenter="show()"
        @mouseleave="open = false"
        @cakupan:open.window="if ($event.detail !== _id) open = false"
        @scroll.window.capture="if (open) reposition()">
        @if($showCount)
            <span class="cursor-default border-b border-dotted border-gray-400">
                @if($onlyArea)
                    {{ $areaCount }} area kirim
                @else
                    {{ $cabangCount }} cabang &middot; {{ $areaCount }} area kirim
                @endif
            </span>
            <template x-teleport="body">
                <div x-show="open" x-cloak @mouseenter="open = true" @mouseleave="open = false"
                    class="fixed z-50 py-1" :style="pos + ';left:' + left + 'px'">
                    <div class="max-h-72 w-80 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 text-xs shadow-lg">
                        @unless($onlyArea)
                            <p class="mb-1 font-semibold text-gray-700">Cabang ({{ $cabangCount }})</p>
                            <ul class="mb-3 space-y-0.5 text-gray-600">
                                @foreach($cabangList as $c)
                                    <li>{{ $c['code'] }}@if($c['nama']) — {{ $c['nama'] }}@endif</li>
                                @endforeach
                            </ul>
                        @endunless
                        <p class="mb-1 font-semibold text-gray-700">Area Kirim ({{ $areaCount }})</p>
                        <ul class="space-y-0.5 text-gray-600">
                            @foreach($areaList as $a)
                                <li>{{ $a }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </template>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </td>
@else
    <td class="{{ $padding }} text-gray-600 whitespace-nowrap"
        x-data="{
            open: false, pos: '', left: 0, _id: null,
            reposition() {
                const rect = $el.getBoundingClientRect();
                this.left = Math.max(8, Math.min(rect.left, window.innerWidth - 336));
                this.pos = (rect.bottom + 300 > window.innerHeight) ? 'bottom:' + (window.innerHeight - rect.top) + 'px' : 'top:' + rect.bottom + 'px';
            },
            show() {
                if (!({{ $onlyArea ? "({$jsVar}.area_count)" : "({$jsVar}.cabang_count || {$jsVar}.area_count)" }})) return;
                this._id = this._id || Math.random();
                this.reposition();
                this.open = true;
                window.dispatchEvent(new CustomEvent('cakupan:open', { detail: this._id }));
            }
        }"
        @mouseenter="show()"
        @mouseleave="open = false"
        @cakupan:open.window="if ($event.detail !== _id) open = false"
        @scroll.window.capture="if (open) reposition()">
        @if($onlyArea)
            <span x-show="({{ $jsVar }}.area_count)" class="cursor-default border-b border-dotted border-gray-400" x-text="({{ $jsVar }}.area_count ?? 0) + ' area kirim'"></span>
            <span x-show="!({{ $jsVar }}.area_count)" class="text-gray-400">—</span>
        @else
            <span x-show="({{ $jsVar }}.cabang_count || {{ $jsVar }}.area_count)" class="cursor-default border-b border-dotted border-gray-400" x-text="({{ $jsVar }}.cabang_count ?? 0) + ' cabang · ' + ({{ $jsVar }}.area_count ?? 0) + ' area kirim'"></span>
            <span x-show="!({{ $jsVar }}.cabang_count || {{ $jsVar }}.area_count)" class="text-gray-400">—</span>
        @endif
        <div x-show="open" x-cloak class="fixed z-50 py-1" :style="pos + ';left:' + left + 'px'">
            <div class="max-h-72 w-80 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 text-xs shadow-lg">
                @unless($onlyArea)
                    <p class="mb-1 font-semibold text-gray-700" x-text="'Cabang (' + ({{ $jsVar }}.cabang_count ?? 0) + ')'"></p>
                    <ul class="mb-3 space-y-0.5 text-gray-600">
                        <template x-for="c in ({{ $jsVar }}.cabang_list ?? [])" :key="c.code">
                            <li x-text="c.code + (c.nama ? ' — ' + c.nama : '')"></li>
                        </template>
                    </ul>
                @endunless
                <p class="mb-1 font-semibold text-gray-700" x-text="'Area Kirim (' + ({{ $jsVar }}.area_count ?? 0) + ')'"></p>
                <ul class="space-y-0.5 text-gray-600">
                    <template x-for="a in ({{ $jsVar }}.area_list ?? [])" :key="a">
                        <li x-text="a"></li>
                    </template>
                </ul>
            </div>
        </div>
    </td>
@endif
