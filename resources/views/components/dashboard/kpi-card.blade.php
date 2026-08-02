@props(['title', 'value', 'color' => 'blue', 'subtext' => '+12 dari bulan lalu', 'subtextStatus' => 'up', 'href' => '#'])

@php
    $colors = match($color) {
        'indigo' => ['bg' => 'bg-indigo-500', 'text' => 'text-white', 'sparkline' => 'stroke-indigo-400', 'fill' => 'fill-indigo-50'],
        'purple' => ['bg' => 'bg-purple-500', 'text' => 'text-white', 'sparkline' => 'stroke-purple-400', 'fill' => 'fill-purple-50'],
        'pink' => ['bg' => 'bg-pink-500', 'text' => 'text-white', 'sparkline' => 'stroke-pink-400', 'fill' => 'fill-pink-50'],
        'amber' => ['bg' => 'bg-orange-400', 'text' => 'text-white', 'sparkline' => 'stroke-orange-400', 'fill' => 'fill-orange-50'],
        'orange' => ['bg' => 'bg-orange-500', 'text' => 'text-white', 'sparkline' => 'stroke-orange-400', 'fill' => 'fill-orange-50'],
        'teal' => ['bg' => 'bg-teal-500', 'text' => 'text-white', 'sparkline' => 'stroke-teal-400', 'fill' => 'fill-teal-50'],
        'cyan' => ['bg' => 'bg-cyan-500', 'text' => 'text-white', 'sparkline' => 'stroke-cyan-400', 'fill' => 'fill-cyan-50'],
        'sky' => ['bg' => 'bg-sky-500', 'text' => 'text-white', 'sparkline' => 'stroke-sky-400', 'fill' => 'fill-sky-50'],
        'slate' => ['bg' => 'bg-slate-500', 'text' => 'text-white', 'sparkline' => 'stroke-slate-400', 'fill' => 'fill-slate-50'],
        'red' => ['bg' => 'bg-red-500', 'text' => 'text-white', 'sparkline' => 'stroke-red-400', 'fill' => 'fill-red-50'],
        'green' => ['bg' => 'bg-emerald-500', 'text' => 'text-white', 'sparkline' => 'stroke-emerald-400', 'fill' => 'fill-emerald-50'],
        'primary' => ['bg' => 'bg-emerald-600', 'text' => 'text-white', 'sparkline' => 'stroke-blue-400', 'fill' => 'fill-blue-50'],
        'blue' => ['bg' => 'bg-emerald-500', 'text' => 'text-white', 'sparkline' => 'stroke-blue-400', 'fill' => 'fill-blue-50'],
        default => ['bg' => 'bg-emerald-500', 'text' => 'text-white', 'sparkline' => 'stroke-blue-400', 'fill' => 'fill-blue-50'],
    };

    $subtextColor = $subtextStatus === 'up' ? 'text-emerald-500' : ($subtextStatus === 'down' ? 'text-red-500' : 'text-slate-500');
@endphp

