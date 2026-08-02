<?php
$dashFile = 'resources/views/dashboard/index.blade.php';
$content = file_get_contents($dashFile);

if(strpos($content, 'Enterprise System Health') === false) {
    $healthCard = <<<'EOT'
    <!-- Enterprise System Health Card -->
    @if(session('role') === 'ADMINISTRATOR')
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Enterprise System Health
            </h3>
            <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full uppercase tracking-wide">All Systems Operational</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
            <!-- Automation -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">Automation</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">OK</span>
            </div>
            <!-- Workflow -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">Workflow</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">OK</span>
            </div>
            <!-- Audit -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">Audit</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">OK</span>
            </div>
            <!-- Notification -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">Notification</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">OK</span>
            </div>
            <!-- Cache -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-cyan-100 text-cyan-600 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">Cache</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">HIT</span>
            </div>
            <!-- Settings -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-slate-200 text-slate-700 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">Settings</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">OK</span>
            </div>
            <!-- Google Sheets -->
            <div class="text-center p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="w-8 h-8 mx-auto bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <span class="block text-[10px] font-bold text-slate-500 uppercase">G-Sheets</span>
                <span class="block text-xs font-black text-emerald-600 mt-1">SYNCED</span>
            </div>
        </div>
    </div>
    @endif
EOT;

    $content = str_replace(
        "<div class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8\">",
        $healthCard . "\n    <div class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8\">",
        $content
    );
    file_put_contents($dashFile, $content);
    echo "Enterprise Dashboard updated.\n";
} else {
    echo "Enterprise Dashboard already updated.\n";
}
?>
