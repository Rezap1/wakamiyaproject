@props(['paginator'])

<div class="flex items-center justify-between px-6 py-4 bg-white border-t border-slate-200 rounded-b-2xl">
    <div class="text-[13px] text-slate-500 font-medium">
        Menampilkan <span class="font-bold text-slate-800">{{ $paginator->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-slate-800">{{ $paginator->lastItem() ?? 0 }}</span> dari <span class="font-bold text-slate-800">{{ $paginator->total() }}</span> total
    </div>
    <div>
        {{ $paginator->links('pagination::tailwind') }}
    </div>
</div>



