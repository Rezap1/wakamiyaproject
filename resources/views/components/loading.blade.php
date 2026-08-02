<div class="flex flex-col items-center justify-center py-12 px-4 w-full">
    <div class="w-full max-w-4xl space-y-4">
        <!-- Skeleton Header -->
        <div class="flex gap-4 mb-8">
            <div class="h-8 bg-slate-200 rounded-lg w-1/4 animate-pulse"></div>
            <div class="h-8 bg-slate-200 rounded-lg w-1/4 animate-pulse"></div>
            <div class="h-8 bg-slate-200 rounded-lg w-1/2 animate-pulse"></div>
        </div>
        <!-- Skeleton Rows -->
        @for($i=0; $i<5; $i++)
        <div class="flex gap-4">
            <div class="h-10 bg-slate-100 rounded-lg w-full animate-pulse" style="animation-delay: {{ $i * 100 }}ms"></div>
        </div>
        @endfor
    </div>
    <p class="text-sm font-medium text-slate-400 mt-6 animate-pulse">Memuat data...</p>
</div>



