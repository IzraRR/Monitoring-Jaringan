@props([
    'title',
    'value',
    'subtitle' => '',
    'valueClass' => 'text-dark',
    'icon' => null,
])

<div class="card border-0 shadow-sm rounded-3 bg-white h-100">
    <div class="card-body">
        @if($icon)
        <div class="d-flex justify-content-between align-items-start mb-2">
            <p class="text-secondary mb-0">{{ $title }}</p>
            <i class="{{ $icon }} text-muted fs-4"></i>
        </div>
        @else
        <p class="text-secondary mb-1">{{ $title }}</p>
        @endif
        
        <h3 class="fw-bold {{ $valueClass }} mb-0">{{ $value }}</h3>
        
        @if($subtitle)
        <small class="text-muted">{{ $subtitle }}</small>
        @endif
    </div>
</div>
