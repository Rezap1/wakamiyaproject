@props(['action', 'url' => '#', 'method' => 'GET', 'confirm' => false, 'confirmMessage' => 'Apakah Anda yakin?'])

@php
    $icon = '';
    $colorClass = '';
    $label = '';
    $isForm = $method !== 'GET';

    switch (strtolower($action)) {
        case 'detail':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            $colorClass = 'text-slate-600 bg-slate-100 hover:bg-slate-200 border-transparent';
            $label = 'Detail';
            break;
        case 'edit':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />';
            $colorClass = 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100 border-transparent';
            $label = 'Edit';
            break;
        case 'approve':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />';
            $colorClass = 'text-green-700 bg-green-50 hover:bg-green-100 border-transparent';
            $label = 'Setujui';
            $isForm = true;
            $method = 'POST';
            break;
        case 'reject':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
            $colorClass = 'text-red-600 bg-red-50 hover:bg-red-100 border-transparent';
            $label = 'Tolak';
            $isForm = true;
            $method = 'POST';
            break;
        case 'delete':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />';
            $colorClass = 'text-red-600 bg-red-50 hover:bg-red-100 border-transparent';
            $label = 'Hapus';
            $isForm = true;
            $method = 'DELETE';
            $confirm = true;
            $confirmMessage = 'Apakah Anda yakin ingin menghapus data ini?';
            break;
        case 'history':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
            $colorClass = 'text-slate-600 bg-slate-100 hover:bg-slate-200 border-transparent';
            $label = 'Riwayat';
            break;
        case 'print':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />';
            $colorClass = 'text-slate-600 bg-slate-100 hover:bg-slate-200 border-transparent';
            $label = 'Cetak';
            break;
        case 'download':
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
            $colorClass = 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100 border-transparent';
            $label = 'Unduh';
            break;
        default:
            $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
            $colorClass = 'text-slate-600 bg-slate-100 hover:bg-slate-200 border-transparent';
            $label = ucfirst($action);
            break;
    }
@endphp

@if($isForm)
    <form action="{{ $url }}" method="POST" class="inline-block" @if($confirm) onsubmit="return confirm('{{ $confirmMessage }}');" @endif>
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif
        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-lg transition-colors border {{ $colorClass }}" title="{{ $label }}">
            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">{!! $icon !!}</svg>
            {{ $label }}
        </button>
    </form>
@else
    <a href="{{ $url }}" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-lg transition-colors border {{ $colorClass }}" @if($confirm) onclick="return confirm('{{ $confirmMessage }}');" @endif title="{{ $label }}">
        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">{!! $icon !!}</svg>
        {{ $label }}
    </a>
@endif



