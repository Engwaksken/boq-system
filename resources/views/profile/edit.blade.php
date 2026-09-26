<x-app-layout>

    <div class="boq-page-stack">

        <div class="boq-page-header">

            <div>

                <h1 class="boq-page-title">
                    <i class="fas fa-user-gear"></i>
                    My Profile
                </h1>

                <p class="boq-page-subtitle">
                    Manage your personal information, account security and preferences.
                </p>

            </div>

            <a
                href="{{ url('/dashboard') }}"
                class="boq-btn-secondary"
            >
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>

        </div>

        <div class="boq-stats-grid">

            <div class="boq-stat-card boq-stat-green">

                <div>
                    <p class="boq-stat-label">
                        Account
                    </p>

                    <p class="boq-stat-value">
                        {{ auth()->user()->name }}
                    </p>
                </div>

                <span class="boq-stat-icon">
                    <i class="fas fa-user"></i>
                </span>

            </div>

            <div class="boq-stat-card boq-stat-blue">

                <div>
                    <p class="boq-stat-label">
                        Email Status
                    </p>

                    <p class="boq-stat-value" style="font-size:1rem;">
                        {{
                            auth()->user()->email_verified_at
                                ? 'Verified'
                                : 'Not Verified'
                        }}
                    </p>
                </div>

                <span class="boq-stat-icon">
                    <i class="fas {{
                        auth()->user()->email_verified_at
                            ? 'fa-circle-check'
                            : 'fa-circle-exclamation'
                    }}"></i>
                </span>

            </div>

            <div class="boq-stat-card boq-stat-amber">

                <div>
                    <p class="boq-stat-label">
                        Language
                    </p>

                    <p class="boq-stat-value" style="font-size:1rem;">
                        {{
                            match(auth()->user()->locale ?? 'en') {
                                'sw' => 'Swahili',
                                'fr' => 'French',
                                default => 'English',
                            }
                        }}
                    </p>
                </div>

                <span class="boq-stat-icon">
                    <i class="fas fa-language"></i>
                </span>

            </div>

            <div class="boq-stat-card boq-stat-purple">

                <div>
                    <p class="boq-stat-label">
                        Timezone
                    </p>

                    <p class="boq-stat-value" style="font-size:1rem;">
                        {{
                            str_replace(
                                '_',
                                ' ',
                                auth()->user()->timezone
                                    ?? 'Africa/Kampala'
                            )
                        }}
                    </p>
                </div>

                <span class="boq-stat-icon">
                    <i class="fas fa-clock"></i>
                </span>

            </div>

        </div>

        <div
            x-data="{
                tab: 'profile'
            }"
            class="boq-panel"
        >

            <div class="boq-profile-tabs">

                <button
                    type="button"
                    @click="tab = 'profile'"
                    :class="tab === 'profile' ? 'is-active' : ''"
                    class="boq-profile-tab"
                >
                    <i class="fas fa-user"></i>
                    Profile Information
                </button>

                <button
                    type="button"
                    @click="tab = 'security'"
                    :class="tab === 'security' ? 'is-active' : ''"
                    class="boq-profile-tab"
                >
                    <i class="fas fa-lock"></i>
                    Password & Security
                </button>

                <button
                    type="button"
                    @click="tab = 'account'"
                    :class="tab === 'account' ? 'is-active' : ''"
                    class="boq-profile-tab"
                >
                    <i class="fas fa-user-xmark"></i>
                    Account
                </button>

            </div>

            <div class="boq-profile-content">

                <div
                    x-show="tab === 'profile'"
                    x-cloak
                >
                    @include(
                        'profile.partials.update-profile-information-form'
                    )
                </div>

                <div
                    x-show="tab === 'security'"
                    x-cloak
                >
                    @include(
                        'profile.partials.update-password-form'
                    )
                </div>

                <div
                    x-show="tab === 'account'"
                    x-cloak
                >
                    @include(
                        'profile.partials.delete-user-form'
                    )
                </div>

            </div>

        </div>

    </div>

</x-app-layout>