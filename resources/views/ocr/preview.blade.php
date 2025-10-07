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
                                <p class="text-gray-600">Jenis Dokumen: {{ $ocrResult->document_type }}</p>
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
                        <div class="relative mb-6" id="image-preview-container">
                            <!-- Rotation Controls -->
                            <div class="flex justify-end mb-2">
                                <div class="flex space-x-2">
                                    <button id="rotate-left-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                        </svg>
                                        Rotate Left
                                    </button>
                                    <button id="rotate-right-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm flex items-center">
                                        Rotate Right
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <script>
                                // Initialize rotation variables
                                let pageRotations = {!! json_encode($ocrResult->page_rotations ?? []) !!} || {};
                                let currentPage = 1;
                                
                                // Wait for DOM to be fully loaded
                                document.addEventListener('DOMContentLoaded', function() {
                                    const rotateLeftBtn = document.getElementById('rotate-left-btn');
                                    const rotateRightBtn = document.getElementById('rotate-right-btn');
                                    const previewImage = document.getElementById('preview-image');
                                    
                                    // Add event listeners for rotation buttons
                                    if (rotateLeftBtn) {
                                        rotateLeftBtn.addEventListener('click', function() {
                                            rotateImage(-90);
                                        });
                                    }
                                    
                                    if (rotateRightBtn) {
                                        rotateRightBtn.addEventListener('click', function() {
                                            rotateImage(90);
                                        });
                                    }
                                    
                                    // Apply initial rotation if exists
                                applyCurrentPageRotation();
                                
                                // Update currentPage when page changes in RegionSelector
                                document.addEventListener('pageChanged', function(e) {
                                    currentPage = e.detail.page;
                                    applyCurrentPageRotation();
                                });
                            });
                            
                            // Function to rotate image
                            function rotateImage(degrees) {
                                const previewImage = document.getElementById('preview-image');
                                if (!previewImage) return;
                                
                                // Get current rotation or default to 0
                                const currentRotation = pageRotations[currentPage] || 0;
                                
                                // Calculate new rotation (normalize to 0-359)
                                let newRotation = (currentRotation + degrees) % 360;
                                if (newRotation < 0) newRotation += 360;
                                
                                // Update rotation value
                                pageRotations[currentPage] = newRotation;
                                
                                // Apply rotation
                                applyCurrentPageRotation();
                                
                                // Save rotations to server
                                saveRotations();
                            }
                                
                                // Apply rotation to current page
                                function applyCurrentPageRotation() {
                                    const previewImage = document.getElementById('preview-image');
                                    if (!previewImage) return;
                                    
                                    const rotation = pageRotations[currentPage] || 0;
                                    previewImage.style.transform = `rotate(${rotation}deg)`;
                                    
                                    // Update RegionSelector's currentPage if it exists
                                    if (window.regionSelector && window.regionSelector.currentPage !== currentPage) {
                                        window.regionSelector.currentPage = currentPage;
                                    }
                                }
                                
                                // Save rotations to server
                                function saveRotations() {
                                    fetch(`/ocr/{{ $ocrResult->id }}/save-rotations`, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            rotations: pageRotations
                                        })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (!data.success) {
                                            console.error('Error saving rotations:', data.message);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error saving rotations:', error);
                                    });
                                }
                            </script>
                            <div id="image-container" class="relative border border-gray-300 rounded overflow-hidden">
                                <img id="preview-image" src="{{ $ocrResult->getImagePathForPage(1) }}" class="max-w-full h-auto" style="display: none;">
                                <!-- UPDATED: Improved loading placeholder with spinner -->
                                <div id="loading-placeholder" class="flex items-center justify-center bg-gray-100 h-96">
                                    <div class="text-center">
                                        <div class="loading-spinner"></div>
                                        <p class="text-gray-600">Loading page {{ $currentPage ?? 1 }} of {{ $ocrResult->page_count ?? 1 }}...</p>
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
                                <div class="flex space-x-2">
                                    <button id="convert-to-json-btn" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Convert to Structured JSON
                                    </button>
                                    <a href="{{ route('ocr.export', $ocrResult->id) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Export to Excel
                                    </a>
                                    <a href="{{ route('ocr.export-json', $ocrResult->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Export JSON
                                    </a>
                                    <a href="{{ route('ocr.export-csv', $ocrResult->id) }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Export CSV
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Tab navigation -->
                            <div class="border-b border-gray-200 mb-4">
                                <nav class="-mb-px flex space-x-8">
                                    <button id="tab-cards" class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Cards View
                                    </button>
                                    <button id="tab-table" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Table View
                                    </button>
                                    <button id="tab-json" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        JSON View
                                    </button>
                                </nav>
                            </div>
                            
                            <!-- Cards View -->
                            <div id="view-cards" class="tab-content">
                                <div class="space-y-4">
                                    @foreach($ocrResult->ocr_results as $index => $result)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-medium">Region {{ $index + 1 }} (Page {{ $result['page'] ?? 1 }})</h4>
                                            <span class="text-xs text-gray-500">
                                                {{ $result['coordinates']['width'] ?? 0 }}x{{ $result['coordinates']['height'] ?? 0 }}px
                                            </span>
                                        </div>
                                        
                                        <!-- ADDED: Display cropped image if available -->
                                        @if(isset($ocrResult->cropped_images) && is_array($ocrResult->cropped_images))
                                            @php
                                                $croppedImage = collect($ocrResult->cropped_images)->firstWhere(function($image) use ($result) {
                                                    return isset($image['region_id']) && $image['region_id'] == ($result['region_id'] ?? null) &&
                                                           isset($image['page']) && $image['page'] == ($result['page'] ?? 1);
                                                });
                                            @endphp
                                            @if($croppedImage && isset($croppedImage['path']))
                                            <div class="mb-3">
                                                <h5 class="text-sm font-medium text-gray-700 mb-2">Selected Image:</h5>
                                                <div class="border border-gray-300 rounded-lg p-2 bg-white inline-block">
                                                    <img src="{{ asset('storage/' . $croppedImage['path']) }}" 
                                                         alt="Cropped region {{ $index + 1 }}" 
                                                         class="max-w-xs max-h-32 object-contain rounded shadow-sm">
                                                </div>
                                            </div>
                                            @endif
                                        @endif
                                        
                                        <div class="bg-gray-50 p-3 rounded border">
                                            <h5 class="text-sm font-medium text-gray-700 mb-2">Extracted Text:</h5>
                                            <pre class="whitespace-pre-wrap text-sm">{{ $result['text'] ?? 'No text detected' }}</pre>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            
                            <!-- Table View -->
                            <div id="view-table" class="tab-content hidden">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selected Image</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Text</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($ocrResult->ocr_results as $index => $result)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $result['page'] ?? 1 }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <!-- ADDED: Display cropped image in table -->
                                                        @if(isset($ocrResult->cropped_images) && is_array($ocrResult->cropped_images))
                                                            @php
                                                                $croppedImage = collect($ocrResult->cropped_images)->firstWhere(function($image) use ($result) {
                                                                    return isset($image['region_id']) && $image['region_id'] == ($result['region_id'] ?? null) &&
                                                                           isset($image['page']) && $image['page'] == ($result['page'] ?? 1);
                                                                });
                                                            @endphp
                                                            @if($croppedImage && isset($croppedImage['path']))
                                                                <img src="{{ asset('storage/' . $croppedImage['path']) }}" 
                                                                     alt="Cropped region {{ $index + 1 }}" 
                                                                     class="w-16 h-16 object-contain border border-gray-300 rounded">
                                                            @else
                                                                <span class="text-gray-400 text-xs">No image</span>
                                                            @endif
                                                        @else
                                                            <span class="text-gray-400 text-xs">No image</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        X: {{ $result['coordinates']['x'] ?? 0 }}, 
                                                        Y: {{ $result['coordinates']['y'] ?? 0 }}, 
                                                        W: {{ $result['coordinates']['width'] ?? 0 }}, 
                                                        H: {{ $result['coordinates']['height'] ?? 0 }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-500">
                                                        <div class="max-h-20 overflow-y-auto">
                                                            {{ $result['text'] ?? 'No text detected' }}
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- JSON View -->
                            <div id="view-json" class="tab-content hidden">
                                <div class="bg-gray-800 text-white p-4 rounded-md overflow-x-auto">
                                    <pre class="text-sm">{{ json_encode($ocrResult->ocr_results, JSON_PRETTY_PRINT) }}</pre>
                                </div>
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
        .image-container {
            position: relative;
            display: inline-block;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f9fafb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 100%;
            /* UPDATED: Improved responsive image container */
            width: fit-content;
            margin: 0 auto;
        }

        .preview-image {
            display: block;
            max-width: 100%;
            height: auto;
            /* UPDATED: Improved image display */
            min-height: 400px;
            object-fit: contain;
            background-color: white;
        }

        .loading-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            background-color: #f3f4f6;
            color: #6b7280;
            font-size: 14px;
            /* UPDATED: Better loading state */
            flex-direction: column;
            gap: 12px;
        }

        .loading-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #e5e7eb;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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
                this.nextRegionId = 1;
                
                this.initializeElements();
                this.loadCurrentPage();
                this.bindEvents();
                
                // Load any existing regions from server if available
                this.loadExistingRegions();
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
                
                // Add keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    // Escape key to cancel drawing
                    if (e.key === 'Escape' && this.isDrawing) {
                        this.stopDrawing();
                        this.clearDrawingPreview();
                    }
                    
                    // Delete key to remove selected region
                    if (e.key === 'Delete' && this.selectedRegion) {
                        this.deleteRegion(this.selectedRegion);
                    }
                });
            }

            loadCurrentPage() {
                if (this.imagePaths.length === 0) return;
                
                const imagePath = this.imagePaths[this.currentPage - 1];
                if (imagePath) {
                    this.loadingPlaceholder.style.display = 'flex';
                    this.previewImage.style.display = 'none';
                    
                    // UPDATED: Improved image loading with error handling
                    this.previewImage.onerror = () => {
                        console.error('Failed to load image:', imagePath);
                        this.loadingPlaceholder.innerHTML = '<p class="text-red-500">Failed to load image for page ' + this.currentPage + '</p>';
                    };
                    
                    // Clear any previous error handlers
                    this.previewImage.onload = () => {
                        this.loadingPlaceholder.style.display = 'none';
                        this.previewImage.style.display = 'block';
                        this.updateRegionsDisplay();
                    };
                    
                    // UPDATED: Use asset helper for proper public storage path
                    this.previewImage.src = `{{ asset('storage') }}/${imagePath}`;
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
                
                // Apply rotation if exists for current page
                if (this.pageRotations && this.pageRotations[this.currentPage]) {
                    this.previewImage.style.transform = `rotate(${this.pageRotations[this.currentPage]}deg)`;
                } else {
                    this.previewImage.style.transform = 'rotate(0deg)';
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
                    id: this.nextRegionId++,
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

            loadExistingRegions() {
                // Check if there are any saved regions in the OcrResult
                const savedRegions = {!! json_encode($ocrResult->selected_regions ?? []) !!};
                
                if (savedRegions && savedRegions.length > 0) {
                    savedRegions.forEach(region => {
                        this.regions.push({
                            id: this.nextRegionId++,
                            x: region.x,
                            y: region.y,
                            width: region.width,
                            height: region.height,
                            page: region.page || 1
                        });
                    });
                    
                    this.updateRegionsList();
                    this.updateProcessButton();
                }
            }
            
            updateRegionsDisplay() {
                // Clear existing regions
                this.regionsOverlay.innerHTML = '';

                // Show only regions for current page
                const currentPageRegions = this.regions.filter(r => r.page === this.currentPage);
                
                // UPDATED: Improved region display with better numbering
                currentPageRegions.forEach((region, index) => {
                    // Calculate global region number across all pages
                    const allRegionsBeforeCurrentPage = this.regions.filter(r => r.page < this.currentPage).length;
                    const globalRegionNumber = allRegionsBeforeCurrentPage + index + 1;
                    
                    const regionElement = this.createRegionElement(region, globalRegionNumber);
                    this.regionsOverlay.appendChild(regionElement);
                });
                
                // Update process button state based on total regions
                this.updateProcessButton();
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
                let globalRegionCounter = 1; // ADDED: Global counter for consistent numbering
                
                Object.keys(groupedRegions).sort((a, b) => parseInt(a) - parseInt(b)).forEach(page => {
                    html += `<div class="mb-3">
                        <h4 class="font-medium text-sm text-gray-700 mb-2">Page ${page} (${groupedRegions[page].length} regions)</h4>
                        <div class="space-y-1">`;
                    
                    groupedRegions[page].forEach((region, index) => {
                        html += `<div class="text-sm text-gray-600 bg-gray-50 p-2 rounded flex justify-between items-center">
                            <span>Region ${globalRegionCounter}: ${Math.round(region.width)}×${Math.round(region.height)}px at (${Math.round(region.x)}, ${Math.round(region.y)})</span>
                            <button onclick="regionManager.deleteRegion(${region.id})" class="text-red-500 hover:text-red-700 text-xs px-2 py-1 rounded">Delete</button>
                        </div>`;
                        globalRegionCounter++; // UPDATED: Increment global counter
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

                // Disable buttons and show processing state
                this.processRegionsBtn.disabled = true;
                this.processRegionsBtn.innerHTML = '<span class="inline-block animate-spin mr-2">↻</span> Processing...';
                this.addRegionBtn.disabled = true;
                this.clearRegionsBtn.disabled = true;

                // Prepare regions data for processing
                const regionsData = this.regions.map(region => ({
                    id: region.id,
                    x: Math.round(region.x),
                    y: Math.round(region.y),
                    width: Math.round(region.width),
                    height: Math.round(region.height),
                    page: region.page
                }));

                // ADDED: Capture preview image dimensions for coordinate scaling
                const previewDimensions = {
                    width: this.previewImage.clientWidth,
                    height: this.previewImage.clientHeight
                };

                // ADDED: Get current page rotation
                const currentRotation = pageRotations[this.currentPage] || 0;

                // Send to server
                fetch('{{ route("ocr.process-regions", $ocrResult->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        regions: regionsData,
                        previewDimensions: previewDimensions, // ADDED: Include preview dimensions
                        pageRotation: currentRotation // ADDED: Include current page rotation
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show processing message
                        const processingDiv = document.createElement('div');
                        processingDiv.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                        processingDiv.innerHTML = `
                            <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
                                <h3 class="text-lg font-medium mb-4">Processing OCR</h3>
                                <div class="flex items-center mb-4">
                                    <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mr-3"></div>
                                    <p>Processing ${this.regions.length} regions across ${Object.keys(this.regions.reduce((acc, r) => ({...acc, [r.page]: true}), {})).length} pages...</p>
                                </div>
                                <p class="text-sm text-gray-500">This may take a few moments. Please don't close this window.</p>
                            </div>
                        `;
                        document.body.appendChild(processingDiv);
                        
                        // Poll for status and redirect when done
                        this.pollForResults();
                    } else {
                        // Reset buttons
                        this.processRegionsBtn.disabled = false;
                        this.processRegionsBtn.textContent = 'Process OCR';
                        this.addRegionBtn.disabled = false;
                        this.clearRegionsBtn.disabled = false;
                        
                        alert('Error processing regions: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Reset buttons
                    this.processRegionsBtn.disabled = false;
                    this.processRegionsBtn.textContent = 'Process OCR';
                    this.addRegionBtn.disabled = false;
                    this.clearRegionsBtn.disabled = false;
                    
                    alert('Error processing regions. Please try again.');
                });
            }
            
            pollForResults() {
                const checkStatus = () => {
                    fetch('{{ route("ocr.status-check", $ocrResult->id) }}')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'done') {
                                window.location.href = '{{ route("ocr.index") }}';
                            } else if (data.status === 'error') {
                                alert('Error processing OCR: ' + (data.message || 'Unknown error'));
                                window.location.reload();
                            } else {
                                // Still processing, check again in 2 seconds
                                setTimeout(checkStatus, 2000);
                            }
                        })
                        .catch(error => {
                            console.error('Error checking status:', error);
                            // Try again in 3 seconds
                            setTimeout(checkStatus, 3000);
                        });
                };
                
                // Start polling
                setTimeout(checkStatus, 2000);
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            @if($ocrResult->status === 'awaiting_selection' && $ocrResult->page_count > 0)
            window.regionManager = new RegionManager(); // UPDATED: Make regionManager globally accessible
            @endif

            // Auto-refresh for processing status
            @if($ocrResult->status === 'processing')
            setTimeout(() => {
                window.location.reload();
            }, 3000);
            @endif
            
            // Tab switching functionality for OCR results
            if (document.getElementById('tab-cards')) {
                const tabs = ['cards', 'table', 'json'];
                const tabButtons = tabs.map(tab => document.getElementById(`tab-${tab}`));
                const tabViews = tabs.map(tab => document.getElementById(`view-${tab}`));
                
                tabButtons.forEach((button, index) => {
                    button.addEventListener('click', () => {
                        // Update tab buttons
                        tabButtons.forEach(btn => {
                            btn.classList.remove('border-blue-500', 'text-blue-600');
                            btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                        });
                        button.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                        button.classList.add('border-blue-500', 'text-blue-600');
                        
                        // Update tab content
                        tabViews.forEach(view => view.classList.add('hidden'));
                        tabViews[index].classList.remove('hidden');
                    });
                });
            }

            // ADDED: JSON Conversion functionality
            const convertToJsonBtn = document.getElementById('convert-to-json-btn');
            if (convertToJsonBtn) {
                convertToJsonBtn.addEventListener('click', function() {
                    convertOcrToStructuredJson();
                });
            }
        });

        // ADDED: OCR to Structured JSON Conversion Function
        function convertOcrToStructuredJson() {
            // Get OCR results from the page
            const ocrResults = @json($ocrResult->ocr_results ?? []);
            
            if (!ocrResults || ocrResults.length === 0) {
                alert('Tidak ada hasil OCR yang tersedia untuk dikonversi.');
                return;
            }

            // Parse each page/region
            const parsedResults = ocrResults.map((result, index) => {
                return parsePage(result.text || '', index + 1, result.page || 1);
            });

            // Combine all results into a single structured JSON
            const combinedResult = {
                metadata: {
                    total_pages: parsedResults.length,
                    conversion_date: new Date().toISOString(),
                    source_file: '{{ $ocrResult->filename ?? "unknown" }}'
                },
                documents: parsedResults
            };

            // Display the result in a modal
            showJsonModal(combinedResult);
        }

        // ADDED: Parse individual page function
        function parsePage(text, regionIndex, pageNumber) {
            const data = {
                region_id: regionIndex,
                page_number: pageNumber,
                bidang: null,
                sub_bidang: null,
                kegiatan: null,
                waktu_pelaksanaan: null,
                output_keluaran: null,
                belanja: []
            };

            if (!text || text.trim() === '') {
                return data;
            }

            // Header detection with improved regex patterns
            const bidangMatch = text.match(/Bidang\s*:?\s*(.+?)(?:\n|$)/i);
            if (bidangMatch) data.bidang = bidangMatch[1].trim();

            const subMatch = text.match(/Sub\s*Bidang\s*:?\s*(.+?)(?:\n|$)/i);
            if (subMatch) data.sub_bidang = subMatch[1].trim();

            const kegMatch = text.match(/Kegiatan\s*:?\s*(.+?)(?:\n|$)/i);
            if (kegMatch) data.kegiatan = kegMatch[1].trim();

            const waktuMatch = text.match(/Waktu\s*Pelaksanaan\s*:?\s*(.+?)(?:\n|$)/i);
            if (waktuMatch) data.waktu_pelaksanaan = waktuMatch[1].trim();

            const outMatch = text.match(/Output\/Keluaran\s*:?\s*(.+?)(?:\n|$)/i);
            if (outMatch) data.output_keluaran = outMatch[1].trim();

            // Belanja/Table parsing
            const lines = text.split("\n").map(l => l.trim()).filter(l => l);
            
            lines.forEach(line => {
                // Detect table rows with price format and DDS
                if (/\d{1,3}(\.\d{3})*,\d{2}/.test(line) && /DDS/i.test(line)) {
                    // Try structured parsing first
                    const parts = line.split(/\s{2,}|\t/).filter(Boolean);
                    if (parts.length >= 5) {
                        data.belanja.push({
                            kode: parts[0].replace(/\.$/, ""),
                            uraian: parts[1],
                            volume: parts[2],
                            harga_satuan: parts[3],
                            jumlah: parts[4]
                        });
                    } else {
                        // Fallback parsing for messy OCR
                        const tokens = line.split(/\s+/);
                        const priceTokens = tokens.filter(t => /\d{1,3}(\.\d{3})*,\d{2}/.test(t));
                        if (priceTokens.length >= 2) {
                            const kodeIndex = 0;
                            const hargaIndex = tokens.lastIndexOf(priceTokens[priceTokens.length - 2]);
                            const jumlahIndex = tokens.lastIndexOf(priceTokens[priceTokens.length - 1]);
                            const volumeIndex = hargaIndex - 1;
                            
                            data.belanja.push({
                                kode: tokens[kodeIndex] || '',
                                uraian: tokens.slice(1, volumeIndex).join(" ") || '',
                                volume: tokens[volumeIndex] || '',
                                harga_satuan: tokens[hargaIndex] || '',
                                jumlah: tokens[jumlahIndex] || ''
                            });
                        }
                    }
                }
            });

            return data;
        }

        // ADDED: Show JSON result in modal
        function showJsonModal(jsonData) {
            // Create modal HTML
            const modalHtml = `
                <div id="json-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
                        <div class="flex justify-between items-center p-6 border-b">
                            <h3 class="text-lg font-medium">Structured JSON Result</h3>
                            <div class="flex space-x-2">
                                <button id="copy-json-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Copy JSON
                                </button>
                                <button id="download-json-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Download JSON
                                </button>
                                <button id="close-modal-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Close
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-auto p-6">
                            <pre id="json-content" class="bg-gray-800 text-white p-4 rounded-md text-sm overflow-auto">${JSON.stringify(jsonData, null, 2)}</pre>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Add event listeners
            document.getElementById('close-modal-btn').addEventListener('click', function() {
                document.getElementById('json-modal').remove();
            });

            document.getElementById('copy-json-btn').addEventListener('click', function() {
                const jsonText = JSON.stringify(jsonData, null, 2);
                navigator.clipboard.writeText(jsonText).then(function() {
                    alert('JSON berhasil disalin ke clipboard!');
                }).catch(function() {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = jsonText;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('JSON berhasil disalin ke clipboard!');
                });
            });

            document.getElementById('download-json-btn').addEventListener('click', function() {
                const jsonText = JSON.stringify(jsonData, null, 2);
                const blob = new Blob([jsonText], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `structured_ocr_${new Date().getTime()}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            // Close modal when clicking outside
            document.getElementById('json-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.remove();
                }
            });
        }
    </script>
</body>
</html>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tambahkan variabel untuk rotasi
        let pageRotations = {!! json_encode($ocrResult->page_rotations ?? []) !!};
        
        // Inisialisasi rotasi halaman jika belum diatur
        if (!pageRotations || Object.keys(pageRotations).length === 0) {
            pageRotations = {};
            for (let i = 1; i <= totalPages; i++) {
                pageRotations[i] = 0;
            }
        }
        
        // Tambahkan event listener untuk tombol rotasi
        const rotateLeftBtn = document.getElementById('rotate-left-btn');
        const rotateRightBtn = document.getElementById('rotate-right-btn');
        
        if (rotateLeftBtn) {
            rotateLeftBtn.addEventListener('click', function() {
                rotateImage(-90);
            });
        }
        
        if (rotateRightBtn) {
            rotateRightBtn.addEventListener('click', function() {
                rotateImage(90);
            });
        }
        
        // Fungsi untuk merotasi gambar
        function rotateImage(degrees) {
            // Dapatkan rotasi saat ini untuk halaman ini
            const currentRotation = pageRotations[currentPage] || 0;
            // Hitung rotasi baru (0, 90, 180, 270)
            let newRotation = (currentRotation + degrees) % 360;
            if (newRotation < 0) newRotation += 360;
            
            // Simpan rotasi baru
            pageRotations[currentPage] = newRotation;
            
            // Terapkan rotasi ke gambar
            applyRotation();
            
            // Simpan rotasi ke server
            saveRotation();
        }
        
        // Fungsi untuk menerapkan rotasi ke gambar
        function applyRotation() {
            const rotation = pageRotations[currentPage] || 0;
            previewImage.style.transform = `rotate(${rotation}deg)`;
            
            // Sesuaikan container jika rotasi 90 atau 270 derajat
            if (rotation === 90 || rotation === 270) {
                previewImage.style.maxWidth = 'none';
                previewImage.style.maxHeight = '100%';
            } else {
                previewImage.style.maxWidth = '100%';
                previewImage.style.maxHeight = 'none';
            }
        }
        
        // Fungsi untuk menyimpan rotasi ke server
        function saveRotation() {
            fetch(`/ocr/{{ $ocrResult->id }}/save-rotations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    page_rotations: pageRotations
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Rotation saved:', data);
            })
            .catch(error => {
                console.error('Error saving rotation:', error);
            });
        }
        
        // Modifikasi fungsi loadCurrentPage untuk menerapkan rotasi
        const originalLoadCurrentPage = loadCurrentPage;
        loadCurrentPage = function() {
            originalLoadCurrentPage.call(this);
            // Terapkan rotasi setelah gambar dimuat
            previewImage.onload = function() {
                loadingPlaceholder.style.display = 'none';
                previewImage.style.display = 'block';
                applyRotation();
                updateRegionsDisplay();
            };
        };
        
        // Update process button state based on total regions
        this.updateProcessButton();
    });
</script>