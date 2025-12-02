{{--
    Report Header Partial
    
    Required variables:
    - $branding (array): Branding configuration
    - $customer (Customer): Customer model
    
    Optional variables:
    - $reportDate (string): Report generation date
--}}

<div class="report-header" style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 15mm; border-bottom: 2px solid {{ $branding['primary_color'] ?? '#1a56db' }}; margin-bottom: 10mm;">
    
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
