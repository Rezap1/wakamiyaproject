@extends('layouts.app')

@section('header', 'Review Pengumpulan')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Review & Penilaian Tugas" 
        description="Berikan nilai dan umpan balik untuk tugas yang dikumpulkan siswa."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Pengumpulan' => route('submissions.index'), 'Review' => '#']"
    >
        <x-slot:actions>
            @php
                $status = $submission['Status'] ?? 'Submitted';
                $statusColor = match($status) {
                    'Graded' => 'green',
                    'Submitted' => 'blue',
                    'Late' => 'orange',
                    'Returned' => 'red',
                    default => 'gray'
                };
            @endphp
            <x-badge color="{{ $statusColor }}" type="solid" class="uppercase">
                Status Saat Ini: {{ $status }}
            </x-badge>
        </x-slot:actions>
    </x-page-header>

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('submissions.update', $submission['Submission_ID']) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="px-6 md:px-12">
                <x-form-section title="Detail Pengumpulan" description="Informasi tugas dan file yang dilampirkan oleh siswa.">
                    <div>
                        <x-input name="Student_ID" label="Student ID" value="{{ $submission['Student_ID'] ?? '' }}" readonly class="bg-slate-50 text-slate-500 cursor-not-allowed" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">File Lampiran</label>
                        @if(!empty($submission['File_URL']))
                            <a href="{{ $submission['File_URL'] }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-slate-100 text-blue-600 hover:text-blue-700 hover:bg-slate-200 font-bold text-sm rounded-xl transition-colors border border-slate-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Lihat / Unduh File Tugas
                            </a>
                        @else
                            <div class="px-4 py-2 bg-slate-50 text-slate-500 text-sm rounded-xl border border-slate-200">
                                Tidak ada file yang dilampirkan.
                            </div>
                        @endif
                    </div>
                </x-form-section>
                
                <div class="my-8 border-t border-slate-100"></div>

                <x-form-section title="Penilaian Guru" description="Berikan skor dan umpan balik terhadap tugas siswa.">
                    <div>
                        <x-input type="number" name="Grade_Received" label="Skor / Nilai (Grade)" required value="{{ old('Grade_Received', $submission['Grade_Received'] ?? '') }}" min="0" max="100" />
                    </div>

                    <div>
                        <x-select name="Status" label="Status Penilaian" required>
                            <option value="Graded" {{ old('Status', $submission['Status'] ?? '') == 'Graded' ? 'selected' : '' }}>Graded (Dinilai)</option>
                            <option value="Returned" {{ old('Status', $submission['Status'] ?? '') == 'Returned' ? 'selected' : '' }}>Returned (Dikembalikan untuk revisi)</option>
                            <!-- Fallback for existing data -->
                            <option value="Reviewed" {{ old('Status', $submission['Status'] ?? '') == 'Reviewed' ? 'selected' : '' }} class="hidden">Reviewed</option>
                            <option value="Completed" {{ old('Status', $submission['Status'] ?? '') == 'Completed' ? 'selected' : '' }} class="hidden">Completed</option>
                        </x-select>
                    </div>

                    <div class="sm:col-span-2">
                        <x-textarea name="Feedback" label="Umpan Balik (Feedback)" rows="4" placeholder="Berikan komentar, saran, atau koreksi...">{{ old('Feedback', $submission['Feedback'] ?? '') }}</x-textarea>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('submissions.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Review
                </x-button>
            </div>
        </form>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
        });
    });
</script>
@endsection



