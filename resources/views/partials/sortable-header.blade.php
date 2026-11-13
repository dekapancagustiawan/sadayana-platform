@php
    $defaultSort = $defaultSort ?? 'created_at';
    $defaultDirection = $defaultDirection ?? 'desc';
    
    $active = request('sort', $defaultSort) === $column;
    $currentDir = request('direction', $defaultDirection);
    
    // Toggle direction: if active and currently asc, toggle to desc, else toggle to asc
    $nextDir = ($active && $currentDir === 'asc') ? 'desc' : 'asc';
@endphp

<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDir, 'page' => 1]) }}" class="text-dark d-inline-flex align-items-center text-decoration-none">
    <span>{{ $label }}</span>
    @if($active)
        @if($currentDir === 'asc')
            <i class="fas fa-sort-up ml-1 text-primary" style="margin-top: 4px;"></i>
        @else
            <i class="fas fa-sort-down ml-1 text-primary" style="margin-bottom: 4px;"></i>
        @endif
    @else
        <i class="fas fa-sort ml-1 text-muted" style="opacity: 0.4;"></i>
    @endif
</a>
