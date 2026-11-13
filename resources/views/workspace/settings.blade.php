@extends('layouts.workspace')
@php($title = 'Workspace settings')
@php($pageTitle = 'Workspace settings')

@section('workspace-content')
    @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="module-heading-row"><div class="module-title-wrap"><span class="module-icon">⚙</span><h1>Workspace settings</h1></div></div>
    <form class="panel settings-panel" method="POST" action="{{ route('organization.settings.update') }}">
        @csrf
        @method('PUT')
        <div class="panel-heading">
            <div>
                <span class="eyebrow">ORGANIZATION PROFILE</span>
                <h3>Workspace identity</h3>
            </div>
            <span class="workspace-icon">{{ strtoupper(substr($organization->name, 0, 1)) }}</span>
        </div>
        <div class="settings-fields">
            <label class="dialog-field"><span>Organization name <b>*</b></span><input name="name" required maxlength="160" value="{{ old('name', $organization->name) }}"></label>
            <label class="dialog-field"><span>Contact email</span><input name="email" type="email" value="{{ old('email', $organization->email) }}"></label>
            <label class="dialog-field"><span>Phone number</span><input name="phone" type="tel" maxlength="32" value="{{ old('phone', $organization->phone) }}"></label>
            <label class="dialog-field"><span>Time zone <b>*</b></span><select name="timezone" required>@foreach(timezone_identifiers_list() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $organization->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></label>
        </div>
        <fieldset class="palette-picker">
            <legend>Workspace color palette</legend>
            <div class="palette-grid">
                @foreach($colorPalettes as $key => $palette)
                    <label class="palette-option">
                        <input type="radio" name="color_palette" value="{{ $key }}" @checked(old('color_palette', $organization->color_palette ?? 'utility-green') === $key)>
                        <span class="palette-card" style="--swatch-primary: {{ $palette['primary'] }}; --swatch-soft: {{ $palette['soft'] }}; --swatch-strong: {{ $palette['strong'] }}">
                            <span class="palette-swatches" aria-hidden="true"><i></i><i></i><i></i></span>
                            <strong>{{ $palette['name'] }}</strong>
                            <small>{{ $palette['category'] }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('color_palette')<span class="field-error" role="alert">{{ $message }}</span>@enderror
        </fieldset>
        <fieldset class="palette-picker">
            <legend>Safaricom M-Pesa collection</legend>
            <p class="muted-row-action">Configure this organization's Daraja merchant credentials. Secrets are encrypted at rest; leave secret fields blank to keep the saved value. Callback URL and token are set by the system administrator.</p>
            <div class="settings-fields">
                <label class="dialog-field"><span>Environment</span><select name="daraja_environment"><option value="">Disabled</option><option value="sandbox" @selected(old('daraja_environment', $organization->daraja_environment) === 'sandbox')>Sandbox</option><option value="production" @selected(old('daraja_environment', $organization->daraja_environment) === 'production')>Production</option></select></label>
                <label class="dialog-field"><span>Transaction type</span><select name="daraja_transaction_type"><option value="CustomerPayBillOnline" @selected(old('daraja_transaction_type', $organization->daraja_transaction_type ?: 'CustomerPayBillOnline') === 'CustomerPayBillOnline')>PayBill</option><option value="CustomerBuyGoodsOnline" @selected(old('daraja_transaction_type', $organization->daraja_transaction_type) === 'CustomerBuyGoodsOnline')>Buy Goods / Till</option></select></label>
                <label class="dialog-field"><span>Consumer key {{ $organization->daraja_consumer_key ? '(saved)' : '' }}</span><input name="daraja_consumer_key" autocomplete="new-password" value="" maxlength="2048" placeholder="{{ $organization->daraja_consumer_key ? 'Leave blank to keep saved key' : 'Daraja consumer key' }}"></label>
                <label class="dialog-field"><span>Consumer secret {{ $organization->daraja_consumer_secret ? '(saved)' : '' }}</span><input name="daraja_consumer_secret" type="password" autocomplete="new-password" maxlength="2048" placeholder="{{ $organization->daraja_consumer_secret ? 'Leave blank to keep saved secret' : 'Daraja consumer secret' }}"></label>
                <label class="dialog-field"><span>Business shortcode</span><input name="daraja_shortcode" value="{{ old('daraja_shortcode', $organization->daraja_shortcode) }}" maxlength="32" placeholder="PayBill or Till shortcode"></label>
                <label class="dialog-field"><span>Passkey {{ $organization->daraja_passkey ? '(saved)' : '' }}</span><input name="daraja_passkey" type="password" autocomplete="new-password" maxlength="2048" placeholder="{{ $organization->daraja_passkey ? 'Leave blank to keep saved passkey' : 'Daraja passkey' }}"></label>
            </div>
            @error('daraja_consumer_key')<span class="field-error" role="alert">{{ $message }}</span>@enderror
            <div class="settings-actions"><span>{{ $darajaConfigured ? 'M-Pesa checkout is enabled for this workspace.' : 'M-Pesa is disabled until merchant credentials and the system HTTPS callback are configured.' }}</span></div>
        </fieldset>
        <div class="settings-actions"><span>Times are presented using this organization's local timezone.</span><button class="button button-primary" type="submit">Save changes</button></div>
    </form>
    <article class="panel settings-panel security-panel"><div class="panel-heading"><div><h3>Workspace access</h3></div><span class="metric-icon metric-green">✓</span></div><div class="settings-security-row"><span><strong>Organization identifier</strong><small>{{ $organization->public_id }}</small></span><span class="status-pill status-active"><i></i>{{ ucfirst($organization->status) }}</span></div></article>
    <footer class="dashboard-footer"><span>© {{ now()->year }} UtilityFlow</span><span>{{ $organization->timezone }} workspace</span><a href="mailto:support@utilityflow.app">Help &amp; support</a></footer>
@endsection
