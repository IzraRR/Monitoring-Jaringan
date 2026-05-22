@php
    $inputId = $id ?? 'password-' . uniqid();
    $inputName = $name ?? 'password';
    $inputValue = $value ?? '';
    $isRequired = $required ?? true;
    $placeholder = $placeholder ?? '';
@endphp
<div class="input-group">
    <input
        type="password"
        name="{{ $inputName }}"
        id="{{ $inputId }}"
        class="form-control {{ $class ?? '' }}"
        value="{{ $inputValue }}"
        placeholder="{{ $placeholder }}"
        @if($isRequired) required @endif
        autocomplete="{{ $autocomplete ?? 'off' }}"
    >
    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="{{ $inputId }}" tabindex="-1" aria-label="Tampilkan sandi">
        <i class="bi bi-eye"></i>
    </button>
</div>
