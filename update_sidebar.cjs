const fs = require('fs');

const filePath = 'd:\\\\orderan\\\\wakamiya\\\\resources\\\\views\\\\components\\\\dashboard\\\\sidebar.blade.php';
let content = fs.readFileSync(filePath, 'utf8');

// Replace sidebar container
content = content.replace(
    'class="w-72 bg-[#0F172A] text-slate-300 shadow-2xl flex-shrink-0 z-40 flex flex-col transition-transform duration-300 ease-in-out border-r border-slate-800 fixed inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0"',
    'class="w-72 bg-[#0B1120] text-slate-300 shadow-[4px_0_24px_rgba(0,0,0,0.05)] flex-shrink-0 z-40 flex flex-col transition-transform duration-300 ease-in-out fixed inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0 lg:rounded-r-[2rem]"'
);

// Replace logo area
const oldLogo = `    <div class="pt-8 pb-6 flex flex-col items-center justify-center border-b border-slate-800/50 bg-[#0F172A] relative">
        <img src="{{ asset('img/logo.png.jpeg') }}" alt="Logo LPK Wakamiya" class="w-16 h-16 object-contain bg-white rounded-full p-1 shadow-[0_0_15px_rgba(255,255,255,0.1)] mb-4" onerror="this.src='https://ui-avatars.com/api/?name=W&background=1e293b&color=fff&rounded=true&bold=true'">
        <span class="text-lg font-black text-white tracking-widest uppercase">WAKAMIYA</span>
        <span class="text-[9px] font-semibold text-slate-400 tracking-[0.2em] mt-1">MANAGEMENT SYSTEM</span>
        <span class="text-[8px] font-bold text-slate-500 mt-0.5">v 1.0</span>
    </div>`;

const newLogo = `    <div class="pt-10 pb-8 flex flex-col items-center justify-center border-b border-slate-800/50 relative">
        <div class="w-[88px] h-[88px] bg-[#0B1120] rounded-full flex items-center justify-center border-4 border-[#38bdf8] p-1 mb-4 shadow-lg shadow-cyan-900/30">
            <img src="{{ asset('img/logo.png.jpeg') }}" alt="Logo LPK Wakamiya" class="w-full h-full object-contain bg-white rounded-full p-1.5" onerror="this.src='https://ui-avatars.com/api/?name=W&background=1e293b&color=fff&rounded=true&bold=true'">
        </div>
        <span class="text-[22px] font-bold text-white tracking-wide uppercase mt-1">WAKAMIYA</span>
        <span class="text-[10px] font-semibold text-cyan-400 tracking-[0.15em] mt-0.5">MANAGEMENT SYSTEM</span>
        <span class="text-[10px] text-slate-500 mt-2 tracking-widest">v 1.0</span>
    </div>`;

content = content.replace(oldLogo, newLogo);

// Replace active menu classes
const pattern1 = /class="flex items-center px-4 py-3 text-sm font-medium rounded-xl \{\{ request\(\)->routeIs\((.*?)\) \? 'bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg shadow-blue-900\/50' : 'text-slate-400 hover:bg-white\/5 hover:text-white' \}\} transition-all( relative)?"/g;
const replacement1 = 'class="flex items-center pl-8 pr-4 py-3.5 text-[14px] font-medium mr-4 rounded-r-2xl {{ request()->routeIs($1) ? \'bg-gradient-to-r from-[#1d4ed8] to-[#3b82f6] text-white shadow-md shadow-blue-900/40\' : \'text-slate-400 hover:bg-white/5 hover:text-white\' }} transition-all$2"';
content = content.replace(pattern1, replacement1);

const pattern2 = /class="flex items-center px-4 py-3 text-sm font-medium rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all( relative)?"/g;
const replacement2 = 'class="flex items-center pl-8 pr-4 py-3.5 text-[14px] font-medium mr-4 rounded-r-2xl text-slate-400 hover:bg-white/5 hover:text-white transition-all$1"';
content = content.replace(pattern2, replacement2);

// Profile area at bottom
const oldProfile = `    <div class="p-4 bg-[#0F172A] border-t border-slate-800/50 mt-auto">
        <div class="flex items-center gap-3 mb-4 px-2">
            <div class="w-10 h-10 rounded-full bg-white relative shrink-0">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->Username ?? 'U') }}&background=1e293b&color=fff" alt="User" class="w-full h-full rounded-full shadow">
                <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-[#0F172A] rounded-full" title="Online"></div>
            </div>
            <div class="overflow-hidden">
                <p class="text-sm font-bold text-white truncate">{{ auth()->user()->Username ?? 'Unknown' }}</p>
                <p class="text-xs text-slate-400 truncate">{{ ucfirst(strtolower($userRole)) }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-start px-4 py-2.5 text-sm font-semibold text-slate-300 hover:text-white transition-colors rounded-xl hover:bg-slate-800">
                <svg class="w-5 h-5 mr-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Logout
            </button>
        </form>
    </div>`;

const newProfile = `    <div class="p-5 bg-[#0B1120] border-t border-slate-800/50 mt-auto">
        <div class="flex items-center gap-3 mb-4 pl-3">
            <div class="w-11 h-11 rounded-full bg-slate-800 relative shrink-0 border-2 border-slate-700">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->Username ?? 'U') }}&background=1e293b&color=fff" alt="User" class="w-full h-full rounded-full shadow-inner">
                <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-[#0B1120] rounded-full" title="Online"></div>
            </div>
            <div class="overflow-hidden flex-1">
                <p class="text-[14px] font-bold text-white truncate">{{ auth()->user()->Username ?? 'Unknown' }}</p>
                <p class="text-[12px] font-medium text-slate-400 truncate flex items-center gap-1 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Online
                </p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-start px-5 py-3 text-[14px] font-semibold text-slate-400 hover:text-white hover:bg-white/5 transition-colors rounded-xl group">
                <svg class="w-5 h-5 mr-3 text-slate-500 group-hover:text-red-400 transition-colors" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"></path></svg>
                Logout
            </button>
        </form>
    </div>`;

content = content.replace(oldProfile, newProfile);

// Replace uppercase headers
const pattern3 = /<div class="px-2 mb-2 mt-4 text-xs font-bold text-slate-500 uppercase tracking-wider">(.*?)<\/div>/g;
const replacement3 = '<div class="pl-8 pr-4 mb-2 mt-6 text-[11px] font-bold text-slate-500 uppercase tracking-widest">$1</div>';
content = content.replace(pattern3, replacement3);

const pattern4 = /<div class="px-2 mt-4 mb-2 text-xs font-bold text-slate-500 uppercase tracking-wider">(.*?)<\/div>/g;
const replacement4 = '<div class="pl-8 pr-4 mt-6 mb-2 text-[11px] font-bold text-slate-500 uppercase tracking-widest">$1</div>';
content = content.replace(pattern4, replacement4);

const pattern5 = /<div class="px-2 mb-2 mt-8 text-xs font-bold text-slate-500 uppercase tracking-wider border-t border-slate-800 pt-4">(.*?)<\/div>/g;
const replacement5 = '<div class="pl-8 pr-4 mb-2 mt-8 text-[11px] font-bold text-slate-500 uppercase tracking-widest border-t border-slate-800/50 pt-6">$1</div>';
content = content.replace(pattern5, replacement5);

fs.writeFileSync(filePath, content, 'utf8');
console.log('Sidebar updated successfully');
