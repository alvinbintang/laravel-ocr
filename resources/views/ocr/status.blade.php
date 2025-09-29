<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Status Pemrosesan - Laravel OCR</title>
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h2 class="text-2xl font-bold mb-4">Status Pemrosesan</h2>

                        @if(session('info'))
                        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4">
                            {{ session('info') }}
                        </div>
                        @endif

                        @if(session('error'))
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                            {{ session('error') }}
                        </div>
                        @endif

                        <div class="space-y-4">
                            <div>
                                <p class="text-gray-600">
                                    Nama File: <span class="font-semibold">{{ $ocrResult->filename }}</span>
                                </p>
                            </div>

                            <div>
                                <p class="text-gray-600">
                                    Status: 
                                    @if($ocrResult->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Menunggu Proses
                                        </span>
                                    @elseif($ocrResult->status === 'processing')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Sedang Diproses
                                        </span>
                                    @elseif($ocrResult->status === 'error')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Error
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $ocrResult->status }}
                                        </span>
                                    @endif
                                </p>
                            </div>

                            @if($ocrResult->status === 'pending' || $ocrResult->status === 'processing')
                            <div class="flex items-center justify-center py-8">
                                <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500"></div>
                            </div>
                            <p class="text-center text-gray-600">
                                Mohon tunggu sebentar, file Anda sedang diproses...
                            </p>
                            @endif

                            @if($ocrResult->status === 'error')
                            <div class="bg-red-50 border border-red-200 rounded p-4">
                                <p class="text-red-700">{{ $ocrResult->ocr_results['error'] ?? 'Terjadi kesalahan dalam memproses file.' }}</p>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('ocr.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    Kembali ke Halaman Upload
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    @if($ocrResult->status === 'pending' || $ocrResult->status === 'processing')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkStatus = async () => {
            try {
                // UPDATED: Check status via API instead of redirect
                const response = await fetch(`/ocr/{{ $ocrResult->id }}/status-check`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'done') {
                        // ADDED: Redirect to preview page when processing is complete
                        window.location.href = `/ocr/{{ $ocrResult->id }}/preview`;
                        return;
                    } else if (data.status === 'error') {
                        // Reload page to show error
                        window.location.reload();
                        return;
                    }
                }
            } catch (error) {
                console.error('Error checking status:', error);
            }
            
            // Check again in 2 seconds
            setTimeout(checkStatus, 2000);
        };

        checkStatus();
    });
    </script>
    @endif
</body>
</html>