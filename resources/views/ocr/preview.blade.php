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
            <div class="w-full px-4 sm:px-6 lg:px-8">
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
                            <div class="flex flex-wrap gap-2">
                                <!-- UPDATED: Rotation Controls -->
                                <button id="rotate-left-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Rotate Left
                                </button>
                                <button id="rotate-right-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 20v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M4.581 15H9m11-11v5h-.581m0 0a8.001 8.001 0 00-15.357 2M4.581 9H9" />
                                    </svg>
                                    Rotate Right
                                </button>
                                <button id="apply-rotation-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center" style="display: none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Apply Rotation
                                </button>
                                <button id="reset-rotation-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-md text-sm font-medium flex items-center" style="display: none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Reset
                                </button>
                                
                                <!-- UPDATED: Separator -->
                                <div class="border-l border-gray-300 mx-2"></div>
                                
                                <!-- UPDATED: Region Controls -->
                                <button id="add-region-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Add Region
                                </button>
                                <button id="clear-regions-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Clear All
                                </button>
                                <button id="process-regions-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium" disabled>
                                    Crop & Preview
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
                        <div class="relative mb-6 bg-gray-300 min-h-96 flex items-center justify-center" id="image-preview-container">
                            
                            <script>
                                // UPDATED: Initialize rotation variables and workflow state
                                let pageRotations = {!! json_encode($ocrResult->page_rotations ?? []) !!} || {};
                                let currentPage = 1;
                                let appliedRotations = {!! json_encode($ocrResult->page_rotations ?? []) !!} || {}; // UPDATED: Use page_rotations from database
                                let currentWorkflowPhase = 'rotation'; // ADDED: Track current phase (rotation or selection)
                                let pendingRotation = 0; // ADDED: Track pending rotation for current page
                                
                                // Wait for DOM to be fully loaded
                                document.addEventListener('DOMContentLoaded', function() {
                                    const rotateLeftBtn = document.getElementById('rotate-left-btn');
                                    const rotateRightBtn = document.getElementById('rotate-right-btn');
                                    const applyRotationBtn = document.getElementById('apply-rotation-btn'); // ADDED
                                    const resetRotationBtn = document.getElementById('reset-rotation-btn'); // ADDED
                                    const previewImage = document.getElementById('preview-image');
                                    
                                    // UPDATED: Add event listeners for all rotation buttons
                                    if (rotateLeftBtn) {
                                        rotateLeftBtn.addEventListener('click', function() {
                                            rotateImagePreview(-90);
                                        });
                                    }
                                    
                                    if (rotateRightBtn) {
                                        rotateRightBtn.addEventListener('click', function() {
                                            rotateImagePreview(90);
                                        });
                                    }
                                    
                                    // ADDED: Apply rotation button event listener
                                    if (applyRotationBtn) {
                                        applyRotationBtn.addEventListener('click', function() {
                                            applyRotationToBackend();
                                        });
                                    }
                                    
                                    // ADDED: Reset rotation button event listener
                                    if (resetRotationBtn) {
                                        resetRotationBtn.addEventListener('click', function() {
                                            resetCurrentPageRotation();
                                        });
                                    }
                                    
                                    // Apply initial rotation if exists
                                    applyCurrentPageRotation();
                                    updateWorkflowPhase(); // ADDED: Initialize workflow phase
                                
                                    // Update currentPage when page changes in RegionSelector
                                    document.addEventListener('pageChanged', function(e) {
                                        currentPage = e.detail.page;
                                        applyCurrentPageRotation();
                                        updateWorkflowPhase(); // ADDED: Update phase when page changes
                                    });
                                });
                            
                            // UPDATED: Function to rotate image preview only (visual rotation)
                            function rotateImagePreview(degrees) {
                                const previewImage = document.getElementById('preview-image');
                                if (!previewImage) return;
                                
                                // Update pending rotation for current page
                                pendingRotation = (pendingRotation + degrees) % 360;
                                if (pendingRotation < 0) pendingRotation += 360;
                                
                                // Apply visual rotation
                                applyVisualRotation();
                                
                                // Show/hide apply and reset buttons
                                updateRotationButtons();
                            }
                            
                            // ADDED: Function to apply visual rotation without backend changes
                            function applyVisualRotation() {
                                const previewImage = document.getElementById('preview-image');
                                const imageContainer = document.getElementById('image-container');
                                const previewContainer = document.getElementById('image-preview-container');
                                if (!previewImage || !imageContainer || !previewContainer) return;
                                
                                // UPDATED: Check if this page has been rotated and applied to backend
                                const hasAppliedRotation = appliedRotations[currentPage] !== undefined;
                                
                                // Get the total rotation (applied + pending)
                                const appliedRotation = appliedRotations[currentPage] || 0;
                                // UPDATED: Only use pending rotation for visual display, don't add applied rotation since image is already rotated
                                const totalRotation = hasAppliedRotation ? pendingRotation : (appliedRotation + pendingRotation) % 360;
                                
                                // Reset any previous styling
                                previewImage.style.transform = '';
                                previewImage.style.transformOrigin = 'center center';
                                previewImage.style.maxWidth = '';
                                previewImage.style.width = '';
                                previewImage.style.height = '';
                                imageContainer.style.height = '';
                                imageContainer.style.width = '';
                                imageContainer.style.position = 'relative';
                                
                                // Get natural dimensions
                                const naturalWidth = previewImage.naturalWidth;
                                const naturalHeight = previewImage.naturalHeight;
                                
                                if (naturalWidth && naturalHeight) {
                                    // Get available space in the preview container
                                    const containerRect = previewContainer.getBoundingClientRect();
                                    const containerStyle = window.getComputedStyle(previewContainer);
                                    const paddingX = parseFloat(containerStyle.paddingLeft) + parseFloat(containerStyle.paddingRight);
                                    const paddingY = parseFloat(containerStyle.paddingTop) + parseFloat(containerStyle.paddingBottom);
                                    
                                    // Calculate available space with margin for safety
                                    const availableWidth = containerRect.width - paddingX - 40;
                                    const availableHeight = containerRect.height - paddingY - 40;
                                    
                                    let scale, finalWidth, finalHeight;
                                    
                                    // UPDATED: For already applied rotations, use normal scaling since the image file itself is rotated
                                    if (!hasAppliedRotation && (totalRotation === 90 || totalRotation === 270)) {
                                        // For 90° and 270° rotations, we need to consider swapped dimensions
                                        const scaleForWidth = availableWidth / naturalHeight;
                                        const scaleForHeight = availableHeight / naturalWidth;
                                        scale = Math.min(scaleForWidth, scaleForHeight, 1);
                                        
                                        finalWidth = naturalWidth * scale;
                                        finalHeight = naturalHeight * scale;
                                        
                                    } else {
                                        // For 0° and 180° rotations, or already applied rotations, use normal dimensions
                                        const scaleForWidth = availableWidth / naturalWidth;
                                        const scaleForHeight = availableHeight / naturalHeight;
                                        scale = Math.min(scaleForWidth, scaleForHeight, 1);
                                        
                                        finalWidth = naturalWidth * scale;
                                        finalHeight = naturalHeight * scale;
                                    }
                                    
                                    // Apply image dimensions
                                    previewImage.style.width = `${finalWidth}px`;
                                    previewImage.style.height = `${finalHeight}px`;
                                    previewImage.style.maxWidth = 'none';
                                    previewImage.style.maxHeight = 'none';
                                    
                                    // UPDATED: Container sizing - for already applied rotations, use normal dimensions
                                    if (!hasAppliedRotation && (totalRotation === 90 || totalRotation === 270)) {
                                        imageContainer.style.width = `${finalHeight}px`;
                                        imageContainer.style.height = `${finalWidth}px`;
                                    } else {
                                        imageContainer.style.width = `${finalWidth}px`;
                                        imageContainer.style.height = `${finalHeight}px`;
                                    }
                                    
                                    // UPDATED: Only apply rotation transform if there's actual rotation to apply
                                    if (totalRotation !== 0) {
                                        previewImage.style.transform = `rotate(${totalRotation}deg)`;
                                    }
                                }
                            }
                            
                            // UPDATED: Function to update rotation button visibility and disable other buttons
                            function updateRotationButtons() {
                                const applyBtn = document.getElementById('apply-rotation-btn');
                                const resetBtn = document.getElementById('reset-rotation-btn');
                                const addRegionBtn = document.getElementById('add-region-btn');
                                const clearRegionsBtn = document.getElementById('clear-regions-btn');
                                const processRegionsBtn = document.getElementById('process-regions-btn');
                                
                                if (pendingRotation !== 0) {
                                    // Show apply and reset buttons
                                    applyBtn.style.display = 'flex';
                                    resetBtn.style.display = 'flex';
                                    
                                    // ADDED: Disable other action buttons when rotation is pending
                                    if (addRegionBtn) {
                                        addRegionBtn.disabled = true;
                                        addRegionBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                        addRegionBtn.classList.remove('hover:bg-blue-700');
                                    }
                                    if (clearRegionsBtn) {
                                        clearRegionsBtn.disabled = true;
                                        clearRegionsBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                        clearRegionsBtn.classList.remove('hover:bg-gray-700');
                                    }
                                    if (processRegionsBtn) {
                                        processRegionsBtn.disabled = true;
                                        processRegionsBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                        processRegionsBtn.classList.remove('hover:bg-green-700');
                                    }
                                } else {
                                    // Hide apply and reset buttons
                                    applyBtn.style.display = 'none';
                                    resetBtn.style.display = 'none';
                                    
                                    // ADDED: Re-enable other action buttons when no rotation is pending
                                    if (addRegionBtn) {
                                        addRegionBtn.disabled = false;
                                        addRegionBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                        addRegionBtn.classList.add('hover:bg-blue-700');
                                    }
                                    if (clearRegionsBtn) {
                                        clearRegionsBtn.disabled = false;
                                        clearRegionsBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                        clearRegionsBtn.classList.add('hover:bg-gray-700');
                                    }
                                    if (processRegionsBtn && !processRegionsBtn.hasAttribute('data-originally-disabled')) {
                                        processRegionsBtn.disabled = false;
                                        processRegionsBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                        processRegionsBtn.classList.add('hover:bg-green-700');
                                    }
                                }
                            }
                            
                            // ADDED: Function to reset current page rotation
                            function resetCurrentPageRotation() {
                                pendingRotation = 0;
                                applyVisualRotation();
                                updateRotationButtons();
                            }
                            
                            // ADDED: Function to apply rotation to backend
                            function applyRotationToBackend() {
                                if (pendingRotation === 0) return;
                                
                                const applyBtn = document.getElementById('apply-rotation-btn');
                                const originalText = applyBtn.innerHTML;
                                
                                // Show loading state
                                applyBtn.innerHTML = '<div class="loading-spinner-small"></div> Applying...';
                                applyBtn.disabled = true;
                                
                                fetch(`/ocr/{{ $ocrResult->id }}/apply-rotation`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({
                                        page_number: currentPage,
                                        rotation_degree: pendingRotation
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // UPDATED: Accumulate the rotation applied to backend (cumulative relative to original)
                                        appliedRotations[currentPage] = ((appliedRotations[currentPage] || 0) + data.rotation_applied) % 360;
                                        pendingRotation = 0;
                                        
                                        // Update image source to the rotated image
                                        const previewImage = document.getElementById('preview-image');
                                        previewImage.src = data.rotated_image_url + '?t=' + Date.now(); // Add timestamp to force reload
                                        
                                        // ADDED: Wait for image to load then apply visual rotation to show correct orientation immediately
                                        previewImage.onload = function() {
                                            applyVisualRotation();
                                        };
                                        
                                        updateRotationButtons();
                                        updateWorkflowPhase();
                                        
                                        // Show success message
                                        showNotification('Rotation applied successfully!', 'success');
                                    } else {
                                        console.error('Error applying rotation:', data.message);
                                        showNotification('Error applying rotation: ' + data.message, 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error applying rotation:', error);
                                    showNotification('Error applying rotation', 'error');
                                })
                                .finally(() => {
                                    // Restore button state
                                    applyBtn.innerHTML = originalText;
                                    applyBtn.disabled = false;
                                });
                            }
                            
                            // ADDED: Function to update workflow phase
                            function updateWorkflowPhase() {
                                const phaseIndicator = document.getElementById('phase-indicator');
                                const rotationControls = document.getElementById('rotation-controls');
                                
                                // Check if current page has pending rotation or if we're in rotation phase
                                const hasAppliedRotation = appliedRotations[currentPage] !== undefined;
                                const hasPendingRotation = pendingRotation !== 0;
                                
                                if (hasPendingRotation) {
                                    currentWorkflowPhase = 'rotation';
                                    phaseIndicator.textContent = 'Phase 1: Rotation (Pending)';
                                    phaseIndicator.className = 'ml-4 px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium';
                                } else if (hasAppliedRotation || pendingRotation === 0) {
                                    currentWorkflowPhase = 'selection';
                                    phaseIndicator.textContent = 'Phase 2: Area Selection';
                                    phaseIndicator.className = 'ml-4 px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium';
                                } else {
                                    currentWorkflowPhase = 'rotation';
                                    phaseIndicator.textContent = 'Phase 1: Rotation';
                                    phaseIndicator.className = 'ml-4 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium';
                                }
                                
                                // Enable/disable region selection based on phase
                                if (window.regionManager) {
                                    window.regionManager.setSelectionEnabled(currentWorkflowPhase === 'selection' && pendingRotation === 0);
                                }
                            }
                            
                            // ADDED: Function to show notifications
                            function showNotification(message, type = 'info') {
                                // Create notification element
                                const notification = document.createElement('div');
                                notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 transition-all duration-300 ${
                                    type === 'success' ? 'bg-green-500 text-white' :
                                    type === 'error' ? 'bg-red-500 text-white' :
                                    'bg-blue-500 text-white'
                                }`;
                                notification.textContent = message;
                                
                                document.body.appendChild(notification);
                                
                                // Auto remove after 3 seconds
                                setTimeout(() => {
                                    notification.style.opacity = '0';
                                    setTimeout(() => {
                                        if (notification.parentNode) {
                                            notification.parentNode.removeChild(notification);
                                        }
                                    }, 300);
                                }, 3000);
                            }
                                
                                // Apply rotation to current page
                                function applyCurrentPageRotation() {
                                    const previewImage = document.getElementById('preview-image');
                                    const imageContainer = document.getElementById('image-container');
                                    const previewContainer = document.getElementById('image-preview-container');
                                    if (!previewImage || !imageContainer || !previewContainer) return;
                                    
                                    // UPDATED: Check if this page has been rotated and applied to backend
                                    const hasAppliedRotation = appliedRotations[currentPage] !== undefined;
                                    const rotation = hasAppliedRotation ? 0 : (pageRotations[currentPage] || 0); // UPDATED: Don't apply visual rotation if already applied to backend
                                    
                                    // Reset any previous styling
                                    previewImage.style.transform = '';
                                    previewImage.style.transformOrigin = 'center center';
                                    previewImage.style.maxWidth = '';
                                    previewImage.style.width = '';
                                    previewImage.style.height = '';
                                    imageContainer.style.height = '';
                                    imageContainer.style.width = '';
                                    imageContainer.style.position = 'relative';
                                    
                                    // Get natural dimensions
                                    const naturalWidth = previewImage.naturalWidth;
                                    const naturalHeight = previewImage.naturalHeight;
                                    
                                    if (naturalWidth && naturalHeight) {
                                        // Get available space in the preview container
                                        const containerRect = previewContainer.getBoundingClientRect();
                                        const containerStyle = window.getComputedStyle(previewContainer);
                                        const paddingX = parseFloat(containerStyle.paddingLeft) + parseFloat(containerStyle.paddingRight);
                                        const paddingY = parseFloat(containerStyle.paddingTop) + parseFloat(containerStyle.paddingBottom);
                                        
                                        // Calculate available space with margin for safety
                                        const availableWidth = containerRect.width - paddingX - 40;
                                        const availableHeight = containerRect.height - paddingY - 40;
                                        
                                        let scale, finalWidth, finalHeight;
                                        
                                        // UPDATED: For already rotated images, use normal scaling since the image file itself is rotated
                                        if (!hasAppliedRotation && (rotation === 90 || rotation === 270)) {
                                            // For 90° and 270° rotations, we need to consider swapped dimensions
                                            // The rotated image will have height as width and width as height
                                            const scaleForWidth = availableWidth / naturalHeight;
                                            const scaleForHeight = availableHeight / naturalWidth;
                                            scale = Math.min(scaleForWidth, scaleForHeight, 1);
                                            
                                            // Final dimensions after scaling (but before rotation)
                                            finalWidth = naturalWidth * scale;
                                            finalHeight = naturalHeight * scale;
                                            
                                        } else {
                                            // For 0° and 180° rotations, or already applied rotations, use normal dimensions
                                            const scaleForWidth = availableWidth / naturalWidth;
                                            const scaleForHeight = availableHeight / naturalHeight;
                                            scale = Math.min(scaleForWidth, scaleForHeight, 1);
                                            
                                            finalWidth = naturalWidth * scale;
                                            finalHeight = naturalHeight * scale;
                                        }
                                        
                                        // Apply image dimensions (these are the dimensions before rotation)
                                        previewImage.style.width = `${finalWidth}px`;
                                        previewImage.style.height = `${finalHeight}px`;
                                        previewImage.style.maxWidth = 'none';
                                        previewImage.style.maxHeight = 'none';
                                        
                                        // UPDATED: Container sizing - for already applied rotations, use normal dimensions
                                        if (!hasAppliedRotation && (rotation === 90 || rotation === 270)) {
                                            // For rotated images, container needs to be sized for the rotated dimensions
                                            imageContainer.style.width = `${finalHeight}px`;  // Swapped
                                            imageContainer.style.height = `${finalWidth}px`;  // Swapped
                                        } else {
                                            imageContainer.style.width = `${finalWidth}px`;
                                            imageContainer.style.height = `${finalHeight}px`;
                                        }
                                        
                                        imageContainer.style.overflow = 'visible';
                                        
                                        // UPDATED: Only apply CSS transform rotation if not already applied to backend
                                        if (!hasAppliedRotation) {
                                            previewImage.style.transform = `rotate(${rotation}deg)`;
                                        }
                                        
                                        // Ensure container is centered
                                        imageContainer.style.margin = '0 auto';
                                        
                                    } else {
                                        // Fallback for when natural dimensions aren't available yet
                                        // UPDATED: Only apply transform if not already applied to backend
                                        if (!hasAppliedRotation) {
                                            previewImage.style.transform = `rotate(${rotation}deg)`;
                                        }
                                        previewImage.style.maxWidth = '100%';
                                        previewImage.style.height = 'auto';
                                    }
                                    
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
                            <div id="image-container" class="relative transition-all duration-300">
                                <img id="preview-image" src="{{ $ocrResult->getImagePathForPage(1) }}" class="block transition-transform duration-300" style="display: none;" onload="handleImageLoad()">
                                <!-- UPDATED: Improved loading placeholder with spinner -->
                                <div id="loading-placeholder" class="flex items-center justify-center bg-gray-100 h-96">
                                    <div class="text-center">
                                        <div class="loading-spinner"></div>
                                        <p class="text-gray-600">Loading page {{ $currentPage ?? 1 }} of {{ $ocrResult->page_count ?? 1 }}...</p>
                                    </div>
                                </div>
                                <div id="regions-overlay" class="absolute inset-0 pointer-events-none"></div>
                            </div>
                            
                            <script>
                                // UPDATED: Handle image load event to apply rotation after image is loaded
                                function handleImageLoad() {
                                    const previewImage = document.getElementById('preview-image');
                                    const loadingPlaceholder = document.getElementById('loading-placeholder');
                                    
                                    if (previewImage && loadingPlaceholder) {
                                        // Hide loading placeholder and show image
                                        loadingPlaceholder.style.display = 'none';
                                        previewImage.style.display = 'block';
                                        
                                        // Apply rotation after image is fully loaded
                                        setTimeout(() => {
                                            applyCurrentPageRotation();
                                        }, 100);
                                    }
                                }
                                
                                // UPDATED: Enhanced rotation function with container adjustment
                                function adjustContainerForRotation(rotation) {
                                    // This function is no longer needed as rotation logic is handled in applyCurrentPageRotation
                                    return;
                                }
                            </script>
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
                                            @if($croppedImage && isset($croppedImage['image_path']))
                                            <div class="mb-3">
                                                <h5 class="text-sm font-medium text-gray-700 mb-2">Selected Image:</h5>
                                                <div class="border border-gray-300 rounded-lg p-2 bg-white inline-block">
                                                    <img src="{{ asset('storage/' . $croppedImage['image_path']) }}" 
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
                                                            @if($croppedImage && isset($croppedImage['image_path']))
                                                                <img src="{{ asset('storage/' . $croppedImage['image_path']) }}" 
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

        /* ADDED: Small loading spinner for buttons */
        .loading-spinner-small {
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff40;
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
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

            getAdjustedCoords(x, y) {
                const rotation = pageRotations?.[this.currentPage] || 0;
                const img = this.previewImage;
                if (!img) return { x, y };

                const w = img.clientWidth;
                const h = img.clientHeight;

                switch (rotation) {
                    case 90:
                        // rotasi 90 derajat searah jarum jam
                        return { x: h - y, y: x };
                    case 180:
                        return { x: w - x, y: h - y };
                    case 270:
                        // rotasi 270 derajat searah jarum jam
                        return { x: y, y: w - x };
                    default:
                        return { x, y };
                }
            }

            handleMouseDown(e) {
                if (!this.imageContainer.classList.contains('drawing-mode')) return;

                const containerRect = this.imageContainer.getBoundingClientRect();
                const imageRect = this.previewImage.getBoundingClientRect();

                // posisi klik relatif terhadap container
                const x = e.clientX - containerRect.left;
                const y = e.clientY - containerRect.top;

                // batas gambar
                const imgLeft = (containerRect.width - imageRect.width) / 2;
                const imgTop = (containerRect.height - imageRect.height) / 2;
                const imgRight = imgLeft + imageRect.width;
                const imgBottom = imgTop + imageRect.height;

                // abaikan klik di luar area gambar
                if (x < imgLeft || x > imgRight || y < imgTop || y > imgBottom) return;

                // kompensasi rotasi gambar
                const adj = this.getAdjustedCoords(x - imgLeft, y - imgTop);

                this.isDrawing = true;
                this.drawingRegion = {
                    startX: adj.x,
                    startY: adj.y,
                    currentX: adj.x,
                    currentY: adj.y,
                    page: this.currentPage
                };

                e.preventDefault();
            }

            handleMouseMove(e) {
                if (!this.isDrawing || !this.drawingRegion) return;

                const containerRect = this.imageContainer.getBoundingClientRect();
                const imageRect = this.previewImage.getBoundingClientRect();
                const imgLeft = (containerRect.width - imageRect.width) / 2;
                const imgTop = (containerRect.height - imageRect.height) / 2;

                let x = e.clientX - containerRect.left;
                let y = e.clientY - containerRect.top;

                // pastikan koordinat masih dalam area gambar
                x = Math.max(imgLeft, Math.min(x, imgLeft + imageRect.width));
                y = Math.max(imgTop, Math.min(y, imgTop + imageRect.height));

                // kompensasi rotasi
                const adj = this.getAdjustedCoords(x - imgLeft, y - imgTop);

                this.drawingRegion.currentX = adj.x;
                this.drawingRegion.currentY = adj.y;

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
                    width,
                    height,
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
                this.processRegionsBtn.innerHTML = '<span class="inline-block animate-spin mr-2">↻</span> Cropping...';
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

                // UPDATED: Use appliedRotations instead of pageRotations for backend-rotated images
                // Since images are now rotated on backend, coordinates are already correct
                const allAppliedRotations = appliedRotations || {};

                // Send to server for cropping only
                fetch('{{ route("ocr.crop-regions", $ocrResult->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        regions: regionsData,
                        previewDimensions: previewDimensions, // ADDED: Include preview dimensions
                        appliedRotations: allAppliedRotations // UPDATED: Send applied rotations instead of pageRotations
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show cropping message
                        const processingDiv = document.createElement('div');
                        processingDiv.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                        processingDiv.innerHTML = `
                            <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
                                <h3 class="text-lg font-medium mb-4">Cropping Images</h3>
                                <div class="flex items-center mb-4">
                                    <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mr-3"></div>
                                    <p>Cropping ${this.regions.length} regions across ${Object.keys(this.regions.reduce((acc, r) => ({...acc, [r.page]: true}), {})).length} pages...</p>
                                </div>
                                <p class="text-sm text-gray-500">Please wait while we prepare your crop preview.</p>
                            </div>
                        `;
                        document.body.appendChild(processingDiv);
                        
                        // Poll for crop completion and redirect to preview
                        this.pollForCropCompletion();
                    } else {
                        // Reset buttons
                        this.processRegionsBtn.disabled = false;
                        this.processRegionsBtn.textContent = 'Crop & Preview';
                        this.addRegionBtn.disabled = false;
                        this.clearRegionsBtn.disabled = false;
                        
                        alert('Error cropping regions: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Reset buttons
                    this.processRegionsBtn.disabled = false;
                    this.processRegionsBtn.textContent = 'Crop & Preview';
                    this.addRegionBtn.disabled = false;
                    this.clearRegionsBtn.disabled = false;
                    
                    alert('Error cropping regions. Please try again.');
                });
            }
            
            // ADDED: Method to enable/disable region selection
            setSelectionEnabled(enabled) {
                if (enabled) {
                    // Enable region selection
                    if (this.addRegionBtn) this.addRegionBtn.disabled = false;
                    if (this.clearRegionsBtn) this.clearRegionsBtn.disabled = false;
                    if (this.processRegionsBtn) this.processRegionsBtn.disabled = false;
                    
                    // Enable drawing on overlay
                    if (this.regionsOverlay) {
                        this.regionsOverlay.style.pointerEvents = 'auto';
                    }
                } else {
                    // Disable region selection
                    if (this.addRegionBtn) this.addRegionBtn.disabled = true;
                    if (this.clearRegionsBtn) this.clearRegionsBtn.disabled = true;
                    if (this.processRegionsBtn) this.processRegionsBtn.disabled = true;
                    
                    // Disable drawing on overlay
                    if (this.regionsOverlay) {
                        this.regionsOverlay.style.pointerEvents = 'none';
                    }
                }
            }
            
            pollForCropCompletion() {
                const checkStatus = () => {
                    fetch(`/ocr/{{ $ocrResult->id }}/status-check`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'awaiting_confirmation') {
                                // Crop completed, redirect to preview
                                window.location.href = `/ocr/{{ $ocrResult->id }}/crop-preview`;
                            } else if (data.status === 'error') {
                                // Remove loading overlay
                                const processingDiv = document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50');
                                if (processingDiv) {
                                    processingDiv.remove();
                                }
                                
                                // Reset buttons
                                this.processRegionsBtn.disabled = false;
                                this.processRegionsBtn.textContent = 'Crop & Preview';
                                this.addRegionBtn.disabled = false;
                                this.clearRegionsBtn.disabled = false;
                                
                                alert('Error during cropping: ' + (data.message || 'Unknown error'));
                            } else {
                                // Still processing, check again
                                setTimeout(checkStatus, 2000);
                            }
                        })
                        .catch(error => {
                            console.error('Error checking status:', error);
                            setTimeout(checkStatus, 2000);
                        });
                };
                
                // Start checking after a short delay
                setTimeout(checkStatus, 1000);
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
        
        // REMOVED: Duplicate rotation functions - using the newer implementation above
        
        // Update process button state based on total regions
        this.updateProcessButton();
    });
</script>