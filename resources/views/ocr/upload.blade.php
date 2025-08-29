<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PDF for OCR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Upload PDF for OCR</div>

                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success" role="alert">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('ocr.extract') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="pdf" class="form-label">Pilih File PDF</label>
                                <input class="form-control" type="file" id="pdf" name="pdf" accept=".pdf">
                            </div>
                            <button type="submit" class="btn btn-primary">Upload dan Proses OCR</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Daftar File yang Diproses</div>
                    <div class="card-body">
                        @if ($ocrResults->isEmpty())
                            <p>Belum ada file yang diproses.</p>
                        @else
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nama File</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ocrResults as $result)
                                        <tr>
                                            <td>{{ $result->filename }}</td>
                                            <td>
                                                @if ($result->status == 'pending')
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif ($result->status == 'processing')
                                                    <span class="badge bg-info">Processing</span>
                                                @elseif ($result->status == 'done')
                                                    <span class="badge bg-success">Done</span>
                                                @else
                                                    <span class="badge bg-danger">Error</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('ocr.result', ['id' => $result->id]) }}" class="btn btn-sm btn-primary">Lihat Hasil</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
