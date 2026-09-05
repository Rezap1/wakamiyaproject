<!-- Global Confirm Dialog -->
<div id="confirm-dialog" class="fixed inset-0 z-[80] hidden items-center justify-center p-3 pointer-events-none" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" tabindex="-1">
    <!-- Backdrop -->
    <div id="confirm-backdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-300 pointer-events-auto" onclick="closeConfirmDialog()"></div>
    
    <!-- Modal -->
    <div id="confirm-modal" class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 w-full max-w-md transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto flex max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden">
        
        <!-- Header Strip -->
        <div id="confirm-strip" class="h-1.5 w-full bg-emerald-500"></div>

        <div class="overflow-y-auto p-4 sm:p-6">
            <div class="flex items-start gap-4">
                <div id="confirm-icon-container" class="flex-shrink-0 w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <svg id="confirm-icon" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="flex-1 mt-1">
                    <h3 id="confirm-title" class="text-lg font-bold text-slate-900 dark:text-white">Konfirmasi</h3>
                    <p id="confirm-message" class="mt-2 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Apakah Anda yakin ingin melanjutkan tindakan ini?</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:flex-row sm:justify-end sm:gap-3 sm:px-6 sm:py-4">
            <button type="button" onclick="closeConfirmDialog()" class="min-h-11 w-full px-4 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors shadow-sm sm:w-auto">
                Batal
            </button>
            <form id="confirm-form" method="POST" action="" class="m-0 w-full sm:w-auto">
                @csrf
                <input type="hidden" name="_method" id="confirm-method" value="POST">
                <button type="submit" id="confirm-btn" class="flex min-h-11 w-full items-center justify-center px-4 py-2 text-sm font-bold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors shadow-sm sm:w-auto">
                    Ya, Lanjutkan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let confirmDialogCallback = null;

    /**
     * Show confirmation dialog
     * @param {Object} options - title, message, type (danger, warning, info), formAction, formMethod
     */
    window.showConfirmDialog = function(options) {
        const dialog = document.getElementById('confirm-dialog');
        const backdrop = document.getElementById('confirm-backdrop');
        const modal = document.getElementById('confirm-modal');
        const titleEl = document.getElementById('confirm-title');
        const messageEl = document.getElementById('confirm-message');
        const formEl = document.getElementById('confirm-form');
        const methodEl = document.getElementById('confirm-method');
        const btnEl = document.getElementById('confirm-btn');
        const stripEl = document.getElementById('confirm-strip');
        const iconContainer = document.getElementById('confirm-icon-container');
        const iconEl = document.getElementById('confirm-icon');

        if (!dialog) return;

        // Set Texts
        titleEl.innerText = options.title || 'Konfirmasi';
        messageEl.innerText = options.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
        
        // Form setups
        if (options.formAction) {
            formEl.action = options.formAction;
            methodEl.value = options.formMethod || 'POST';
            btnEl.type = 'submit';
        } else {
            formEl.action = "javascript:void(0);";
            btnEl.type = 'button';
            btnEl.onclick = function() {
                if (options.onConfirm) options.onConfirm();
                closeConfirmDialog();
            };
        }

        // Set Types
        const type = options.type || 'info';
        btnEl.className = 'flex min-h-11 w-full items-center justify-center px-4 py-2 text-sm font-bold text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors shadow-sm sm:w-auto ' + (
            type === 'danger' ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' :
            type === 'warning' ? 'bg-orange-500 hover:bg-orange-600 focus:ring-orange-500' :
            type === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' :
            'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500'
        );
        btnEl.innerText = options.confirmText || 'Ya, Lanjutkan';

        stripEl.className = 'h-1.5 w-full ' + (
            type === 'danger' ? 'bg-red-500' :
            type === 'warning' ? 'bg-orange-500' :
            type === 'success' ? 'bg-green-500' :
            'bg-emerald-500'
        );

        iconContainer.className = 'flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center ' + (
            type === 'danger' ? 'bg-red-50 text-red-600 dark:bg-red-900/30' :
            type === 'warning' ? 'bg-orange-50 text-orange-600 dark:bg-orange-900/30' :
            type === 'success' ? 'bg-green-50 text-green-600 dark:bg-green-900/30' :
            'bg-emerald-50 text-emerald-600 dark:bg-blue-900/30'
        );

        if (type === 'danger') {
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>';
        } else if (type === 'success') {
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        } else {
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        }

        // Show Dialog
        window.wmsLastConfirmTrigger = document.activeElement;
        document.body.classList.add('wms-modal-open');
        dialog.classList.remove('hidden');
        dialog.classList.add('flex');
        dialog.focus({ preventScroll: true });
        
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            modal.classList.remove('scale-95', 'opacity-0');
            modal.classList.add('scale-100', 'opacity-100');
        });
    };

    window.closeConfirmDialog = function() {
        const dialog = document.getElementById('confirm-dialog');
        const backdrop = document.getElementById('confirm-backdrop');
        const modal = document.getElementById('confirm-modal');

        if (!dialog) return;

        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        modal.classList.remove('scale-100', 'opacity-100');
        modal.classList.add('scale-95', 'opacity-0');

        setTimeout(() => {
            dialog.classList.remove('flex');
            dialog.classList.add('hidden');
            document.body.classList.remove('wms-modal-open');
            if (window.wmsLastConfirmTrigger instanceof HTMLElement) {
                window.wmsLastConfirmTrigger.focus({ preventScroll: true });
            }
        }, 300);
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.getElementById('confirm-dialog')?.classList.contains('flex')) {
            closeConfirmDialog();
        }
    });

    // Form submission interceptor for standard delete buttons
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-confirm="true"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                showConfirmDialog({
                    title: this.dataset.confirmTitle || 'Hapus Data',
                    message: this.dataset.confirmMessage || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.',
                    type: this.dataset.confirmType || 'danger',
                    confirmText: this.dataset.confirmText || 'Ya, Hapus',
                    onConfirm: () => this.submit()
                });
            });
        });
    });
</script>



