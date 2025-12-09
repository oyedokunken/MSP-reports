<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branding Partials Test Preview</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 20mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-info {
            background: #e0f2fe;
            border: 2px solid #0284c7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .test-info h2 {
            color: #0284c7;
            font-size: 16pt;
            margin-bottom: 10px;
        }
        
        .test-info p {
            color: #0c4a6e;
            font-size: 10pt;
            line-height: 1.6;
        }
        
        .sample-content {
            margin: 30mm 0;
            padding: 20px;
            background: #fafafa;
            border-radius: 8px;
        }
        
        .sample-content h3 {
            color: {{ $branding['primary_color'] ?? '#1a56db' }};
            margin-bottom: 10px;
        }
        
        .sample-content p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Test Info Banner -->
        <div class="test-info">
            <h2>🎨 Branding Partials Test Preview</h2>
            <p>
                <strong>Phase 1 Testing:</strong> This page demonstrates the header and footer partials with test branding data.
                <br><strong>Company:</strong> {{ $branding['company_name'] }}
                <br><strong>Primary Color:</strong> {{ $branding['primary_color'] }}
                <br><strong>Customer:</strong> {{ $customer->canonical_name }}
            </p>
        </div>
        
        <!-- Header Partial -->
        @include('reports.partials.header', [
            'branding' => $branding,
            'customer' => $customer,
            'reportDate' => $reportDate
        ])
        
        <!-- Sample Content -->
        <div class="sample-content">
            <h3>Sample Report Content</h3>
            <p>
                This is where the actual report content would appear. The header above and footer below 
                are reusable partials that will be included in all report templates.
            </p>
            <p style="margin-top: 10px;">
                <strong>Key Features:</strong>
            </p>
            <ul style="margin-left: 20px; margin-top: 5px; color: #666;">
                <li>Company branding on the left (logo or name)</li>
                <li>Customer information on the right</li>
                <li>Dynamic colors from branding settings</li>
                <li>Support contact information in footer</li>
                <li>Optional customer-specific footer notes</li>
            </ul>
        </div>
        
        <!-- Footer Partial -->
        @include('reports.partials.footer', [
            'branding' => $branding
        ])
    </div>
</body>
</html>
