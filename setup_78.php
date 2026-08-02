<?php
$baseDir = __DIR__;

// --- 1. GlobalSearchService ---
$searchService = <<<PHP
<?php
namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;

class GlobalSearchService
{
    // Inject repositories as needed, for now we will simulate them or inject later
    // using app() helper to avoid massive constructor dependencies

    public function search(\$keyword, \$role, \$userId)
    {
        if (empty(trim(\$keyword))) return [];
        
        \$keyword = strtolower(trim(\$keyword));
        \$cacheKey = "wms_search_{\$role}_{\$userId}_" . md5(\$keyword);
        
        return Cache::remember(\$cacheKey, 60, function () use (\$keyword, \$role, \$userId) {
            \$results = [
                'Students' => [],
                'Teachers' => [],
                'Subjects' => [],
                'Announcements' => [],
            ];
            
            // Progressive Search Logic based on Role
            
            // 1. Search Announcements (All roles can see announcements, usually)
            try {
                \$announcementRepo = app(\App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface::class);
                \$announcements = collect(\$announcementRepo->fetchAll())->filter(function(\$a) use (\$keyword) {
                    return str_contains(strtolower(\$a['Title'] ?? ''), \$keyword) || str_contains(strtolower(\$a['Content'] ?? ''), \$keyword);
                })->take(5);
                foreach (\$announcements as \$a) {
                    \$results['Announcements'][] = [
                        'title' => \$a['Title'] ?? 'Announcement',
                        'desc' => substr(\$a['Content'] ?? '', 0, 50) . '...',
                        'url' => route('announcements.index')
                    ];
                }
            } catch (\Exception \$e) {
                // Ignore if not setup
            }

            // 2. Search Students (Admin, HR, Academic, Teacher)
            if (in_array(\$role, ['ADMINISTRATOR', 'ACADEMIC', 'TEACHER'])) {
                try {
                    \$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
                    \$students = collect(\$studentRepo->fetchAll())->filter(function(\$s) use (\$keyword) {
                        return str_contains(strtolower(\$s['Name'] ?? ''), \$keyword) || str_contains(strtolower(\$s['Student_ID'] ?? ''), \$keyword);
                    })->take(5);
                    foreach (\$students as \$s) {
                        \$results['Students'][] = [
                            'title' => \$s['Name'] ?? 'Unknown',
                            'desc' => \$s['Student_ID'] ?? '',
                            'url' => route('students.index')
                        ];
                    }
                } catch (\Exception \$e) {}
            }

            // 3. Search Teachers (Admin, HR, Academic)
            if (in_array(\$role, ['ADMINISTRATOR', 'ACADEMIC', 'HR'])) {
                try {
                    \$teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
                    \$teachers = collect(\$teacherRepo->fetchAll())->filter(function(\$t) use (\$keyword) {
                        return str_contains(strtolower(\$t['Name'] ?? ''), \$keyword) || str_contains(strtolower(\$t['Teacher_ID'] ?? ''), \$keyword);
                    })->take(5);
                    foreach (\$teachers as \$t) {
                        \$results['Teachers'][] = [
                            'title' => \$t['Name'] ?? 'Unknown',
                            'desc' => \$t['Teacher_ID'] ?? '',
                            'url' => route('teachers.index')
                        ];
                    }
                } catch (\Exception \$e) {}
            }

            // Filter out empty categories
            return array_filter(\$results, function(\$group) { return count(\$group) > 0; });
        });
    }

    public function getHistory(\$userId)
    {
        return Cache::get("wms_search_history_{\$userId}", []);
    }

    public function saveHistory(\$userId, \$keyword)
    {
        if (empty(trim(\$keyword))) return;
        
        \$history = \$this->getHistory(\$userId);
        
        // Remove if exists to put it at the top
        \$history = array_filter(\$history, function(\$k) use (\$keyword) { return strtolower(\$k) !== strtolower(\$keyword); });
        
        array_unshift(\$history, \$keyword);
        \$history = array_slice(\$history, 0, 10);
        
        Cache::put("wms_search_history_{\$userId}", \$history, 86400 * 30); // Save for 30 days
    }

    public function clearHistory(\$userId)
    {
        Cache::forget("wms_search_history_{\$userId}");
    }
}
PHP;
@mkdir("$baseDir/app/Services/Core", 0777, true);
file_put_contents("$baseDir/app/Services/Core/GlobalSearchService.php", $searchService);


// --- 2. GlobalSearchController ---
$searchCtrl = <<<PHP
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\GlobalSearchService;
use App\Services\Core\RoleService;

class GlobalSearchController extends Controller
{
    protected \$searchService;
    protected \$roleService;

    public function __construct(GlobalSearchService \$searchService, RoleService \$roleService)
    {
        \$this->searchService = \$searchService;
        \$this->roleService = \$roleService;
    }

    public function index(Request \$request)
    {
        \$keyword = \$request->get('q', '');
        \$user = auth()->user();
        \$roleData = \$this->roleService->getRoleById(\$user->Role_ID);
        \$roleName = strtoupper(trim(\$roleData['Role_Name'] ?? ''));
        
        \$results = [];
        if (\$keyword) {
            \$results = \$this->searchService->search(\$keyword, \$roleName, \$user->Employee_ID ?? \$user->User_ID);
            \$this->searchService->saveHistory(\$user->Employee_ID ?? \$user->User_ID, \$keyword);
        }

        return view('search.index', compact('keyword', 'results'));
    }

