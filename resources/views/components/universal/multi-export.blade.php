@props(['routePrefix', 'extraParams' => []])

<div class="flex flex-wrap items-center gap-2" x-data="exportManager()">
    <!-- Preview PDF -->
    <button type="button" @click="openModal('preview')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-blue-600 focus:ring-2 focus:ring-slate-100 transition-all shadow-sm">
        <svg class="w-4 h-4 mr-1.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        Preview PDF
    </button>

    <!-- Download PDF -->
    <button type="button" @click="openModal('pdf')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-red-600 focus:ring-2 focus:ring-slate-100 transition-all shadow-sm">
        <svg class="w-4 h-4 mr-1.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
        Download PDF
    </button>

    <!-- Export Dropdown -->
    <div class="relative group">
        <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 focus:ring-2 focus:ring-slate-100 transition-all shadow-sm">
            <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Export Data
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>
        <div class="absolute right-0 w-40 mt-1 origin-top-right bg-white border border-slate-200 divide-y divide-slate-100 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
            <div class="py-1">
                <button type="button" @click="openModal('excel')" class="w-full text-left block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-green-600">
                    <span class="flex items-center">
                        <span class="mr-2">📊</span> Export Excel
                    </span>
                </button>
                <button type="button" @click="openModal('csv')" class="w-full text-left block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-slate-900">
                    <span class="flex items-center">
                        <span class="mr-2">📄</span> Export CSV
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Print -->
    <button type="button" @click="openModal('print')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-900 focus:ring-2 focus:ring-slate-100 transition-all shadow-sm">
        <svg class="w-4 h-4 mr-1.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        Print
    </button>

    <!-- Export Modal -->
    <div x-show="showExportModal" style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showExportModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showExportModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showExportModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full relative">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-50 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-bold text-slate-900" id="modal-title">Export Options</h3>
                            <div class="mt-4 space-y-4 text-left">
                                
                                <div>
                                    <label class="block text-[13px] font-bold text-slate-700 mb-1">Export Type</label>
                                    <select x-model="exportType" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm">
                                        <option value="preview">Preview PDF</option>
                                        <option value="pdf">Download PDF</option>
                                        <option value="excel">Excel</option>
                                        <option value="csv">CSV</option>
                                        <option value="print">Print</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-[13px] font-bold text-slate-700 mb-1">Data Filter</label>
                                    <select x-model="exportFilter" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm">
                                        <option value="all">Semua Data (All Records)</option>
                                        <option value="current_page">Halaman Saat Ini (Current Page)</option>
                                        <option value="today">Hari Ini (Today)</option>
                                        <option value="range">Rentang Tanggal (Date Range)</option>
                                    </select>
                                </div>
                                
                                <div x-show="exportFilter === 'range'" class="grid grid-cols-2 gap-4" x-transition>
                                    <div>
                                        <label class="block text-[13px] font-bold text-slate-700 mb-1">Start Date</label>
                                        <input type="date" x-model="startDate" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-[13px] font-bold text-slate-700 mb-1">End Date</label>
                                        <input type="date" x-model="endDate" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[13px] font-bold text-slate-700 mb-1">Sorting</label>
                                        <select x-model="sortOrder" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm">
                                            <option value="desc">Terbaru (Latest First)</option>
                                            <option value="asc">Terlama (Oldest First)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[13px] font-bold text-slate-700 mb-1">Orientation</label>
                                        <select x-model="orientation" class="block w-full text-[13px] rounded-xl bg-slate-50 border-slate-200 text-slate-800 focus:ring-2 focus:border-blue-500 focus:ring-blue-500/20 px-4 py-2.5 shadow-sm" :disabled="['excel', 'csv'].includes(exportType)">
                                            <option value="auto">Auto</option>
                                            <option value="portrait">Portrait</option>
                                            <option value="landscape">Landscape</option>
                                        </select>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-slate-100">
                    <button type="button" @click="submitExport()" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Process
                    </button>
                    <button type="button" @click="showExportModal = false" class="mt-3 w-full inline-flex justify-center rounded-xl border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function exportManager() {
        return {
            showExportModal: false,
            exportType: 'pdf',
            exportFilter: 'all',
            startDate: '',
            endDate: '',
            sortOrder: 'desc',
            orientation: 'auto',
            
            openModal(type) {
                this.exportType = type;
                this.showExportModal = true;
            },
            
            submitExport() {
                if (this.exportFilter === 'range' && (!this.startDate || !this.endDate)) {
                    alert('Please select both Start Date and End Date.');
                    return;
                }
                
                let routeMap = {
                    'preview': '{{ route($routePrefix . '.preview-pdf') }}',
                    'pdf': '{{ route($routePrefix . '.export-pdf') }}',
                    'excel': '{{ route($routePrefix . '.export-excel') }}',
                    'csv': '{{ route($routePrefix . '.export-csv') }}',
                    'print': '{{ route($routePrefix . '.print') }}',
                };
                
                let baseUrl = routeMap[this.exportType];
                
                // Construct URL
                let url = new URL(baseUrl, window.location.origin);
                
                // Pass extra params explicitly
                let extraParams = @json($extraParams);
                for (let key in extraParams) {
                    url.searchParams.set(key, extraParams[key]);
                }

                // Pass existing filters (from the page)
                let currentParams = new URLSearchParams(window.location.search);
                for (let [key, value] of currentParams.entries()) {
                    if (key !== 'page' && !extraParams.hasOwnProperty(key)) {
                        url.searchParams.append(key, value);
                    }
                }
                
                // Handle current page export
                if (this.exportFilter === 'current_page') {
                    let page = currentParams.get('page') || 1;
                    url.searchParams.set('page', page);
                }
                
                // Add export configuration parameters
                url.searchParams.set('export_filter', this.exportFilter);
                if (this.exportFilter === 'range') {
                    url.searchParams.set('start_date', this.startDate);
                    url.searchParams.set('end_date', this.endDate);
                }
                url.searchParams.set('sort_order', this.sortOrder);
                url.searchParams.set('orientation', this.orientation);
                
                if (this.exportType === 'preview' || this.exportType === 'print') {
                    window.open(url.toString(), '_blank');
                } else {
                    window.location.href = url.toString();
                }
                
                this.showExportModal = false;
            }
        }
    }
</script>
