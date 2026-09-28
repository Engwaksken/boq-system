@php
    $me = Auth::user();
    $languages = \App\Models\Language::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['code', 'name', 'native_name']);
@endphp

<div class="grid gap-5 lg:grid-cols-3">
    <form wire:submit.prevent="updateProfile" class="boq-card lg:col-span-2">
        <div class="boq-card-header">
            <div>
                <h3 class="boq-card-title"><i class="fas fa-id-card" aria-hidden="true"></i> {{ __('Account Information') }}</h3>
                <p class="boq-card-subtitle">{{ __('Your name, contact details and profile picture.') }}</p>
            </div>
        </div>

        <div class="boq-card-body">
            <div class="boq-form-grid">
                <x-ui.field :label="__('Name')" for="profile-name" error="form.name" required>
                    <input id="profile-name" placeholder="{{ __('Enter name') }}" type="text" wire:model="form.name" class="boq-field @error('form.name') has-error @enderror" required autocomplete="name">
                </x-ui.field>

                <x-ui.field :label="__('Email')" for="profile-email" error="form.email" required>
                    <input id="profile-email" placeholder="{{ __('e.g. name@example.com') }}" type="email" wire:model="form.email" class="boq-field @error('form.email') has-error @enderror" required autocomplete="email">
                </x-ui.field>

                <x-ui.field :label="__('Phone')" for="profile-phone" error="form.phone">
                    <input id="profile-phone" placeholder="+1 202 555 0143" type="tel" wire:model="form.phone" class="boq-field @error('form.phone') has-error @enderror" autocomplete="tel">
                </x-ui.field>

                <x-ui.field :label="__('Language')" for="profile-locale" error="form.locale" required>
                    <select id="profile-locale" wire:model="form.locale" class="boq-field @error('form.locale') has-error @enderror" required>
                        @forelse($languages as $language)
                            <option value="{{ $language->code }}">{{ $language->name }}{{ $language->native_name && $language->native_name !== $language->name ? ' · '.$language->native_name : '' }}</option>
                        @empty
                            <option value="en">{{ __('English') }}</option>
                        @endforelse
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Timezone')" for="profile-timezone" error="form.timezone" required class="boq-form-span-2">
                    <x-timezone-select id="profile-timezone" wire:model="form.timezone" required />
                </x-ui.field>

                <x-ui.field :label="__('Avatar')" for="profile-avatar" error="form.avatar" :hint="__('JPG, PNG or WEBP up to 2 MB.')" class="boq-form-span-2">
                    <div class="boq-upload-row">
                        <span class="boq-avatar boq-avatar-xl">
                            @if(isset($form['avatar']) && $form['avatar'] && ! $errors->has('form.avatar') && method_exists($form['avatar'], 'isPreviewable') && $form['avatar']->isPreviewable())
                                <img src="{{ $form['avatar']->temporaryUrl() }}" alt="{{ __('Avatar') }}">
                            @elseif($me->avatar_url)
                                <img src="{{ $me->avatar_url }}" alt="{{ __('Avatar') }}">
                            @else
                                <i class="fas fa-user" aria-hidden="true"></i>
                            @endif
                        </span>
                        <input id="profile-avatar" type="file" wire:model="form.avatar" accept="image/*" class="max-w-sm">
                    </div>
                    <p wire:loading wire:target="form.avatar" class="boq-field-help"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Uploading...') }}</p>
                </x-ui.field>
            </div>
        </div>

        <div class="boq-card-footer">
            <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="updateProfile,form.avatar">
                <i wire:loading.remove wire:target="updateProfile" class="fas fa-floppy-disk" aria-hidden="true"></i>
                <i wire:loading wire:target="updateProfile" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                <span wire:loading.remove wire:target="updateProfile">{{ __('Save Changes') }}</span>
                <span wire:loading wire:target="updateProfile">{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>

    <x-ui.card class="self-start" :title="__('Account Status')" icon="fa-circle-info">
        <dl class="space-y-4 text-sm">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500">{{ __('Email Verified') }}</dt>
                <dd>
                    @if($me->hasVerifiedEmail())
                        <x-ui.badge color="success" icon="fa-circle-check">{{ __('Yes') }}</x-ui.badge>
                    @else
                        <x-ui.badge color="warning" icon="fa-triangle-exclamation">{{ __('No') }}</x-ui.badge>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-slate-500">{{ __('Member Since') }}</dt>
                <dd class="font-medium text-slate-900"><x-date :value="$me->created_at" /></dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-slate-500">{{ __('Last Login') }}</dt>
                <dd class="font-medium text-slate-900">
                    @if($me->last_login_at)
                        <x-date :value="$me->last_login_at" time />
                    @else
                        {{ __('Never') }}
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-slate-500">{{ __('Organisation') }}</dt>
                <dd class="text-right font-medium text-slate-900">{{ $me->organisation?->name ?? __('Personal') }}</dd>
            </div>
        </dl>
    </x-ui.card>
</div>
