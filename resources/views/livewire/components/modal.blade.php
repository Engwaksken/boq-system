
<div>
    @php
        $modalWidth = match ($size) {
            'sm' => 'max-w-md',
            'lg' => 'max-w-2xl',
            'xl' => 'max-w-4xl',
            default => 'max-w-xl',
        };
    @endphp

    @if($isOpen)
        <div
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"

            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"

            @keydown.escape.window="
                open = false;
                $wire.closeModal();
            "
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
                aria-hidden="true"
                @click="
                    open = false;
                    $wire.closeModal();
                "
            ></div>

            {{-- Modal positioning --}}
            <div class="relative flex min-h-full items-center justify-center p-4 sm:p-6">

                {{-- Modal panel --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"

                    class="relative w-full {{ $modalWidth }} overflow-hidden rounded-2xl bg-white shadow-2xl"
                    @click.stop
                >
                    {{-- Header --}}
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h3
                            id="modal-title"
                            class="text-lg font-semibold text-slate-900"
                        >
                            {{ $title }}
                        </h3>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            aria-label="Close modal"
                            @click="
                                open = false;
                                $wire.closeModal();
                            "
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="max-h-[75vh] overflow-y-auto p-6">

                        {{-- Error message --}}
                        @if(session('modal_error'))
                            <div
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition
                                x-init="setTimeout(() => show = false, 5000)"
                                class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                                role="alert"
                            >
                                <svg
                                    class="mt-0.5 h-5 w-5 flex-shrink-0"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <span>
                                    {{ session('modal_error') }}
                                </span>
                            </div>
                        @endif

                        {{-- Success message --}}
                        @if(session('modal_success'))
                            <div
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition
                                x-init="setTimeout(() => show = false, 5000)"
                                class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                                role="status"
                            >
                                <svg
                                    class="mt-0.5 h-5 w-5 flex-shrink-0"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <span>
                                    {{ session('modal_success') }}
                                </span>
                            </div>
                        @endif

                        {{-- Validation errors --}}
                        @if($errors->any())
                            <div
                                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                                role="alert"
                            >
                                <div class="mb-1 font-semibold">
                                    Please correct the following:
                                </div>

                                <ul class="list-inside list-disc space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Modal content --}}
                        {{ $slot ?? '' }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

