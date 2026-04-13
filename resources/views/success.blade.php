<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .success-card { max-width: 500px; margin: 50px auto; }
        .success-icon { font-size: 4rem; color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card success-card shadow">
            <div class="card-body text-center py-5">
                <div class="success-icon mb-3">✓</div>
                <h2 class="text-success">Payment Successful!</h2>
                <p class="text-muted">Your payment has been processed successfully.</p>
                
                <div class="bg-light rounded p-3 mt-4 text-start">
                    <p class="mb-1"><strong>Transaction ID:</strong> {{ $transactionId }}</p>
                    @if($approvalCode)
                    <p class="mb-0"><strong>Approval Code:</strong> {{ $approvalCode }}</p>
                    @endif
                </div>

                <a href="/" class="btn btn-primary mt-4">Return to Home</a>
            </div>
        </div>
    </div>
</body>
</html>