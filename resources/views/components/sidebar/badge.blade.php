@props(['count' => 0, 'label' => 'notifikasi'])

@php
    $badgeCount = max(0, (int) $count);
    $displayCount = $badgeCount > 99 ? '99+' : (string) $badgeCount;
@endphp

@if($badgeCount > 0)
    <span {{ $attributes->class([
            'inline-flex shrink-0 items-center justify-center rounded-full bg-red-600 px-1.5',
            'min-w-[1.25rem] h-5 text-[10px] font-extrabold leading-none text-white shadow-sm',
        ]) }}
        role="status"
        aria-label="{{ $badgeCount }} {{ $label }}">
        {{ $displayCount }}
    </span>
@endif
