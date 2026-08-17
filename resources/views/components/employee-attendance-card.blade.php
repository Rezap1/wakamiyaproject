@props(['employee' => null])

@php
    $user = auth()->user();
    $employeeName = $employee['Full_Name'] ?? $user->Username ?? $user->Name ?? 'Pegawai WMS';
    $employeeId = $employee['Employee_ID'] ?? $user->User_ID ?? 'EMP-001';

    // Check today check-in status
    $todayStr = now()->toDateString();
    $attRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
    $allAtt = collect($attRepo->fetchAll());
    $todayRecord = $allAtt->first(function($att) use ($employeeId, $todayStr) {
        return ($att['Employee_ID'] ?? '') === $employeeId && 
               ($att['Attendance_Date'] ?? '') === $todayStr && 
               strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
    });

    $hasCheckedIn = !empty($todayRecord);
    $checkInTime = $hasCheckedIn ? \Carbon\Carbon::parse($todayRecord['Check_In_Time'] ?? $todayRecord['Created_At'])->format('H:i') : null;
@endphp

<!-- MOBILE-FIRST EMPLOYEE ATTENDANCE CARD (SECTION 5) -->
<div class="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white rounded-3xl p-6 shadow-xl border border-white/10 relative overflow-hidden mb-6" x-data="employeeLocationWidget()">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center text-2xl backdrop-blur-md shrink-0">
                📱
            </div>
            <div>
                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest block">WMS &bull; Presensi Pegawai</span>
                <h3 class="text-base font-black text-white leading-snug">{{ $employeeName }}</h3>
            </div>
        </div>
        
        <!-- STATUS BADGE -->
        @if($hasCheckedIn)
            <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 font-extrabold text-xs rounded-full border border-emerald-400/30 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                Sudah Check-In {{ $checkInTime }} WIB
            </span>
        @else
            <span class="px-3 py-1 bg-amber-500/20 text-amber-300 font-extrabold text-xs rounded-full border border-amber-400/30 flex items-center gap-1.5 animate-pulse">
                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                Belum Check-In
            </span>
        @endif
    </div>

    <!-- LOCATION CARD -->
    <div class="mt-5 p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 text-xs space-y-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold">
                <span>📍 LPK Wakamiya</span>
            </div>
            <template x-if="isLocating">
                <span class="text-[11px] text-slate-300 animate-pulse">Memeriksa GPS...</span>
            </template>
            <template x-if="!isLocating && isInside">
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-extrabold border border-emerald-400/30 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    🟢 Dalam Area
                </span>
            </template>
            <template x-if="!isLocating && !isInside">
                <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 font-extrabold border border-rose-400/30 flex items-center gap-1.5">
                    🔴 Di Luar Area LPK
                </span>
            </template>
        </div>

        <div class="text-slate-300 font-medium flex items-center justify-between text-[11px]">
            <span>Jarak ke LPK Wakamiya:</span>
            <span class="font-extrabold text-white" x-text="distanceText"></span>
        </div>
    </div>

    <!-- CTA BUTTON -->
    <div class="mt-5">
        <a href="{{ route('hr.attendance.qr.scanner') }}" 
           class="w-full py-4 px-6 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 hover:from-blue-400 hover:to-purple-500 text-white font-black text-base rounded-2xl shadow-lg shadow-indigo-500/30 active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-3 text-center">
            <span class="text-xl">📷</span>
            <span>SCAN QR PEGAWAI</span>
        </a>
    </div>
</div>

<script>
    function employeeLocationWidget() {
        return {
            isLocating: true,
            isInside: false,
            distanceText: 'Menghitung...',
            lpkLat: -6.812391,
            lpkLon: 107.194458,

            init() {
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            const userLat = pos.coords.latitude;
                            const userLon = pos.coords.longitude;
                            const dist = this.haversine(userLat, userLon, this.lpkLat, this.lpkLon);
                            this.isLocating = false;
                            this.isInside = dist <= 20;
                            this.distanceText = dist.toFixed(1) + ' meter';
                        },
                        (err) => {
                            this.isLocating = false;
                            this.distanceText = 'GPS Tidak Aktif';
                        },
                        { enableHighAccuracy: true, timeout: 8000 }
                    );
                } else {
                    this.isLocating = false;
                    this.distanceText = 'GPS Tidak Didukung';
                }
            },

            haversine(lat1, lon1, lat2, lon2) {
                const R = 6371000;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }
        }
    }
</script>
