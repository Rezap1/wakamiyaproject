@props(['title', 'description' => '', 'breadcrumbs' => [], 'status' => null, 'badgeColor' => 'slate'])

<div x-data="{ activeTab: 'information' }" class="flex flex-col gap-6">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            @if(count($breadcrumbs) > 0)
                <nav class="flex mb-2" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        @foreach($breadcrumbs as $label => $url)
                            <li class="inline-flex items-center">
                                @if(!$loop->first)
                                    <svg class="w-4 h-4 text-slate-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                @endif
                                <a href="{{ $url }}" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">{{ $title }}</h1>
                @if($status)
                    <x-badge color="{{ $badgeColor }}">{{ $status }}</x-badge>
                @endif
            </div>
            @if($description)
                <p class="text-slate-500 text-sm mt-1">{{ $description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if(isset($actions))
                {{ $actions }}
            @endif
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-slate-200">
        <ul class="flex flex-wrap -mb-px text-sm font-bold text-center text-slate-500 overflow-x-auto">
            <li class="mr-2">
                <button @click="activeTab = 'information'" :class="{'text-blue-600 border-blue-600': activeTab === 'information', 'border-transparent hover:text-slate-600 hover:border-slate-300': activeTab !== 'information'}" class="inline-flex p-4 border-b-2 rounded-t-lg group transition-colors whitespace-nowrap">
                    Informasi
                </button>
            </li>
            <li class="mr-2">
                <button @click="activeTab = 'activity'" :class="{'text-blue-600 border-blue-600': activeTab === 'activity', 'border-transparent hover:text-slate-600 hover:border-slate-300': activeTab !== 'activity'}" class="inline-flex p-4 border-b-2 rounded-t-lg group transition-colors whitespace-nowrap">
                    Aktivitas
                </button>
            </li>
            <li class="mr-2">
                <button @click="activeTab = 'documents'" :class="{'text-blue-600 border-blue-600': activeTab === 'documents', 'border-transparent hover:text-slate-600 hover:border-slate-300': activeTab !== 'documents'}" class="inline-flex p-4 border-b-2 rounded-t-lg group transition-colors whitespace-nowrap">
                    Dokumen
                </button>
            </li>
            <li class="mr-2">
                <button @click="activeTab = 'workflow'" :class="{'text-blue-600 border-blue-600': activeTab === 'workflow', 'border-transparent hover:text-slate-600 hover:border-slate-300': activeTab !== 'workflow'}" class="inline-flex p-4 border-b-2 rounded-t-lg group transition-colors whitespace-nowrap">
                    Alur Kerja
                </button>
            </li>
            <li class="mr-2">
                <button @click="activeTab = 'related'" :class="{'text-blue-600 border-blue-600': activeTab === 'related', 'border-transparent hover:text-slate-600 hover:border-slate-300': activeTab !== 'related'}" class="inline-flex p-4 border-b-2 rounded-t-lg group transition-colors whitespace-nowrap">
                    Data Terkait
                </button>
            </li>
            <li class="mr-2">
                <button @click="activeTab = 'audit'" :class="{'text-blue-600 border-blue-600': activeTab === 'audit', 'border-transparent hover:text-slate-600 hover:border-slate-300': activeTab !== 'audit'}" class="inline-flex p-4 border-b-2 rounded-t-lg group transition-colors whitespace-nowrap">
                    Log Audit
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Information Tab -->
        <div x-show="activeTab === 'information'" class="p-6 transition-all" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
            @if(isset($information))
                {{ $information }}
            @else
                <x-universal.empty-state title="Tidak ada informasi tambahan" description="" />
            @endif
        </div>

        <!-- Activity Tab -->
        <div x-show="activeTab === 'activity'" class="p-6 transition-all" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" style="display: none;">
            @if(isset($activity))
                {{ $activity }}
            @else
                <x-universal.empty-state title="Tidak ada aktivitas terbaru" description="" />
            @endif
        </div>

        <!-- Documents Tab -->
        <div x-show="activeTab === 'documents'" class="p-6 transition-all" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" style="display: none;">
            @if(isset($documents))
                {{ $documents }}
            @else
                <x-universal.empty-state title="Tidak ada dokumen terlampir" description="" />
            @endif
        </div>

        <!-- Workflow Tab -->
        <div x-show="activeTab === 'workflow'" class="p-6 transition-all" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" style="display: none;">
            @if(isset($workflow))
                {{ $workflow }}
            @else
                <x-universal.empty-state title="Tidak ada riwayat persetujuan" description="" />
            @endif
        </div>

        <!-- Related Data Tab -->
        <div x-show="activeTab === 'related'" class="p-6 transition-all" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" style="display: none;">
            @if(isset($related))
                {{ $related }}
            @else
                <x-universal.empty-state title="Tidak ada data terkait" description="" />
            @endif
        </div>

        <!-- Audit Log Tab -->
        <div x-show="activeTab === 'audit'" class="p-6 transition-all" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" style="display: none;">
            @if(isset($audit))
                {{ $audit }}
            @else
                <x-universal.empty-state title="Audit Log Kosong" description="Tidak ada rekaman perubahan sistem pada data ini." />
            @endif
        </div>

    </div>
</div>
