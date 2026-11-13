@extends('layouts.app')
@php($title = 'UtilityFlow water management')

@section('meta_title', 'UtilityFlow | Water readings and billing for property teams')
@section('meta_description', 'Capture water meter readings, calculate consumption, prepare utility bills and track collections across your properties.')
@section('robots', 'index, follow, max-image-preview:large')
@section('canonical', route('home'))
@section('og_title', 'UtilityFlow | Water operations for property teams')
@section('og_type', 'website')
@section('structured_data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'UtilityFlow',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'description' => 'Water meter reading, usage calculation, utility billing and collection tracking for property teams.',
        'url' => route('home'),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('body')
<main class="marketing-page">
    <header class="marketing-header">
        <a class="brand" href="{{ route('home') }}" aria-label="UtilityFlow home"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a>
        <nav aria-label="Main navigation"><a href="#workflow">How it works</a><a href="#features">Features</a><a href="#plans">Plans</a><a href="#faq">FAQs</a><a class="button button-muted" href="{{ route('login') }}">Sign in</a><a class="button button-primary" href="{{ route('register') }}">Get started</a></nav>
    </header>

    <section class="marketing-hero">
        <div class="marketing-hero-copy"><p class="eyebrow">WATER OPERATIONS, MADE CLEAR</p><h1>Know what every property uses. Bill it with confidence.</h1><p>Record meter readings from your phone, calculate each unit’s water usage automatically, prepare bills and track collections in one workspace.</p><div class="marketing-hero-actions"><a class="button button-primary" href="{{ route('register') }}">Start managing water <span aria-hidden="true">→</span></a><a class="marketing-text-link" href="#workflow">See how it works</a></div><ul class="marketing-highlights"><li>Previous and current readings</li><li>Automatic usage calculation</li><li>Works offline for reading entry</li></ul><p class="marketing-hero-note">M-Pesa checkout is available when your organization connects its Daraja merchant account.</p></div>
        <div class="marketing-demo-card" aria-label="Illustrative UtilityFlow water reading preview">
            <div class="marketing-demo-top"><div><span class="marketing-live-dot"></span><span>WATER WORKSPACE</span></div><span class="marketing-demo-tag">ILLUSTRATIVE PREVIEW</span></div>
            <div class="marketing-demo-heading"><div><small>PROPERTY</small><strong>Kilimani Demo Court</strong></div><span class="marketing-demo-cycle">November cycle</span></div>
            <div class="marketing-demo-summary"><div><small>UNITS TO READ</small><strong>24</strong></div><div><small>READINGS ENTERED</small><strong>18 <em>/ 24</em></strong></div><div><small>NEEDS REVIEW</small><strong>2</strong></div></div>
            <div class="marketing-meter-row marketing-meter-head"><span>UNIT / METER</span><span>PREVIOUS</span><span>CURRENT</span><span>USAGE</span></div>
            <div class="marketing-meter-row"><span><b>A1</b><small>Water meter</small></span><span>1,248</span><span>1,256</span><strong>8 m³</strong></div>
            <div class="marketing-meter-row"><span><b>A2</b><small>Water meter</small></span><span>906</span><span>913</span><strong>7 m³</strong></div>
            <div class="marketing-meter-row"><span><b>B1</b><small>Shared meter</small></span><span>2,810</span><span>2,824</span><strong>14 m³</strong></div>
            <div class="marketing-demo-foot"><span>Illustrative data, not customer records.</span><span>Usage = current − previous</span></div>
        </div>
    </section>

    <section class="marketing-workflow" id="workflow"><div class="marketing-section-heading"><p class="eyebrow">A SIMPLE WATER WORKFLOW</p><h2>From meter to collection, step by step.</h2><p>Keep each reading connected to the right property, unit and billing cycle.</p></div>
        <div class="marketing-tabs" role="tablist" aria-label="Water workflow preview">
            <button type="button" role="tab" id="water-tab-reading" aria-controls="water-panel-reading" aria-selected="true" data-water-tab="reading"><span>01</span> Read meters</button>
            <button type="button" role="tab" id="water-tab-calculate" aria-controls="water-panel-calculate" aria-selected="false" data-water-tab="calculate"><span>02</span> Calculate usage</button>
            <button type="button" role="tab" id="water-tab-bill" aria-controls="water-panel-bill" aria-selected="false" data-water-tab="bill"><span>03</span> Prepare bills</button>
            <button type="button" role="tab" id="water-tab-collect" aria-controls="water-panel-collect" aria-selected="false" data-water-tab="collect"><span>04</span> Track collections</button>
        </div>
        <div class="marketing-tab-panels">
            <article class="marketing-tab-panel" id="water-panel-reading" role="tabpanel" aria-labelledby="water-tab-reading" data-water-panel="reading"><div class="marketing-panel-copy"><p class="eyebrow">01 · FIELD ENTRY</p><h3>Enter readings where the meters are.</h3><p>Choose a property, see its units and meters, then enter current values. The Water Reader can save entries offline and sync them when the connection returns.</p><ul><li>Previous reading shown beside the entry</li><li>Reading dates and review status stay attached</li><li>Offline entries remain on the device until synced</li></ul></div><div class="marketing-panel-visual marketing-reader-visual"><div class="marketing-ui-title"><span>Water Reader</span><span class="marketing-ui-pill">Offline ready</span></div><div class="marketing-ui-select">Kilimani Demo Court <span>⌄</span></div><div class="marketing-ui-unit"><div><b>Unit A1</b><small>Previous reading · 1,248 m³</small></div><label>Current reading <strong>1,256</strong></label><div class="marketing-ui-calculation">Usage calculated <b>8 m³</b></div></div><div class="marketing-ui-unit muted"><div><b>Unit A2</b><small>Previous reading · 906 m³</small></div><label>Enter current value <strong>___</strong></label></div></div></article>
            <article class="marketing-tab-panel" id="water-panel-calculate" role="tabpanel" aria-labelledby="water-tab-calculate" data-water-panel="calculate" hidden><div class="marketing-panel-copy"><p class="eyebrow">02 · USAGE CALCULATION</p><h3>See consumption before you bill.</h3><p>UtilityFlow compares the current value with the last approved reading and calculates usage. Review unusual values before they move into a billing cycle.</p><ul><li>Usage follows current minus previous reading</li><li>Readings can be reviewed before approval</li><li>History remains available for the next cycle</li></ul></div><div class="marketing-panel-visual marketing-calc-visual"><div class="marketing-ui-title"><span>Reading comparison</span><span class="marketing-ui-pill">Review</span></div><div class="marketing-calc-equation"><div><small>PREVIOUS</small><b>1,248</b></div><span>−</span><div><small>CURRENT</small><b>1,256</b></div><span>=</span><div class="marketing-calc-result"><small>USAGE</small><b>8 m³</b></div></div><div class="marketing-ui-notice"><b>✓</b><span>Within expected range<small>Illustrative reading check</small></span></div></div></article>
            <article class="marketing-tab-panel" id="water-panel-bill" role="tabpanel" aria-labelledby="water-tab-bill" data-water-panel="bill" hidden><div class="marketing-panel-copy"><p class="eyebrow">03 · BILLING CYCLE</p><h3>Turn approved usage into utility bills.</h3><p>Set up a cycle, apply your configured water charges and generate invoices for the accounts in that property. Shared meters can use your chosen allocation method.</p><ul><li>Bill from approved readings</li><li>Allocate shared usage by configured method</li><li>Keep invoices and balances connected</li></ul></div><div class="marketing-panel-visual marketing-bill-visual"><div class="marketing-ui-title"><span>November water cycle</span><span class="marketing-ui-pill green">Ready to review</span></div><div class="marketing-bill-line"><span>Unit A1 · 8 m³</span><b>Usage charge</b></div><div class="marketing-bill-line"><span>Unit A2 · 7 m³</span><b>Usage charge</b></div><div class="marketing-bill-line"><span>Shared meter allocation</span><b>Configured method</b></div><div class="marketing-bill-total"><span>Invoice preview</span><b>Based on your tariff</b></div><small class="marketing-demo-disclaimer">Illustrative preview; charges depend on your workspace tariff.</small></div></article>
            <article class="marketing-tab-panel" id="water-panel-collect" role="tabpanel" aria-labelledby="water-tab-collect" data-water-panel="collect" hidden><div class="marketing-panel-copy"><p class="eyebrow">04 · COLLECTION TRACKING</p><h3>Keep payments tied to the right bill.</h3><p>Record collections and review outstanding balances. If your organization configures its own Safaricom Daraja credentials, tenants can also use M-Pesa checkout.</p><ul><li>Manual collection recording and receipts</li><li>Invoice balance tracking</li><li>Daraja checkout depends on organization setup</li></ul></div><div class="marketing-panel-visual marketing-collect-visual"><div class="marketing-ui-title"><span>Water invoice</span><span class="marketing-ui-pill amber">Awaiting payment</span></div><div class="marketing-collect-amount"><small>AMOUNT DUE · ILLUSTRATIVE</small><strong>KES 1,240</strong></div><div class="marketing-payment-row"><span>Collection method</span><b>M-Pesa checkout</b></div><div class="marketing-ui-notice"><b>↗</b><span>Daraja account connected<small>Example status only</small></span></div></div></article>
        </div>
    </section>

    <section class="marketing-section" id="features"><div class="marketing-section-heading"><p class="eyebrow">MADE FOR WATER MANAGEMENT</p><h2>The tools to run your water cycle.</h2><p>Keep property structure, reading work and collection records joined up.</p></div><div class="marketing-feature-grid">
        <article><span class="marketing-feature-icon">⌂</span><h3>Property and unit register</h3><p>Organize properties, blocks, floors and units so meter work lands in the right place.</p></article>
        <article><span class="marketing-feature-icon">◉</span><h3>Meter assignments</h3><p>Connect meters to units or shared areas and manage the tariffs used for billing.</p></article>
        <article><span class="marketing-feature-icon">▤</span><h3>Mobile reading entry</h3><p>Use the Water Reader on site, with offline entry and later sync when you reconnect.</p></article>
        <article><span class="marketing-feature-icon">↕</span><h3>Usage review</h3><p>Compare previous and current values, calculate consumption and review submissions.</p></article>
        <article><span class="marketing-feature-icon">▧</span><h3>Water billing</h3><p>Prepare cycles, generate invoices and allocate shared meter usage using your chosen method.</p></article>
        <article><span class="marketing-feature-icon">✓</span><h3>Collection records</h3><p>Record payments and follow invoice balances, with optional Daraja checkout setup.</p></article>
    </div></section>

    <section class="marketing-plans" id="plans"><div><p class="eyebrow">CLEAR CAPACITY-BASED PLANS</p><h2>Choose a workspace that fits your portfolio.</h2><p>Plan prices and capacity limits are managed in the platform and can change. See the current options during workspace registration. Water tools are available according to your active plan’s limits.</p></div><a class="button button-primary" href="{{ route('register') }}">View current plans <span aria-hidden="true">→</span></a></section>

    <section class="marketing-faq" id="faq"><div class="marketing-section-heading"><p class="eyebrow">GOOD TO KNOW</p><h2>Water management FAQs.</h2></div><div class="marketing-faq-list">
        <details><summary>How is water usage calculated?</summary><p>For a meter reading, usage is the current value minus the previous approved value. The result is available for review before billing.</p></details>
        <details><summary>Can a caretaker enter readings without internet?</summary><p>The Water Reader can save reading entries on the device while offline and sync them after connectivity returns. Saved entries remain local until they sync successfully.</p></details>
        <details><summary>How does billing for a shared meter work?</summary><p>Shared meter usage can be allocated with a method configured for the workspace, such as an equal or proportional allocation. The allocation setup determines each unit’s share.</p></details>
        <details><summary>Can tenants pay water bills through M-Pesa?</summary><p>Yes, when the organization has configured its own Safaricom Daraja merchant credentials and the required callback settings are in place. Payment tracking is also available for manually recorded collections.</p></details>
        <details><summary>Can I import readings from a spreadsheet?</summary><p>Reading entry and review are available in the workspace. Ask support about your existing data before moving it so the right import path can be confirmed.</p></details>
        <details><summary>What happens when my portfolio grows?</summary><p>Plans have configurable property, unit and staff capacity limits. You can review available plans and current limits from your workspace subscription page.</p></details>
    </div></section>

    <section class="marketing-bottom"><div><p class="eyebrow">READY FOR YOUR NEXT READING CYCLE?</p><h2>Bring your water records into one clear flow.</h2><p>Set up your properties, connect meters and give your team a practical way to capture usage and follow bills.</p></div><a class="button button-primary" href="{{ route('register') }}">Create your workspace <span aria-hidden="true">→</span></a></section>
    <footer class="marketing-footer"><a class="brand" href="{{ route('home') }}"><img class="brand-mark" src="{{ asset('icon.svg').'?v=2' }}" alt=""><span>utility<span class="brand-accent">flow</span></span></a><span>Water operations for property teams.</span><a href="mailto:support@utilityflow.app">Contact support</a><span>© {{ now()->year }} UtilityFlow</span></footer>
</main>
@endsection