    public function overlay(Request \$request)
    {
        \$keyword = \$request->get('q', '');
        \$user = auth()->user();
        \$userId = \$user->Employee_ID ?? \$user->User_ID;
        \$roleData = \$this->roleService->getRoleById(\$user->Role_ID);
        \$roleName = strtoupper(trim(\$roleData['Role_Name'] ?? ''));

        if (\$keyword) {
            \$results = \$this->searchService->search(\$keyword, \$roleName, \$userId);
            \$this->searchService->saveHistory(\$userId, \$keyword);
            return response()->json(['status' => 'success', 'data' => \$results]);
        } else {
            // Return history
            \$history = \$this->searchService->getHistory(\$userId);
            return response()->json(['status' => 'history', 'data' => \$history]);
        }
    }

    public function clearHistory()
    {
        \$user = auth()->user();
        \$this->searchService->clearHistory(\$user->Employee_ID ?? \$user->User_ID);
        return back()->with('success', 'Search history cleared.');
    }
}
PHP;
@mkdir("$baseDir/app/Http/Controllers/Core", 0777, true);
file_put_contents("$baseDir/app/Http/Controllers/Core/GlobalSearchController.php", $searchCtrl);

// --- 3. Views ---
@mkdir("$baseDir/resources/views/search", 0777, true);

$searchIndexView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Enterprise Search')
@section('content')
<div class="mb-6 bg-white rounded-2xl shadow p-6">
    <form action="{{ route('search.index') }}" method="GET" class="flex gap-4">
        <div class="flex-1 relative">
            <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" name="q" value="{{ $keyword }}" placeholder="Search anything in WMS..." class="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-blue-500 font-medium text-lg">
        </div>
        <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition">Search</button>
    </form>
</div>

@if($keyword)
    <h3 class="font-bold text-gray-500 mb-4 uppercase tracking-wider text-sm">Search Results for "<span class="text-slate-800">{{ $keyword }}</span>"</h3>
    
    @if(empty($results))
    <div class="bg-white rounded-2xl shadow p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h3 class="text-xl font-bold text-gray-700">No Results Found</h3>
        <p class="text-gray-500 mt-2">Try adjusting your keyword or check your access permissions.</p>
    </div>
    @else
        <div class="space-y-6">
        @foreach($results as $group => $items)
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="bg-slate-50 px-6 py-3 border-b border-gray-100">
                    <h4 class="font-bold text-slate-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        {{ $group }} <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded ml-2">{{ count($items) }}</span>
                    </h4>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($items as $item)
                    <a href="{{ $item['url'] }}" class="block px-6 py-4 hover:bg-slate-50 transition">
                        <p class="font-bold text-blue-700 text-lg mb-1">{{ $item['title'] }}</p>
                        <p class="text-sm text-gray-500">{{ $item['desc'] }}</p>
                    </a>
                    @endforeach
                </div>
            </div>
        @endforeach
        </div>
    @endif

@else
    <div class="bg-white rounded-2xl shadow p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        <h3 class="text-xl font-bold text-gray-700">Enterprise Search</h3>
        <p class="text-gray-500 mt-2">Type a keyword above to scan across permitted modules.</p>
        
        <form action="{{ route('search.clearHistory') }}" method="POST" class="mt-6">
            @csrf
            <button type="submit" class="text-sm font-bold text-red-500 hover:underline">Clear Search History</button>
        </form>
    </div>
@endif
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/search/index.blade.php", $searchIndexView);

// --- 4. Global Filter Component ---
$globalFilterView = <<<'BLADE'
@props(['filters' => []])
<div class="bg-white p-4 rounded-xl shadow mb-6 border border-slate-100" x-data="{ expanded: false }">
    <div class="flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
        <h3 class="font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Global Filter
        </h3>
        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </div>

    <form method="GET" x-show="expanded" class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-4" style="display: none;">
        @if(in_array('keyword', $filters))
        <div class="md:col-span-4">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Keyword</label>
            <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Search..." class="w-full border-gray-300 rounded-lg text-sm">
        </div>
        @endif

        @if(in_array('program', $filters))
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Program</label>
            <select name="program" class="w-full border-gray-300 rounded-lg text-sm">
                <option value="">All Programs</option>
            </select>
        </div>
        @endif
        
        @if(in_array('batch', $filters))
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Batch</label>
            <select name="batch" class="w-full border-gray-300 rounded-lg text-sm">
                <option value="">All Batches</option>
            </select>
        </div>
        @endif

        @if(in_array('class', $filters))
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Class</label>
            <select name="class" class="w-full border-gray-300 rounded-lg text-sm">
                <option value="">All Classes</option>
            </select>
        </div>
        @endif

        <div class="md:col-span-4 flex items-center justify-end gap-3 pt-2">
            <a href="{{ url()->current() }}" class="text-sm font-bold text-slate-500 hover:text-slate-800">Reset</a>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-bold shadow-sm hover:bg-slate-900 transition">Apply Filter</button>
        </div>
    </form>
</div>
BLADE;
file_put_contents("$baseDir/resources/views/components/dashboard/global-filter.blade.php", $globalFilterView);

echo "Global Search Service, Controller, and views created.\\n";
