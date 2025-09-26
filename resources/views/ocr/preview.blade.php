@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Pilih Area untuk OCR</h2>
        
        <div id="image-container" class="relative inline-block">
            <img src="{{ Storage::url($ocrResult->image_path) }}" 
                 id="ocr-image" 
                 class="max-w-full h-auto"
                 alt="Document to process">
            <div id="selection-overlay"></div>
        </div>

        <div class="mt-4">
            <button id="process-regions" 
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Proses Area Terpilih
            </button>
            <button id="clear-regions" 
                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-2">
                Hapus Semua Area
            </button>
        </div>

        <div id="selected-regions" class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Area Terpilih:</h3>
            <ul id="regions-list" class="list-disc pl-5">
            </ul>
        </div>

        <div id="results" class="mt-4 hidden">
            <h3 class="text-lg font-semibold mb-2">Hasil OCR:</h3>
            <pre id="ocr-results" class="bg-gray-100 p-4 rounded"></pre>
        </div>
    </div>
</div>

@push('styles')
<style>
    #image-container {
        cursor: crosshair;
    }
    .selection-box {
        position: absolute;
        border: 2px solid rgba(0, 123, 255, 0.8);
        background-color: rgba(0, 123, 255, 0.1);
        pointer-events: none;
    }
    .region {
        position: absolute;
        border: 2px solid rgba(0, 123, 255, 0.8);
        background-color: rgba(0, 123, 255, 0.1);
        cursor: move;
    }
    .region .remove {
        position: absolute;
        top: -10px;
        right: -10px;
        background: red;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        text-align: center;
        line-height: 20px;
        cursor: pointer;
    }
</style>
@endpush

@push('scripts')
<script>
const imageContainer = document.getElementById('image-container');
const image = document.getElementById('ocr-image');
let regions = [];
let isDrawing = false;
let startX, startY;
let currentBox = null;

// Make regions draggable
function makeRegionDraggable(region) {
    let isDragging = false;
    let currentX;
    let currentY;

    region.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('remove')) return;
        isDragging = true;
        currentX = e.clientX - region.offsetLeft;
        currentY = e.clientY - region.offsetTop;
        e.preventDefault();
    });

    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            region.style.left = (e.clientX - currentX) + 'px';
            region.style.top = (e.clientY - currentY) + 'px';
        }
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });
}

// Start drawing selection
imageContainer.addEventListener('mousedown', function(e) {
    isDrawing = true;
    const rect = imageContainer.getBoundingClientRect();
    startX = e.clientX - rect.left;
    startY = e.clientY - rect.top;

    currentBox = document.createElement('div');
    currentBox.classList.add('selection-box');
    currentBox.style.left = startX + 'px';
    currentBox.style.top = startY + 'px';
    imageContainer.appendChild(currentBox);
});

// Draw selection
imageContainer.addEventListener('mousemove', function(e) {
    if (!isDrawing) return;
    const rect = imageContainer.getBoundingClientRect();
    const currentX = e.clientX - rect.left;
    const currentY = e.clientY - rect.top;

    const width = currentX - startX;
    const height = currentY - startY;

    currentBox.style.width = Math.abs(width) + 'px';
    currentBox.style.height = Math.abs(height) + 'px';
    currentBox.style.left = (width > 0 ? startX : currentX) + 'px';
    currentBox.style.top = (height > 0 ? startY : currentY) + 'px';
});

// Finish drawing selection
imageContainer.addEventListener('mouseup', function() {
    if (!isDrawing) return;
    isDrawing = false;

    if (currentBox) {
        const region = document.createElement('div');
        region.classList.add('region');
        region.style.left = currentBox.style.left;
        region.style.top = currentBox.style.top;
        region.style.width = currentBox.style.width;
        region.style.height = currentBox.style.height;

        const removeButton = document.createElement('div');
        removeButton.classList.add('remove');
        removeButton.innerHTML = '×';
        removeButton.addEventListener('click', function() {
            region.remove();
            updateRegionsList();
        });

        region.appendChild(removeButton);
        imageContainer.appendChild(region);
        currentBox.remove();
        currentBox = null;

        makeRegionDraggable(region);
        updateRegionsList();
    }
});

// Update regions list
function updateRegionsList() {
    const regionsList = document.getElementById('regions-list');
    regionsList.innerHTML = '';
    
    document.querySelectorAll('.region').forEach((region, index) => {
        const li = document.createElement('li');
        li.textContent = `Area ${index + 1}: ${Math.round(region.offsetWidth)}x${Math.round(region.offsetHeight)} piksel`;
        regionsList.appendChild(li);
    });
}

// Process selected regions
document.getElementById('process-regions').addEventListener('click', function() {
    const regions = [];
    document.querySelectorAll('.region').forEach((region, index) => {
        const rect = region.getBoundingClientRect();
        const containerRect = imageContainer.getBoundingClientRect();
        regions.push({
            id: index + 1,
            x: region.offsetLeft,
            y: region.offsetTop,
            width: region.offsetWidth,
            height: region.offsetHeight
        });
    });

    if (regions.length === 0) {
        alert('Pilih minimal satu area untuk diproses');
        return;
    }

    // Send regions to server
    fetch('{{ route("ocr.process-regions", $ocrResult->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ regions })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('results').classList.remove('hidden');
        document.getElementById('ocr-results').textContent = JSON.stringify(data, null, 2);
    })
    .catch(error => {
        alert('Error processing regions: ' + error.message);
    });
});

// Clear all regions
document.getElementById('clear-regions').addEventListener('click', function() {
    document.querySelectorAll('.region').forEach(region => region.remove());
    updateRegionsList();
    document.getElementById('results').classList.add('hidden');
});
</script>
@endpush