@props(['user' => null, 'class' => 'w-9 h-9', 'textSize' => 'text-xs'])

@php
    $u = $user ?? auth()->user();
    $name = $u->Username ?? $u->Name ?? $u->Full_Name ?? $u->name ?? 'User';
    $photoUrl = app(\App\Services\Core\AvatarResolver::class)->resolve($u);

    // Dynamic Initials Fallback (Never Hardcoded DE)
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    $words = array_values(array_filter(explode(' ', $cleanName)));
    if (count($words) >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
    } elseif (count($words) === 1) {
        $initials = strtoupper(substr($words[0], 0, min(2, strlen($words[0]))));
    } else {
        $initials = 'US';
    }
@endphp

@if($photoUrl)
    <div class="relative shrink-0 flex items-center justify-center">
        <img src="{{ $photoUrl }}" alt="{{ $name }}" class="{{ $class }} rounded-full object-cover border-2 border-sky-400/80 shadow-sm shrink-0" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');">
        <div class="hidden {{ $class }} rounded-full bg-gradient-to-tr from-[#111827] to-sky-600 text-white font-extrabold items-center justify-center shrink-0 border-2 border-sky-400/80 shadow-sm {{ $textSize }}">
            <span>{{ $initials }}</span>
        </div>
    </div>
@else
    <div class="{{ $class }} rounded-full bg-gradient-to-tr from-[#111827] to-sky-600 text-white font-extrabold flex items-center justify-center shrink-0 border-2 border-sky-400/80 shadow-sm {{ $textSize }}">
        <span>{{ $initials }}</span>
    </div>
@endif
