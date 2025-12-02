{{--
    Report Footer Partial
    
    Required variables:
    - $branding (array): Branding configuration
    
    Optional variables:
    - $pageNumber (int): Current page number
    - $totalPages (int): Total number of pages
--}}

<div class="report-footer" style="margin-top: 20mm; padding-top: 10mm; border-top: 1px solid #e5e5e5;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
        
        <!-- Left: Company info -->
        <div style="font-size: 8pt; color: #666; flex: 1; min-width: 200px;">
            @if(isset($branding['footer_text']) && $branding['footer_text'])
                <div style="margin-bottom: 5px; font-weight: 500;">
                    {{ $branding['footer_text'] }}
                </div>
            @endif
            
            @if(isset($branding['support_email']) || isset($branding['support_phone']))
                <div style="line-height: 1.6;">
                    @if(isset($branding['support_phone']) && $branding['support_phone'])
                        <div><strong>Phone:</strong> {{ $branding['support_phone'] }}</div>
                    @endif
                    @if(isset($branding['support_email']) && $branding['support_email'])
                        <div><strong>Email:</strong> {{ $branding['support_email'] }}</div>
                    @endif
                </div>
            @endif
        </div>
        
        <!-- Right: Customer-specific notes -->
        @if(isset($branding['footer_notes']) && $branding['footer_notes'])
            <div style="font-size: 8pt; color: #999; text-align: right; flex: 1; min-width: 200px; font-style: italic;">
                {{ $branding['footer_notes'] }}
            </div>
        @endif
    </div>
    
    <!-- Page number (centered) -->
    <div style="text-align: center; margin-top: 10mm; font-size: 7pt; color: #999;">
        @if(isset($pageNumber) && isset($totalPages))
            Page {{ $pageNumber }} of {{ $totalPages }}
        @else
            <span class="page-number"></span>
        @endif
    </div>
</div>
