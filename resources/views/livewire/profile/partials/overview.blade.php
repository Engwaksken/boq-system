<div class="grid gap-6 sm:grid-cols-2">
    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">{{ __('Account Information') }}</h3>
        <form wire:submit.prevent="updateProfile" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Name') }}</label>
                <input placeholder="{{ __('Enter name') }}" type="text" wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                @error('form.name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Email') }}</label>
                <input placeholder="name@example.com" type="email" wire:model="form.email" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                @error('form.email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Phone') }}</label>
                <input placeholder="+1 202 555 0143" type="tel" wire:model="form.phone" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('form.phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Language') }}</label>
                    <select wire:model="form.locale" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="en">{{ __('English') }}</option>
                        <option value="fr">{{ __('French') }}</option>
                        <option value="sw">{{ __('Swahili') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Timezone') }}</label>
                    <x-timezone-select wire:model="form.timezone" required />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Avatar') }}</label>
                @if (auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ __('Avatar') }}" class="mb-2 h-16 w-16 rounded-full object-cover ring-1 ring-slate-200">
                @endif
                <input type="file" wire:model="form.avatar" accept="image/*" class="mt-1 w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('form.avatar') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                <svg wire:loading.class="animate-spin" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                <span wire:loading.remove>{{ __('Save Changes') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </form>
    </div>

    <div class="bg-slate-50 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">{{ __('Account Status') }}</h3>
        <dl class="space-y-4 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">{{ __('Email Verified') }}</dt>
                <dd class="font-medium {{ Auth::user()->hasVerifiedEmail() ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ Auth::user()->hasVerifiedEmail() ? 'Yes' : 'No' }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">{{ __('Member Since') }}</dt>
                <dd class="font-medium text-slate-900">{{ Auth::user()->created_at->format('F j, Y') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">{{ __('Last Login') }}</dt>
                <dd class="font-medium text-slate-900">{{ Auth::user()->last_login_at?->format('F j, Y H:i') ?? 'Never' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">{{ __('Organisation') }}</dt>
                <dd class="font-medium text-slate-900">{{ Auth::user()->organisation?->name ?? 'Personal' }}</dd>
            </div>
        </dl>
    </div>
</div>