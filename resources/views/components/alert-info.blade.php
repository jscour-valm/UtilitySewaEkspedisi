{{--
  Info/Warning Alert Box Component
  Used in: Detail view (over-threshold notifications)

  @props(['title', 'message'])
  @example
    <x-alert-info
      title="Perlu Persetujuan Berlapis"
      message="Rasio Sewa 2.30% melebihi threshold 2.5% — pengajuan ini akan diteruskan ke WH setelah disetujui WM."
    />
--}}

@props(['title' => null, 'message' => null])

<div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
  <div class="flex gap-3">
    {{-- Warning Icon --}}
    <svg class="w-5 h-5 text-orange-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
    </svg>

    {{-- Content --}}
    <div class="flex-1">
      @isset($title)
        <h4 class="font-semibold text-orange-900">{{ $title }}</h4>
      @endisset
      @isset($message)
        <p class="text-sm text-orange-800 {{ isset($title) && $title ? 'mt-1' : '' }}">
          {{ $message }}
        </p>
      @endisset
      @if($slot->isNotEmpty())
        <div class="text-sm text-orange-800 {{ (isset($title) && $title) || (isset($message) && $message) ? 'mt-2' : '' }}">
          {{ $slot }}
        </div>
      @endif
    </div>
  </div>
</div>
