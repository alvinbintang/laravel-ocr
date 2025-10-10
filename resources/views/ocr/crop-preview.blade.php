<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crop Preview - Laravel OCR</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="{{ route('ocr.index') }}" class="text-xl font-bold text-gray-800">Laravel OCR</a>
                        </div>
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="{{ route('ocr.index') }}" class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-900">
                                Upload
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-10">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold">Konfirmasi Hasil Crop</h2>
                                <p class="text-gray-600">File: {{ $ocrResult->filename }}</p>
                                <p class="text-gray-600">Jenis Dokumen: {{ $ocrResult->document_type }}</p>
                                <p class="text-gray-600">
                                    Status: 
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Menunggu Konfirmasi
                                    </span>
                                </p>
                            </div>
                            <div class="flex space-x-4">
                                <button onclick="goBack()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                    Kembali & Pilih Ulang
                                </button>
                                <button onclick="confirmCrop()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                    Ya, Lanjutkan OCR
                                </button>
                            </div>
                        </div>

                        <!-- Crop Preview Section -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Preview Gambar yang Akan Diproses OCR:</h3>
                            
                            @if(empty($croppedImages))
                                <div class="text-center py-8">
                                    <p class="text-gray-500">Tidak ada gambar hasil crop yang tersedia.</p>
                                </div>
                            @else
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    @foreach($croppedImages as $index => $image)
                                        <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                                            <div class="mb-3">
                                                <h4 class="font-medium text-gray-800">Region {{ $index + 1 }}</h4>
                                                <p class="text-sm text-gray-600">Halaman: {{ $image['page'] ?? 'N/A' }}</p>
                                                <p class="text-sm text-gray-600">
                                                    Koordinat: {{ $image['coordinates']['x'] ?? 0 }}, {{ $image['coordinates']['y'] ?? 0 }} 
                                                    ({{ $image['coordinates']['width'] ?? 0 }}x{{ $image['coordinates']['height'] ?? 0 }})
                                                </p>
                                            </div>
                                            
                                            <div class="border border-gray-200 rounded overflow-hidden">
                                                @if(isset($image['file_path']) && file_exists(storage_path('app/public/' . $image['file_path'])))
                                                    <img src="{{ asset('storage/' . $image['file_path']) }}" 
                                                         alt="Cropped Region {{ $index + 1 }}" 
                                                         class="w-full h-auto max-h-64 object-contain bg-white">
                                                @else
                                                    <div class="w-full h-32 bg-gray-200 flex items-center justify-center">
                                                        <p class="text-gray-500 text-sm">Gambar tidak tersedia</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Instructions -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-blue-800 mb-2">Instruksi:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• Periksa gambar hasil crop di atas</li>
                                <li>• Pastikan area yang dipilih sudah sesuai dengan yang diinginkan</li>
                                <li>• Klik <strong>"Ya, Lanjutkan OCR"</strong> jika sudah sesuai</li>
                                <li>• Klik <strong>"Kembali & Pilih Ulang"</strong> jika ingin memilih ulang area</li>
                            </ul>
                        </div>

                        <!-- Action Buttons (Repeated for better UX) -->
                        <div class="flex justify-center space-x-4">
                            <button onclick="goBack()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded-lg">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Kembali & Pilih Ulang
                            </button>
                            <button onclick="confirmCrop()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Ya, Lanjutkan OCR
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-4"></div>
                <p class="text-lg">Memproses OCR...</p>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            window.location.href = "{{ route('ocr.preview', $ocrResult->id) }}";
        }

        function confirmCrop() {
            // Show loading modal
            document.getElementById('loadingModal').classList.remove('hidden');
            document.getElementById('loadingModal').classList.add('flex');

            fetch(`/ocr/{{ $ocrResult->id }}/confirm-crop`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to status page to monitor OCR progress
                    window.location.href = `/ocr/{{ $ocrResult->id }}/status`;
                } else {
                    // Hide loading modal
                    document.getElementById('loadingModal').classList.add('hidden');
                    document.getElementById('loadingModal').classList.remove('flex');
                    
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                // Hide loading modal
                document.getElementById('loadingModal').classList.add('hidden');
                document.getElementById('loadingModal').classList.remove('flex');
                
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memproses OCR');
            });
        }
    </script>
</body>
</html>