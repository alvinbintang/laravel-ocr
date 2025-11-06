<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Result</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> <!-- ADDED -->
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">OCR Result for {{ $ocrResult->filename }}</div>

                    <div class="card-body">
                        @if ($ocrResult->status == 'pending' || $ocrResult->status == 'processing')
                            <div class="alert alert-info" role="alert">
                                File sedang diproses. Silakan refresh halaman ini nanti.
                            </div>
                        @elseif ($ocrResult->status == 'done')
                            <h5>Extracted Text:</h5>
                            <pre class="bg-light p-3 rounded">{{ $ocrResult->text }}</pre>
                            
                        @elseif ($ocrResult->status == 'error')
                            <div class="alert alert-danger" role="alert">
                                Terjadi kesalahan saat memproses file: {{ $ocrResult->text }}
                            </div>
                        @endif
                        <a href="{{ route('ocr.index') }}" class="btn btn-primary">Upload New PDF</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
