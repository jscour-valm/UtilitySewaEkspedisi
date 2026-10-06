<div x-show="previewIndex !== null" x-cloak
    @keydown.escape.window="closeLightbox()"
    @keydown.arrow-left.window="prevLightbox()"
    @keydown.arrow-right.window="nextLightbox()"
    @click.self="closeLightbox()"
    class="fixed inset-0 z-50 bg-black bg-opacity-80">

    <button type="button" @click="closeLightbox()"
        class="fixed top-4 right-4 z-[60] flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-lg hover:bg-white transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    <template x-if="previewPhotos.length > 1">
        <button type="button" @click="prevLightbox()"
            class="fixed left-4 top-1/2 -translate-y-1/2 z-[60] flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow-lg hover:bg-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
    </template>
    <template x-if="previewPhotos.length > 1">
        <button type="button" @click="nextLightbox()"
            class="fixed right-4 top-1/2 -translate-y-1/2 z-[60] flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow-lg hover:bg-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </template>

    {{-- Strip thumbnail — nempel ke tepi bawah-tengah layar --}}
    <template x-if="previewPhotos.length > 1">
        <div @click.stop class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[60] flex gap-2 justify-center flex-wrap max-w-[90vw] px-2">
            <template x-for="(src, i) in previewPhotos" :key="i">
                <img :src="src" @click="previewIndex = i; zoomed = false"
                    :class="i === previewIndex ? 'ring-2 ring-avian-green opacity-100' : 'opacity-60 hover:opacity-100'"
                    class="h-14 w-14 rounded-md object-cover cursor-pointer border border-gray-200 transition shadow-lg">
            </template>
        </div>
    </template>

    <div @click.self="closeLightbox()" class="flex h-full w-full items-center justify-center p-4">
        <div class="flex items-center justify-center overflow-auto max-h-[75vh] max-w-[85vw]">
            <img :src="previewPhotos[previewIndex]" @click.stop="toggleZoom()"
                :class="zoomed ? 'max-w-none max-h-none cursor-zoom-out' : 'max-h-[75vh] max-w-[85vw] object-contain cursor-zoom-in'"
                class="rounded-lg">
        </div>
    </div>
</div>
