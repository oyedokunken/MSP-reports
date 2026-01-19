<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact;
    }

    .cover-page {
        width: 100%;
        height: 100vh;
        max-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 60px 80px;
        font-family: 'Outfit', sans-serif;
        color: #1a1a1a;
        background: #ffffff;
        position: relative;
        overflow: hidden;
    }

    /* Unique Geometric Background Shapes */
    .bg-shape-1 {
        position: absolute;
        top: 0;
        right: 0;
        width: 60%;
        height: 100%;
        background: #1e293b;
        opacity: 0.07;
        clip-path: polygon(100% 0, 0% 0, 100% 100%);
        z-index: 1;
    }

    .bg-shape-2 {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 40%;
        height: 50%;
        background: #1e293b;
        opacity: 0.05;
        clip-path: polygon(0 100%, 100% 100%, 0 0);
        z-index: 1;
    }


    .top-center-brand {
        position: absolute;
        top: 30px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3em;
        color: #94a3b8;
        opacity: 0.25;
        z-index: 20;
        white-space: nowrap;
    }

    .cover-logo-container {
        max-width: 140px;
        margin-bottom: 24px;
        display: flex;
        justify-content: center;
    }

    .cover-logo-container img {
        max-width: 100%;
        max-height: 60px;
        height: auto;
        object-fit: contain;
    }

    .msp-bottom-logo {
        position: absolute;
        bottom: 30px;
        left: 40px;
        z-index: 20;
        opacity: 0.6;
    }

    .msp-bottom-logo img {
        height: 25px;
        width: auto;
    }

    .cover-main {
        position: relative;
        z-index: 10;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        width: 100%;
    }

    .report-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        background: {{ $branding['primary_color'] ?? '#1a56db' }}12;
        color: {{ $branding['primary_color'] ?? '#1a56db' }};
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 32px;
        border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }};
    }

    .title-group h1 {
        font-size: 64px;
        font-weight: 800;
        line-height: 1.1;
        margin: 0;
        color: #0f172a;
        letter-spacing: -0.04em;
    }

    .title-group .subtitle {
        font-size: 24px;
        color: #64748b;
        margin: 20px auto 0;
        font-weight: 300;
        max-width: 700px;
        line-height: 1.4;
    }

    .accent-bar {
        width: 80px;
        height: 6px;
        background: linear-gradient(to right, {{ $branding['primary_color'] ?? '#1a56db' }}, {{ $branding['secondary_color'] ?? '#7c3aed' }});
        margin: 40px auto;
        border-radius: 4px;
    }

    .meta-info {
        display: flex;
        justify-content: center;
        gap: 60px;
        margin-top: 10px;
    }

    .meta-block .label {
        font-size: 12px;
        text-transform: uppercase;
        color: #94a3b8;
        font-weight: 600;
        letter-spacing: 0.15em;
        margin-bottom: 10px;
    }

    .meta-block .value {
        font-size: 20px;
        font-weight: 500;
        color: #334155;
    }

    .client-section {
        margin-top: 80px;
        padding: 40px;
        background: #f8fafc;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        gap: 30px;
        border: 1px solid #f1f5f9;
    }

    .client-logo-box {
        width: 70px;
        height: 70px;
        background: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
    }

    .client-logo-box img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .client-details .label {
        font-size: 11px;
        color: #94a3b8;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .client-details .name {
        font-size: 22px;
        font-weight: 700;
        color: #1e293b;
    }

    .contact-section {
        margin-top: 60px;
        padding: 18px 40px;
        background: #ffffff;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 40px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.02);
    }

    .contact-info-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .contact-label {
        font-size: 10px;
        text-transform: uppercase;
        color: #94a3b8;
        font-weight: 700;
        letter-spacing: 0.05em;
    }

    .contact-value {
        font-size: 14px;
        color: #475569;
        font-weight: 600;
    }

    .contact-divider {
        width: 1px;
        height: 24px;
        background: #f1f5f9;
    }

    .confidential-tag {
        position: absolute;
        top: 40px;
        left: -50px;
        background: #1e293b;
        color: #ffffff;
        padding: 8px 60px;
        transform: rotate(-45deg);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    }
</style>

<div class="cover-page">
    <div class="bg-shape-1"></div>
    <div class="bg-shape-2"></div>
    <div class="confidential-tag">Confidential</div>

    <div class="generation-date" style="position: absolute; top: 30px; right: 40px; text-align: right; opacity: 0.4; z-index: 20;">
        <span style="font-size: 9px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.15em;">Generated</span><br>
        <span style="font-size: 12px; font-weight: 500; color: #475569;">{{ now()->format('M d, Y') }}</span>
    </div>

    <div class="top-center-brand">
        {{ $branding['company_name'] ?? 'Reporting' }}
    </div>

    <div class="cover-main">
        <div class="cover-logo-container">
            @if(isset($branding['company_logo']) && $branding['company_logo'])
                <img src="{{ $branding['company_logo'] }}" alt="Company Logo">
            @else
                <div style="font-weight: 800; font-size: 20px; color: {{ $branding['primary_color'] ?? '#1a56db' }}; letter-spacing: -0.01em; opacity: 0.8;">
                    {{ $branding['company_name'] ?? 'Reporting' }}
                </div>
            @endif
        </div>

        <div class="report-badge">
            {{ ucfirst($options['frequency'] ?? 'Monthly') }} Operations Review
        </div>
        
        <div class="title-group">
            <h1>{{ ucfirst($options['frequency'] ?? 'Service Summary') }} Service Report</h1>
            <div class="subtitle">A detailed performance audit and infrastructure health analysis for your managed environment.</div>
        </div>

        <div class="accent-bar"></div>

        <div class="meta-info">
            <div class="meta-block">
                <div class="label">Reporting Period</div>
                <div class="value">{{ $period['formatted'] ?? 'Q1 Review' }}</div>
            </div>
            <div class="meta-block">
                <div class="label">Report Focus</div>
                <div class="value">Tech Infrastructure</div>
            </div>
        </div>

        <div class="client-section">
            @if(isset($branding['customer_logo']) && $branding['customer_logo'])
                <div class="client-logo-box">
                    <img src="{{ $branding['customer_logo'] }}" alt="Customer Logo">
                </div>
            @endif
            <div class="client-details">
                <div class="label">Exclusively Prepared For</div>
                <div class="name">{{ $customer->canonical_name }}</div>
            </div>
        </div>

        <div class="contact-section">
            @if(isset($branding['support_email']) && $branding['support_email'])
                <div class="contact-info-item">
                    <div class="contact-label">Email Support</div>
                    <div class="contact-value">{{ $branding['support_email'] }}</div>
                </div>
            @endif
            
            @if((isset($branding['support_email']) && $branding['support_email']) && (isset($branding['support_phone']) && $branding['support_phone']))
                <div class="contact-divider"></div>
            @endif

            @if(isset($branding['support_phone']) && $branding['support_phone'])
                <div class="contact-info-item">
                    <div class="contact-label">Direct Line</div>
                    <div class="contact-value">{{ $branding['support_phone'] }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="msp-bottom-logo">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.png'))) }}" alt="MSP Reports">
    </div>
</div>

@if(!isset($render_part) || $render_part === 'all')
<div style="page-break-after: always;"></div>
@endif
