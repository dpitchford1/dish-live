jQuery(document).ready(function($) {
    var isProcessing = false;
    var isPaused = false;
    var processingTimeout;
    var consecutiveErrors = 0;
    var maxConsecutiveErrors = 5;
    var progressUpdateInterval;
    
    // Store progress data
    var progressData = {
        processed: parseInt($('.progress-text').data('processed') || 0),
        total: parseInt($('.progress-text').data('total') || 0),
        failedImages: []
    };
    
    // Initialize buttons
    if ($('.progress-bar').length) {
        showButton('start-processing');
    }
    
    // Start conversion when clicking the start button
    $('#start-processing').on('click', function() {
        startProcessing();
    });
    
    // Pause processing
    $('#pause-processing').on('click', function() {
        pauseProcessing();
    });
    
    // Resume processing
    $('#resume-processing').on('click', function() {
        resumeProcessing();
    });
    
    /**
     * Start the conversion process
     */
    function startProcessing() {
        if (isProcessing) return;
        
        isProcessing = true;
        isPaused = false;
        consecutiveErrors = 0;
        
        // Update UI
        showButton('pause-processing');
        $('#current-processing').show();
        
        // Add start log entry
        addLogEntry('⚡️ Starting WebP conversion process', 'info');
        
        // Start progress update interval
        startProgressUpdateInterval();
        
        // Process first image
        processNextImage();
    }
    
    /**
     * Pause the conversion process
     */
    function pauseProcessing() {
        isPaused = true;
        isProcessing = false;
        
        // Clear any pending timeouts
        clearTimeout(processingTimeout);
        clearInterval(progressUpdateInterval);
        
        // Update UI
        showButton('resume-processing');
        
        // Add log entry
        addLogEntry('⏸️ Conversion process paused', 'paused');
    }
    
    /**
     * Resume the conversion process
     */
    function resumeProcessing() {
        if (isProcessing) return;
        
        isProcessing = true;
        isPaused = false;
        consecutiveErrors = 0;
        
        // Update UI
        showButton('pause-processing');
        
        // Add log entry
        addLogEntry('▶️ Resuming conversion process', 'info');
        
        // Start progress update interval
        startProgressUpdateInterval();
        
        // Process next image
        processNextImage();
    }
    
    /**
     * Process the next image in the queue
     */
    function processNextImage() {
        if (!isProcessing || isPaused) return;
        
        // AJAX request to process a single image
        $.ajax({
            url: basecampWebp.ajaxUrl,
            type: 'POST',
            data: {
                action: 'basecamp_webp_process_single',
                nonce: basecampWebp.nonce
            },
            dataType: 'json',
            success: function(response) {
                // Reset consecutive errors counter on success
                consecutiveErrors = 0;
                
                if (response.success) {
                    // Handle successful response
                    handleSuccessResponse(response);
                } else {
                    // Handle error response
                    handleErrorResponse(response);
                }
                
                // If more images to process, continue
                if (response.data && response.data.converting) {
                    processingTimeout = setTimeout(processNextImage, 250);
                } else {
                    handleCompletion();
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX error
                handleAjaxError(xhr, status, error);
            }
        });
    }
    
    /**
     * Handle successful AJAX response
     */
    function handleSuccessResponse(response) {
        if (!response.data) return;
        
        // Extract data from response
        var data = response.data;
        
        // Update progress counters
        if (data.progress) {
            updateProgressDisplay(data.progress);
        }
        
        // Display current image being processed
        if (data.image && data.image.name) {
            $('#current-image-name').text(data.image.name);
        }
        
        // Add log entry for this image
        if (data.image) {
            var image = data.image;
            var logMessage = '';
            var logType = '';
            
            if (image.success) {
                logMessage = '✅ Converted: ' + image.name;
                if (image.original_size && image.webp_size) {
                    var originalSize = formatBytes(image.original_size);
                    var webpSize = formatBytes(image.webp_size);
                    var savings = Math.round((1 - (image.webp_size / image.original_size)) * 100);
                    logMessage += ' (' + originalSize + ' → ' + webpSize + ', ' + savings + '% smaller)';
                }
                logType = 'success';
            } else {
                logMessage = '❌ Failed: ' + image.name + ' - ' + (image.error || 'Unknown error');
                logType = 'error';
                // Track failed images
                progressData.failedImages.push(image.name);
            }
            
            addLogEntry(logMessage, logType);
        }
        
        // Check if we need to add other message types
        if (data.messages && data.messages.length) {
            data.messages.forEach(function(msg) {
                addLogEntry(msg.text, msg.type || 'info');
            });
        }
    }
    
    /**
     * Handle error response from AJAX
     */
    function handleErrorResponse(response) {
        var errorMessage = response.data && response.data.message 
            ? response.data.message 
            : 'Unknown error occurred during conversion';
            
        addLogEntry('⚠️ ' + errorMessage, 'error');
        
        // Track error count
        consecutiveErrors++;
        
        // If too many consecutive errors, pause the process
        if (consecutiveErrors >= maxConsecutiveErrors) {
            addLogEntry('🛑 Too many consecutive errors. Process paused. Please check the logs and try again.', 'warning');
            pauseProcessing();
        }
    }
    
    /**
     * Handle AJAX request error
     */
    function handleAjaxError(xhr, status, error) {
        addLogEntry('🛑 AJAX Error: ' + (error || status), 'error');
        
        // Track error count
        consecutiveErrors++;
        
        // If too many consecutive errors, pause the process
        if (consecutiveErrors >= maxConsecutiveErrors) {
            addLogEntry('🛑 Too many consecutive errors. Process paused. Please refresh the page and try again.', 'warning');
            pauseProcessing();
        } else {
            // Try again after a short delay
            processingTimeout = setTimeout(processNextImage, 3000);
        }
    }
    
    /**
     * Handle completion of the conversion process
     */
    function handleCompletion() {
        isProcessing = false;
        clearInterval(progressUpdateInterval);
        
        // Update UI
        showButton('start-processing');
        $('#current-processing').hide();
        
        // Add completion log entry
        if (progressData.failedImages.length > 0) {
            addLogEntry('🏁 Conversion process completed with ' + progressData.failedImages.length + ' failed images', 'warning');
        } else {
            addLogEntry('🎉 Conversion process completed successfully! All images have been converted.', 'success');
        }
        
        // Refresh the page to update stats
        setTimeout(function() {
            window.location.reload();
        }, 2000);
    }
    
    /**
     * Start the interval to update progress display periodically
     */
    function startProgressUpdateInterval() {
        progressUpdateInterval = setInterval(function() {
            $.ajax({
                url: basecampWebp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'basecamp_webp_get_progress',
                    nonce: basecampWebp.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        updateProgressDisplay(response.data);
                    }
                }
            });
        }, 5000); // Update every 5 seconds
    }
    
    /**
     * Update the progress display based on current progress
     */
    function updateProgressDisplay(progress) {
        if (!progress) return;
        
        // Update progress bar
        var percent = progress.total_images > 0 
            ? (progress.processed_images / progress.total_images) * 100 
            : 0;
            
        $('.progress').css('width', percent + '%');
        
        // Update progress text
        $('.progress-text').html(
            '<strong>Progress:</strong> ' + progress.processed_images + ' of ' + 
            progress.total_images + ' images processed (' + 
            Math.round(percent * 10) / 10 + '%)'
        );
        
        // Update internal progress data
        progressData.processed = progress.processed_images;
        progressData.total = progress.total_images;
        
        // If we have space saved info, update that too
        if (progress.space_saved !== undefined && $('.stats-section').length) {
            // This will be handled by the page reload after completion
            // But could add real-time updates here if needed
        }
    }
    
    /**
     * Add an entry to the processing log
     */
    function addLogEntry(message, type) {
        if (!message) return;
        
        type = type || 'info';
        
        var now = new Date();
        var timeStr = now.getHours().toString().padStart(2, '0') + ':' +
                     now.getMinutes().toString().padStart(2, '0') + ':' +
                     now.getSeconds().toString().padStart(2, '0');
        
        var icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️',
            'paused': '⏸️'
        };
        
        var icon = icons[type] || icons['info'];
        
        var logEntry = $('<li class="log-entry log-' + type + '"></li>');
        logEntry.html('<span class="log-time">[' + timeStr + ']</span> ' + message);
        
        $('#log-entries').prepend(logEntry);
        
        // Scroll to top of log
        $('#processing-log').scrollTop(0);
    }
    
    /**
     * Show a specific button and hide others
     */
    function showButton(buttonId) {
        var buttons = ['start-processing', 'pause-processing', 'resume-processing'];
        
        buttons.forEach(function(id) {
            if (id === buttonId) {
                $('#' + id).show();
            } else {
                $('#' + id).hide();
            }
        });
    }
    
    /**
     * Format bytes to human readable format
     */
    function formatBytes(bytes, decimals) {
        if (bytes === 0) return '0 Bytes';
        
        decimals = decimals || 2;
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
    }
}); 