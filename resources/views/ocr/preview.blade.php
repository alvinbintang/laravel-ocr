<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Preview - Laravel OCR</title>
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
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold">Preview & Select Regions</h2>
                                <p class="text-gray-600">File: {{ $ocrResult->filename }}</p>
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
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $ocrResult->status }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                            
                            @if($ocrResult->status === 'awaiting_selection' && $ocrResult->page_count > 0)
                            <div class="flex space-x-2">
                                <button id="add-region-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Add Region
                                </button>
                                <button id="clear-regions-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Clear All
                                </button>
                                <button id="process-regions-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium" disabled>
                                    Process OCR
                                </button>
                            </div>
                            @endif
                        </div>

                        @if($ocrResult->status === 'processing')
                        <div class="flex items-center justify-center py-16">
                            <div class="text-center">
                                <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500 mx-auto mb-4"></div>
                                <p class="text-gray-600">Converting PDF to images...</p>
                                <p class="text-sm text-gray-500 mt-2">This may take a few moments</p>
                            </div>
                        </div>
                        @elseif($ocrResult->status === 'error')
                        <div class="bg-red-50 border border-red-200 rounded p-4">
                            <p class="text-red-700">{{ $ocrResult->text }}</p>
                        </div>
                        @elseif($ocrResult->status === 'awaiting_selection' && $ocrResult->page_count > 0)
                        
                        <!-- Page Navigation -->
                        @if($ocrResult->page_count > 1)
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium">Pages ({{ $ocrResult->page_count }} total)</h3>
                                <div class="flex items-center space-x-2">
                                    <button id="prev-page-btn" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1 rounded text-sm" disabled>
                                        Previous
                                    </button>
                                    <span id="page-indicator" class="text-sm text-gray-600">Page 1 of {{ $ocrResult->page_count }}</span>
                                    <button id="next-page-btn" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1 rounded text-sm">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Image Preview Container -->
                        <div class="mb-6">
                            <div id="image-container" class="relative border-2 border-gray-300 rounded-lg overflow-hidden bg-white" style="min-height: 600px;">
                                <img id="preview-image" src="" alt="Document Preview" class="max-w-full h-auto block mx-auto" style="display: none;">
                                <div id="loading-placeholder" class="flex items-center justify-center h-96">
                                    <div class="text-center">
                                        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mx-auto mb-4"></div>
                                        <p class="text-gray-600">Loading image...</p>
                                    </div>
                                </div>
                                <div id="regions-overlay" class="absolute inset-0 pointer-events-none"></div>
                            </div>
                        </div>

                        <!-- Selected Regions List -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium mb-3">Selected Regions</h3>
                            <div id="regions-list" class="space-y-2">
                                <p class="text-gray-500 text-sm" id="no-regions-message">No regions selected. Click "Add Region" and draw on the image to select areas for OCR.</p>
                            </div>
                        </div>

                        @endif

                        <!-- OCR Results Section -->
                        @if($ocrResult->status === 'done' && $ocrResult->ocr_results)
                        <div id="results-section" class="mt-8">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium">OCR Results</h3>
                                <a href="{{ route('ocr.export', $ocrResult->id) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Export to Excel
                                </a>
                            </div>
                            
                            <div class="space-y-4">
                                @foreach($ocrResult->ocr_results as $index => $result)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-medium">Region {{ $index + 1 }} (Page {{ $result['page'] ?? 1 }})</h4>
                                        <span class="text-xs text-gray-500">
                                            {{ $result['coordinates']['width'] ?? 0 }}x{{ $result['coordinates']['height'] ?? 0 }}px
                                        </span>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded border">
                                        <pre class="whitespace-pre-wrap text-sm">{{ $result['text'] ?? 'No text detected' }}</pre>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Back Button -->
                        <div class="mt-6">
                            <a href="{{ route('ocr.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                ← Back to Upload
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .region-container {
            position: absolute;
            border: 2px solid #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
            cursor: move;
            min-width: 20px;
            min-height: 20px;
        }

        .region-number {
            position: absolute;
            top: -25px;
            left: 0;
            background-color: #3b82f6;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .region-controls {
            position: absolute;
            top: -25px;
            right: 0;
            display: flex;
            gap: 2px;
        }

        .region-controls button {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            cursor: pointer;
        }

        .region-controls button:hover {
            background-color: #dc2626;
        }

        .region-resize-handle {
            position: absolute;
            background-color: #3b82f6;
            border: 1px solid #1e40af;
        }

        .region-resize-handle.nw { top: -4px; left: -4px; width: 8px; height: 8px; cursor: nw-resize; }
        .region-resize-handle.n { top: -4px; left: 50%; transform: translateX(-50%); width: 8px; height: 8px; cursor: n-resize; }
        .region-resize-handle.ne { top: -4px; right: -4px; width: 8px; height: 8px; cursor: ne-resize; }
        .region-resize-handle.w { top: 50%; left: -4px; transform: translateY(-50%); width: 8px; height: 8px; cursor: w-resize; }
        .region-resize-handle.e { top: 50%; right: -4px; transform: translateY(-50%); width: 8px; height: 8px; cursor: e-resize; }
        .region-resize-handle.sw { bottom: -4px; left: -4px; width: 8px; height: 8px; cursor: sw-resize; }
        .region-resize-handle.s { bottom: -4px; left: 50%; transform: translateX(-50%); width: 8px; height: 8px; cursor: s-resize; }
        .region-resize-handle.se { bottom: -4px; right: -4px; width: 8px; height: 8px; cursor: se-resize; }

        .drawing-mode {
            cursor: crosshair !important;
        }

        .drawing-mode * {
            cursor: crosshair !important;
        }
    </style>

    <script>
        class RegionManager {
            constructor() {
                this.regions = [];
                this.currentPage = 1;
                this.totalPages = {{ $ocrResult->page_count ?? 1 }};
                this.imagePaths = @json($ocrResult->image_paths ?? []);
                this.isDrawing = false;
                this.drawingRegion = null;
                this.selectedRegion = null;
                this.isResizing = false;
                this.resizeHandle = null;
                
                this.initializeElements();
                this.loadCurrentPage();
                this.bindEvents();
            }

            initializeElements() {
                this.imageContainer = document.getElementById('image-container');
                this.previewImage = document.getElementById('preview-image');
                this.regionsOverlay = document.getElementById('regions-overlay');
                this.regionsList = document.getElementById('regions-list');
                this.noRegionsMessage = document.getElementById('no-regions-message');
                this.loadingPlaceholder = document.getElementById('loading-placeholder');
                
                // Buttons
                this.addRegionBtn = document.getElementById('add-region-btn');
                this.clearRegionsBtn = document.getElementById('clear-regions-btn');
                this.processRegionsBtn = document.getElementById('process-regions-btn');
                
                // Page navigation
                this.prevPageBtn = document.getElementById('prev-page-btn');
                this.nextPageBtn = document.getElementById('next-page-btn');
                this.pageIndicator = document.getElementById('page-indicator');
            }

            bindEvents() {
                // Button events
                if (this.addRegionBtn) {
                    this.addRegionBtn.addEventListener('click', () => this.startDrawing());
                }
                if (this.clearRegionsBtn) {
                    this.clearRegionsBtn.addEventListener('click', () => this.clearAllRegions());
                }
                if (this.processRegionsBtn) {
                    this.processRegionsBtn.addEventListener('click', () => this.processRegions());
                }

                // Page navigation
                if (this.prevPageBtn) {
                    this.prevPageBtn.addEventListener('click', () => this.previousPage());
                }
                if (this.nextPageBtn) {
                    this.nextPageBtn.addEventListener('click', () => this.nextPage());
                }

                // Image container events
                this.imageContainer.addEventListener('mousedown', (e) => this.handleMouseDown(e));
                this.imageContainer.addEventListener('mousemove', (e) => this.handleMouseMove(e));
                this.imageContainer.addEventListener('mouseup', (e) => this.handleMouseUp(e));

                // Image load event
                this.previewImage.addEventListener('load', () => {
                    this.loadingPlaceholder.style.display = 'none';
                    this.previewImage.style.display = 'block';
                    this.updateRegionsDisplay();
                });

                // Prevent context menu on image container
                this.imageContainer.addEventListener('contextmenu', (e) => e.preventDefault());
            }

            loadCurrentPage() {
                if (this.imagePaths.length === 0) return;
                
                const imagePath = this.imagePaths[this.currentPage - 1];
                if (imagePath) {
                    this.loadingPlaceholder.style.display = 'flex';
                    this.previewImage.style.display = 'none';
                    this.previewImage.src = `/storage/${imagePath}`;
                }

                this.updatePageNavigation();
                this.updateRegionsDisplay();
            }

            updatePageNavigation() {
                if (this.pageIndicator) {
                    this.pageIndicator.textContent = `Page ${this.currentPage} of ${this.totalPages}`;
                }
                
                if (this.prevPageBtn) {
                    this.prevPageBtn.disabled = this.currentPage <= 1;
                }
                
                if (this.nextPageBtn) {
                    this.nextPageBtn.disabled = this.currentPage >= this.totalPages;
                }
            }

            previousPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadCurrentPage();
                }
            }

            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadCurrentPage();
                }
            }

            startDrawing() {
                this.imageContainer.classList.add('drawing-mode');
                this.addRegionBtn.textContent = 'Click & Drag to Select';
                this.addRegionBtn.disabled = true;
            }

            stopDrawing() {
                this.imageContainer.classList.remove('drawing-mode');
                this.addRegionBtn.textContent = 'Add Region';
                this.addRegionBtn.disabled = false;
                this.isDrawing = false;
                this.drawingRegion = null;
            }

            handleMouseDown(e) {
                if (!this.imageContainer.classList.contains('drawing-mode')) return;
                
                const rect = this.imageContainer.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                this.isDrawing = true;
                this.drawingRegion = {
                    startX: x,
                    startY: y,
                    currentX: x,
                    currentY: y,
                    page: this.currentPage
                };

                e.preventDefault();
            }

            handleMouseMove(e) {
                if (!this.isDrawing || !this.drawingRegion) return;

                const rect = this.imageContainer.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                this.drawingRegion.currentX = x;
                this.drawingRegion.currentY = y;

                this.updateDrawingPreview();
            }

            handleMouseUp(e) {
                if (!this.isDrawing || !this.drawingRegion) return;

                const minSize = 20;
                const width = Math.abs(this.drawingRegion.currentX - this.drawingRegion.startX);
                const height = Math.abs(this.drawingRegion.currentY - this.drawingRegion.startY);

                if (width >= minSize && height >= minSize) {
                    this.addRegion(this.drawingRegion);
                }

                this.clearDrawingPreview();
                this.stopDrawing();
            }

            updateDrawingPreview() {
                this.clearDrawingPreview();

                const preview = document.createElement('div');
                preview.id = 'drawing-preview';
                preview.className = 'region-container';
                preview.style.pointerEvents = 'none';

                const left = Math.min(this.drawingRegion.startX, this.drawingRegion.currentX);
                const top = Math.min(this.drawingRegion.startY, this.drawingRegion.currentY);
                const width = Math.abs(this.drawingRegion.currentX - this.drawingRegion.startX);
                const height = Math.abs(this.drawingRegion.currentY - this.drawingRegion.startY);

                preview.style.left = left + 'px';
                preview.style.top = top + 'px';
                preview.style.width = width + 'px';
                preview.style.height = height + 'px';

                this.regionsOverlay.appendChild(preview);
            }

            clearDrawingPreview() {
                const preview = document.getElementById('drawing-preview');
                if (preview) {
                    preview.remove();
                }
            }

            addRegion(drawingData) {
                const left = Math.min(drawingData.startX, drawingData.currentX);
                const top = Math.min(drawingData.startY, drawingData.currentY);
                const width = Math.abs(drawingData.currentX - drawingData.startX);
                const height = Math.abs(drawingData.currentY - drawingData.startY);

                const region = {
                    id: Date.now(),
                    x: left,
                    y: top,
                    width: width,
                    height: height,
                    page: drawingData.page
                };

                this.regions.push(region);
                this.updateRegionsDisplay();
                this.updateRegionsList();
                this.updateProcessButton();
            }

            updateRegionsDisplay() {
                // Clear existing regions
                this.regionsOverlay.innerHTML = '';

                // Show only regions for current page
                const currentPageRegions = this.regions.filter(r => r.page === this.currentPage);
                
                currentPageRegions.forEach((region, index) => {
                    const regionElement = this.createRegionElement(region, index + 1);
                    this.regionsOverlay.appendChild(regionElement);
                });
            }

            createRegionElement(region, displayNumber) {
                const regionDiv = document.createElement('div');
                regionDiv.className = 'region-container';
                regionDiv.style.left = region.x + 'px';
                regionDiv.style.top = region.y + 'px';
                regionDiv.style.width = region.width + 'px';
                regionDiv.style.height = region.height + 'px';
                regionDiv.dataset.regionId = region.id;

                // Region number
                const numberLabel = document.createElement('div');
                numberLabel.className = 'region-number';
                numberLabel.textContent = displayNumber;
                regionDiv.appendChild(numberLabel);

                // Delete button
                const controls = document.createElement('div');
                controls.className = 'region-controls';
                const deleteBtn = document.createElement('button');
                deleteBtn.textContent = '×';
                deleteBtn.onclick = () => this.deleteRegion(region.id);
                controls.appendChild(deleteBtn);
                regionDiv.appendChild(controls);

                // Resize handles
                const handles = ['nw', 'n', 'ne', 'w', 'e', 'sw', 's', 'se'];
                handles.forEach(handle => {
                    const handleDiv = document.createElement('div');
                    handleDiv.className = `region-resize-handle ${handle}`;
                    handleDiv.dataset.handle = handle;
                    regionDiv.appendChild(handleDiv);
                });

                return regionDiv;
            }

            deleteRegion(regionId) {
                this.regions = this.regions.filter(r => r.id !== regionId);
                this.updateRegionsDisplay();
                this.updateRegionsList();
                this.updateProcessButton();
            }

            clearAllRegions() {
                this.regions = [];
                this.updateRegionsDisplay();
                this.updateRegionsList();
                this.updateProcessButton();
            }

            updateRegionsList() {
                if (this.regions.length === 0) {
                    this.regionsList.innerHTML = '<p class="text-gray-500 text-sm" id="no-regions-message">No regions selected. Click "Add Region" and draw on the image to select areas for OCR.</p>';
                    return;
                }

                const groupedRegions = {};
                this.regions.forEach(region => {
                    if (!groupedRegions[region.page]) {
                        groupedRegions[region.page] = [];
                    }
                    groupedRegions[region.page].push(region);
                });

                let html = '';
                Object.keys(groupedRegions).sort((a, b) => parseInt(a) - parseInt(b)).forEach(page => {
                    html += `<div class="mb-3">
                        <h4 class="font-medium text-sm text-gray-700 mb-2">Page ${page} (${groupedRegions[page].length} regions)</h4>
                        <div class="space-y-1">`;
                    
                    groupedRegions[page].forEach((region, index) => {
                        html += `<div class="text-sm text-gray-600 bg-gray-50 p-2 rounded">
                            Region ${index + 1}: ${Math.round(region.width)}×${Math.round(region.height)}px at (${Math.round(region.x)}, ${Math.round(region.y)})
                        </div>`;
                    });
                    
                    html += '</div></div>';
                });

                this.regionsList.innerHTML = html;
            }

            updateProcessButton() {
                if (this.processRegionsBtn) {
                    this.processRegionsBtn.disabled = this.regions.length === 0;
                }
            }

            processRegions() {
                if (this.regions.length === 0) {
                    alert('Please select at least one region before processing.');
                    return;
                }

                // Prepare regions data for processing
                const regionsData = this.regions.map(region => ({
                    x: Math.round(region.x),
                    y: Math.round(region.y),
                    width: Math.round(region.width),
                    height: Math.round(region.height),
                    page: region.page
                }));

                // Send to server
                fetch('{{ route("ocr.process-regions", $ocrResult->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        regions: regionsData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to results or reload page
                        window.location.reload();
                    } else {
                        alert('Error processing regions: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing regions. Please try again.');
                });
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            @if($ocrResult->status === 'awaiting_selection' && $ocrResult->page_count > 0)
            new RegionManager();
            @endif

            // Auto-refresh for processing status
            @if($ocrResult->status === 'processing')
            setTimeout(() => {
                window.location.reload();
            }, 3000);
            @endif
        });
    </script>
</body>
</html>