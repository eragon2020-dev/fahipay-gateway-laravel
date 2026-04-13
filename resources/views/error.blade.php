<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .error-card { max-width: 500px; margin: 50px auto; }
        .error-icon { font-size: 4rem; color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card error-card shadow">
            <div class="card-body text-center py-5">
                <div class="error-icon mb-3">✗</div>
                <h2 class="text-danger">Payment Failed</h2>
                <p class="text-muted">{{ $message ?? 'An error occurred during payment.' }}</p>
                
                @if($transactionId ?? false)
                <div class="bg-light rounded p-3 mt-4 text-start">
                    <p class="mb-0"><strong>Transaction ID:</strong> {{ $transactionId }}</p>
                </div>
                @endif

                <a href="/" class="btn btn-primary mt-4">Try Again</a>
            </div>
        </div>
    </div>
</body>
</html>