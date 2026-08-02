<!-- Global Toast Container -->
<div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3 pointer-events-none w-full max-w-sm"></div>

<script>
    window.showToast = function(type, title, message) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const id = 'toast-' + Date.now();
        let bgClass, iconClass, iconSvg;

        if (type === 'success') {
            bgClass = 'bg-white dark:bg-slate-900 border-green-500';
            iconClass = 'text-green-500 bg-green-50 dark:bg-slate-800';
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        } else if (type === 'error') {
            bgClass = 'bg-white dark:bg-slate-900 border-red-500';
            iconClass = 'text-red-500 bg-red-50 dark:bg-slate-800';
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        } else if (type === 'warning') {
            bgClass = 'bg-white dark:bg-slate-900 border-orange-500';
            iconClass = 'text-orange-500 bg-orange-50 dark:bg-slate-800';
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>';
        } else {
            bgClass = 'bg-white dark:bg-slate-900 border-blue-500';
            iconClass = 'text-emerald-500 bg-emerald-50 dark:bg-slate-800';
            iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        }

        const toastHTML = `
            <div id="${id}" class="pointer-events-auto flex items-start w-full overflow-hidden ${bgClass} border-l-4 rounded-xl shadow-lg transform transition-all duration-300 translate-x-full opacity-0">
                <div class="flex items-center justify-center p-4 ${iconClass}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${iconSvg}</svg>
                </div>
                <div class="p-4 flex-1">
                    <h4 class="text-sm font-bold text-slate-800 dark:text-white">${title}</h4>
                    ${message ? `<p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">${message}</p>` : ''}
                </div>
                <button onclick="dismissToast('${id}')" class="p-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHTML);
        const toastEl = document.getElementById(id);
        
        // Trigger animation
        requestAnimationFrame(() => {
            toastEl.classList.remove('translate-x-full', 'opacity-0');
            toastEl.classList.add('translate-x-0', 'opacity-100');
        });

        // Auto dismiss
        setTimeout(() => {
            dismissToast(id);
        }, 5000);
    };

    window.dismissToast = function(id) {
        const toastEl = document.getElementById(id);
        if (toastEl) {
            toastEl.classList.remove('translate-x-0', 'opacity-100');
            toastEl.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => {
                if (toastEl.parentNode) {
                    toastEl.parentNode.removeChild(toastEl);
                }
            }, 300);
        }
    };

    // Auto-fire session flashes if present
    document.addEventListener('DOMContentLoaded', () => {
        @if(session('success')) showToast('success', 'Berhasil', '{{ session('success') }}'); @endif
        @if(session('error')) showToast('error', 'Terjadi Kesalahan', '{{ session('error') }}'); @endif
        @if(session('warning')) showToast('warning', 'Peringatan', '{{ session('warning') }}'); @endif
        @if(session('info')) showToast('info', 'Informasi', '{{ session('info') }}'); @endif
        
        @if($errors->any())
            showToast('error', 'Validasi Gagal', 'Harap periksa kembali inputan Anda.');
        @endif
    });
</script>



