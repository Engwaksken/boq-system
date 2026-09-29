import './bootstrap';
import './pwa';
import './autohide';
import Webpass from '@laragear/webpass';

/*
 * Password visibility toggle.
 * Any button with [data-password-toggle] shows/hides the password input in the
 * same wrapper. Delegated on document so it also works for fields Livewire
 * renders after page load.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');

    if (! button) {
        return;
    }

    const wrapper = button.closest('.auth-input-wrap, .boq-password-field') ?? button.parentElement;
    const input = wrapper?.querySelector('input[type="password"], input[data-password-visible]');

    if (! input) {
        return;
    }

    const show = input.type === 'password';

    input.type = show ? 'text' : 'password';
    input.toggleAttribute('data-password-visible', show);
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', show ? 'true' : 'false');

    const icon = button.querySelector('i');
    icon?.classList.toggle('fa-eye', ! show);
    icon?.classList.toggle('fa-eye-slash', show);
});

/*
 * Biometric (WebAuthn passkey) helpers: Windows Hello, Touch ID, fingerprint.
 * Webpass sends no CSRF token by default, so pass the page's token explicitly.
 */
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const webpass = () => Webpass.create({
    headers: { 'X-CSRF-TOKEN': csrfToken() },
    credentials: 'same-origin',
});

const biometric = {
    /** True only when this device has a built-in biometric/PIN authenticator. */
    async available() {
        if (Webpass.isUnsupported() || ! window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable) {
            return false;
        }

        try {
            return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        } catch {
            return false;
        }
    },

    async login() {
        const { success, user, error } = await webpass().assert('/webauthn/login/options', '/webauthn/login');

        if (! success) {
            throw new Error(error?.data?.message ?? 'Biometric sign-in was cancelled or failed.');
        }

        window.location.assign(user?.redirect ?? '/dashboard');
    },

    async register(alias) {
        const { success, error } = await webpass().attest(
            { path: '/webauthn/register/options' },
            { path: '/webauthn/register', body: { alias } },
        );

        if (! success) {
            throw new Error(error?.data?.message ?? 'This device could not be registered.');
        }
    },
};

window.boqBiometric = biometric;

/* Reveal elements marked [data-biometric-only] when the device supports biometrics. */
async function revealBiometricOptions() {
    const elements = document.querySelectorAll('[data-biometric-only]');

    if (elements.length === 0 || ! (await biometric.available())) {
        return;
    }

    elements.forEach((element) => element.classList.remove('hidden'));
}

document.addEventListener('DOMContentLoaded', revealBiometricOptions);
document.addEventListener('livewire:navigated', revealBiometricOptions);

/* Login page: [data-biometric-login] button with an optional [data-biometric-error] message slot. */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-biometric-login]');

    if (! button) {
        return;
    }

    const errorSlot = document.querySelector('[data-biometric-error]');
    const icon = button.querySelector('i');

    button.disabled = true;
    icon?.classList.replace('fa-fingerprint', 'fa-spinner');
    icon?.classList.add('fa-spin');
    errorSlot?.classList.add('hidden');

    try {
        await biometric.login();
    } catch (error) {
        if (errorSlot) {
            errorSlot.textContent = error.message;
            errorSlot.classList.remove('hidden');
        }

        button.disabled = false;
        icon?.classList.remove('fa-spin');
        icon?.classList.replace('fa-spinner', 'fa-fingerprint');
    }
});
