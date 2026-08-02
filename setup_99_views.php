<?php
$dirSet = 'resources/views/system/settings';
if(!is_dir($dirSet)) mkdir($dirSet, 0755, true);

$index = <<<'EOT'
@extends('layouts.app')
@section('header', 'System Settings')
@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <x-page-header title="System Settings" description="Master configuration center for Wakamiya Management System." :breadcrumbs="['Dashboard' => route('dashboard.admin'), 'Settings' => '#']" />

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Sidebar Navigation -->
        <div class="w-full md:w-64 shrink-0">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden sticky top-6">
                <div class="p-4 bg-slate-50 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm tracking-wide uppercase">Categories</h3>
                </div>
                <div class="flex flex-col">
                    @foreach($categories as $cat)
                        <a href="{{ route('settings.index', ['tab' => $cat]) }}" 
                           class="px-5 py-3 text-sm font-semibold border-l-4 transition-all {{ $activeTab == $cat ? 'bg-blue-50 border-blue-600 text-blue-700' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                            {{ $cat }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="flex-grow">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h2 class="text-xl font-bold text-slate-800">{{ $activeTab }} Configuration</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage all parameters and settings related to {{ strtolower($activeTab) }}.</p>
                </div>
                
                <form action="{{ route('settings.update') }}" method="POST" class="p-6">
                    @csrf
                    <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                    
                    @if($settings->count() > 0)
                    <div class="space-y-6 mb-8">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Global Settings</h3>
                        @foreach($settings as $s)
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 py-2 border-b border-slate-50 last:border-0">
                                <div class="w-full md:w-1/2">
                                    <label class="font-bold text-slate-700 text-sm block">{{ $s['Setting_Name'] }}</label>
                                    <span class="text-xs text-slate-400">{{ $s['Description'] ?? '' }}</span>
                                </div>
                                <div class="w-full md:w-1/2">
                                    @if($s['Value_Type'] == 'boolean')
                                        <select name="settings[{{ $s['Setting_ID'] }}]" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50">
                                            <option value="true" {{ $s['Setting_Value'] == 'true' ? 'selected' : '' }}>Enabled</option>
                                            <option value="false" {{ $s['Setting_Value'] == 'false' ? 'selected' : '' }}>Disabled</option>
                                        </select>
                                    @elseif($s['Value_Type'] == 'textarea')
                                        <textarea name="settings[{{ $s['Setting_ID'] }}]" rows="3" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50">{{ $s['Setting_Value'] }}</textarea>
                                    @else
                                        <input type="{{ $s['Value_Type'] == 'number' ? 'number' : 'text' }}" name="settings[{{ $s['Setting_ID'] }}]" value="{{ $s['Setting_Value'] }}" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50">
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($parameters->count() > 0)
                    <div class="space-y-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Module Parameters</h3>
                        @foreach($parameters as $p)
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 py-2 border-b border-slate-50 last:border-0">
                                <div class="w-full md:w-1/2">
                                    <label class="font-bold text-slate-700 text-sm block">{{ str_replace('_', ' ', $p['Parameter_Key']) }}</label>
                                    <span class="text-xs text-slate-400">{{ $p['Description'] ?? '' }}</span>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <input type="text" name="parameters[{{ $p['Parameter_ID'] }}]" value="{{ $p['Parameter_Value'] }}" class="w-full rounded-lg border-slate-200 text-sm font-mono text-blue-600 focus:ring-blue-500 focus:border-blue-500 bg-blue-50/50">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($settings->count() == 0 && $parameters->count() == 0)
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 text-slate-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <h3 class="text-slate-400 font-bold">No Configurations Found</h3>
                        <p class="text-sm text-slate-400 mt-1">There are no settings available for this category yet.</p>
                    </div>
                    @else
                    <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl shadow-sm hover:bg-blue-700 transition-colors">Save Changes</button>
                    </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents("$dirSet/index.blade.php", $index);

echo "Settings View created.\n";
?>
