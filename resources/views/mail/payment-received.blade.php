<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Received</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>We have received your payment. Thank you!</p>
            
            <h3>Payment Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Transaction ID</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">{{ $payment->transaction_id }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Amount</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">MVR {{ number_format($payment->amount, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Status</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">{{ $payment->status->label() }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Date</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">{{ $payment->created_at->format('d M Y, h:i A') }}</td>
                </tr>
            </table>
            
            @if($payment->approval_code)
            <p style="margin-top: 15px;"><strong>Approval Code:</strong> {{ $payment->approval_code }}</p>
            @endif
        </div>
        <div class="footer">
            <p>This is an automated email from FahiPay Gateway</p>
        </div>
    </div>
</body>
</html>