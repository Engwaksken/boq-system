<div class="boq-page-stack" x-data="{ tab: 'general' }">
    <x-ui.page-header
        :title="__('Site Settings')"
        icon="fa-gear"
        :subtitle="__('Configure global system settings and defaults.')"
    />

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <div class="boq-tabs" role="tablist" aria-label="{{ __('Settings sections') }}">
            @foreach([
                'general' => ['fa-sliders', __('General')],
                'branding' => ['fa-image', __('Branding')],
                'mobile' => ['fa-mobile-screen', __('Mobile App')],
                'currencies' => ['fa-coins', __('Currencies')],
                'languages' => ['fa-language', __('Languages')],
                'access' => ['fa-user-lock', __('Registration & Access')],
                'legal' => ['fa-scale-balanced', __('Legal')],
            ] as $key => [$icon, $label])
                <button
                    type="button"
                    role="tab"
                    class="boq-tab"
                    :class="tab === '{{ $key }}' ? 'is-active' : ''"
                    :aria-selected="(tab === '{{ $key }}').toString()"
                    @click="tab = '{{ $key }}'"
                >
                    <i class="fas {{ $icon }}" aria-hidden="true"></i>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Currencies and languages are separate components with their own forms,
             so they must not sit inside the settings <form> (nested forms are invalid
             HTML and their Save buttons submitted the site settings instead). --}}
        <div class="boq-panel-body" x-show="tab === 'currencies'" x-cloak>
            <livewire:admin.currencies-manager />
        </div>

        <div class="boq-panel-body" x-show="tab === 'languages'" x-cloak>
            <livewire:admin.languages-manager />
        </div>

        <form wire:submit="save" x-show="! ['currencies', 'languages'].includes(tab)">
        <div class="boq-panel-body">
            {{-- General --}}
            <div x-show="tab === 'general'" class="boq-form-grid">
                <div>
                    <label for="system_name" class="boq-field-label">{{ __('System Name') }}</label>
                    <input id="system_name" type="text" wire:model="settings.system_name" class="boq-field" placeholder="{{ __('e.g. Civil Works AI BOQ Platform') }}">
                    @error('settings.system_name') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency" class="boq-field-label">{{ __('Default Currency') }}</label>
                    <x-currency-select id="currency" wire:model="settings.currency" :current="$settings['currency'] ?? null" />
                    <p class="boq-field-help">{{ __('Add or deactivate currencies on the Currencies tab.') }}</p>
                    @error('settings.currency') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="language" class="boq-field-label">{{ __('Default Language') }}</label>
                    <select id="language" wire:model="settings.language" class="boq-field">
                        @foreach($languageOptions as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <p class="boq-field-help">{{ __('Add or deactivate languages on the Languages tab.') }}</p>
                    @error('settings.language') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="country" class="boq-field-label">{{ __('Default Country') }}</label>
                    <select id="country" wire:model="settings.country" class="boq-field">
                        <option value="">{{ __('Not set (international)') }}</option>
                        @foreach(\App\Models\Country::options() as $iso => $name)
                            <option value="{{ $iso }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="boq-field-help">{{ __('Used for AI price research and as the default for new projects.') }}</p>
                    @error('settings.country') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="market_location" class="boq-field-label">{{ __('Default Market Location') }}</label>
                    <input id="market_location" type="text" wire:model="settings.market_location" class="boq-field" placeholder="{{ __('e.g. Nairobi, Lagos, Dubai or London') }}">
                    <p class="boq-field-help">{{ __('City or market used when fetching daily prices.') }}</p>
                    @error('settings.market_location') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="timezone" class="boq-field-label">{{ __('System Timezone') }}</label>
                    <x-timezone-select id="timezone" wire:model="settings.timezone" />
                    <p class="boq-field-help">{{ __('Scheduled jobs such as the daily price fetch run in this timezone.') }}</p>
                    @error('settings.timezone') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="trial_duration" class="boq-field-label">{{ __('Trial Duration (Days)') }}</label>
                    <input id="trial_duration" type="number" wire:model="settings.trial_duration" class="boq-field" placeholder="14">
                    @error('settings.trial_duration') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <label class="boq-check boq-form-span-2">
                    <input type="checkbox" wire:model="settings.maintenance_mode">
                    <span>{{ __('Enable maintenance mode') }}</span>
                </label>
            </div>

            {{-- Branding --}}
            <div x-show="tab === 'branding'" x-cloak class="boq-form-grid">
                <div>
                    <span class="boq-field-label">{{ __('Logo') }}</span>
                    <div class="boq-upload-row">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ __('Logo') }}" class="boq-upload-preview">
                        @else
                            <span class="boq-upload-preview is-empty">{{ __('No logo') }}</span>
                        @endif

                        <div class="flex flex-col gap-2">
                            <input id="site-logo-file" type="file" wire:model="logoFile" accept="image/png,image/jpeg,image/webp" class="peer sr-only">
                            <label for="site-logo-file" class="boq-btn-secondary cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                                <i class="fas fa-upload" aria-hidden="true"></i>
                                {{ __('Choose logo') }}
                            </label>
                            @if($logoUrl)
                                <button type="button" wire:click="removeLogo" class="boq-btn-secondary text-red-600">
                                    <i class="fas fa-trash"></i>
                                    {{ __('Remove') }}
                                </button>
                            @endif
                        </div>
                    </div>
                    <p wire:loading wire:target="logoFile" class="boq-field-help">{{ __('Uploading logo...') }}</p>
                    @error('logoFile') <p class="boq-field-error">{{ $message }}</p> @enderror
                    <p class="boq-field-help">{{ __('Shown in the sidebar and on the sign-in pages. Max 2MB: PNG, JPG, WEBP.') }}</p>
                </div>

                <div>
                    <span class="boq-field-label">{{ __('Favicon') }}</span>
                    <div class="boq-upload-row">
                        @if($faviconUrl)
                            <img src="{{ $faviconUrl }}" alt="{{ __('Favicon') }}" class="boq-upload-preview is-small">
                        @else
                            <span class="boq-upload-preview is-small is-empty">{{ __('No icon') }}</span>
                        @endif

                        <div class="flex flex-col gap-2">
                            <input id="site-favicon-file" type="file" wire:model="faviconFile" accept="image/png,image/jpeg,image/webp,image/x-icon" class="peer sr-only">
                            <label for="site-favicon-file" class="boq-btn-secondary cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                                <i class="fas fa-upload" aria-hidden="true"></i>
                                {{ __('Choose favicon') }}
                            </label>
                            @if($faviconUrl)
                                <button type="button" wire:click="removeFavicon" class="boq-btn-secondary text-red-600">
                                    <i class="fas fa-trash"></i>
                                    {{ __('Remove') }}
                                </button>
                            @endif
                        </div>
                    </div>
                    <p wire:loading wire:target="faviconFile" class="boq-field-help">{{ __('Uploading favicon...') }}</p>
                    @error('faviconFile') <p class="boq-field-error">{{ $message }}</p> @enderror
                    <p class="boq-field-help">{{ __('Max 1MB: PNG, JPG, WEBP, ICO.') }}</p>
                </div>
            </div>

            {{-- Mobile app --}}
            <div x-show="tab === 'mobile'" x-cloak>
                <p class="mb-4 text-sm text-slate-500">{{ __('Controls the splash screen and login screen shown in the Flutter app.') }}</p>

                <div class="boq-form-grid">
                    <label class="boq-check boq-form-span-2">
                        <input type="checkbox" wire:model="settings.splash_enabled">
                        <span>{{ __('Show splash screen on launch') }}</span>
                    </label>

                    <div>
                        <label for="splash_duration" class="boq-field-label">{{ __('Splash Duration (milliseconds)') }}</label>
                        <input id="splash_duration" type="number" wire:model="settings.splash_duration" class="boq-field" placeholder="2000">
                        @error('settings.splash_duration') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="splash_message" class="boq-field-label">{{ __('Splash Message') }}</label>
                        <input id="splash_message" type="text" wire:model="settings.splash_message" class="boq-field" placeholder="{{ __('e.g. Civil Works Solutions') }}">
                        @error('settings.splash_message') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="login_title" class="boq-field-label">{{ __('Login Title') }}</label>
                        <input id="login_title" type="text" wire:model="settings.login_title" class="boq-field" placeholder="{{ __('Optional - overrides the app title') }}">
                        @error('settings.login_title') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="login_subtitle" class="boq-field-label">{{ __('Login Subtitle') }}</label>
                        <input id="login_subtitle" type="text" wire:model="settings.login_subtitle" class="boq-field" placeholder="{{ __('Optional - shown under the login title') }}">
                        @error('settings.login_subtitle') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Access --}}
            <div x-show="tab === 'access'" x-cloak>
                <p class="mb-4 text-sm text-slate-500">{{ __('Which options appear on the login screens.') }}</p>

                <div class="boq-form-grid">
                    <label class="boq-check">
                        <input type="checkbox" wire:model="settings.allow_registration">
                        <span>{{ __('Allow account sign up') }}</span>
                    </label>

                    <label class="boq-check">
                        <input type="checkbox" wire:model="settings.allow_forgot_password">
                        <span>{{ __('Allow forgot password') }}</span>
                    </label>
                </div>
            </div>

            {{-- Legal --}}
            <div x-show="tab === 'legal'" x-cloak>
                <x-ui.alert type="info" class="mb-4">
                    {{ __('Linked from the registration page and mobile login screen. Leave blank to hide a link. If the value is a URL, that address opens. Otherwise it is shown as a page, and basic HTML is allowed (headings, paragraphs, lists, bold/italic, links, tables). Scripts and unsafe markup are removed automatically.') }}
                </x-ui.alert>

                <div class="space-y-4">
                    <div>
                        <label for="privacy_policy" class="boq-field-label">{{ __('Privacy Policy') }}</label>
                        <textarea id="privacy_policy" wire:model="settings.privacy_policy" rows="10" class="boq-field boq-textarea font-mono text-xs" placeholder="&lt;h2&gt;Privacy Policy&lt;/h2&gt;&lt;p&gt;...&lt;/p&gt; or a full URL"></textarea>
                        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="boq-field-help inline-block"><i class="fas fa-arrow-up-right-from-square"></i> {{ __('Preview saved page') }}</a>
                        @error('settings.privacy_policy') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="terms_of_use" class="boq-field-label">{{ __('Terms of Use') }}</label>
                        <textarea id="terms_of_use" wire:model="settings.terms_of_use" rows="10" class="boq-field boq-textarea font-mono text-xs" placeholder="&lt;h2&gt;Terms of Use&lt;/h2&gt;&lt;p&gt;...&lt;/p&gt; or a full URL"></textarea>
                        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="boq-field-help inline-block"><i class="fas fa-arrow-up-right-from-square"></i> {{ __('Preview saved page') }}</a>
                        @error('settings.terms_of_use') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="boq-panel-footer">
            @if($errors->any())
                <p class="boq-field-error mr-auto">
                    <i class="fas fa-circle-exclamation"></i>
                    {{ __('Some fields need attention. Check each tab.') }}
                </p>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="boq-btn-primary">
                <i wire:loading.remove wire:target="save" class="fas fa-floppy-disk"></i>
                <i wire:loading wire:target="save" class="fas fa-spinner fa-spin"></i>
                {{ __('Save Settings') }}
            </button>
        </div>
        </form>
    </div>
</div>
