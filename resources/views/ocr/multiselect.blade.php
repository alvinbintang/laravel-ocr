<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Multi-Select - Laravel OCR</title>
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
                                <h2 class="text-2xl font-bold">Multi-Select Regions</h2>
                                <p class="text-gray-600">File: {{ $ocrResult->filename }}</p>
                                <p class="text-gray-600">Jenis Dokumen: {{ $ocrResult->document_type }}</p>
                                <p class="text-sm text-gray-500">Langkah 2: Pilih Area untuk OCR</p>
                                <p class="text-gray-600">
                                    Status: 
                                    @if($ocrResult->status === 'awaiting_selection')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Ready for Selection
                                        </span>
                                    @elseif($ocrResult->status === 'processing')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Converting PDF...
                                        </span>
                                    @elseif($ocrResult->status === 'error')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Error
                                        </span>
                                    @elseif($ocrResult->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Completed
                                        </span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex space-x-3">
                                <a href="/ocr/{{ $ocrResult->id }}/rka-preview" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                                    Kembali ke RKA
                                </a>
                                @if($ocrResult->status === 'completed')
                                    <a href="{{ route('ocr.result', $ocrResult->id) }}" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors">
                                        View Results
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if($ocrResult->status === 'processing')
                            <div class="text-center py-8">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                <p class="mt-2 text-gray-600">Converting PDF to images...</p>
                            </div>
                        @elseif($ocrResult->status === 'error')
                            <div class="text-center py-8">
                                <div class="text-red-600 text-lg">Error processing file</div>
                                <p class="text-gray-600 mt-2">{{ $ocrResult->error_message ?? 'Unknown error occurred' }}</p>
                            </div>
                        @else
                            <!-- Image Preview and Controls -->
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                                <!-- Image Preview Section -->
                                <div class="lg:col-span-3">
                                    <div class="mb-4 flex justify-between items-center">
                                        <div class="flex items-center space-x-4">
                                            <label for="page-selector" class="text-sm font-medium text-gray-700">Page:</label>
                                            <select id="page-selector" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                @foreach($ocrResult->images as $index => $image)
                                                    <option value="{{ $index }}">{{ $index + 1 }}</option>
                                                @endforeach
                                            </select>
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
                                        
                                        <!-- Regions Overlay -->
                                        <div id="regions-overlay" class="absolute inset-0 pointer-events-none">
                                            <!-- Regions will be drawn here -->
                                        </div>
                                    </div>

                                    <!-- Selection Controls -->
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <h3 class="text-lg font-medium text-gray-800 mb-3">Kontrol Seleksi Area</h3>
                                        <div class="flex flex-wrap items-center gap-3 mb-3">
                                            <button id="add-region" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                                Tambah Area
                                            </button>
                                            <button id="clear-regions" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Hapus Semua
                                            </button>
                                            <button id="process-regions" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center" disabled>
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Proses OCR
                                            </button>
                                        </div>
                                        <div id="selection-status" class="text-sm text-gray-600">
                                            Klik "Tambah Area" untuk mulai memilih area OCR
                                        </div>
                                    </div>
                                </div>

                                <!-- Sidebar -->
                                <div class="lg:col-span-1">
                                    <div class="bg-white rounded-lg shadow-md p-6">
                                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Area Terpilih</h3>
                                        <div id="regions-list" class="space-y-2">
                                            <p class="text-sm text-gray-500">Belum ada area yang dipilih</p>
                                        </div>

                                        <hr class="my-4">

                                        <div class="space-y-3">
                                            <h4 class="text-md font-medium text-gray-800">Instruksi</h4>
                                            <ul class="text-sm text-gray-600 space-y-1">
                                                <li>• Klik "Tambah Area" untuk mulai</li>
                                                <li>• Drag untuk membuat kotak seleksi</li>
                                                <li>• Klik area untuk mengedit</li>
                                                <li>• Tekan Delete untuk menghapus</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </main>

        <!-- Notification -->
        <div id="notification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span id="notification-message">Success!</span>
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

        .region {
            position: absolute;
            border: 2px solid #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
            cursor: move;
            min-width: 20px;
            min-height: 20px;
        }

        .region:hover {
            border-color: #1d4ed8;
            background-color: rgba(29, 78, 216, 0.2);
        }

        .region.selected {
            border-color: #dc2626;
            background-color: rgba(220, 38, 38, 0.1);
        }

        .region-handle {
            position: absolute;
            width: 8px;
            height: 8px;
            background-color: #3b82f6;
            border: 1px solid #ffffff;
            cursor: nw-resize;
        }

        .region-handle.nw { top: -4px; left: -4px; cursor: nw-resize; }
        .region-handle.ne { top: -4px; right: -4px; cursor: ne-resize; }
        .region-handle.sw { bottom: -4px; left: -4px; cursor: sw-resize; }
        .region-handle.se { bottom: -4px; right: -4px; cursor: se-resize; }

        .region-label {
            position: absolute;
            top: -20px;
            left: 0;
            background-color: #3b82f6;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        #regions-overlay {
            pointer-events: auto;
        }

        .drawing-mode {
            cursor: crosshair !important;
        }
    </style>

    <script>
        // Global variables
        let currentPage = 0;
        let appliedRotations = @json($ocrResult->page_rotations ?? []); // UPDATED: Use page_rotations from database
        const images = @json($ocrResult->images);
        const ocrResultId = {{ $ocrResult->id }};
        let regionManager;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeRegionManager();
            initializeEventListeners();
            loadCurrentPage();
        });

        function initializeEventListeners() {
            // Page selector
            document.getElementById('page-selector').addEventListener('change', function() {
                currentPage = parseInt(this.value);
                loadCurrentPage();
            });

            // Region controls
            document.getElementById('add-region').addEventListener('click', () => regionManager.startDrawing());
            document.getElementById('clear-regions').addEventListener('click', () => regionManager.clearAllRegions());
            document.getElementById('process-regions').addEventListener('click', processRegions);
        }

        function loadCurrentPage() {
            const previewImage = document.getElementById('preview-image');
            previewImage.src = `/storage/${images[currentPage]}`;
            
            // Clear regions when changing pages
            regionManager.clearAllRegions();
            updateProcessButton();
        }

        function initializeRegionManager() {
            regionManager = new RegionManager('regions-overlay', 'preview-image');
        }

        function updateProcessButton() {
            const processBtn = document.getElementById('process-regions');
            const hasRegions = regionManager.getRegions().length > 0;
            processBtn.disabled = !hasRegions;
            
            const statusDiv = document.getElementById('selection-status');
            if (hasRegions) {
                statusDiv.textContent = `${regionManager.getRegions().length} area dipilih. Siap untuk diproses.`;
            } else {
                statusDiv.textContent = 'Klik "Tambah Area" untuk mulai memilih area OCR';
            }
        }

        function processRegions() {
            const regions = regionManager.getRegions();
            if (regions.length === 0) {
                showNotification('Pilih minimal satu area terlebih dahulu', 'error');
                return;
            }

            const processBtn = document.getElementById('process-regions');
            const originalContent = processBtn.innerHTML;
            
            // Show loading state
            processBtn.innerHTML = '<div class="loading-spinner-small"></div>Memproses...';
            processBtn.disabled = true;

            // Prepare regions data
            const regionsData = regions.map((region, index) => ({
                id: region.id,
                x: region.x,
                y: region.y,
                width: region.width,
                height: region.height,
                page: currentPage,
                label: `Region ${index + 1}`
            }));

            // Get preview dimensions
            const previewImage = document.getElementById('preview-image');
            const previewDimensions = {
                width: previewImage.naturalWidth,
                height: previewImage.naturalHeight,
                displayWidth: previewImage.offsetWidth,
                displayHeight: previewImage.offsetHeight
            };

            fetch(`{{ route('ocr.crop-regions', $ocrResult->id) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    regions: regionsData,
                    previewDimensions: previewDimensions,
                    appliedRotations: appliedRotations
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Memulai proses OCR...', 'success');
                    // Start polling for completion
                    pollForCompletion();
                } else {
                    showNotification('Gagal memproses: ' + (data.message || 'Unknown error'), 'error');
                    processBtn.innerHTML = originalContent;
                    processBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat memproses', 'error');
                processBtn.innerHTML = originalContent;
                processBtn.disabled = false;
            });
        }

        function pollForCompletion() {
            const pollInterval = setInterval(() => {
                fetch(`/ocr/${ocrResultId}/status`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'completed') {
                            clearInterval(pollInterval);
                            showNotification('OCR selesai! Mengarahkan ke hasil...', 'success');
                            setTimeout(() => {
                                window.location.href = `{{ route('ocr.result', $ocrResult->id) }}`;
                            }, 2000);
                        } else if (data.status === 'error') {
                            clearInterval(pollInterval);
                            showNotification('Terjadi kesalahan saat memproses OCR', 'error');
                            const processBtn = document.getElementById('process-regions');
                            processBtn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Proses OCR';
                            processBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Polling error:', error);
                    });
            }, 2000);
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

        // Region Manager Class
        class RegionManager {
            constructor(overlayId, imageId) {
                this.overlay = document.getElementById(overlayId);
                this.image = document.getElementById(imageId);
                this.regions = [];
                this.isDrawing = false;
                this.currentRegion = null;
                this.dragData = null;
                this.resizeData = null;
                this.regionCounter = 0;

                this.bindEvents();
            }

            // ADDED: Function to adjust coordinates for rotated images
            getAdjustedCoords(x, y) {
                const rotation = appliedRotations[currentPage] || 0;
                if (!this.image) return { x, y };

                const w = this.image.offsetWidth;
                const h = this.image.offsetHeight;

                // Only adjust coordinates if image has been rotated and applied to backend
                if (rotation === 0) return { x, y };

                switch (rotation) {
                    case 90:
                        // 90 degrees clockwise rotation
                        return { x: h - y, y: x };
                    case 180:
                        // 180 degrees rotation
                        return { x: w - x, y: h - y };
                    case 270:
                        // 270 degrees clockwise rotation
                        return { x: y, y: w - x };
                    default:
                        return { x, y };
                }
            }

            bindEvents() {
                this.overlay.addEventListener('mousedown', this.handleMouseDown.bind(this));
                this.overlay.addEventListener('mousemove', this.handleMouseMove.bind(this));
                this.overlay.addEventListener('mouseup', this.handleMouseUp.bind(this));
                document.addEventListener('keydown', this.handleKeyDown.bind(this));
            }

            startDrawing() {
                this.isDrawing = true;
                this.overlay.classList.add('drawing-mode');
                document.getElementById('selection-status').textContent = 'Drag untuk membuat area seleksi...';
            }

            handleMouseDown(e) {
                if (!this.isDrawing) return;

                const rect = this.overlay.getBoundingClientRect();
                const imageRect = this.image.getBoundingClientRect();
                
                // Calculate position relative to image
                const imgLeft = (rect.width - imageRect.width) / 2;
                const imgTop = (rect.height - imageRect.height) / 2;
                
                let x = e.clientX - rect.left;
                let y = e.clientY - rect.top;
                
                // Check if click is within image bounds
                if (x < imgLeft || x > imgLeft + imageRect.width || 
                    y < imgTop || y > imgTop + imageRect.height) return;
                
                // Adjust coordinates relative to image
                x = x - imgLeft;
                y = y - imgTop;
                
                // ADDED: Apply coordinate adjustment for rotated images
                const adjusted = this.getAdjustedCoords(x, y);

                this.currentRegion = {
                    id: ++this.regionCounter,
                    startX: adjusted.x,
                    startY: adjusted.y,
                    x: adjusted.x,
                    y: adjusted.y,
                    width: 0,
                    height: 0,
                    element: null
                };

                // Create region element
                const regionElement = document.createElement('div');
                regionElement.className = 'region';
                regionElement.style.left = (adjusted.x + imgLeft) + 'px';
                regionElement.style.top = (adjusted.y + imgTop) + 'px';
                regionElement.style.width = '0px';
                regionElement.style.height = '0px';
                
                this.overlay.appendChild(regionElement);
                this.currentRegion.element = regionElement;
            }

            handleMouseMove(e) {
                if (!this.isDrawing || !this.currentRegion) return;

                const rect = this.overlay.getBoundingClientRect();
                const imageRect = this.image.getBoundingClientRect();
                
                // Calculate position relative to image
                const imgLeft = (rect.width - imageRect.width) / 2;
                const imgTop = (rect.height - imageRect.height) / 2;
                
                let x = e.clientX - rect.left - imgLeft;
                let y = e.clientY - rect.top - imgTop;
                
                // Clamp coordinates to image bounds
                x = Math.max(0, Math.min(x, imageRect.width));
                y = Math.max(0, Math.min(y, imageRect.height));
                
                // ADDED: Apply coordinate adjustment for rotated images
                const adjusted = this.getAdjustedCoords(x, y);

                const width = Math.abs(adjusted.x - this.currentRegion.startX);
                const height = Math.abs(adjusted.y - this.currentRegion.startY);
                const left = Math.min(adjusted.x, this.currentRegion.startX);
                const top = Math.min(adjusted.y, this.currentRegion.startY);

                this.currentRegion.x = left;
                this.currentRegion.y = top;
                this.currentRegion.width = width;
                this.currentRegion.height = height;

                this.currentRegion.element.style.left = (left + imgLeft) + 'px';
                this.currentRegion.element.style.top = (top + imgTop) + 'px';
                this.currentRegion.element.style.width = width + 'px';
                this.currentRegion.element.style.height = height + 'px';
            }

            handleMouseUp(e) {
                if (!this.isDrawing || !this.currentRegion) return;

                // Only add region if it has meaningful size
                if (this.currentRegion.width > 10 && this.currentRegion.height > 10) {
                    this.regions.push(this.currentRegion);
                    this.addRegionLabel(this.currentRegion);
                    this.updateRegionsList();
                } else {
                    // Remove small regions
                    this.currentRegion.element.remove();
                }

                this.isDrawing = false;
                this.currentRegion = null;
                this.overlay.classList.remove('drawing-mode');
                updateProcessButton();
            }

            handleKeyDown(e) {
                if (e.key === 'Delete' && this.selectedRegion) {
                    this.removeRegion(this.selectedRegion);
                }
            }

            addRegionLabel(region) {
                const label = document.createElement('div');
                label.className = 'region-label';
                label.textContent = `Area ${region.id}`;
                region.element.appendChild(label);
            }

            removeRegion(region) {
                const index = this.regions.findIndex(r => r.id === region.id);
                if (index > -1) {
                    this.regions.splice(index, 1);
                    region.element.remove();
                    this.updateRegionsList();
                    updateProcessButton();
                }
            }

            clearAllRegions() {
                this.regions.forEach(region => {
                    region.element.remove();
                });
                this.regions = [];
                this.updateRegionsList();
                updateProcessButton();
            }

            updateRegionsList() {
                const listContainer = document.getElementById('regions-list');
                
                if (this.regions.length === 0) {
                    listContainer.innerHTML = '<p class="text-sm text-gray-500">Belum ada area yang dipilih</p>';
                    return;
                }

                const listHTML = this.regions.map(region => `
                    <div class="flex justify-between items-center p-2 bg-gray-100 rounded">
                        <span class="text-sm">Area ${region.id}</span>
                        <button onclick="regionManager.removeRegion(regionManager.regions.find(r => r.id === ${region.id}))" 
                                class="text-red-500 hover:text-red-700 text-xs">
                            Hapus
                        </button>
                    </div>
                `).join('');

                listContainer.innerHTML = listHTML;
            }

            getRegions() {
                return this.regions;
            }
        }
    </script>
</body>
</html>