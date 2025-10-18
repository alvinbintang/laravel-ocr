<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RKA Preview - {{ $ocrResult->filename }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">RKA Preview</h1>
                    <p class="text-gray-600">File: {{ $ocrResult->filename }}</p>
                    <p class="text-sm text-gray-500">Langkah 1: Preview dan Rotasi Gambar</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('ocr.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Image Preview Section -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Preview Gambar</h2>
                        <div class="flex flex-wrap gap-2 items-center">
                            <!-- Page Counter -->
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">Pages ({{ count($ocrResult->images) }} total)</span>
                                <select id="page-selector" class="border border-gray-300 rounded px-3 py-1 text-sm">
                                    @foreach($ocrResult->images as $index => $image)
                                        <option value="{{ $index }}">{{ $index + 1 }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- UPDATED: Separator -->
                            <div class="border-l border-gray-300 mx-2"></div>
                            
                            <!-- UPDATED: Rotation Controls -->
                            <button id="rotate-left" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg transition-colors flex items-center text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"></path>
                                </svg>
                                Rotate Left
                            </button>
                            <button id="rotate-right" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg transition-colors flex items-center text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M21 12l-6.414 6.414a2 2 0 01-1.414.586H5a2 2 0 01-2-2V7a2 2 0 012-2h8.172a2 2 0 011.414.586L21 12z"></path>
                                </svg>
                                Rotate Right
                            </button>
                            <button id="apply-rotation" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg transition-colors flex items-center text-sm" style="display: none;">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Apply
                            </button>
                            <button id="reset-rotation" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg transition-colors flex items-center text-sm" style="display: none;">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Reset
                            </button>
                        </div>
                    </div>

                    <!-- Image Container -->
                    <div class="relative mb-6 bg-gray-300 min-h-96 flex items-center justify-center" id="image-preview-container">
                        <div id="loading-spinner" class="loading-spinner" style="display: none;"></div>
                        <img id="preview-image" 
                             src="{{ asset('storage/' . $ocrResult->images[0]) }}" 
                             alt="Preview" 
                             class="max-w-full max-h-96 object-contain shadow-lg rounded"
                             style="display: block;">
                    </div>

                    <!-- Rotation Status -->
                    <div id="rotation-status" class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200" style="display: none;">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm text-blue-800" id="rotation-status-text"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm font-medium text-gray-600">Total Halaman:</span>
                            <span class="text-sm text-gray-800">{{ count($ocrResult->images) }}</span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600">Status:</span>
                            <span class="text-sm text-green-600">Siap untuk rotasi</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="space-y-3">
                        <h4 class="text-md font-medium text-gray-800">Langkah Selanjutnya</h4>
                        <p class="text-sm text-gray-600">
                            Setelah selesai merotasi gambar (jika diperlukan), Anda dapat melanjutkan ke tahap pemilihan area untuk OCR.
                        </p>
                        <button id="continue-to-multiselect" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-colors font-medium flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                            Lanjut ke Multi-Select
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span id="notification-message">Rotasi berhasil diterapkan!</span>
            </div>
        </div>
    </div>

    <style>
        .loading-spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        .loading-spinner-small {
            border: 2px solid #f3f4f6;
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .notification-show {
            transform: translateX(0) !important;
        }
    </style>

    <script>
        // Global variables
        let currentPage = 0;
        let pendingRotation = 0;
        let appliedRotations = @json($ocrResult->applied_rotations ?? []);
        const images = @json($ocrResult->images);
        const ocrResultId = {{ $ocrResult->id }};

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            updateRotationStatus();
        });

        function initializeEventListeners() {
            // Page selector
            document.getElementById('page-selector').addEventListener('change', function() {
                currentPage = parseInt(this.value);
                loadCurrentPage();
            });

            // Rotation buttons
            document.getElementById('rotate-left').addEventListener('click', () => rotateImagePreview(-90));
            document.getElementById('rotate-right').addEventListener('click', () => rotateImagePreview(90));
            document.getElementById('apply-rotation').addEventListener('click', applyRotationToBackend);
            document.getElementById('reset-rotation').addEventListener('click', resetCurrentPageRotation);

            // Continue button
            document.getElementById('continue-to-multiselect').addEventListener('click', function() {
                window.location.href = `/ocr/${ocrResultId}/multiselect`;
            });
        }

        function loadCurrentPage() {
            const previewImage = document.getElementById('preview-image');
            previewImage.src = `/storage/${images[currentPage]}`;
            pendingRotation = 0;
            updateRotationButtons();
            updateRotationStatus();
        }

        function rotateImagePreview(degrees) {
            pendingRotation = (pendingRotation + degrees) % 360;
            if (pendingRotation < 0) pendingRotation += 360;
            
            applyVisualRotation();
            updateRotationButtons();
            updateRotationStatus();
        }

        function applyVisualRotation() {
            const previewImage = document.getElementById('preview-image');
            const totalRotation = (appliedRotations[currentPage] || 0) + pendingRotation;
            previewImage.style.transform = `rotate(${totalRotation}deg)`;
        }

        function updateRotationButtons() {
            const applyBtn = document.getElementById('apply-rotation');
            const resetBtn = document.getElementById('reset-rotation');
            // UPDATED: Get continue button to disable/enable based on rotation state
            const continueBtn = document.getElementById('continue-btn');
            
            if (pendingRotation !== 0) {
                applyBtn.style.display = 'flex';
                resetBtn.style.display = 'flex';
                // UPDATED: Disable continue button when rotation is pending
                if (continueBtn) {
                    continueBtn.disabled = true;
                    continueBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            } else {
                applyBtn.style.display = 'none';
                resetBtn.style.display = 'none';
                // UPDATED: Re-enable continue button when no rotation is pending
                if (continueBtn) {
                    continueBtn.disabled = false;
                    continueBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
        }

        function resetCurrentPageRotation() {
            pendingRotation = 0;
            applyVisualRotation();
            updateRotationButtons();
            updateRotationStatus();
        }

        function applyRotationToBackend() {
            if (pendingRotation === 0) return;

            const applyBtn = document.getElementById('apply-rotation');
            const originalContent = applyBtn.innerHTML;
            
            // Show loading state
            applyBtn.innerHTML = '<div class="loading-spinner-small"></div>Menerapkan...';
            applyBtn.disabled = true;

            fetch(`/ocr/${ocrResultId}/apply-rotation`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    page: currentPage,
                    rotation: pendingRotation
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update applied rotations
                    appliedRotations[currentPage] = (appliedRotations[currentPage] || 0) + pendingRotation;
                    pendingRotation = 0;
                    
                    // Reload image
                    const previewImage = document.getElementById('preview-image');
                    previewImage.src = `/storage/${data.rotated_image_path}?t=${Date.now()}`;
                    previewImage.style.transform = 'rotate(0deg)';
                    
                    updateRotationButtons();
                    updateRotationStatus();
                    showNotification('Rotasi berhasil diterapkan!', 'success');
                } else {
                    showNotification('Gagal menerapkan rotasi: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat menerapkan rotasi', 'error');
            })
            .finally(() => {
                // Restore button
                applyBtn.innerHTML = originalContent;
                applyBtn.disabled = false;
            });
        }

        function updateRotationStatus() {
            const statusDiv = document.getElementById('rotation-status');
            const appliedRotation = appliedRotations[currentPage] || 0;
            
            let statusText = `Halaman ${currentPage + 1}: `;
            if (appliedRotation !== 0) {
                statusText += `Rotasi diterapkan: ${appliedRotation}°`;
            } else {
                statusText += 'Belum ada rotasi';
            }
            
            if (pendingRotation !== 0) {
                statusText += ` | Rotasi pending: ${pendingRotation}°`;
            }
            
            statusDiv.textContent = statusText;
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const messageSpan = document.getElementById('notification-message');
            
            messageSpan.textContent = message;
            
            // Set color based on type
            notification.className = notification.className.replace(/bg-\w+-500/, 
                type === 'error' ? 'bg-red-500' : 'bg-green-500');
            
            // Show notification
            notification.classList.add('notification-show');
            
            // Hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('notification-show');
            }, 3000);
        }
    </script>
</body>
</html>