<?php
$file = 'resources/views/academic/attendances/index.blade.php';
$content = file_get_contents($file);

// Find the toolbar slot block
$startStr = '<x-slot:toolbar>';
$endStr = '</x-slot:toolbar>';

$start = strpos($content, $startStr);
$end = strpos($content, $endStr);

if ($start !== false && $end !== false) {
    $end += strlen($endStr);
    
    $newToolbar = <<<'EOD'
    <x-slot:toolbar>
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <form action="{{ route('attendances.index') }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-end lg:items-center justify-between">
                <div class="flex-1 flex flex-col md:flex-row gap-3 w-full flex-wrap items-center">
                    
                    <!-- Search -->
                    <div class="w-full md:max-w-[200px] relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari siswa/ID..." class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block pl-9 p-2 transition-colors">
                    </div>
                    
                    <!-- Class -->
                    <div class="w-full md:w-auto">
                        <select name="class_id" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 pr-8 transition-colors" onchange="this.form.submit()">
                            <option value="">Semua Kelas</option>
                            @foreach($classOptions ?? [] as $id => $label)
                                <option value="{{ $id }}" {{ request('class_id') == $id ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Date Range -->
                    <div class="flex items-center gap-1.5 w-full md:w-auto">
                        <input type="date" name="date" value="{{ request('date', date('Y-m-d')) }}" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 transition-colors" onchange="this.form.submit()">
                        <span class="text-slate-400 font-bold">—</span>
                        <input type="date" name="date_end" value="{{ request('date_end') }}" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 transition-colors" onchange="this.form.submit()">
                    </div>
                    
                    <!-- Status -->
                    <div class="w-full md:w-auto">
                        <select name="status" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-[13px] rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2 pr-8 transition-colors" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="Hadir" {{ request('status') == 'Hadir' ? 'selected' : '' }}>Hadir</option>
                            <option value="Terlambat" {{ request('status') == 'Terlambat' ? 'selected' : '' }}>Terlambat</option>
                            <option value="Izin" {{ request('status') == 'Izin' ? 'selected' : '' }}>Izin</option>
                            <option value="Sakit" {{ request('status') == 'Sakit' ? 'selected' : '' }}>Sakit</option>
                            <option value="Alpha" {{ request('status') == 'Alpha' ? 'selected' : '' }}>Alpa</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="hidden">Filter</button>
                </div>
                
                <!-- Action Tools -->
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('attendances.index') }}" class="flex items-center justify-center p-2.5 text-slate-500 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 hover:text-blue-600 focus:ring-2 focus:ring-blue-500 transition-colors shadow-sm" title="Segarkan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </a>
                    
                    @if(request()->anyFilled(['search', 'status', 'date', 'date_end', 'class_id']))
                        <a href="{{ route('attendances.index') }}" class="flex items-center justify-center p-2.5 text-red-500 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 focus:ring-2 focus:ring-red-500 transition-colors shadow-sm" title="Reset Filter">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </x-slot:toolbar>
EOD;

    $content = substr_replace($content, $newToolbar, $start, $end - $start);
    file_put_contents($file, $content);
    echo "Updated filter toolbar in index.blade.php successfully.\n";
} else {
    echo "Could not find toolbar slot block.\n";
}
