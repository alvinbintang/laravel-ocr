<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Preview OCR - Laravel OCR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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
                <h1 class="text-3xl font-bold mb-6">Pilih Area untuk Ekstraksi Teks</h1>

                @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
                @endif

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="mb-4">
                        <p class="text-gray-600 mb-2">
                            Status: <span class="font-semibold" id="status-text">{{ $ocrResult->status }}</span>
                        </p>
                        <p class="text-gray-600">
                            Nama File: <span class="font-semibold">{{ $ocrResult->filename }}</span>
                        </p>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex space-x-4 mb-4">
                            <button id="add-region" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-plus mr-2"></i> Tambah Area
                            </button>
                            <button id="clear-regions" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-trash mr-2"></i> Hapus Semua Area
                            </button>
                            <button id="process-regions" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-play mr-2"></i> Proses Area Terpilih
                            </button>
                        </div>

                        <div class="flex flex-wrap md:flex-nowrap space-y-4 md:space-y-0 md:space-x-4">
                            <!-- Image Container -->
                            <div class="w-full md:w-3/4">
                                <div id="image-container" class="relative border-2 border-gray-300 rounded-lg overflow-hidden">
                                    <img src="{{ Storage::url($ocrResult->image_path) }}" 
                                         id="source-image" 
                                         class="max-w-full h-auto"
                                         alt="Document Preview">
                                    <div id="regions-overlay" class="absolute top-0 left-0 w-full h-full pointer-events-none"></div>
                                </div>
                            </div>

                            <!-- Regions List -->
                            <div class="w-full md:w-1/4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold mb-3">Daftar Area</h3>
                                    <div id="regions-list" class="space-y-2">
                                        <!-- Regions will be added here dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Results Section -->
                        <div id="results-section" class="mt-6 hidden">
                            <h3 class="text-xl font-semibold mb-3">Hasil OCR</h3>
                            <div id="ocr-results" class="bg-gray-50 rounded-lg p-4">
                                <!-- OCR results will be displayed here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<style>
.region-container {
    position: absolute;
    border: 2px solid rgba(59, 130, 246, 0.5);
    background-color: rgba(59, 130, 246, 0.1);
    cursor: move;
}

.region-container.selected {
    border-color: rgba(59, 130, 246, 1);
    background-color: rgba(59, 130, 246, 0.2);
}

.region-container .region-number {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    background-color: #3B82F6;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

.region-container .region-controls {
    position: absolute;
    top: -20px;
    right: -2px;
    display: none;
}

.region-container:hover .region-controls {
    display: flex;
}

.region-resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background-color: white;
    border: 2px solid #3B82F6;
    border-radius: 50%;
}

.region-resize-handle.nw { top: -5px; left: -5px; cursor: nw-resize; }
.region-resize-handle.ne { top: -5px; right: -5px; cursor: ne-resize; }
.region-resize-handle.sw { bottom: -5px; left: -5px; cursor: sw-resize; }
.region-resize-handle.se { bottom: -5px; right: -5px; cursor: se-resize; }

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3B82F6;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
class Region {
    constructor(container, id) {
        this.id = id;
        this.container = container;
        this.imageContainer = document.getElementById('image-container');
        this.setupElements();
        this.setupEventListeners();
    }

    setupElements() {
        // Create region container
        this.element = document.createElement('div');
        this.element.className = 'region-container';
        this.element.dataset.regionId = this.id;

        // Add region number
        const number = document.createElement('div');
        number.className = 'region-number';
        number.textContent = this.id;
        this.element.appendChild(number);

        // Add controls
        const controls = document.createElement('div');
        controls.className = 'region-controls';
        controls.innerHTML = `
            <button class="delete-region bg-red-500 hover:bg-red-700 text-white px-2 py-1 rounded text-xs">
                <i class="fas fa-trash"></i>
            </button>
        `;
        this.element.appendChild(controls);

        // Add resize handles
        ['nw', 'ne', 'sw', 'se'].forEach(pos => {
            const handle = document.createElement('div');
            handle.className = `region-resize-handle ${pos}`;
            handle.dataset.handle = pos;
            this.element.appendChild(handle);
        });

        this.container.appendChild(this.element);
    }

