@props(['action', 'method' => 'POST', 'hasFiles' => false, 'title' => 'Form', 'description' => '', 'buttonText' => 'Simpan'])

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
        <h3 class="text-lg font-bold text-slate-800">{{ $title }}</h3>
        @if($description)
            <p class="text-sm text-slate-500 mt-1">{{ $description }}</p>
        @endif
    </div>

    <form action="{{ $action }}" method="{{ $method === 'GET' ? 'GET' : 'POST' }}" {!! $hasFiles ? 'enctype="multipart/form-data"' : '' !!} x-data="smartForm()" @submit="isSubmitting = true">
        @csrf
        @if(in_array($method, ['PUT', 'PATCH', 'DELETE']))
            @method($method)
        @endif

        <div class="p-4 sm:p-6 space-y-5">
            {{ $slot }}
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-end sm:gap-3 sm:p-6">
            <button type="button" onclick="history.back()" class="min-h-11 w-full px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm sm:w-auto" :disabled="isSubmitting">
                Batal
            </button>
            <button type="submit" class="flex min-h-11 w-full items-center justify-center gap-2 px-5 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm shadow-blue-600/20 sm:w-auto" :disabled="isSubmitting" :class="{'opacity-75 cursor-not-allowed': isSubmitting}">
                <svg x-show="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span x-text="isSubmitting ? 'Menyimpan...' : '{{ $buttonText }}'"></span>
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('smartForm', () => ({
            isSubmitting: false,
            // Smart Auto Fill logic can be expanded here
            autoFill(data) {
                for (const [key, value] of Object.entries(data)) {
                    const input = this.$el.querySelector(`[name="${key}"]`);
                    if (input) input.value = value;
                }
            }
        }))
    });
</script>
