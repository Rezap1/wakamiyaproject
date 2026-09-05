@props(['paginator'])

<div class="flex flex-col items-stretch gap-3 px-4 py-4 bg-white border-t border-slate-200 rounded-b-2xl sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <div class="text-[13px] text-slate-500 font-medium">
        Menampilkan <span class="font-bold text-slate-800">{{ $paginator->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-slate-800">{{ $paginator->lastItem() ?? 0 }}</span> dari <span class="font-bold text-slate-800">{{ $paginator->total() }}</span> total
    </div>
    <div class="max-w-full overflow-x-auto">
        {{ $paginator->links('pagination::tailwind') }}
    </div>
</div>



