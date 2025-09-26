@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
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
                    <p class="text-red-700">{{ json_decode($ocrResult->ocr_results)->error ?? 'Terjadi kesalahan dalam memproses file.' }}</p>
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

@if($ocrResult->status === 'pending' || $ocrResult->status === 'processing')
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkStatus = async () => {
        try {
            const response = await fetch('{{ route("ocr.preview", $ocrResult->id) }}');
            if (response.redirected) {
                window.location.href = response.url;
                return;
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
@endpush
@endif
@endsection