@extends('layouts.app')
@section('header', 'Buat Penggajian')
@section('content')
<div class="space-y-6" x-data="payrollForm()">
    <x-page-header title="Buat Penggajian" description="Hitung gaji baru." :breadcrumbs="['Dashboard' => route('dashboard.hr'), 'Penggajian' => route('payrolls.index'), 'Buat' => '#']" />
    <form action="{{ route('payrolls.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-800">Detail Penggajian</h3>
                <p class="text-xs text-slate-500 mt-1">Pilih karyawan dan periode penggajian.</p>
            </div>
            
            <div class="p-6">
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

                <!-- SMART FORM SECTION (AUTO-FILL) -->
                <div class="bg-slate-50 rounded-xl p-5 border border-slate-200 mb-8" x-show="employeeId">
                    <h3 class="text-sm font-bold text-slate-700 mb-4 border-b border-slate-200 pb-2">Informasi Pegawai (Auto-fill)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <x-input label="Nama Lengkap" x-model="employee.Full_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Departemen" x-model="employee.Department_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Posisi / Jabatan" x-model="employee.Position_Name" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Status Kepegawaian" x-model="employee.Employment_Status" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Email" x-model="employee.Email" readonly class="bg-slate-100 cursor-not-allowed" />
                        <x-input label="Telepon" x-model="employee.Phone_Number" readonly class="bg-slate-100 cursor-not-allowed" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-4">
                    <x-input type="number" name="Net_Salary" label="Total Gaji (Bersih)" value="0" required />
                </div>
                
                <p class="text-xs text-slate-400 mt-2 mb-6">* Karena penyederhanaan sistem, BPJS dan Pajak dianggap 0, Total Gaji yang Anda masukkan adalah nominal bersih yang akan diterima karyawan.</p>
                
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('payrolls.index') }}" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-slate-800 transition-colors">Batal</a>
                    <button type="submit" :disabled="!employee.Employee_ID && isSelected" class="px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed">Hitung & Buat</button>
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
            }
        }
    }
</script>
@endsection



