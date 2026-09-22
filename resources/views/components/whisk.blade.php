@props(['size' => 26])

{{-- The whisk, tilted. The handle keeps Vispa's raspberry; the bowl follows the surrounding
     text colour so the mark reads on any background. --}}
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 26 26" aria-hidden="true" class="whisk">
    <path d="M13 2v7" stroke="#dd1f6e" stroke-width="2.4" stroke-linecap="round" />
    <g fill="none" stroke="currentColor" stroke-width="1.6" opacity="0.85">
        <path d="M13 9c-4 0-6 3.4-6 7.5S9.2 24 13 24s6-3.4 6-7.5S17 9 13 9z" />
        <path d="M13 9c-1.9 0-2.7 3.4-2.7 7.5S11.6 24 13 24s2.7-3.4 2.7-7.5S14.9 9 13 9z" />
        <path d="M7.4 16.5h11.2" stroke-width="1.3" />
    </g>
</svg>
