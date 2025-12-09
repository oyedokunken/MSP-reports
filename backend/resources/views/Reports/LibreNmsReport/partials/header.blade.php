{{--
    Report Header Partial
    
    Required variables:
    - $branding (array): Branding configuration
    - $customer (Customer): Customer model
    
    Optional variables:
    - $reportDate (string): Report generation date
--}}

<div class="report-header" style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 10px; border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }}; margin-bottom: 20px;">
    
    <!-- Left side: Company branding -->
    <div class="header-left" style="flex: 1;">
        @if(isset($branding['company_logo']) && $branding['company_logo'])
            <img src="{{ $branding['company_logo'] }}" 
                 alt="{{ $branding['company_name'] ?? 'Company' }}" 
                 style="max-height: 50px; max-width: 200px; margin-bottom: 10px; display: block;">
        @else
            <h1 style="font-size: 18pt; font-weight: 600; color: {{ $branding['primary_color'] ?? '#1a56db' }}; margin: 0 0 5px 0;">
                {{ $branding['company_name'] ?? 'Company Name' }}
            </h1>
        @endif
        
        <div class="subtitle" style="font-size: 11pt; color: #666; font-weight: 400; margin-top: 5px;">
            {{ $branding['report_title'] ?? 'Service Report' }}
        </div>
    </div>
    
    <!-- Right side: Customer info and logo -->
    <div class="header-right" style="text-align: right; flex: 1;">
        @if(isset($branding['customer_logo']) && $branding['customer_logo'])
            <img src="{{ $branding['customer_logo'] }}" 
                 alt="{{ $customer->canonical_name }}" 
                 style="max-height: 50px; max-width: 200px; margin-bottom: 10px; display: block; margin-left: auto;">
        @endif
        
        <div style="font-size: 9pt; color: #666;">
            <div class="label" style="font-weight: 500; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">
                Prepared For
            </div>
            <div class="value" style="color: #1a1a1a; margin-bottom: 8px; font-size: 11pt; font-weight: 500;">
                {{ $customer->canonical_name }}
            </div>
            
            @if(isset($reportDate))
            <div class="label" style="font-weight: 500; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">
                Report Date
            </div>
            <div class="value" style="color: #1a1a1a; font-size: 9pt;">
                {{ $reportDate }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Report Title and Configuration Section --}}
@if(isset($reportTitle) || isset($reportType) || isset($period))
<div class="report-info" style="background: #f8fafc; border-left: 4px solid {{ $branding['primary_color'] ?? '#1a56db' }}; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px;">
    @if(isset($reportTitle))
        <h2 style="font-size: 16pt; font-weight: 600; color: #1a1a1a; margin: 0 0 10px 0;">
            {{ $reportTitle }}
        </h2>
    @endif
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 9pt; color: #666;">
        @if(isset($reportType))
            <div>
                <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Report Type</div>
                <div style="color: #1a1a1a; font-weight: 500;">{{ $reportType }}</div>
            </div>
        @endif
        
        @if(isset($period))
            <div>
                <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Report Period</div>
                <div style="color: #1a1a1a; font-weight: 500;">{{ $period['formatted'] ?? ($period['start'] . ' - ' . $period['end']) }}</div>
            </div>
        @endif
        
        @if(isset($generated_at))
            <div>
                <div style="font-weight: 600; color: #999; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; margin-bottom: 3px;">Generated</div>
                <div style="color: #1a1a1a; font-weight: 500;">{{ \Carbon\Carbon::parse($generated_at)->format('F j, Y g:i A') }}</div>
            </div>
        @endif
    </div>
</div>
@endif
