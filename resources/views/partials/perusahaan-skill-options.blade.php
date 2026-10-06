@php $selected = $selected ?? []; @endphp
@foreach($options as $opt)
    <label>
      <input type="checkbox" name="skill[]" value="{{ $opt['value'] }}"
        @checked(in_array((string) $opt['value'], $selected, true))>
          <span>
            {{ $opt['label'] }}
          </span>
    </label>
@endforeach
