/**
 * Admin JavaScript for Document Generator
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initializeDocumentGenerator();
    });
    
    /**
     * Initialize document generator functionality
     */
    function initializeDocumentGenerator() {
        initTemplateGallery();
        initDocumentEditor();
        initFormValidation();
        initS3Testing();
        initPlaceholderHelpers();
    }
    
    /**
     * Template Gallery functionality
     */
    function initTemplateGallery() {
        // Template actions
        $('.template-action').on('click', function(e) {
            e.preventDefault();
            
            var action = $(this).data('action');
            var templateId = $(this).data('template-id');
            
            switch(action) {
                case 'duplicate':
                    duplicateTemplate(templateId);
                    break;
                case 'delete':
                    deleteTemplate(templateId);
                    break;
                case 'edit':
                    editTemplate(templateId);
                    break;
            }
        });
        
        // Template category filter
        $('#template-category-filter').on('change', function() {
            var category = $(this).val();
            filterTemplates(category);
        });
        
        // Template search
        $('#template-search').on('input', debounce(function() {
            var search = $(this).val();
            searchTemplates(search);
        }, 300));
    }
    
    /**
     * Document Editor functionality
     */
    function initDocumentEditor() {
        // Auto-save functionality
        if ($('#document_content').length > 0) {
            setInterval(function() {
                autoSaveDraft();
            }, 120000); // Auto-save every 2 minutes
        }
        
        // Save draft button
        $('#save-draft').on('click', function() {
            saveDraft();
        });
        
        // Generate PDF button
        $('#generate-pdf').on('click', function() {
            generatePDF();
        });
        
        // Preview button
        $('#preview-document').on('click', function() {
            previewDocument();
        });
    }
    
    /**
     * Form validation
     */
    function initFormValidation() {
        // Template form validation
        $('#template-form').on('submit', function(e) {
            var isValid = validateTemplateForm();
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Document generation form validation
        $('#generate-document-form').on('submit', function(e) {
            var templateId = $('#template_select').val();
            if (!templateId) {
                e.preventDefault();
                showNotice('error', klageDocAjax.strings.error_no_template || 'Please select a template.');
                return false;
            }
        });
    }
    
    /**
     * S3 Testing functionality
     */
    function initS3Testing() {
        $('#test-s3-connection').on('click', function() {
            testS3Connection();
        });
    }
    
    /**
     * Placeholder helpers
     */
    function initPlaceholderHelpers() {
        // Insert placeholder on click
        $('.placeholder-category code').on('click', function() {
            var placeholder = $(this).text();
            insertPlaceholder(placeholder);
        });
    }
    
    /**
     * Duplicate template
     */
    function duplicateTemplate(templateId) {
        if (!confirm(klageDocAjax.strings.confirm_duplicate || 'Are you sure you want to duplicate this template?')) {
            return;
        }
        
        showLoading();
        
        $.post(klageDocAjax.ajax_url, {
            action: 'duplicate_template',
            template_id: templateId,
            nonce: klageDocAjax.nonce
        })
        .done(function(response) {
            hideLoading();
            
            if (response.success) {
                showNotice('success', response.data.message);
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotice('error', response.data || klageDocAjax.strings.error_occurred);
            }
        })
        .fail(function() {
            hideLoading();
            showNotice('error', klageDocAjax.strings.error_occurred);
        });
    }
    
    /**
     * Delete template
     */
    function deleteTemplate(templateId) {
        if (!confirm(klageDocAjax.strings.confirm_delete || 'Are you sure you want to delete this template?')) {
            return;
        }
        
        showLoading();
        
        $.post(klageDocAjax.ajax_url, {
            action: 'delete_template',
            template_id: templateId,
            nonce: klageDocAjax.nonce
        })
        .done(function(response) {
            hideLoading();
            
            if (response.success) {
                showNotice('success', response.data.message);
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotice('error', response.data || klageDocAjax.strings.error_occurred);
            }
        })
        .fail(function() {
            hideLoading();
            showNotice('error', klageDocAjax.strings.error_occurred);
        });
    }
    
    /**
     * Save document draft
     */
    function saveDraft() {
        var draftId = $('#draft_id').val();
        var editor = tinymce.get('document_content');
        var content = editor ? editor.getContent() : $('#document_content').val();
        var button = $('#save-draft');
        
        if (!draftId) {
            showNotice('error', 'Draft ID not found.');
            return;
        }
        
        button.addClass('loading').prop('disabled', true);
        var originalText = button.val() || button.text();
        button.val('Saving...').text('Saving...');
        
        $.post(klageDocAjax.ajax_url, {
            action: 'save_document_draft',
            draft_id: draftId,
            draft_html: content,
            nonce: klageDocAjax.nonce
        })
        .done(function(response) {
            button.removeClass('loading').prop('disabled', false);
            
            if (response.success) {
                button.val('Saved!').text('Saved!');
                showNotice('success', response.data.message);
                setTimeout(function() {
                    button.val(originalText).text(originalText);
                }, 2000);
            } else {
                button.val(originalText).text(originalText);
                showNotice('error', response.data || klageDocAjax.strings.error_occurred);
            }
        })
        .fail(function() {
            button.removeClass('loading').prop('disabled', false);
            button.val(originalText).text(originalText);
            showNotice('error', klageDocAjax.strings.error_occurred);
        });
    }
    
    /**
     * Auto-save draft
     */
    function autoSaveDraft() {
        if ($('#save-draft').length > 0) {
            saveDraft();
        }
    }
    
    /**
     * Generate PDF
     */
    function generatePDF() {
        var draftId = $('#draft_id').val();
        var editor = tinymce.get('document_content');
        var content = editor ? editor.getContent() : $('#document_content').val();
        var button = $('#generate-pdf');
        
        if (!draftId) {
            showNotice('error', 'Draft ID not found.');
            return;
        }
        
        button.addClass('loading').prop('disabled', true);
        var originalText = button.val() || button.text();
        button.val(klageDocAjax.strings.generating_pdf || 'Generating PDF...').text(klageDocAjax.strings.generating_pdf || 'Generating PDF...');
        
        // First save the current content
        $.post(klageDocAjax.ajax_url, {
            action: 'save_document_draft',
            draft_id: draftId,
            draft_html: content,
            nonce: klageDocAjax.nonce
        })
        .done(function(saveResponse) {
            if (saveResponse.success) {
                // Then generate PDF
                $.post(klageDocAjax.ajax_url, {
                    action: 'generate_final_pdf',
                    draft_id: draftId,
                    nonce: klageDocAjax.nonce
                })
                .done(function(pdfResponse) {
                    button.removeClass('loading').prop('disabled', false).val(originalText).text(originalText);
                    
                    if (pdfResponse.success) {
                        showNotice('success', pdfResponse.data.message);
                        // Trigger download
                        if (pdfResponse.data.download_url) {
                            window.location.href = pdfResponse.data.download_url;
                        }
                    } else {
                        showNotice('error', 'PDF Error: ' + (pdfResponse.data || klageDocAjax.strings.error_occurred));
                    }
                })
                .fail(function() {
                    button.removeClass('loading').prop('disabled', false).val(originalText).text(originalText);
                    showNotice('error', klageDocAjax.strings.error_occurred);
                });
            } else {
                button.removeClass('loading').prop('disabled', false).val(originalText).text(originalText);
                showNotice('error', 'Save Error: ' + (saveResponse.data || klageDocAjax.strings.error_occurred));
            }
        })
        .fail(function() {
            button.removeClass('loading').prop('disabled', false).val(originalText).text(originalText);
            showNotice('error', klageDocAjax.strings.error_occurred);
        });
    }
    
    /**
     * Test S3 connection
     */
    function testS3Connection() {
        var button = $('#test-s3-connection');
        var result = $('#s3-test-result');
        
        button.prop('disabled', true).text('Testing...');
        result.html('');
        
        $.post(klageDocAjax.ajax_url, {
            action: 'test_s3_connection',
            nonce: klageDocAjax.nonce
        })
        .done(function(response) {
            if (response.success) {
                result.html('<div class="notice notice-success"><p>S3 connection successful!</p></div>');
            } else {
                result.html('<div class="notice notice-error"><p>S3 connection failed: ' + (response.data || 'Unknown error') + '</p></div>');
            }
        })
        .fail(function() {
            result.html('<div class="notice notice-error"><p>S3 connection test failed due to network error.</p></div>');
        })
        .always(function() {
            button.prop('disabled', false).text('Test Connection');
        });
    }
    
    /**
     * Insert placeholder into editor
     */
    function insertPlaceholder(placeholder) {
        var editor = tinymce.get('template_html') || tinymce.get('document_content');
        
        if (editor) {
            editor.insertContent(placeholder);
            editor.focus();
        } else {
            var textarea = $('#template_html, #document_content');
            if (textarea.length > 0) {
                var pos = textarea[0].selectionStart;
                var text = textarea.val();
                textarea.val(text.substring(0, pos) + placeholder + text.substring(pos));
                textarea.focus();
            }
        }
    }
    
    /**
     * Validate template form
     */
    function validateTemplateForm() {
        var isValid = true;
        var errors = [];
        
        // Template name
        var templateName = $('#template_name').val().trim();
        if (!templateName) {
            errors.push('Template name is required.');
            $('#template_name').addClass('error');
            isValid = false;
        } else {
            $('#template_name').removeClass('error');
        }
        
        // Template HTML
        var editor = tinymce.get('template_html');
        var templateHtml = editor ? editor.getContent() : $('#template_html').val();
        
        if (!templateHtml.trim()) {
            errors.push('Template content is required.');
            if (editor) {
                $(editor.getContainer()).addClass('error');
            } else {
                $('#template_html').addClass('error');
            }
            isValid = false;
        } else {
            if (editor) {
                $(editor.getContainer()).removeClass('error');
            } else {
                $('#template_html').removeClass('error');
            }
        }
        
        if (!isValid) {
            showNotice('error', errors.join('<br>'));
        }
        
        return isValid;
    }
    
    /**
     * Show notification
     */
    function showNotice(type, message) {
        var noticeClass = 'notice-' + type;
        var notice = $('<div class="notice ' + noticeClass + ' klage-doc-notice is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Manual dismiss
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }
    
    /**
     * Show loading state
     */
    function showLoading() {
        $('body').addClass('loading');
    }
    
    /**
     * Hide loading state
     */
    function hideLoading() {
        $('body').removeClass('loading');
    }
    
    /**
     * Debounce function
     */
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    /**
     * Filter templates by category
     */
    function filterTemplates(category) {
        // This would be implemented with AJAX to reload the table
        // For now, redirect with query parameter
        var url = new URL(window.location);
        if (category) {
            url.searchParams.set('category', category);
        } else {
            url.searchParams.delete('category');
        }
        window.location.href = url.toString();
    }
    
    /**
     * Search templates
     */
    function searchTemplates(search) {
        // This would be implemented with AJAX to reload the table
        // For now, redirect with query parameter
        var url = new URL(window.location);
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }
    
    // Global functions for backwards compatibility
    window.duplicateTemplate = duplicateTemplate;
    window.deleteTemplate = deleteTemplate;
    
    // Generate encryption key function
    window.generateEncryptionKey = function() {
        var key = '';
        var chars = '0123456789abcdef';
        for (var i = 0; i < 64; i++) {
            key += chars[Math.floor(Math.random() * chars.length)];
        }
        $('input[name="klage_doc_encryption_key"]').val(key);
    };

})(jQuery);