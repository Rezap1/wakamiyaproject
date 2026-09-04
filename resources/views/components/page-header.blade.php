@props(['title', 'description' => '', 'breadcrumbs' => []])

<div class="flex flex-col md:flex-row md:items-end justify-between gap-3 md:gap-4 mb-5 md:mb-8">
    <div>
        <!-- Breadcrumb -->
        @if(!empty($breadcrumbs))
        <nav class="hidden sm:flex mb-2 overflow-x-auto" aria-label="Breadcrumb">
            <ol class="inline-flex min-w-0 items-center space-x-1 md:space-x-1.5 whitespace-nowrap">
                @foreach($breadcrumbs as $label => $url)
                    <li class="inline-flex items-center">
                        @if(!$loop->last)
                            <a href="{{ is_numeric($label) ? '#' : $url }}" class="inline-flex items-center text-[11px] font-extrabold text-slate-400 dark:text-slate-500 hover:text-emerald-600 dark:hover:text-blue-400 uppercase tracking-widest transition-colors">
                                {{ is_numeric($label) ? $url : $label }}
                            </a>
                            <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600 mx-1 md:mx-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                        @else
                            <span class="text-[11px] font-extrabold text-slate-800 dark:text-white uppercase tracking-widest">{{ is_numeric($label) ? $url : $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        @endif
        
        <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight break-words">{!! strip_tags($title) !!}</h1>
        @if($description)
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed break-words">{{ $description }}</p>
        @endif
    </div>
    
    @if(isset($actions))
        <div class="flex w-full flex-wrap items-center gap-2 sm:gap-3 md:w-auto shrink-0 mt-2 md:mt-0">
            {{ $actions }}
        </div>
    @endif
</div>



