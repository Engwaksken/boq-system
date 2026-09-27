<div class="max-w-2xl space-y-6">
    <div class="boq-panel boq-panel-body">
        <h3 class="boq-section-title mb-4">
            <i class="fas fa-key"></i>
            Change Password
        </h3>

        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <label for="current_password" class="boq-field-label">Current Password</label>
                <x-password-input placeholder="••••••••" id="current_password" wire:model="passwordForm.current_password" required autocomplete="current-password" />
                @error('passwordForm.current_password') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="new_password" class="boq-field-label">New Password</label>
                <x-password-input placeholder="••••••••" id="new_password" wire:model="passwordForm.password" required autocomplete="new-password" minlength="8" />
                @error('passwordForm.password') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="new_password_confirmation" class="boq-field-label">Confirm New Password</label>
                <x-password-input placeholder="••••••••" id="new_password_confirmation" wire:model="passwordForm.password_confirmation" required autocomplete="new-password" />
                @error('passwordForm.password_confirmation') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword" class="boq-btn-primary">
                <i wire:loading.remove wire:target="updatePassword" class="fas fa-floppy-disk"></i>
                <i wire:loading wire:target="updatePassword" class="fas fa-spinner fa-spin"></i>
                Update Password
            </button>
        </form>
    </div>

    <div
        class="boq-panel boq-panel-body"
        x-data="{
            busy: false,
            error: '',
            supported: false,
            async init() { this.supported = await window.boqBiometric?.available() ?? false },
            async enroll() {
                this.busy = true;
                this.error = '';
                try {
                    await window.boqBiometric.register(navigator.userAgentData?.platform || navigator.platform || 'This device');
                    $wire.$refresh();
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.busy = false;
                }
            },
        }"
    >
        <h3 class="boq-section-title mb-1">
            <i class="fas fa-fingerprint"></i>
            Biometric Sign-in
        </h3>
        <p class="mb-4 text-sm text-slate-500">
            Sign in with Windows Hello, Touch ID or your fingerprint instead of typing your password.
            Your fingerprint or face never leaves your device.
        </p>

        @if($biometricDevices->isNotEmpty())
            <ul class="mb-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach($biometricDevices as $device)
                    <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="passkey-{{ $device->id }}">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">
                                <i class="fas fa-laptop mr-1 text-slate-400"></i>
                                {{ $device->alias ?: 'Registered device' }}
                            </div>
                            <div class="text-xs text-slate-500">Added {{ \App\Support\Format::date($device->created_at, false) }}</div>
                        </div>
                        <button
                            type="button"
                            wire:click="removeBiometricDevice(@js($device->id))"
                            wire:confirm="Remove biometric sign-in for this device?"
                            class="boq-icon-btn boq-icon-danger"
                            title="Remove"
                            aria-label="Remove device"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <template x-if="supported">
            <button type="button" class="boq-btn-primary" :disabled="busy" @click="enroll()">
                <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-fingerprint'"></i>
                Enable on this device
            </button>
        </template>

        <template x-if="! supported">
            <p class="text-sm text-slate-500">
                <i class="fas fa-circle-info mr-1"></i>
                This device or browser has no biometric sign-in (Windows Hello, Touch ID or fingerprint) set up.
            </p>
        </template>

        <p x-show="error" x-text="error" class="boq-field-error mt-2"></p>
    </div>
</div>
