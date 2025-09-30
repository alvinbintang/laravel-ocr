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
                                    @elseif($ocrResult->status === 'done' || $ocrResult->status === 'awaiting_selection')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $ocrResult->status }}
                                        </span>
                                        <a href="/ocr/{{ $ocrResult->id }}/preview" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                            Lihat Pratinjau
                                        </a>
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
                            <div id="processing-message" class="text-center">
                                <p class="text-gray-600 mb-2">
                                    Mohon tunggu sebentar, file Anda sedang diproses...
                                </p>
                                <div id="extended-message" class="hidden">
                                    <p class="text-amber-600 text-sm mb-4">
                                        Proses ini membutuhkan waktu lebih lama dari biasanya. Mohon bersabar...
                                    </p>
                                    <button id="refresh-btn" onclick="window.location.reload()" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Refresh Halaman
                                    </button>
                                </div>
                            </div>
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
        // ADDED: Timer untuk menampilkan pesan extended setelah 5 detik
        let startTime = Date.now();
        let extendedMessageShown = false;
        
        const showExtendedMessage = () => {
            if (!extendedMessageShown && (Date.now() - startTime) >= 5000) {
                document.getElementById('extended-message').classList.remove('hidden');
                extendedMessageShown = true;
            }
        };
        
        const checkStatus = async () => {
            try {
                // Cek apakah sudah 5 detik untuk menampilkan pesan extended
                showExtendedMessage();
                
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
                    if (data.status === 'done' || data.status === 'awaiting_selection') {
                        // ADDED: Redirect to preview page when processing is complete or awaiting selection
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