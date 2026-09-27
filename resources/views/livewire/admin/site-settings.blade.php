<div class="boq-page-stack" x-data="{ tab: 'general' }">
    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-gear"></i>
                Site Settings
            </h1>
            <p class="boq-page-subtitle">Configure global system settings and defaults.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="boq-flash">
            <i class="fas fa-circle-check"></i>
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="boq-panel overflow-hidden">
        <div class="boq-tabs" role="tablist" aria-label="Settings sections">
            @foreach([
                'general' => ['fa-sliders', 'General'],
                'branding' => ['fa-image', 'Branding'],
                'mobile' => ['fa-mobile-screen', 'Mobile App'],
                'currencies' => ['fa-coins', 'Currencies'],
                'languages' => ['fa-language', 'Languages'],
                'access' => ['fa-user-lock', 'Registration & Access'],
                'legal' => ['fa-scale-balanced', 'Legal'],
            ] as $key => [$icon, $label])
                <button
                    type="button"
                    role="tab"
                    class="boq-tab"
                    :class="tab === '{{ $key }}' ? 'is-active' : ''"
                    :aria-selected="(tab === '{{ $key }}').toString()"
                    @click="tab = '{{ $key }}'"
                >
                    <i class="fas {{ $icon }}"></i>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="boq-panel-body">
            {{-- General --}}
            <div x-show="tab === 'general'" class="boq-form-grid">
                <div>
                    <label for="system_name" class="boq-field-label">System Name</label>
                    <input id="system_name" type="text" wire:model="settings.system_name" class="boq-field" placeholder="e.g. Civil Works AI BOQ Platform">
                    @error('settings.system_name') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency" class="boq-field-label">Default Currency</label>
                    <x-currency-select id="currency" wire:model="settings.currency" :current="$settings['currency'] ?? null" />
                    <p class="boq-field-help">Add or deactivate currencies on the Currencies tab.</p>
                    @error('settings.currency') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="language" class="boq-field-label">Default Language</label>
                    <select id="language" wire:model="settings.language" class="boq-field">
                        @foreach($languageOptions as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <p class="boq-field-help">Add or deactivate languages on the Languages tab.</p>
                    @error('settings.language') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="country" class="boq-field-label">Default Country</label>
                    <select id="country" wire:model="settings.country" class="boq-field">
                        <option value="">Not set (international)</option>
                        @foreach(\App\Models\Country::options() as $iso => $name)
                            <option value="{{ $iso }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="boq-field-help">Used for AI price research and as the default for new projects.</p>
                    @error('settings.country') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="market_location" class="boq-field-label">Default Market Location</label>
                    <input id="market_location" type="text" wire:model="settings.market_location" class="boq-field" placeholder="e.g. Nairobi, Lagos, Dubai or London">
                    <p class="boq-field-help">City or market used when fetching daily prices.</p>
                    @error('settings.market_location') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="timezone" class="boq-field-label">System Timezone</label>
                    <x-timezone-select id="timezone" wire:model="settings.timezone" />
                    <p class="boq-field-help">Scheduled jobs such as the daily price fetch run in this timezone.</p>
                    @error('settings.timezone') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="trial_duration" class="boq-field-label">Trial Duration (Days)</label>
                    <input id="trial_duration" type="number" wire:model="settings.trial_duration" class="boq-field" placeholder="14">
                    @error('settings.trial_duration') <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                <label class="boq-check boq-form-span-2">
                    <input type="checkbox" wire:model="settings.maintenance_mode">
                    <span>Enable maintenance mode</span>
                </label>
            </div>

            {{-- Branding --}}
            <div x-show="tab === 'branding'" x-cloak class="boq-form-grid">
                <div>
                    <span class="boq-field-label">Logo</span>
                    <div class="boq-upload-row">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="boq-upload-preview">
                        @else
                            <span class="boq-upload-preview is-empty">No logo</span>
                        @endif

                        <div class="flex flex-col gap-2">
                            <label class="boq-btn-secondary cursor-pointer">
                                <i class="fas fa-upload"></i>
                                Choose logo
                                <input type="file" wire:model="logoFile" accept="image/png,image/jpeg,image/webp" class="hidden">
                            </label>
                            @if($logoUrl)
                                <button type="button" wire:click="removeLogo" class="boq-btn-secondary text-red-600">
                                    <i class="fas fa-trash"></i>
                                    Remove
                                </button>
                            @endif
                        </div>
                    </div>
                    <p wire:loading wire:target="logoFile" class="boq-field-help">Uploading logo...</p>
                    @error('logoFile') <p class="boq-field-error">{{ $message }}</p> @enderror
                    <p class="boq-field-help">Shown in the sidebar and on the sign-in pages. Max 2MB: PNG, JPG, WEBP.</p>
                </div>

                <div>
                    <span class="boq-field-label">Favicon</span>
                    <div class="boq-upload-row">
                        @if($faviconUrl)
                            <img src="{{ $faviconUrl }}" alt="Favicon" class="boq-upload-preview is-small">
                        @else
                            <span class="boq-upload-preview is-small is-empty">No icon</span>
                        @endif

                        <div class="flex flex-col gap-2">
                            <label class="boq-btn-secondary cursor-pointer">
                                <i class="fas fa-upload"></i>
                                Choose favicon
                                <input type="file" wire:model="faviconFile" accept="image/png,image/jpeg,image/webp,image/x-icon" class="hidden">
                            </label>
                            @if($faviconUrl)
                                <button type="button" wire:click="removeFavicon" class="boq-btn-secondary text-red-600">
                                    <i class="fas fa-trash"></i>
                                    Remove
                                </button>
                            @endif
                        </div>
                    </div>
                    <p wire:loading wire:target="faviconFile" class="boq-field-help">Uploading favicon...</p>
                    @error('faviconFile') <p class="boq-field-error">{{ $message }}</p> @enderror
                    <p class="boq-field-help">Max 1MB: PNG, JPG, WEBP, ICO.</p>
                </div>
            </div>

            {{-- Mobile app --}}
            <div x-show="tab === 'mobile'" x-cloak>
                <p class="mb-4 text-sm text-slate-500">Controls the splash screen and login screen shown in the Flutter app.</p>

                <div class="boq-form-grid">
                    <label class="boq-check boq-form-span-2">
                        <input type="checkbox" wire:model="settings.splash_enabled">
                        <span>Show splash screen on launch</span>
                    </label>

                    <div>
                        <label for="splash_duration" class="boq-field-label">Splash Duration (milliseconds)</label>
                        <input id="splash_duration" type="number" wire:model="settings.splash_duration" class="boq-field" placeholder="2000">
                        @error('settings.splash_duration') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="splash_message" class="boq-field-label">Splash Message</label>
                        <input id="splash_message" type="text" wire:model="settings.splash_message" class="boq-field" placeholder="e.g. Civil Works Solutions">
                        @error('settings.splash_message') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="login_title" class="boq-field-label">Login Title</label>
                        <input id="login_title" type="text" wire:model="settings.login_title" class="boq-field" placeholder="Optional - overrides the app title">
                        @error('settings.login_title') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="login_subtitle" class="boq-field-label">Login Subtitle</label>
                        <input id="login_subtitle" type="text" wire:model="settings.login_subtitle" class="boq-field" placeholder="Optional - shown under the login title">
                        @error('settings.login_subtitle') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Currencies (own component, saves independently) --}}
            <div x-show="tab === 'currencies'" x-cloak>
                <livewire:admin.currencies-manager />
            </div>

            {{-- Languages (own component, saves independently) --}}
            <div x-show="tab === 'languages'" x-cloak>
                <livewire:admin.languages-manager />
            </div>

            {{-- Access --}}
            <div x-show="tab === 'access'" x-cloak>
                <p class="mb-4 text-sm text-slate-500">Which options appear on the login screens.</p>

                <div class="boq-form-grid">
                    <label class="boq-check">
                        <input type="checkbox" wire:model="settings.allow_registration">
                        <span>Allow account sign up</span>
                    </label>

                    <label class="boq-check">
                        <input type="checkbox" wire:model="settings.allow_forgot_password">
                        <span>Allow forgot password</span>
                    </label>
                </div>
            </div>

            {{-- Legal --}}
            <div x-show="tab === 'legal'" x-cloak>
                <p class="mb-4 text-sm text-slate-500">
                    Linked from the registration page and mobile login screen. Leave blank to hide a link.
                    If the value is a URL, that address opens. Otherwise it is shown as a page, and basic HTML is allowed
                    (headings, paragraphs, lists, bold/italic, links, tables). Scripts and unsafe markup are removed automatically.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="privacy_policy" class="boq-field-label">Privacy Policy</label>
                        <textarea id="privacy_policy" wire:model="settings.privacy_policy" rows="10" class="boq-field boq-textarea font-mono text-xs" placeholder="&lt;h2&gt;Privacy Policy&lt;/h2&gt;&lt;p&gt;...&lt;/p&gt; or a full URL"></textarea>
                        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="boq-field-help inline-block"><i class="fas fa-arrow-up-right-from-square"></i> Preview saved page</a>
                        @error('settings.privacy_policy') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="terms_of_use" class="boq-field-label">Terms of Use</label>
                        <textarea id="terms_of_use" wire:model="settings.terms_of_use" rows="10" class="boq-field boq-textarea font-mono text-xs" placeholder="&lt;h2&gt;Terms of Use&lt;/h2&gt;&lt;p&gt;...&lt;/p&gt; or a full URL"></textarea>
                        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="boq-field-help inline-block"><i class="fas fa-arrow-up-right-from-square"></i> Preview saved page</a>
                        @error('settings.terms_of_use') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="boq-panel-footer">
            @if($errors->any())
                <p class="boq-field-error mr-auto">
                    <i class="fas fa-circle-exclamation"></i>
                    Some fields need attention. Check each tab.
                </p>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="boq-btn-primary">
                <i wire:loading.remove wire:target="save" class="fas fa-floppy-disk"></i>
                <i wire:loading wire:target="save" class="fas fa-spinner fa-spin"></i>
                Save Settings
            </button>
        </div>
    </form>
</div>
