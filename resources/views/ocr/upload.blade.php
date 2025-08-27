<!DOCTYPE html>
<html>
<head>
    <title>Upload PDF untuk OCR</title>
</head>
<body>
    <h2>Upload PDF (gambar → teks)</h2>
    <form action="{{ route('ocr.extract') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="pdf" required>
        <button type="submit">Proses OCR</button>
    </form>
</body>
</html>
