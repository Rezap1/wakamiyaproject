@extends('layouts.app')
@section('header', 'Buat Penggajian')
@section('content')
@php
    $currentRoleName = strtoupper(\App\Helpers\UserResolverHelper::getRoleName(auth()->user()->Role_ID ?? ''));
    $payrollDashboardUrl = $currentRoleName === 'FINANCE' ? route('dashboard.finance') : route('dashboard.hr');
@endphp
<div class="space-y-6" x-data="payrollForm()">
    <x-page-header title="Buat Penggajian" description="Hitung gaji baru." :breadcrumbs="['Dashboard' => $payrollDashboardUrl, 'Penggajian' => route('payrolls.index'), 'Buat' => '#']" />
    <form action="{{ route('payrolls.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-800">Detail Penggajian</h3>
                <p class="text-xs text-slate-500 mt-1">Pilih karyawan dan periode penggajian.</p>
            </div>
            
            <div class="p-6">
                @if($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold space-y-1">
                        @foreach($errors->all() as $err)
                            <p>• {{ $err }}</p>
                        @endforeach
                    </div>
                @endif

                <x-smart-warning x-show="!employee.Employee_ID && isSelected" 
                    message="Data pegawai tidak lengkap. Silakan lengkapi data master terlebih dahulu." 
                    type="warning" 
                    class="mb-6" />

@php
    $employeeOptions = [];
    if(isset($employees)) {
        foreach($employees as $e) {
            $employeeOptions[$e['Employee_ID'] ?? ''] = ($e['Full_Name'] ?? 'Unknown') . ' (' . ($e['Employee_Number'] ?? '-') . ')';
        }
    }
@endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <x-universal.searchable-select 
                            name="Employee_ID" 
                            id="Employee_ID"
                            label="Karyawan" 
                            :options="$employeeOptions" 
                            :required="true" 
                            value="" 
                        />
                    </div>
                    <div>
                        <x-input type="month" name="Payroll_Period" label="Periode Penggajian (YYYY-MM)" value="{{ date('Y-m') }}" required />
                    </div>
                </div>

                <!-- SMART FORM SECTION (AUTO-FILL & HR SALARY PREVIEW) -->
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200 mb-8 space-y-4" x-show="employeeId">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span>👤</span> Informasi Pegawai & Acuan Gaji HR
                        </h3>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                            Auto-Sync HR Settings
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <x-input label="Nama Lengkap" x-model="employee.Full_Name" readonly class="bg-white font-medium cursor-not-allowed" />
                        <x-input label="Departemen / Divisi" x-model="employee.Department_Name || employee.Department_ID || '-'" readonly class="bg-white font-medium cursor-not-allowed" />
                        <x-input label="Posisi / Jabatan" x-model="employee.Position_Name || employee.Position_ID || '-'" readonly class="bg-white font-medium cursor-not-allowed" />
                        <x-input label="Status Kepegawaian" x-model="employee.Employment_Status" readonly class="bg-white font-medium cursor-not-allowed" />
                        <x-input label="Email" x-model="employee.Email" readonly class="bg-white font-medium cursor-not-allowed" />
                        <x-input label="Telepon" x-model="employee.Phone_Number" readonly class="bg-white font-medium cursor-not-allowed" />
                    </div>

                    <!-- HR Settings Standard Preview Card -->
                    <div class="bg-violet-900 text-white rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">💵</span>
                            <div>
                                <p class="text-[11px] text-violet-300 font-bold uppercase tracking-wider">Acuan Gaji Pokok HR (Pengaturan HR)</p>
                                <p class="text-base font-extrabold text-white mt-0.5" x-text="getStandardSalaryText()"></p>
                            </div>
                        </div>
                        <span class="text-xs bg-violet-800/80 px-3 py-1.5 rounded-lg border border-violet-700 font-semibold text-violet-200">
                            ⚙️ Termuat dari Pengaturan HR
                        </span>
                    </div>
                </div>

                <div class="space-y-3 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <x-input type="number" name="Net_Salary" label="Total Gaji Bersih / Override Nominal (Rp)" value="0" placeholder="0 = Hitung Otomatis" required />
                    </div>
                    <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/80 text-blue-900 text-xs font-medium space-y-1 leading-relaxed">
                        <p class="font-bold flex items-center gap-1.5 text-blue-950">
                            <span>💡</span> Informasi Perhitungan Otomatis:
                        </p>
                        <p>• Jika diisi <strong>0</strong>, sistem akan menghitung total gaji secara otomatis sesuai standar **Pengaturan HR** berdasarkan divisi/jabatan karyawan (misal: Finance Rp 3,8 Jt, Guru Rp 4 Jt, Staff Rp 3,5 Jt).</p>
                        <p>• Jika Anda memasukkan nominal tertentu (misal: 5.000.000), nominal tersebut akan digunakan sebagai gaji bersih karyawan.</p>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('payrolls.index') }}" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-slate-800 transition-colors">Batal</a>
                    <button type="submit" :disabled="!employee.Employee_ID && isSelected" class="px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                        <span>⚡</span>
                        <span>Hitung & Buat Payroll</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function payrollForm() {
        return {
            employeeId: '',
            isSelected: false,
            employee: {},
            init() {
                const el = document.getElementById('Employee_ID');
                if (el) {
                    el.addEventListener('change', (e) => {
                        this.employeeId = e.target.value;
                        this.fetchEmployeeData();
                    });
                }
            },
            fetchEmployeeData() {
                if (!this.employeeId) {
                    this.employee = {};
                    this.isSelected = false;
                    return;
                }
                
                this.isSelected = true;
                
                // Fetch from API
                fetch(`/api/employees/${this.employeeId}`)
                    .then(response => response.json())
                    .then(data => {
                        this.employee = data;
                    })
                    .catch(error => {
                        console.error('Error fetching employee data:', error);
                        this.employee = {};
                    });
            },
            getStandardSalaryText() {
                if (!this.employee || !this.employee.Full_Name) return 'Pilih Karyawan...';
                const dept = (this.employee.Department_Name || this.employee.Department_ID || '').toUpperCase();
                const pos = (this.employee.Position_Name || this.employee.Position_ID || '').toUpperCase();
                const name = (this.employee.Full_Name || '').toUpperCase();

                if (dept.includes('FINANCE') || pos.includes('FINANCE') || name.includes('FINANCE')) {
                    return 'Rp 3.800.000 / Bulan (Standar Staff Finance)';
                } else if (dept.includes('TEACHER') || pos.includes('GURU') || pos.includes('SENSEI') || name.includes('TEACHER') || name.includes('SENSEI')) {
                    return 'Rp 4.000.000 / Bulan (Standar Guru / Sensei)';
                } else if (dept.includes('ACADEMIC') || pos.includes('AKADEMIK') || name.includes('ACADEMIC')) {
                    return 'Rp 3.700.000 / Bulan (Standar Tim Akademik)';
                } else if (dept.includes('MARKETING') || pos.includes('MARKETING') || name.includes('MARKETING')) {
                    return 'Rp 3.500.000 / Bulan (Standar Tim Marketing)';
                } else if (dept.includes('HR') || pos.includes('HR') || name.includes('HR')) {
                    return 'Rp 4.000.000 / Bulan (Standar Tim HR)';
                } else {
                    return 'Rp 3.500.000 / Bulan (Standar Staff Umum)';
                }
            }
        }
    }
</script>
@endsection



