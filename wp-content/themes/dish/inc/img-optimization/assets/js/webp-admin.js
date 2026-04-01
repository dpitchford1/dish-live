/**
 * WebP Conversion Admin JavaScript
 *
 * @package basecamp
 */

(function($) {
    'use strict';

    // Variables to store conversion state
    let isProcessing = false;
    let isPaused = false;
    let processingQueue = [];
    let currentImageIndex = 0;
    let originalPageTitle = document.title;
    let retryCount = 0;
    let maxRetries = 3;
    let processingDelay = 500; // Default delay
    
    // DOM elements
    const $startButton = $('#start-processing');
    const $pauseButton = $('#pause-processing');
    const $resumeButton = $('#resume-processing');
    const $progressBar = $('.progress');
    const $progressText = $('.progress-text');
    const $currentProcessing = $('#current-processing');
    const $currentImageName = $('#current-image-name');
    const $logEntries = $('#log-entries');
    
    /**
     * Initialize the page
     */
    function init() {
        // Set up button event handlers
        $startButton.on('click', startProcessing);
        $pauseButton.on('click', pauseProcessing);
        $resumeButton.on('click', resumeProcessing);
        
        // Make sure forms have proper confirmation
        $('#webp-conversion-form').on('submit', function() {
            return confirm('Are you sure you want to start a new conversion process? This might take some time depending on the number of images in your Media Library.');
        });
        
        // If there's a conversion in progress, check if we should auto-start
        checkAutoStart();
        
        // Save state in localStorage when user leaves page
        $(window).on('beforeunload', saveState);
        
        // Request notification permission
        requestNotificationPermission();
        
        // Store original page title
        originalPageTitle = document.title;
    }
    
    /**
     * Request permission for browser notifications
     */
    function requestNotificationPermission() {
        if ("Notification" in window && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
    }
    
    /**
     * Show browser notification
     */
    function showNotification(title, message) {
        if ("Notification" in window && Notification.permission === "granted") {
            const notification = new Notification(title, {
                body: message,
                icon: "/wp-includes/images/w-logo-blue.png"
            });
            
            // Close notification after 5 seconds
            setTimeout(() => {
                notification.close();
            }, 5000);
        }
    }
    
    /**
     * Check if we should auto-start the conversion
     */
    function checkAutoStart() {
        const savedState = loadState();
        if (savedState && savedState.isProcessing && !savedState.isPaused) {
            // Auto-start with a slight delay
            setTimeout(function() {
                if (confirm(basecampWebp.strings.resume || 'Resume the WebP conversion that was in progress?')) {
                    resumeProcessing();
                }
            }, 1000);
        }
    }
    
    /**
     * Start the conversion process
     */
    function startProcessing() {
        isProcessing = true;
        isPaused = false;
        retryCount = 0;
        
        // Update UI
        $startButton.hide();
        $pauseButton.show();
        $resumeButton.hide();
        $currentProcessing.show();
        
        // Clear the log if starting from scratch
        $logEntries.empty();
        
        // Process the first image
        processNextImage();
        
        // Save state
        saveState();
    }
    
    /**
     * Pause the conversion process
     */
    function pauseProcessing() {
        isPaused = true;
        
        // Update UI
        $pauseButton.hide();
        $resumeButton.show();
        
        // Add pause message to log
        addLogEntry('Conversion paused by user.', 'paused');
        
        // Update browser tab title
        document.title = '⏸ ' + originalPageTitle;
        
        // Save state
        saveState();
    }
    
    /**
     * Resume the conversion process
     */
    function resumeProcessing() {
        isPaused = false;
        isProcessing = true;
        retryCount = 0;
        
        // Update UI
        $startButton.hide();
        $resumeButton.hide();
        $pauseButton.show();
        $currentProcessing.show();
        
        // Add resume message to log
        addLogEntry('Conversion resumed.', 'info');
        
        // Process the next image
        processNextImage();
        
        // Save state
        saveState();
    }
    
    /**
     * Calculate adaptive delay based on processing time
     */
    function calculateAdaptiveDelay(startTime) {
        const processingTime = new Date().getTime() - startTime;
        
        // Adjust delay based on processing time
        if (processingTime > 2000) {
            // Slow conversion, increase delay
            processingDelay = Math.min(2000, processingDelay + 100);
        } else if (processingTime < 500 && processingDelay > 500) {
            // Fast conversion, decrease delay
            processingDelay = Math.max(500, processingDelay - 50);
        }
        
        return processingDelay;
    }
    
    /**
     * Process the next image in the queue
     */
    function processNextImage() {
        if (!isProcessing || isPaused) {
            return;
        }
        
        // Reset retry count for each new image
        retryCount = 0;
        
        // Update UI to show we're processing
        $currentImageName.text('Loading next image...');
        
        const startTime = new Date().getTime();
        
        // Make AJAX request to process a single image
        $.ajax({
            url: basecampWebp.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'basecamp_webp_process_single',
                nonce: basecampWebp.nonce
            },
            success: function(response) {
                if (response.success) {
                    handleSuccessResponse(response.data, startTime);
                } else {
                    handleErrorResponse(response.data);
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX error with retry logic
                if (retryCount < maxRetries) {
                    retryCount++;
                    const retryDelay = 2000 * retryCount; // Progressive backoff
                    
                    addLogEntry('Server error occurred. Retrying in ' + (retryDelay/1000) + ' seconds... (Attempt ' + retryCount + '/' + maxRetries + ')', 'warning');
                    $currentImageName.text('Retry in ' + (retryDelay/1000) + 's...');
                    
                    setTimeout(processNextImage, retryDelay);
                } else {
                    const errorMessage = (xhr.responseJSON && xhr.responseJSON.message) ? 
                        xhr.responseJSON.message : 
                        'Error: ' + status + (error ? ' - ' + error : '');
                        
                    addLogEntry('Server error occurred after ' + maxRetries + ' attempts. Please try again. ' + errorMessage, 'error');
                    $currentImageName.text('Error occurred');
                    
                    pauseProcessing();
                    showNotification('WebP Conversion Error', 'An error occurred during conversion. Process has been paused.');
                }
            },
            timeout: 60000 // 60 second timeout for very large images
        });
    }
    
    /**
     * Handle a successful AJAX response
     */
    function handleSuccessResponse(data, startTime) {
        // Check if we're done
        if (data.status === 'complete') {
            // Conversion is complete
            isProcessing = false;
            
            // Update UI
            $currentProcessing.hide();
            $pauseButton.hide();
            $resumeButton.hide();
            
            // Update progress for 100% completion
            updateProgress(data.progress);
            
            // Add completion message to log
            addLogEntry('WebP conversion complete! All images have been processed.', 'success');
            
            // Show browser notification
            showNotification('WebP Conversion Complete', 'All images have been processed successfully!');
            
            // Reset browser title
            document.title = '✓ ' + originalPageTitle;
            
            // Clear saved state
            clearState();
            
            return;
        }
        
        // Process the current image result
        const image = data.image;
        $currentImageName.text(image.filename);
        
        // Update the log with detailed information
        if (image.success) {
            let sizeInfo = '';
            if (image.details && image.details.original) {
                sizeInfo = ' (' + image.details.original.reduction + ' reduction)';
            }
            
            addLogEntry('Converted: ' + image.filename + sizeInfo, 'success');
            
            // Add more details if available
            if (image.details && image.details.sizes_converted) {
                addLogEntry('Size variants: ' + image.details.sizes_converted, 'info');
            }
        } else {
            addLogEntry('Failed to convert: ' + image.filename + ' - ' + image.message, 'error');
        }
        
        // Update progress
        updateProgress(data.progress);
        
        // Process the next image if we're still going
        if (isProcessing && !isPaused) {
            // Calculate adaptive delay based on processing time
            const delay = calculateAdaptiveDelay(startTime);
            setTimeout(processNextImage, delay);
        }
        
        // Save state
        saveState();
    }
    
    /**
     * Handle an error response
     */
    function handleErrorResponse(data) {
        // Add error to log
        addLogEntry('Error: ' + (data.message || 'Unknown error occurred'), 'error');
        
        // Pause processing
        pauseProcessing();
        
        // Show notification
        showNotification('WebP Conversion Error', data.message || 'An error occurred during conversion.');
    }
    
    /**
     * Update the progress bar and text
     */
    function updateProgress(progress) {
        const total = progress.total_images || 0;
        const processed = progress.processed_images || 0;
        
        if (total > 0) {
            const percentage = (processed / total) * 100;
            const roundedPercentage = Math.round(percentage * 10) / 10;
            
            // Update progress bar
            $progressBar.css('width', percentage + '%');
            
            // Update text
            $progressText.html('<strong>Progress:</strong> ' + processed + ' of ' + total + ' images processed (' + roundedPercentage + '%)');
            
            // Update browser tab title with progress
            if (isProcessing && !isPaused) {
                document.title = '(' + Math.round(percentage) + '%) ' + originalPageTitle;
            }
        }
    }
    
    /**
     * Add an entry to the log
     */
    function addLogEntry(message, type) {
        const timestamp = new Date().toLocaleTimeString();
        let icon = '•';
        
        // Set icon based on type
        switch (type) {
            case 'success':
                icon = '✓';
                break;
            case 'error':
                icon = '⚠';
                break;
            case 'paused':
                icon = '⏸';
                break;
            case 'info':
                icon = 'ℹ';
                break;
            case 'warning':
                icon = '⚠';
                break;
        }
        
        // Create and append log entry
        const $entry = $('<li>').addClass('log-entry log-' + type).html('<span class="log-time">[' + timestamp + ']</span> <span class="log-icon">' + icon + '</span> ' + message);
        $logEntries.prepend($entry);
        
        // Scroll to top of log
        const $logContainer = $('#processing-log');
        $logContainer.scrollTop(0);
    }
    
    /**
     * Save the current state to localStorage
     */
    function saveState() {
        const state = {
            isProcessing: isProcessing,
            isPaused: isPaused,
            timestamp: new Date().getTime()
        };
        
        localStorage.setItem('basecampWebpState', JSON.stringify(state));
    }
    
    /**
     * Load state from localStorage
     */
    function loadState() {
        const stateJson = localStorage.getItem('basecampWebpState');
        if (stateJson) {
            try {
                const state = JSON.parse(stateJson);
                
                // Check if state is recent (less than 1 hour old)
                const currentTime = new Date().getTime();
                const stateAge = currentTime - (state.timestamp || 0);
                
                if (stateAge < 3600000) {
                    return state;
                }
            } catch (e) {
                // Invalid JSON, ignore
            }
        }
        
        return null;
    }
    
    /**
     * Clear saved state
     */
    function clearState() {
        localStorage.removeItem('basecampWebpState');
    }
    
    // Initialize when DOM is ready
    $(document).ready(init);
    
})(jQuery); 