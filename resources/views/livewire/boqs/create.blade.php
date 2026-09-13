<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">New BOQ</h1>
            <p class="mt-1 text-sm text-gray-500">
                Upload a Bill of Quantities file
            </p>
        </div>

        <a
            href="{{ url('/boqs') }}"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition"
        >
            <svg
                class="w-4 h-4 mr-2"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                />
            </svg>

            Back
        </a>
    </div>

    @if (session()->has('status'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
        >
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit.prevent="save">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Project --}}
                <div>
                    <label
                        for="projectId"
                        class="block text-sm font-medium text-gray-700 mb-1"
                    >
                        Project
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="projectId"
                        wire:model="projectId"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                        <option value="">Select a project</option>

                        @if(isset($projects) && $projects->isNotEmpty())
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">
                                    {{ $project->name }}
                                    @if(!empty($project->code))
                                        ({{ $project->code }})
                                    @endif
                                </option>
                            @endforeach
                        @endif
                    </select>

                    @error('projectId')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- BOQ Name --}}
                <div>
                    <label
                        for="name"
                        class="block text-sm font-medium text-gray-700 mb-1"
                    >
                        BOQ Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        wire:model="name"
                        placeholder="Leave blank to use the file name"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >

                    @error('name')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- File Upload --}}
                <div class="md:col-span-2">
                    <label
                        for="file"
                        class="block text-sm font-medium text-gray-700 mb-1"
                    >
                        BOQ File
                        <span class="text-red-500">*</span>
                    </label>

                    <div class="rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-5">

                        <input
                            type="file"
                            id="file"
                            wire:model.live="file"
                            accept=".xlsx,.csv,.pdf,.jpg,.jpeg,.png"
                            class="block w-full text-sm text-gray-700
                                   file:mr-4
                                   file:py-2
                                   file:px-4
                                   file:rounded-lg
                                   file:border-0
                                   file:text-sm
                                   file:font-semibold
                                   file:bg-indigo-50
                                   file:text-indigo-700
                                   hover:file:bg-indigo-100"
                        >

                        <p class="mt-2 text-xs text-gray-500">
                            Accepted formats:
                            Excel (.xlsx), CSV, PDF, JPG, JPEG, PNG.
                            Maximum file size: 20MB.
                        </p>

                        {{-- Upload Progress --}}
                        <div
                            wire:loading
                            wire:target="file"
                            class="mt-3 rounded-lg bg-indigo-50 px-3 py-2 text-sm text-indigo-700"
                        >
                            Uploading file...
                        </div>

                        {{-- Selected File --}}
                        @if ($file)
                            <div class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-green-800">
                                            File selected
                                        </p>

                                        <p class="text-xs text-green-700 break-all">
                                            {{ $file->getClientOriginalName() }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="$set('file', null)"
                                        class="text-xs font-semibold text-red-600 hover:text-red-700"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        @endif

                        @error('file')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-gray-200">

                <a
                    href="{{ url('/boqs') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="file,save"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:bg-indigo-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition"
                >
                    <span wire:loading.remove wire:target="save">
                        Upload BOQ
                    </span>

                    <span
                        wire:loading
                        wire:target="save"
                        class="inline-flex items-center"
                    >
                        <svg
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                            ></path>
                        </svg>

                        Processing...
                    </span>
                </button>
            </div>
        </div>
    </form>
</div>