    setupEventListeners() {
        let isDragging = false;
        let isResizing = false;
        let currentHandle = null;
        let startX, startY, startWidth, startHeight, startLeft, startTop;

        const onMouseDown = (e) => {
            if (e.target.classList.contains('region-resize-handle')) {
                isResizing = true;
                currentHandle = e.target.dataset.handle;
            } else if (!e.target.classList.contains('delete-region')) {
                isDragging = true;
            }

            startX = e.clientX;
            startY = e.clientY;
            startWidth = this.element.offsetWidth;
            startHeight = this.element.offsetHeight;
            startLeft = this.element.offsetLeft;
            startTop = this.element.offsetTop;

            e.preventDefault();
        };

        const onMouseMove = (e) => {
            if (!isDragging && !isResizing) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            if (isResizing) {
                this.resize(currentHandle, dx, dy, startWidth, startHeight, startLeft, startTop);
            } else if (isDragging) {
                this.move(dx, dy, startLeft, startTop);
            }

            this.updateListItem();
        };

        const onMouseUp = () => {
            isDragging = false;
            isResizing = false;
        };

        this.element.addEventListener('mousedown', onMouseDown);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);

        // Delete button handler
        this.element.querySelector('.delete-region').addEventListener('click', () => {
            this.delete();
        });
    }

    move(dx, dy, startLeft, startTop) {
        const containerRect = this.imageContainer.getBoundingClientRect();
        const regionRect = this.element.getBoundingClientRect();

        let newLeft = startLeft + dx;
        let newTop = startTop + dy;

        // Constrain to container bounds
        newLeft = Math.max(0, Math.min(newLeft, containerRect.width - regionRect.width));
        newTop = Math.max(0, Math.min(newTop, containerRect.height - regionRect.height));

        this.element.style.left = `${newLeft}px`;
        this.element.style.top = `${newTop}px`;
    }

    resize(handle, dx, dy, startWidth, startHeight, startLeft, startTop) {
        const containerRect = this.imageContainer.getBoundingClientRect();
        let newWidth = startWidth;
        let newHeight = startHeight;
        let newLeft = startLeft;
        let newTop = startTop;

        switch (handle) {
            case 'se':
                newWidth = startWidth + dx;
                newHeight = startHeight + dy;
                break;
            case 'sw':
                newWidth = startWidth - dx;
                newHeight = startHeight + dy;
                newLeft = startLeft + dx;
                break;
            case 'ne':
                newWidth = startWidth + dx;
                newHeight = startHeight - dy;
                newTop = startTop + dy;
                break;
            case 'nw':
                newWidth = startWidth - dx;
                newHeight = startHeight - dy;
                newLeft = startLeft + dx;
                newTop = startTop + dy;
                break;
        }

        // Enforce minimum size
        const minSize = 50;
        if (newWidth >= minSize && newHeight >= minSize) {
            // Constrain to container bounds
            if (newLeft >= 0 && newLeft + newWidth <= containerRect.width &&
                newTop >= 0 && newTop + newHeight <= containerRect.height) {
                this.element.style.width = `${newWidth}px`;
                this.element.style.height = `${newHeight}px`;
                this.element.style.left = `${newLeft}px`;
                this.element.style.top = `${newTop}px`;
            }
        }
    }

    delete() {
        this.element.remove();
        document.querySelector(`#region-item-${this.id}`).remove();
        RegionManager.updateRegionsList();
    }

    updateListItem() {
        const listItem = document.querySelector(`#region-item-${this.id}`);
        if (listItem) {
            const dimensions = this.getDimensions();
            listItem.querySelector('.region-dimensions').textContent =
                `${Math.round(dimensions.width)}×${Math.round(dimensions.height)}`;
        }
    }

    getDimensions() {
        return {
            x: this.element.offsetLeft,
            y: this.element.offsetTop,
            width: this.element.offsetWidth,
            height: this.element.offsetHeight
        };
    }
}

class RegionManager {
    static regions = [];
    static nextId = 1;

    static initialize() {
        // Initialize buttons
        document.getElementById('add-region').addEventListener('click', () => this.addRegion());
        document.getElementById('clear-regions').addEventListener('click', () => this.clearRegions());
        document.getElementById('process-regions').addEventListener('click', () => this.processRegions());

        // Initialize regions list
        const regionsList = document.getElementById('regions-list');
        if (!regionsList.children.length) {
            regionsList.innerHTML = '<p class="text-gray-500 text-sm">Belum ada area yang dipilih</p>';
        }
    }

