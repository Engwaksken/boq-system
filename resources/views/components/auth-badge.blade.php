@props(['icon'])

@php
    $logo = \App\Models\SiteSetting::get('logo', '');
    $siteName = \App\Models\SiteSetting::get('system_name', 'BOQ System') ?: 'BOQ System';
@endphp

@if($logo)
    <img
        src="{{ asset('storage/'.$logo) }}"
        alt="{{ $siteName }}"
        class="auth-logo"
    >
@else
    <div class="auth-badge">
        <i class="fas {{ $icon }} text-lg"></i>
    </div>
@endif
