<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Report is Ready</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: {{ $primaryColor }}; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">{{ $companyName }}</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 20px;">Your Report is Ready</h2>
                            
                            <p style="margin: 0 0 15px 0; color: #666666; line-height: 1.6;">
                                Hello {{ $customerName }},
                            </p>

                            <p style="margin: 0 0 15px 0; color: #666666; line-height: 1.6;">
                                Your <strong>{{ ucwords(str_replace('_', ' ', $reportType)) }}</strong> report has been generated and is attached to this email.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 25px 0; background-color: #f9f9f9; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 8px 0; color: #666666; font-size: 14px;">
                                                    <strong>Report Period:</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #333333; font-size: 14px; text-align: right;">
                                                    {{ $reportPeriod }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666666; font-size: 14px;">
                                                    <strong>Generated:</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #333333; font-size: 14px; text-align: right;">
                                                    {{ $generatedAt }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 15px 0; color: #666666; line-height: 1.6;">
                                The report is attached as a PDF file. If you have any questions or need assistance, please don't hesitate to contact us.
                            </p>

                            @if($supportPhone)
                            <p style="margin: 0 0 15px 0; color: #666666; line-height: 1.6;">
                                <strong>Phone:</strong> {{ $supportPhone }}<br>
                                <strong>Email:</strong> <a href="mailto:{{ $supportEmail }}" style="color: {{ $primaryColor }}; text-decoration: none;">{{ $supportEmail }}</a>
                            </p>
                            @else
                            <p style="margin: 0 0 15px 0; color: #666666; line-height: 1.6;">
                                <strong>Email:</strong> <a href="mailto:{{ $supportEmail }}" style="color: {{ $primaryColor }}; text-decoration: none;">{{ $supportEmail }}</a>
                            </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9f9f9; padding: 20px 30px; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; color: #999999; font-size: 12px; line-height: 1.6; text-align: center;">
                                @if($footerText)
                                    {{ $footerText }}<br>
                                @endif
                                This is an automated email. Please do not reply to this message.<br>
                                © {{ date('Y') }} {{ $companyName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
