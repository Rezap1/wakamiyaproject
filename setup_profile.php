<?php
$controllerCode = <<<EOT
<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\RoleService;
use App\Services\Core\DepartmentService;
use App\Services\Core\PositionService;

class ProfileController extends Controller
{
    public function index()
    {
        \$user = auth()->user();
        \$roleName = 'Unknown';
        
        try {
            \$roleService = app(RoleService::class);
            if (isset(\$user->Role_ID)) {
                \$roleData = \$roleService->getRoleById(\$user->Role_ID);
                \$roleName = \$roleData['Role_Name'] ?? 'Unknown';
            }
        } catch (\Exception \$e) {}
        
        // Mock recent activities (in a real app, query Activity Log)
        \$recentActivities = [
            [
                'Title' => 'Logged In',
                'Message' => 'User logged into the system successfully from 192.168.1.1',
                'Created_At' => now()->subHours(2)->toDateTimeString()
            ],
            [
                'Title' => 'Viewed Dashboard',
                'Message' => 'User navigated to the Enterprise Dashboard',
                'Created_At' => now()->subHours(1)->toDateTimeString()
            ],
            [
                'Title' => 'Updated Profile Settings',
                'Message' => 'User successfully updated their account preferences',
                'Created_At' => now()->subMinutes(15)->toDateTimeString()
            ]
        ];

        return view('profile.index', compact('user', 'roleName', 'recentActivities'));
    }
}
EOT;
file_put_contents('app/Http/Controllers/Core/ProfileController.php', $controllerCode);
echo "ProfileController created.\n";

if (!is_dir('resources/views/profile')) {
    mkdir('resources/views/profile', 0777, true);
}

$viewCode = <<<'EOT'
@extends('layouts.app')

@section('header', 'My Profile')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="My Profile" 
        description="Manage your account information, security settings, and view recent activities."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Profile' => '#']"
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Profile Card & Account Info -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- My Profile Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="h-24 bg-gradient-to-r from-blue-600 to-indigo-600"></div>
                <div class="px-6 pb-6 relative">
                    <div class="absolute -top-12 left-6">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($user->Username ?? 'U') }}&size=96&background=022c43&color=fff&rounded=true" alt="Avatar" class="w-24 h-24 rounded-full border-4 border-white shadow-md">
                    </div>
                    <div class="pt-14">
                        <h2 class="text-xl font-extrabold text-slate-900">{{ $user->Username ?? 'User' }}</h2>
                        <p class="text-sm font-medium text-slate-500 mb-4">{{ $roleName }}</p>
                        
                        <div class="space-y-3">
                            <div class="flex items-center text-sm text-slate-600">
                                <svg class="w-5 h-5 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                                <span class="font-semibold">{{ $user->Employee_ID ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center text-sm text-slate-600">
                                <svg class="w-5 h-5 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                {{ $user->Email ?? 'No email provided' }}
                            </div>
                            <div class="flex items-center text-sm text-slate-600">
                                <svg class="w-5 h-5 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                {{ $user->Phone ?? 'No phone provided' }}
                            </div>
                            <div class="flex items-center text-sm text-slate-600">
                                <svg class="w-5 h-5 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold uppercase">{{ $user->Status ?? 'ACTIVE' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="font-bold text-slate-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Account Information
                    </h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">User ID</dt>
                            <dd class="text-sm font-medium text-slate-900">{{ $user->User_ID ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Created Date</dt>
                            <dd class="text-sm font-medium text-slate-900">{{ isset($user->Created_At) ? \Carbon\Carbon::parse($user->Created_At)->format('d F Y') : 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Last Updated</dt>
                            <dd class="text-sm font-medium text-slate-900">{{ isset($user->Updated_At) ? \Carbon\Carbon::parse($user->Updated_At)->format('d F Y, H:i') : 'Unknown' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            
        </div>

        <!-- Right Column: Security & Timeline -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Security -->
            <div id="security" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Security Settings
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Change Password -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 mb-4">Change Password</h4>
                        <form action="#" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Current Password</label>
                                <input type="password" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" placeholder="••••••••" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">New Password</label>
                                <input type="password" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" placeholder="••••••••" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Confirm New Password</label>
                                <input type="password" class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" placeholder="••••••••" required>
                            </div>
                            <button type="button" class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-bold rounded-xl text-sm px-5 py-2.5 text-center transition-all shadow-md shadow-blue-200" onclick="alert('Demo: Password update triggered')">Update Password</button>
                        </form>
                    </div>
                    
                    <!-- Login History -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center justify-between">
                            Login History
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full uppercase tracking-wider">Last 3</span>
                        </h4>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0 w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center border border-blue-100">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">MacBook Pro (Chrome)</p>
                                    <p class="text-[11px] text-slate-500 font-medium">114.120.30.1 • Jakarta, ID</p>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5">Active now</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0 w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">iPhone 13 (Safari)</p>
                                    <p class="text-[11px] text-slate-500 font-medium">114.120.30.2 • Bandung, ID</p>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5">Yesterday, 14:20</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0 w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">Windows PC (Edge)</p>
                                    <p class="text-[11px] text-slate-500 font-medium">103.11.200.5 • Surabaya, ID</p>
                                    <p class="text-[10px] text-slate-400 font-semibold mt-0.5">May 15, 08:00</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Recent Activity
                    </h3>
                    <a href="{{ route('activity.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline">View All</a>
                </div>
                <div class="p-6">
                    @if(count($recentActivities) > 0)
                    <div class="relative border-l border-slate-200 ml-3">
                        @foreach($recentActivities as $idx => $activity)
                        <div class="mb-8 ml-6 relative">
                            <!-- Indicator -->
                            <span class="absolute -left-[35px] flex items-center justify-center w-6 h-6 bg-blue-100 rounded-full ring-4 ring-white">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </span>
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 mb-1">
                                <h3 class="flex items-center text-sm font-bold text-slate-900">{{ $activity['Title'] }}</h3>
                                <time class="text-xs font-medium text-slate-400">{{ \Carbon\Carbon::parse($activity['Created_At'])->diffForHumans() }}</time>
                            </div>
                            <p class="text-sm font-medium text-slate-500">{{ $activity['Message'] }}</p>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-10">
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200">
                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">No recent activity</h3>
                        <p class="text-xs font-medium text-slate-500 mt-1">Activity log is empty.</p>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
EOT;
file_put_contents('resources/views/profile/index.blade.php', $viewCode);
echo "profile/index.blade.php created.\n";

$routes = file_get_contents('routes/web.php');
if (strpos($routes, "Route::get('/profile'") === false) {
    // Add ProfileController route in auth group
    $routes = str_replace(
        "Route::middleware('auth')->group(function () {\n    \n    // Dynamic Dashboard Router",
        "Route::middleware('auth')->group(function () {\n    \n    // Profile Page\n    Route::get('/profile', [\\App\\Http\\Controllers\\Core\\ProfileController::class, 'index'])->name('profile.index');\n\n    // Dynamic Dashboard Router",
        $routes
    );
    file_put_contents('routes/web.php', $routes);
    echo "routes/web.php updated.\n";
}
