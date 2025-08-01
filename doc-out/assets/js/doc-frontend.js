/**
 * Frontend JavaScript for Document Generator
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initializeFrontend();
    });
    
    /**
     * Initialize frontend functionality
     */
    function initializeFrontend() {
        initFormSubmission();
        initProgressTracking();
    }
    
    /**
     * Initialize form submission handling
     */
    function initFormSubmission() {
        $('.klage-doc-form').on('submit', function(e) {
            e.preventDefault();
            handleFormSubmission($(this));
        });
    }
    
    /**
     * Initialize progress tracking
     */
    function initProgressTracking() {
        $('.klage-doc-track-progress').each(function() {
            var progressId = $(this).data('progress-id');
            if (progressId) {
                trackProgress(progressId);
            }
        });
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmission($form) {
        var submitButton = $form.find('button[type="submit"]');
        var originalText = submitButton.text();
        
        // Show loading state
        submitButton.prop('disabled', true).html('<span class="klage-doc-loading"></span> Processing...');
        
        // Clear previous notices
        $form.find('.klage-doc-notice').remove();
        
        var formData = new FormData($form[0]);
        
        $.ajax({
            url: $form.attr('action') || klageDocFrontend.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitButton.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showNotice($form, 'success', response.data.message || 'Success!');
                    
                    if (response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    }
                    
                    if (response.data.download_url) {
                        window.location.href = response.data.download_url;
                    }
                } else {
                    showNotice($form, 'error', response.data || 'An error occurred.');
                }
            },
            error: function() {
                submitButton.prop('disabled', false).text(originalText);
                showNotice($form, 'error', 'Network error. Please try again.');
            }
        });
    }
    
    /**
     * Track progress for long-running operations
     */
    function trackProgress(progressId) {
        var interval = setInterval(function() {
            $.post(klageDocFrontend.ajax_url, {
                action: 'track_progress',
                progress_id: progressId,
                nonce: klageDocFrontend.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var progress = response.data;
                    updateProgressBar(progressId, progress.percentage);
                    
                    if (progress.completed) {
                        clearInterval(interval);
                        handleProgressComplete(progressId, progress);
                    }
                } else {
                    clearInterval(interval);
                    showProgressError(progressId, response.data);
                }
            })
            .fail(function() {
                clearInterval(interval);
                showProgressError(progressId, 'Failed to track progress');
            });
        }, 1000);
    }
    
    /**
     * Update progress bar
     */
    function updateProgressBar(progressId, percentage) {
        var $progressBar = $('.klage-doc-progress[data-progress-id="' + progressId + '"] .klage-doc-progress-bar');
        $progressBar.css('width', percentage + '%');
        
        var $progressText = $('.klage-doc-progress-text[data-progress-id="' + progressId + '"]');
        $progressText.text(percentage + '% complete');
    }
    
    /**
     * Handle progress completion
     */
    function handleProgressComplete(progressId, progress) {
        var $container = $('.klage-doc-progress-container[data-progress-id="' + progressId + '"]');
        
        if (progress.success) {
            $container.html('<div class="klage-doc-notice success">Operation completed successfully!</div>');
            
            if (progress.download_url) {
                $container.append('<a href="' + progress.download_url + '" class="klage-doc-button">Download Result</a>');
            }
        } else {
            showProgressError(progressId, progress.error || 'Operation failed');
        }
    }
    
    /**
     * Show progress error
     */
    function showProgressError(progressId, error) {
        var $container = $('.klage-doc-progress-container[data-progress-id="' + progressId + '"]');
        $container.html('<div class="klage-doc-notice error">' + error + '</div>');
    }
    
    /**
     * Show notice
     */
    function showNotice($context, type, message) {
        var $notice = $('<div class="klage-doc-notice ' + type + '">' + message + '</div>');
        $context.prepend($notice);
        
        // Auto-dismiss success notices
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        }
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: $notice.offset().top - 100
        }, 500);
    }
    
    /**
     * Utility function to format file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Validate file uploads
     */
    function validateFileUpload($input) {
        var files = $input[0].files;
        var maxSize = $input.data('max-size') || 5 * 1024 * 1024; // 5MB default
        var allowedTypes = $input.data('allowed-types') ? $input.data('allowed-types').split(',') : [];
        
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            
            // Check file size
            if (file.size > maxSize) {
                showNotice($input.closest('form'), 'error', 'File "' + file.name + '" is too large. Maximum size is ' + formatFileSize(maxSize) + '.');
                return false;
            }
            
            // Check file type
            if (allowedTypes.length > 0) {
                var fileType = file.type || '';
                var extension = file.name.split('.').pop().toLowerCase();
                
                var isAllowed = allowedTypes.some(function(type) {
                    return fileType.includes(type) || extension === type;
                });
                
                if (!isAllowed) {
                    showNotice($input.closest('form'), 'error', 'File "' + file.name + '" has an invalid type. Allowed types: ' + allowedTypes.join(', ') + '.');
                    return false;
                }
            }
        }
        
        return true;
    }
    
    // File upload validation
    $(document).on('change', 'input[type="file"]', function() {
        validateFileUpload($(this));
    });
    
    // Export functions for external use
    window.klageDocFrontend = {
        showNotice: showNotice,
        trackProgress: trackProgress,
        formatFileSize: formatFileSize,
        validateFileUpload: validateFileUpload
    };

})(jQuery);