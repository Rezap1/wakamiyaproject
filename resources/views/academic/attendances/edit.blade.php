@extends('layouts.app')
@section('header', 'Edit Kehadiran')
@section('content')
<div class="max-w-4xl mx-auto">
    <x-universal.form 
        action="{{ route('attendances.update', $attendance['Attendance_ID'] ?? 1) }}" 
        method="PUT"
        title="Edit Kehadiran" 
        description="Perbarui data kehadiran."
        buttonText="Perbarui Kehadiran"
    >
        <div class="space-y-8">
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Data Kehadiran</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Student_ID" 
                        label="Target Pengguna" 
                        value="{{ $attendance['Student_ID'] ?? 'John Doe' }}"
                        readonly
                    />

                    <x-universal.input 
                        name="Attendance_Date" 
                        label="Tanggal" 
                        type="date"
                        :required="true"
                        value="{{ $attendance['Attendance_Date'] ?? date('Y-m-d') }}"
                    />

                    <x-universal.select 
                        name="Status" 
                        label="Status" 
                        :options="['Present' => 'Present', 'Late' => 'Late', 'Sick' => 'Sick', 'Leave' => 'Leave', 'Absent' => 'Absent']"
                        value="{{ $attendance['Status'] ?? 'Present' }}"
                    />

                    <x-universal.input 
                        name="Time_In" 
                        label="Waktu Masuk" 
                        type="time"
                        value="08:00"
                    />

                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan" 
                            placeholder="Catatan..."
                            value="{{ $attendance['Notes'] ?? '' }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
