@props([
    'eyebrow' => null,
    'title' => null,
    'center' => true,
])

<div @class([
    'sec_title position-relative',
    'text-center' => $center,
])>
    @if ($eyebrow)
        <h2 class="off_title">{{ $eyebrow }}</h2>
    @endif
    @if ($title)
        <h3 class="ft-bold pt-3">{{ $title }}</h3>
    @endif
</div>
