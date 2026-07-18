@extends('layouts.app')

@section('header', 'Edit Konfigurasi Hak Akses')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Perbarui Aturan Akses</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">ID: <span class="font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded font-mono">{{ $permission['Permission_ID'] }}</span></p>
            </div>
            <div>
                @if(($permission['Is_Active'] ?? 'TRUE') === 'TRUE')
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                        Status: Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-red-50 text-red-700 border border-red-200">
                        Status: Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <div class="p-6 md:p-8">
            <form action="{{ route('permissions.update', $permission['Permission_ID']) }}" method="POST" class="space-y-8">
                @csrf
                @method('PUT')
                
                <!-- Section 1: Relasi -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">1. Identitas Relasi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div>
                            <label for="Role_ID" class="block text-sm font-bold text-gray-700">Pilih Role (Peran) <span class="text-red-500">*</span></label>
                            <select name="Role_ID" id="Role_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Role_ID') border-red-300 text-red-900 @enderror">
                                <option value="">-- Pilih Role --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role['Role_ID'] }}" {{ old('Role_ID', $permission['Role_ID']) == $role['Role_ID'] ? 'selected' : '' }}>
                                        {{ $role['Role_Name'] ?? $role['Role_ID'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Role_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="Module_ID" class="block text-sm font-bold text-gray-700">Pilih Modul Sistem <span class="text-red-500">*</span></label>
                            <select name="Module_ID" id="Module_ID" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors @error('Module_ID') border-red-300 text-red-900 @enderror">
                                <option value="">-- Pilih Modul --</option>
                                @foreach($modules as $module)
                                    <option value="{{ $module['Module_ID'] }}" {{ old('Module_ID', $permission['Module_ID']) == $module['Module_ID'] ? 'selected' : '' }}>
                                        {{ $module['Module_Name'] ?? $module['Module_ID'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Module_ID') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                <!-- Section 2: Matriks Akses -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">2. Matriks Hak Akses (Izin)</h3>
                    <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                        
                        <!-- Toggle Checkbox Custom Design -->
                        @foreach([
                            'Can_View' => ['icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'label' => 'Melihat Data (View)', 'color' => 'blue'],
                            'Can_Create' => ['icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6', 'label' => 'Menambah (Create)', 'color' => 'green'],
                            'Can_Edit' => ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => 'Mengubah (Edit)', 'color' => 'yellow'],
                            'Can_Delete' => ['icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', 'label' => 'Menghapus (Delete)', 'color' => 'red'],
                            'Can_Print' => ['icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z', 'label' => 'Mencetak (Print)', 'color' => 'indigo'],
                            'Can_Export_PDF' => ['icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', 'label' => 'Ekspor (Export PDF)', 'color' => 'purple']
                        ] as $field => $config)
                        <div class="relative flex items-start p-4 border border-gray-200 bg-white rounded-xl shadow-sm hover:border-{{ $config['color'] }}-300 transition-colors group">
                            <div class="flex items-center h-5 mt-1">
                                <input id="{{ $field }}" name="{{ $field }}" type="checkbox" value="1" {{ old($field, ($permission[$field] ?? 'FALSE') === 'TRUE') ? 'checked' : '' }} class="focus:ring-{{ $config['color'] }}-500 h-5 w-5 text-{{ $config['color'] }}-600 border-gray-300 rounded cursor-pointer transition-all">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="{{ $field }}" class="font-bold text-gray-800 cursor-pointer group-hover:text-{{ $config['color'] }}-700 flex items-center transition-colors">
                                    <svg class="w-4 h-4 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $config['icon'] }}"></path></svg>
                                    {{ $config['label'] }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                        
                    </div>
                </div>

                <!-- Section 3: Status & Notes -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">3. Status & Log</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="Is_Active" class="block text-sm font-bold text-gray-700">Status Konfigurasi <span class="text-red-500">*</span></label>
                            <select name="Is_Active" id="Is_Active" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors font-bold">
                                <option value="TRUE" {{ old('Is_Active', $permission['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'selected' : '' }}>Aktif</option>
                                <option value="FALSE" {{ old('Is_Active', $permission['Is_Active'] ?? 'TRUE') === 'FALSE' ? 'selected' : '' }}>Nonaktif (Soft Delete)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="Notes" class="block text-sm font-bold text-gray-700">Catatan Internal / Log Perubahan</label>
                            <textarea id="Notes" name="Notes" rows="2" class="mt-2 block w-full rounded-xl border-gray-300 focus:ring-primary-500 focus:border-primary-500 sm:text-sm px-4 py-3 bg-gray-50 focus:bg-white transition-colors">{{ old('Notes', $permission['Notes']) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3 mt-8">
                    <a href="{{ route('permissions.index') }}" class="px-6 py-3 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all">
                        Batal
                    </a>
                    <button type="submit" id="submitBtn" class="px-6 py-3 border border-transparent shadow-lg text-sm font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/30 flex items-center justify-center gap-2">
                        <span id="submitText">Perbarui Hak Akses</span>
                        <svg id="submitLoading" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Submit Loading State
    document.querySelector('form').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('submitText');
        const loading = document.getElementById('submitLoading');
        
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
        btn.classList.remove('hover:-translate-y-0.5');
        text.innerText = 'Menyimpan...';
        loading.classList.remove('hidden');
    });
</script>
@endsection