    static addRegion() {
        const container = document.getElementById('regions-overlay');
        const region = new Region(container, this.nextId++);
        
        // Set initial position and size
        region.element.style.left = '10%';
        region.element.style.top = '10%';
        region.element.style.width = '100px';
        region.element.style.height = '100px';

        this.regions.push(region);
        this.updateRegionsList();
    }

    static clearRegions() {
        this.regions.forEach(region => region.delete());
        this.regions = [];
        this.updateRegionsList();
    }

    static updateRegionsList() {
        const list = document.getElementById('regions-list');
        const regions = document.querySelectorAll('.region-container');

        if (!regions.length) {
            list.innerHTML = '<p class="text-gray-500 text-sm">Belum ada area yang dipilih</p>';
            return;
        }

        list.innerHTML = '';
        regions.forEach(regionElement => {
            const id = regionElement.dataset.regionId;
            const dimensions = {
                width: Math.round(regionElement.offsetWidth),
                height: Math.round(regionElement.offsetHeight)
            };

            const item = document.createElement('div');
            item.id = `region-item-${id}`;
            item.className = 'bg-white p-3 rounded shadow-sm';
            item.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="font-medium">Area ${id}</span>
                    <span class="text-sm text-gray-500 region-dimensions">${dimensions.width}×${dimensions.height}</span>
                </div>
            `;
            list.appendChild(item);
        });
    }

    static async processRegions() {
        const regions = document.querySelectorAll('.region-container');
        if (!regions.length) {
            alert('Pilih minimal satu area untuk diproses');
            return;
        }

        const regionsData = Array.from(regions).map(region => {
            const rect = region.getBoundingClientRect();
            const container = document.getElementById('image-container').getBoundingClientRect();
            
            return {
                id: parseInt(region.dataset.regionId),
                x: region.offsetLeft,
                y: region.offsetTop,
                width: region.offsetWidth,
                height: region.offsetHeight
            };
        });

        // Show loading overlay
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
        document.body.appendChild(loadingOverlay);

        try {
            const response = await fetch(`/ocr/${ocrResultId}/process-regions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ regions: regionsData })
            });

            const data = await response.json();
            
            if (data.status === 'processing') {
                // Poll for results
                await this.pollResults();
            } else {
                throw new Error(data.message || 'Failed to process regions');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        } finally {
            loadingOverlay.remove();
        }
    }

    static async pollResults() {
        const maxAttempts = 30; // 30 seconds
        let attempts = 0;

        const poll = async () => {
            const response = await fetch(`/ocr/${ocrResultId}/result`);
            const data = await response.json();

            if (data.status === 'done') {
                this.displayResults(data.results);
                return true;
            } else if (data.status === 'error') {
                throw new Error(data.message);
            }

            if (++attempts >= maxAttempts) {
                throw new Error('Timeout waiting for results');
            }

            await new Promise(resolve => setTimeout(resolve, 1000));
            return poll();
        };

        await poll();
    }

    static displayResults(results) {
        const resultsSection = document.getElementById('results-section');
        const resultsContainer = document.getElementById('ocr-results');
        
        resultsContainer.innerHTML = '';
        results.forEach(result => {
            const resultDiv = document.createElement('div');
            resultDiv.className = 'mb-4 p-4 bg-white rounded shadow';
            resultDiv.innerHTML = `
                <div class="font-medium mb-2">Area ${result.region_id}</div>
                <div class="text-sm text-gray-600">
                    Koordinat: (${result.coordinates.x}, ${result.coordinates.y})
                    Ukuran: ${result.coordinates.width}×${result.coordinates.height}
                </div>
                <pre class="mt-2 p-2 bg-gray-50 rounded">${result.text}</pre>
            `;
            resultsContainer.appendChild(resultDiv);
        });

        resultsSection.classList.remove('hidden');
        resultsSection.scrollIntoView({ behavior: 'smooth' });
    }
}

// Initialize when the page loads
document.addEventListener('DOMContentLoaded', () => {
    RegionManager.initialize();
});

// Get the OCR Result ID from the page
const ocrResultId = '{{ $ocrResult->id }}';
</script>
</body>
</html>