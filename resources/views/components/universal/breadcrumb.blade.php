@props(['links' => []])

<nav class="flex mb-4 text-xs font-medium" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-3">
        @foreach($links as $label => $url)
            <li class="inline-flex items-center">
                @if(!$loop->first)
                    <svg class="w-3.5 h-3.5 text-slate-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                @endif
                @if($url === '#' || $loop->last)
                    <span class="text-slate-500 font-bold {{ $loop->last ? 'text-slate-800' : '' }}">{{ $label }}</span>
                @else
                    <a href="{{ $url }}" class="text-slate-500 hover:text-emerald-600 transition-colors inline-flex items-center">
                        @if($loop->first)
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        @endif
                        {{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>



