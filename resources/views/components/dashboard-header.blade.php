<div class="relative bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6" x-data="realtimeClock({{ $dashboardContext['timestamp'] ?? time() }})">
    <div class="absolute inset-0 bg-cover bg-center opacity-90" style="background-image: url('https://images.unsplash.com/photo-1490806843957-31f4c9a91c65?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-white via-white/90 to-transparent"></div>
    <div class="relative p-6 md:p-10 z-10 w-full flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 mb-1">
                {{ $dashboardContext['greeting'] }} {{ $dashboardContext['greeting_icon'] }}
            </h1>
            <p class="text-xl text-blue-700 font-bold mb-3">{{ $dashboardContext['user_name'] }}</p>
            
            <div class="flex flex-wrap gap-2 text-sm font-medium text-slate-600">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200">
                    {{ $dashboardContext['role'] }}
                </span>
                
                @if(isset($dashboardContext['batch']))
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $dashboardContext['batch'] }}
                </span>
                @endif

                @if(isset($dashboardContext['department']))
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200">
                    {{ $dashboardContext['department'] }}
                </span>
                @endif
                
                @if(isset($dashboardContext['class']))
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ $dashboardContext['class'] }}
                </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col items-start md:items-end bg-white/60 backdrop-blur-sm p-4 rounded-xl border border-white/40 shadow-sm w-full md:w-auto">
            <div class="text-2xl font-black text-slate-800 tracking-tight font-mono" x-text="timeString">
                {{ $dashboardContext['time'] }} WIB
            </div>
            <div class="text-sm font-semibold text-slate-600 mt-1">
                {{ $dashboardContext['date'] }}
            </div>
            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mt-1">
                Timezone: {{ $dashboardContext['timezone'] }}
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('realtimeClock', (serverTimestamp) => ({
        timestamp: serverTimestamp,
        timeString: '',
        
        init() {
            this.updateTimeString();
            setInterval(() => {
                this.timestamp++;
                this.updateTimeString();
            }, 1000);
        },
        
        updateTimeString() {
            // Create a date object from the timestamp
            const date = new Date(this.timestamp * 1000);
            
            // Format time using options, forcing Asia/Jakarta for localized string
            // Even though the JS Date might be constructed in user's timezone, 
            // formatting it using specific Intl options will show the correct time.
            const formatter = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            
            this.timeString = formatter.format(date).replace(/\./g, ':') + ' WIB';
        }
    }));
});
</script>
