@extends('layouts.app')
@section('header', 'Tambah Penilaian')

@section('content')
<div class="space-y-6" x-data="assessmentForm()">
    <x-page-header 
        title="Tambah Penilaian" 
        description="Masukkan penilaian aspektual untuk siswa di kelas Anda."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Penilaian' => route('teacher.workspace.scores'), 'Tambah' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <form action="{{ route('teacher.workspace.scores.store') }}" method="POST" class="p-6 md:p-8 space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Siswa -->
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Pilih Siswa</label>
                    <select name="Student_ID" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50">
                        <option value="">-- Pilih Siswa --</option>
                        @foreach($students as $student)
                            <option value="{{ $student['Student_ID'] }}">{{ $student['Full_Name'] ?? $student['Username'] }} ({{ $student['Class_Name'] ?? $student['Class_ID'] }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Tanggal -->
                <div class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700">Tanggal Penilaian</label>
                    <input type="date" name="Date" required value="{{ date('Y-m-d') }}" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50">
                </div>

                <!-- Kategori Penilaian -->
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-bold text-slate-700">Kategori Penilaian</label>
                    <select name="Assessment_Category" x-model="category" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($assessmentConfigs as $config)
                            <option value="{{ $config['Category_ID'] }}">{{ strtoupper($config['Category_Name']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Aspek Penilaian Dinamis -->
            <div x-show="category !== '' && hasAspects" class="space-y-6 pt-4 border-t border-slate-100" style="display: none;">
                <h4 class="font-bold text-slate-800 text-lg" x-text="'Aspek Penilaian ' + (activeConfig ? activeConfig.Category_Name : '')"></h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <template x-for="aspect in activeAspects" :key="aspect.id">
                        <div class="space-y-1">
                            <label class="block text-sm font-semibold text-slate-600" x-text="aspect.label"></label>
                            <select :name="aspect.id" required class="w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <option value="">-- Pilih Tingkat --</option>
                                <option value="1">1 - Sangat Kurang</option>
                                <option value="2">2 - Kurang</option>
                                <option value="3">3 - Cukup</option>
                                <option value="4">4 - Baik</option>
                                <option value="5">5 - Sangat Baik</option>
                            </select>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Aspek Kosong / Belum Ada Konfigurasi -->
            <div x-show="category !== '' && !hasAspects" class="space-y-6 pt-4 border-t border-slate-100" style="display: none;">
                <x-empty-state icon="cog" title="Konfigurasi Penilaian Belum Tersedia" message="Kategori ini belum memiliki aspek penilaian yang dapat digunakan. Hubungi Administrator untuk menyiapkan konfigurasi penilaian kategori ini." />
            </div>

            <!-- Catatan -->
            <div x-show="category !== ''" class="space-y-2 pt-4 border-t border-slate-100" style="display: none;">
                <label class="block text-sm font-bold text-slate-700">Catatan (Opsional)</label>
                <textarea name="Notes" rows="3" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50" placeholder="Tambahkan catatan khusus terkait penilaian ini..."></textarea>
            </div>

            <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('teacher.workspace.scores') }}" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                    Batal
                </a>
                <button type="submit" x-bind:disabled="category === '' || !hasAspects" class="px-6 py-2.5 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Simpan Penilaian
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('assessmentForm', () => ({
        category: '',
        configs: @json($assessmentConfigs ?? []),
        get activeConfig() {
            return this.configs.find(c => c.Category_ID === this.category);
        },
        get activeAspects() {
            if (!this.activeConfig || !this.activeConfig.Aspects_JSON) return [];
            try {
                return JSON.parse(this.activeConfig.Aspects_JSON);
            } catch (e) {
                return [];
            }
        },
        get hasAspects() {
            return this.activeAspects.length > 0;
        }
    }))
})
</script>
@endsection
