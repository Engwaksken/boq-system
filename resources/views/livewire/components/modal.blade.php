@if($isOpen)
<div x-data="{ open: true }" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="closeModal()" @closeModal.window="open = false">
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeModal()"></div>

        <div class="relative w-full max-w-{{ $size === 'sm' ? 'md' : ($size === 'lg' ? '2xl' : ($size === 'xl' ? '4xl' : 'xl')) }} bg-white rounded-2xl shadow-xl" @click.outside="closeModal()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="p-6">
                @if (session('modal_error'))
                    <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 flex items-center gap-3 text-sm text-red-700">
                        <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                        <span>{{ session('modal_error') }}</span>
                    </div>
                @endif

                @if (session('modal_success'))
                    <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 flex items-center gap-3 text-sm text-emerald-700">
                        <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        <span>{{ session('modal_success') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</div>

<script>
    function closeModal() {
        @this.call('closeModal');
    }
    document.addEventListener('modalClosed', () => {
        window.Alpine.store('modal', { open: false });
    });
</script>
@endif