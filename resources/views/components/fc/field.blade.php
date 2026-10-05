@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'autocomplete' => null, 'required' => true])
@php($err = $errors->first($name))
<div class="field">
  <label for="f-{{ $name }}">{{ $label }}</label>
  @if($hint)<p class="hint" id="h-{{ $name }}">{{ $hint }}</p>@endif
  <input class="input" id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ old($name, $value) }}" @endif
    @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if($required) required @endif
    @if($err || $hint) aria-describedby="{{ $err ? 'e-'.$name : '' }} {{ $hint ? 'h-'.$name : '' }}" @endif
    @if($err) aria-invalid="true" @endif>
  @if($err)<p class="field-error" id="e-{{ $name }}"><x-fc.icon name="error" :size="16" />{{ $err }}</p>@endif
</div>
