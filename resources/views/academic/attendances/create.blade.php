@extends('layouts.app')
@section('header', 'Catat Kehadiran Harian')
@section('content')
@php
    if (!isset($classOptions) || !is_array($classOptions)) {
        $classOptions = [];
    }
@endphp

<div class="max-w-5xl mx-auto space-y-6" x-data="bulkAttendanceForm()">
    <x-universal.form 
        action="{{ route('attendances.store') }}" 
        method="POST"
        title="Absensi Kelas" 
        description="Catat kehadiran seluruh siswa di kelas Anda untuk hari ini."
        buttonText="Simpan Kehadiran"
    >
        <x-slot:alpineBinding>
            :disabled="!classId || students.length === 0"
        </x-slot:alpineBinding>

        <div class="space-y-8">
            <x-smart-warning x-show="!classId" 
                message="Silakan pilih kelas terlebih dahulu untuk memuat daftar siswa." 
                type="info" 
                class="mb-6" />

            @if(empty($classOptions))
                <x-smart-warning
                    message="Belum ada kelas aktif yang dapat dipilih untuk akun ini."
                    type="warning"
                    class="mb-6" />
            @endif
                
            <x-smart-warning x-show="classId && students.length === 0 && !isLoading" 
                message="Tidak ada siswa di kelas ini atau gagal memuat data." 
                type="warning" 
                class="mb-6" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Pilih Kelas *</label>
                    <select name="Class_ID" x-model="classId" @change="fetchStudents" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm" required>
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($classOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <x-universal.input 
                    name="Attendance_Date" 
                    label="Tanggal Absensi *" 
                    type="date"
                    :required="true"
                    value="{{ date('Y-m-d') }}"
                />
            </div>
            
            <div x-show="isLoading" class="py-8 flex justify-center">
                <x-loading />
            </div>

            <!-- TABLE VIEW FOR BULK ATTENDANCE -->
            <div x-show="students.length > 0 && !isLoading" style="display: none;" class="mt-8">
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center justify-between">
                    <span>Daftar Siswa</span>
                    <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-1 rounded-lg" x-text="students.length + ' Siswa'"></span>
                </h3>
                
                <div class="overflow-x-auto bg-white rounded-xl border border-slate-200 shadow-sm">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr class="text-slate-500 text-[11px] font-extrabold uppercase tracking-widest">
                                <th class="px-4 py-3">Siswa</th>
                                <th class="px-4 py-3 text-center w-64">Status Kehadiran</th>
                                <th class="px-4 py-3">Catatan Tambahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[13px] text-slate-700">
                            <template x-for="(student, index) in students" :key="student.Student_ID">
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs mr-3 flex-shrink-0" x-text="student.Full_Name.charAt(0)"></div>
                                            <div>
                                                <p class="font-bold text-slate-800" x-text="student.Full_Name"></p>
                                                <p class="text-[10px] text-slate-500 font-medium" x-text="student.Student_ID"></p>
                                            </div>
                                        </div>
                                        <input type="hidden" :name="`students[${index}][Student_ID]`" :value="student.Student_ID">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="inline-flex rounded-lg border border-slate-200 p-1 bg-slate-50">
                                            <label class="cursor-pointer px-3 py-1.5 rounded-md text-[11px] font-bold transition-all" 
                                                :class="student.status === 'Hadir' ? 'bg-green-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-200'">
                                                <input type="radio" :name="`students[${index}][Status]`" value="Hadir" x-model="student.status" class="hidden"> H
                                            </label>
                                            <label class="cursor-pointer px-3 py-1.5 rounded-md text-[11px] font-bold transition-all" 
                                                :class="student.status === 'Sakit' ? 'bg-blue-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-200'">
                                                <input type="radio" :name="`students[${index}][Status]`" value="Sakit" x-model="student.status" class="hidden"> S
                                            </label>
                                            <label class="cursor-pointer px-3 py-1.5 rounded-md text-[11px] font-bold transition-all" 
                                                :class="student.status === 'Izin' ? 'bg-indigo-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-200'">
                                                <input type="radio" :name="`students[${index}][Status]`" value="Izin" x-model="student.status" class="hidden"> I
                                            </label>
                                            <label class="cursor-pointer px-3 py-1.5 rounded-md text-[11px] font-bold transition-all" 
                                                :class="student.status === 'Alpha' ? 'bg-red-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-200'">
                                                <input type="radio" :name="`students[${index}][Status]`" value="Alpha" x-model="student.status" class="hidden"> A
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" :name="`students[${index}][Notes]`" x-model="student.notes" placeholder="Catatan..." class="w-full text-xs rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500/20 px-3 py-2 bg-slate-50">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </x-universal.form>
    
    <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100 mt-6">
        <h3 class="text-sm font-bold text-blue-900 mb-2 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Panduan Status Kehadiran
        </h3>
        <ul class="text-xs font-medium text-blue-700 flex gap-4 mt-3">
            <li><span class="inline-block w-4 h-4 bg-green-500 rounded text-white text-center leading-4 mr-1">H</span> Hadir</li>
            <li><span class="inline-block w-4 h-4 bg-blue-500 rounded text-white text-center leading-4 mr-1">S</span> Sakit</li>
            <li><span class="inline-block w-4 h-4 bg-indigo-500 rounded text-white text-center leading-4 mr-1">I</span> Izin</li>
            <li><span class="inline-block w-4 h-4 bg-red-500 rounded text-white text-center leading-4 mr-1">A</span> Alpha</li>
        </ul>
    </div>
</div>

<script>
    function bulkAttendanceForm() {
        return {
            classId: '',
            students: [],
            isLoading: false,

            fetchStudents() {
                if (!this.classId) {
                    this.students = [];
                    return;
                }
                
                this.isLoading = true;
                
                fetch(`/api/classes/${this.classId}/students`, {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Not found');
                        return response.json();
                    })
                    .then(data => {
                        const rows = Array.isArray(data) ? data : Object.values(data || {});
                        this.students = rows.map(student => ({
                            ...student,
                            status: 'Hadir',
                            notes: ''
                        }));
                    })
                    .catch(error => {
                        console.error('Error fetching students:', error);
                        this.students = [];
                    })
                    .finally(() => {
                        this.isLoading = false;
                    });
            }
        }
    }
</script>
@endsection