<a href="{{ $href }}" class="block bg-white rounded-2xl p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-300 relative overflow-hidden group hover:border-{{ $colors['bg'] }}">
    <div class="flex justify-between items-start mb-2 relative z-10">
        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 {{ $colors['bg'] }} {{ $colors['text'] }}">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                @if(str_contains(strtolower($title), 'student') || str_contains(strtolower($title), 'siswa'))
                    <!-- Solid User for Student -->
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                @elseif(str_contains(strtolower($title), 'employee') || str_contains(strtolower($title), 'user') || str_contains(strtolower($title), 'pegawai') || str_contains(strtolower($title), 'pengguna'))
                    <!-- Solid Users for Employee -->
                    <path fill-rule="evenodd" d="M8.25 6.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM15.75 9.75a3 3 0 116 0 3 3 0 01-6 0zM2.25 9.75a3 3 0 116 0 3 3 0 01-6 0zM6.31 15.117A6.745 6.745 0 0112 12a6.745 6.745 0 016.709 7.498.75.75 0 01-.372.568A12.696 12.696 0 0112 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 01-.372-.568 6.787 6.787 0 011.019-4.38z" clip-rule="evenodd" />
                    <path d="M5.082 14.254a8.287 8.287 0 00-1.308 5.135 9.687 9.687 0 01-1.764-.44l-.115-.04a.563.563 0 01-.373-.487l-.01-.121a3.75 3.75 0 016.576-3.036c.326.323.636.66 .924 1.013a7.973 7.973 0 00-3.93-2.022zM19.279 18.949a8.287 8.287 0 00-1.308-5.135 7.973 7.973 0 00-3.93 2.022c.288-.353.598-.69.924-1.013a3.75 3.75 0 016.576 3.036l-.01.121a.563.563 0 01-.373.486l-.115.04c-.56.195-1.15.356-1.764.44z" />
                @elseif(str_contains(strtolower($title), 'teacher') || str_contains(strtolower($title), 'academic') || str_contains(strtolower($title), 'guru') || str_contains(strtolower($title), 'akademik'))
                    <!-- Solid Academic Cap -->
                    <path d="M11.7 2.805a.75.75 0 01.6 0A60.65 60.65 0 0122.83 8.72a.75.75 0 01-.231 1.337 49.949 49.949 0 00-9.902 3.912l-.003.002c-.114.06-.227.119-.34.18a.75.75 0 01-.707 0A50.88 50.88 0 007.5 12.173v-.224c0-.131.067-.248.172-.311a54.615 54.615 0 014.653-2.52.75.75 0 00-.65-1.352 56.123 56.123 0 00-4.78 2.589 1.858 1.858 0 00-.859 1.228 49.803 49.803 0 00-4.634-1.527.75.75 0 01-.231-1.337A60.653 60.653 0 0111.7 2.805z" />
                    <path d="M13.06 15.473a48.45 48.45 0 017.666-3.282c.134 1.414.22 2.843.256 4.287.025 1.002-.596 1.9-1.528 2.193a50.92 50.92 0 01-7.14 1.439.75.75 0 01-.587-.14 49.255 49.255 0 01-10.638-7.772 1.996 1.996 0 001.373 1.077c.452.091.908.163 1.368.217v.933c0 .54-.26 1.05-.726 1.362A10.742 10.742 0 014.5 17.5a.75.75 0 00-1.5 0c0 1.25.437 2.404 1.168 3.313A1.75 1.75 0 005.5 21.5h13a1.75 1.75 0 001.332-.687c.731-.909 1.168-2.063 1.168-3.313 0-1.895-.494-3.673-1.363-5.234z" />
                @elseif(str_contains(strtolower($title), 'company') || str_contains(strtolower($title), 'perusahaan'))
                    <!-- Solid Lightning Bolt -->
                    <path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" clip-rule="evenodd" />
                @elseif(str_contains(strtolower($title), 'document') || str_contains(strtolower($title), 'file') || str_contains(strtolower($title), 'dokumen') || str_contains(strtolower($title), 'arsip'))
                    <!-- Solid Document -->
                    <path fill-rule="evenodd" d="M5.625 1.5H9a3.75 3.75 0 013.75 3.75v1.875c0 1.036.84 1.875 1.875 1.875H16.5a3.75 3.75 0 013.75 3.75v7.875c0 1.035-.84 1.875-1.875 1.875H5.625a1.875 1.875 0 01-1.875-1.875V3.375c0-1.036.84-1.875 1.875-1.875zM12.75 12a.75.75 0 00-1.5 0v2.25H9a.75.75 0 000 1.5h2.25V18a.75.75 0 001.5 0v-2.25H15a.75.75 0 000-1.5h-2.25V12z" clip-rule="evenodd" />
                    <path d="M14.25 5.25a5.23 5.23 0 00-1.279-3.434 9.768 9.768 0 016.963 6.963A5.23 5.23 0 0016.5 7.5h-1.875a.375.375 0 01-.375-.375V5.25z" />
                @else
                    <!-- Default Solid Star -->
                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z" clip-rule="evenodd" />
                @endif
            </svg>
        </div>
        <div class="text-right">
            <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">{{ $title }}</h3>
            <p class="text-3xl font-extrabold text-slate-800 tracking-tight leading-none">{{ is_numeric($value) ? number_format($value) : $value }}</p>
        </div>
    </div>

    <div class="flex items-end justify-between relative z-10 mt-6">
        <span class="text-[12px] font-bold {{ $subtextColor }}">{{ $subtext }}</span>
        
        <!-- Small Sparkline -->
        <div class="w-20 h-10 opacity-80 group-hover:opacity-100 transition-opacity">
            @php 
                $points = [20, 15, 18, 5, 12, 8, 18, 2, 10, 4, 10]; 
                for($i=0; $i<count($points); $i++) {
                    $points[$i] += rand(-3, 3);
                }
                $pathData = "M0,{$points[0]}";
                for($i=1; $i<count($points); $i++) {
                    $x = $i * 10;
                    $pathData .= " L{$x},{$points[$i]}";
                }
                $fillPath = $pathData . " L100,20 L0,20 Z";
            @endphp
            <svg viewBox="0 0 100 20" preserveAspectRatio="none" class="w-full h-full">
                <path d="{{ $fillPath }}" class="{{ $colors['fill'] }} opacity-50 border-0" stroke="none"></path>
                <path d="{{ $pathData }}" fill="none" class="{{ $colors['sparkline'] }}" stroke-width="2" vector-effect="non-scaling-stroke"></path>
            </svg>
        </div>
    </div>
</a>



