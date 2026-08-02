<?php
$dirDoc = 'resources/views/document';
$dirTpl = 'resources/views/document/templates';
if(!is_dir($dirTpl)) mkdir($dirTpl, 0755, true);

// 1. Document Index
$docIndex = <<<'EOT'
@extends('layouts.app')
@section('header', 'Document Management')
@section('content')
<div class="space-y-6">
    <x-page-header title="Document Management" description="Manage all generated documents and slips." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Documents' => '#']">
        <x-slot:actions>
            <a href="{{ route('templates.index') }}" class="px-4 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl shadow-sm hover:bg-slate-50 mr-2">Manage Templates</a>
            <a href="{{ route('documents.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-sm hover:bg-blue-700">Generate Custom Document</a>
        </x-slot:actions>
    </x-page-header>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">Document Number</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">Reference</th>
                        <th class="px-6 py-4">Generated Date</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($documents as $doc)
                        <tr>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $doc['Document_Number'] ?? '' }}</td>
                            <td class="px-6 py-4">{{ $doc['Document_Type'] ?? 'Unknown' }}</td>
                            <td class="px-6 py-4">
                                @if(isset($doc['Reference_Module']))
                                    <span class="text-xs bg-slate-100 text-slate-500 px-2 py-1 rounded">{{ $doc['Reference_Module'] }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $doc['Generated_Date'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $status = $doc['Status'] ?? 'Draft';
                                    $bg = 'bg-slate-100 text-slate-700';
                                    if($status == 'Published' || $status == 'Approved') $bg = 'bg-emerald-100 text-emerald-700';
                                    elseif($status == 'Generated') $bg = 'bg-indigo-100 text-indigo-700';
                                    elseif($status == 'Waiting Approval') $bg = 'bg-amber-100 text-amber-700';
                                    elseif($status == 'Archived') $bg = 'bg-rose-100 text-rose-700';
                                @endphp
                                <span class="{{ $bg }} px-2 py-1 text-[11px] font-bold rounded-lg">{{ $status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('documents.show', $doc['Document_ID']) }}" class="text-blue-600 font-bold text-xs hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No documents found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirDoc/index.blade.php", $docIndex);

// 2. Document Show
$docShow = <<<'EOT'
@extends('layouts.app')
@section('header', 'Document Detail')
@section('content')
<div class="space-y-6">
    <x-page-header title="Document Detail" description="View and manage document." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Documents' => route('documents.index'), 'Detail' => '#']" />
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Metadata -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Document Information</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Document Number</span>
                        <span class="font-bold text-slate-800">{{ $document['Document_Number'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Type</span>
                        <span class="font-bold text-slate-800">{{ $document['Document_Type'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Reference</span>
                        <span class="font-bold text-slate-800">{{ $document['Reference_Module'] ?? '-' }} / {{ $document['Reference_ID'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500">Generated By</span>
                        <span class="font-bold text-slate-800">{{ $document['Generated_By'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between pb-2">
                        <span class="text-slate-500">Status</span>
                        <span class="font-bold text-blue-600">{{ $document['Status'] ?? 'Draft' }}</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Security</h3>
                <div class="space-y-3 text-sm">
                    <div class="bg-slate-50 p-3 rounded text-center">
                        <p class="text-xs font-bold text-slate-400">QR VERIFICATION</p>
                        <p class="font-mono text-slate-800 mt-1">{{ $document['QRCode'] ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded text-center">
                        <p class="text-xs font-bold text-slate-400">DIGITAL SIGNATURE</p>
                        <p class="font-mono text-slate-800 mt-1">{{ $document['Digital_Signature'] ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('documents.preview', $document['Document_ID']) }}" target="_blank" class="block w-full text-center py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700">Preview Engine (HTML)</a>
                    <button disabled class="w-full py-2.5 bg-slate-100 text-slate-400 font-bold rounded-xl text-sm cursor-not-allowed">Download PDF (Soon)</button>
                    
                    @if(($document['Status'] ?? '') !== 'Archived')
                    <form action="{{ route('documents.destroy', $document['Document_ID']) }}" method="POST" onsubmit="return confirm('Archive this document?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full py-2.5 mt-2 bg-rose-50 text-rose-600 font-bold rounded-xl text-sm hover:bg-rose-100">Archive Document</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Right: Small Preview Window -->
        <div class="lg:col-span-2">
            <div class="bg-slate-200 rounded-2xl border border-slate-300 h-full flex flex-col items-center justify-center p-8 text-center min-h-[500px]">
                <svg class="w-16 h-16 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h4 class="font-bold text-slate-600">Document Engine Ready</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm">This document is linked to the DMS Engine. Click 'Preview Engine' to render the actual HTML view.</p>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirDoc/show.blade.php", $docShow);

// 3. Document Preview
$docPreview = <<<'EOT'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview - {{ $document['Document_Number'] ?? 'Doc' }}</title>
    @vite('resources/css/app.css')
    <style>
        body { background-color: #525659; font-family: 'Inter', sans-serif; display: flex; justify-content: center; padding: 2rem; }
        .page { background: white; width: 210mm; min-height: 297mm; padding: 20mm; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
    </style>
</head>
<body>
    <div class="page">
        {!! $html ?? '<h1>Preview Output</h1>' !!}
        
        <div style="margin-top: 50px; border-top: 1px dashed #ccc; padding-top: 20px;">
            <p><strong>QR Code Placeholder:</strong> {{ $document['QRCode'] ?? '-' }}</p>
            <p><strong>Signature Placeholder:</strong> {{ $document['Digital_Signature'] ?? '-' }}</p>
        </div>
    </div>
</body>
</html>
EOT;
file_put_contents("$dirDoc/preview.blade.php", $docPreview);

// 4. Template Index
$tplIndex = <<<'EOT'
@extends('layouts.app')
@section('header', 'Template Manager')
@section('content')
<div class="space-y-6">
    <x-page-header title="Template Manager" description="Manage HTML templates for documents." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Documents' => route('documents.index'), 'Templates' => '#']">
        <x-slot:actions>
            <a href="{{ route('templates.create') }}" class="px-4 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-sm hover:bg-blue-700">Create Template</a>
        </x-slot:actions>
    </x-page-header>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($templates as $tpl)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col h-full">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded uppercase">{{ $tpl['Document_Type'] ?? 'General' }}</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">{{ $tpl['Status'] ?? 'Active' }}</span>
                </div>
                <h3 class="font-bold text-slate-800 text-lg">{{ $tpl['Template_Name'] ?? 'Untitled' }}</h3>
                <p class="text-sm text-slate-500 mt-1 mb-6 flex-grow">{{ $tpl['Description'] ?? 'No description.' }}</p>
                <div class="border-t border-slate-50 pt-4 flex justify-between items-center">
                    <span class="text-xs text-slate-400 font-mono">{{ $tpl['Template_Code'] ?? '-' }}</span>
                    <a href="{{ route('templates.edit', $tpl['Template_ID']) }}" class="text-sm text-blue-600 font-bold hover:underline">Edit</a>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 text-slate-400 bg-white rounded-2xl border border-slate-100 shadow-sm">No templates found.</div>
        @endforelse
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirTpl/index.blade.php", $tplIndex);

// Dummy create/edit for templates and documents to prevent route errors
file_put_contents("$dirDoc/create.blade.php", "@extends('layouts.app')\n@section('content')\n<p>Document Create Placeholder</p>\n@endsection");
file_put_contents("$dirDoc/edit.blade.php", "@extends('layouts.app')\n@section('content')\n<p>Document Edit Placeholder</p>\n@endsection");
file_put_contents("$dirTpl/create.blade.php", "@extends('layouts.app')\n@section('content')\n<p>Template Create Placeholder</p>\n@endsection");
file_put_contents("$dirTpl/edit.blade.php", "@extends('layouts.app')\n@section('content')\n<p>Template Edit Placeholder</p>\n@endsection");

echo "DMS Views created.\n";
?>
