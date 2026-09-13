<div class="min-h-screen bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">Profile</h1>
            <p class="mt-2 text-slate-600">Manage your account settings and preferences.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 overflow-x-auto">
                <nav class="flex gap-1 px-4" aria-label="Profile tabs">
                    @foreach($tabs as $key => $label)
                        <button wire:click="setActiveTab('{{ $key }}')" class="relative px-4 py-3 text-sm font-medium transition-colors {{ $activeTab === $key ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-700' }} whitespace-nowrap">
                            {{ $label }}
                            @if($activeTab === $key)
                                <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-600"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                @if($activeTab === 'overview')
                    @include('livewire.profile.partials.overview')
                @elseif($activeTab === 'security')
                    @include('livewire.profile.partials.security')
                @elseif($activeTab === 'preferences')
                    @include('livewire.profile.partials.preferences')
                @elseif($activeTab === 'notifications')
                    @include('livewire.profile.partials.notifications')
                @elseif($activeTab === 'hardware-bookmarks')
                    @include('livewire.profile.partials.hardware-bookmarks')
                @endif
            </div>
        </div>
    </div>

    @livewire('components.modal')
</div>