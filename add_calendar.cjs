const fs = require('fs');
const filePath = 'd:\\\\orderan\\\\wakamiya\\\\resources\\\\views\\\\dashboard\\\\index.blade.php';
let content = fs.readFileSync(filePath, 'utf8');

// Change grid columns for bottom section
content = content.replace(
    '<!-- Bottom Section: Notifications & Recent Activity -->\n    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">',
    '<!-- Bottom Section: Notifications, Recent Activity & Calendar -->\n    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">'
);

// Update Recent Activity col-span
content = content.replace(
    '<!-- Recent Activity -->\n        <div class="lg:col-span-2">',
    '<!-- Recent Activity -->\n        <div class="lg:col-span-1 xl:col-span-2">'
);

// Define Calendar Widget
const calendarWidget = `
        <!-- Calendar Widget -->
        <div class="bg-white rounded-2xl p-6 lg:p-8 shadow-[0_2px_12px_-4px_rgba(6,81,237,0.06)] border border-slate-100 lg:col-span-1 flex flex-col h-full hover:shadow-lg transition-shadow duration-300">
            <div class="flex justify-between items-center mb-6">
                <h4 class="text-lg font-bold text-slate-800 flex items-center">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center mr-4 text-purple-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    Calendar
                </h4>
            </div>
            
            <div class="flex items-center justify-between mb-4">
                <button class="p-1 hover:bg-slate-50 text-slate-400 rounded transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg></button>
                <span class="text-[13px] font-extrabold text-slate-700 tracking-wide">{{ \\Carbon\\Carbon::now()->translatedFormat('F Y') }}</span>
                <button class="p-1 hover:bg-slate-50 text-slate-400 rounded transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg></button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center mb-2">
                @foreach(['Mo','Tu','We','Th','Fr','Sa','Su'] as $day)
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest py-2">{{ $day }}</div>
                @endforeach
            </div>
            
            @php
                $firstDay = \\Carbon\\Carbon::now()->startOfMonth()->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
                $daysInMonth = \\Carbon\\Carbon::now()->daysInMonth;
                $today = date('j');
            @endphp
            
            <div class="grid grid-cols-7 gap-1 text-center">
                @for($i = 1; $i < $firstDay; $i++)
                    <div></div>
                @endfor
                
                @for($i = 1; $i <= $daysInMonth; $i++)
                    @php
                        $isToday = $i == $today;
                        $hasEvent = in_array($i, [12, 18, 25, $today+1]);
                    @endphp
                    <div class="relative py-2 text-[12px] font-bold rounded-lg cursor-pointer transition-all {{ $isToday ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-slate-600 hover:bg-slate-50' }}">
                        {{ $i }}
                        @if($hasEvent && !$isToday)
                            <div class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-amber-500 rounded-full"></div>
                        @endif
                    </div>
                @endfor
            </div>
            
            <div class="mt-6 border-t border-slate-100 pt-6 space-y-4 flex-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Upcoming Schedules</p>
                <div class="flex items-start gap-3 group cursor-pointer">
                    <div class="w-2.5 h-2.5 mt-1 rounded-full bg-blue-500 ring-4 ring-blue-50 group-hover:scale-110 transition-transform"></div>
                    <div>
                        <p class="text-[12px] font-bold text-slate-800 group-hover:text-blue-600 transition-colors">Evaluasi Interview Peserta</p>
                        <p class="text-[11px] text-slate-500 font-medium">Hari ini, 14:00 WIB</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 group cursor-pointer">
                    <div class="w-2.5 h-2.5 mt-1 rounded-full bg-amber-500 ring-4 ring-amber-50 group-hover:scale-110 transition-transform"></div>
                    <div>
                        <p class="text-[12px] font-bold text-slate-800 group-hover:text-amber-600 transition-colors">Pengurusan COE Baru</p>
                        <p class="text-[11px] text-slate-500 font-medium">Besok, 09:00 WIB</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>`;

// Replace the closing div of the Recent Activity to include the Calendar
content = content.replace(
    '        </div>\n    </div>\n</div>\n\n<script>',
    '        </div>\n' + calendarWidget + '\n\n<script>'
);

fs.writeFileSync(filePath, content, 'utf8');
console.log('Calendar added successfully');
