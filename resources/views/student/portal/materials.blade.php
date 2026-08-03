@extends('layouts.app')

@section('header', 'Materi Pelajaran')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Materi Pembelajaran" 
        description="Dokumen dan referensi belajar untuk kelas Anda."
        :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Portal' => '#', 'Materi' => '#']"
    />

    <!-- Mata Pelajaran -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Daftar Mata Pelajaran
            </h3>
        </div>
        <div class="p-4">
            @if(isset($subjects) && $subjects->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($subjects as $subject)
                    <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition-shadow bg-gradient-to-br from-white to-slate-50">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm shrink-0">
                                {{ strtoupper(substr($subject['Subject_Code'] ?? $subject['Subject_ID'] ?? 'S', 0, 2)) }}
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm">{{ $subject['Subject_Name'] ?? 'Untitled' }}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $subject['Subject_Code'] ?? $subject['Subject_ID'] ?? '-' }}</p>
                                @if(!empty($subject['Credit']))
                                <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold bg-purple-100 text-purple-700 rounded-full">{{ $subject['Credit'] }} SKS</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-center text-slate-500 py-6">Belum ada mata pelajaran yang terdaftar.</p>
            @endif
        </div>
    </div>

    <!-- Pengumuman / Materi -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                Pengumuman & Materi Terbaru
            </h3>
        </div>
        <div class="p-4">
            @if(isset($announcements) && $announcements->count() > 0)
                <div class="space-y-4">
                    @foreach($announcements as $ann)
                    <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-bold text-slate-800">{{ $ann['Title'] ?? 'Tanpa Judul' }}</h4>
                                <p class="text-sm text-slate-600 mt-1">{{ $ann['Content'] ?? $ann['Description'] ?? '' }}</p>
                                <div class="flex items-center gap-3 mt-2">
                                    @if(!empty($ann['Publish_Date']))
                                    <span class="text-xs text-slate-400">📅 {{ $ann['Publish_Date'] }}</span>
                                    @endif
                                    @if(!empty($ann['Category']))
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded-full">{{ $ann['Category'] }}</span>
                                    @endif
                                </div>
                            </div>
                            @if(!empty($ann['Attachment_URL']))
                            <a href="{{ $ann['Attachment_URL'] }}" target="_blank" class="shrink-0 ml-3 px-3 py-1.5 text-xs font-bold bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 transition-colors">
                                📎 Download
                            </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-slate-100 text-slate-400 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <p class="text-slate-500 text-sm">Belum ada pengumuman atau materi yang dipublikasikan untuk kelas Anda.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
