@props(['id', 'title' => '', 'maxWidth' => 'md', 'show' => false])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-md',
    };
@endphp

<div id="{{ $id }}" class="fixed inset-0 z-50 overflow-y-auto {{ $show ? 'flex' : 'hidden' }} items-end sm:items-center justify-center p-3 sm:p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0 modal-backdrop" aria-hidden="true" onclick="closeModal('{{ $id }}')"></div>

    <!-- Modal Panel -->
    <div class="relative bg-white rounded-t-2xl sm:rounded-2xl shadow-xl border border-slate-200 transform transition-all opacity-0 scale-95 modal-panel w-full {{ $maxWidthClass }} mx-auto flex flex-col max-h-[calc(100dvh-1.5rem)] sm:max-h-[90vh]">
        
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4 border-b border-slate-100 shrink-0">
            <h3 class="text-base sm:text-lg font-extrabold text-slate-800 break-words" id="modal-title">
                {{ $title }}
            </h3>
            <button type="button" class="text-slate-400 bg-transparent hover:bg-slate-100 hover:text-slate-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center transition-colors" onclick="closeModal('{{ $id }}')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span class="sr-only">Tutup modal</span>
            </button>
        </div>

        <!-- Body -->
        <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar">
            {{ $slot }}
        </div>

        <!-- Footer -->
        @if(isset($footer))
        <div class="px-4 py-3 sm:px-6 sm:py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3 shrink-0">
            {{ $footer }}
        </div>
        @endif
    </div>
</div>

<script>
    if (typeof window.openModal !== 'function') {
        window.openModal = function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Trigger reflow
            void modal.offsetWidth;
            
            const backdrop = modal.querySelector('.modal-backdrop');
            const panel = modal.querySelector('.modal-panel');
            
            if (backdrop) {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
            }
            if (panel) {
                panel.classList.remove('opacity-0', 'scale-95');
                panel.classList.add('opacity-100', 'scale-100');
            }
            
            document.body.style.overflow = 'hidden';
        };

        window.closeModal = function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            
            const backdrop = modal.querySelector('.modal-backdrop');
            const panel = modal.querySelector('.modal-panel');
            
            if (backdrop) {
                backdrop.classList.remove('opacity-100');
                backdrop.classList.add('opacity-0');
            }
            if (panel) {
                panel.classList.remove('opacity-100', 'scale-100');
                panel.classList.add('opacity-0', 'scale-95');
            }
            
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }, 200);
        };
    }
    
    // Check initial state
    @if($show)
        document.addEventListener('DOMContentLoaded', () => {
            openModal('{{ $id }}');
        });
    @endif
</script>



