@props([
    'name',
    'id' => null,
    'type' => 'password',
    'placeholder' => '',
    'variant' => 'default',
    'required' => false,
    'inputmode' => null,
    'autocomplete' => null,
])

@php
    $inputId = $id ?? $name;
    $fieldClass = 'auth-secret-field';
    if ($variant === 'login') {
        $fieldClass .= ' auth-secret-field--login';
    }
@endphp

<div class="{{ $fieldClass }}">
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $inputId }}"
        @if ($required) required @endif
        @if ($inputmode) inputmode="{{ $inputmode }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        class="auth-secret-input"
        placeholder="{{ $placeholder }}"
        data-secret-input
    />
    <button
        type="button"
        class="auth-secret-toggle"
        aria-label="Show value"
        data-secret-toggle
        tabindex="-1"
    >
        <svg data-icon-show xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
        <svg data-icon-hide xmlns="http://www.w3.org/2000/svg" class="auth-secret-icon-hidden" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
            <line x1="2" x2="22" y1="2" y2="22" />
        </svg>
    </button>
</div>
