/**
 * Admin JavaScript for Document Analysis Plugin
 */

jQuery(document).ready(function($) {
    
    // Assignment modal functionality
    $('.assign-btn').on('click', function(e) {
        e.preventDefault();
        var communicationId = $(this).data('id');
        $('#communication-id').val(communicationId);
        $('#assignment-modal').show();
    });
    
    // Cancel assignment modal
    $('#cancel-assignment, #assignment-modal').on('click', function(e) {
        if (e.target === this) {
            $('#assignment-modal').hide();
        }
    });
    
    // Prevent modal close when clicking inside modal content
    $('.assignment-modal-content').on('click', function(e) {
        e.stopPropagation();
    });
    
    // Assignment form submission
    $('#assignment-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        
        // Show loading state
        $submitBtn.text('Assigning...').prop('disabled', true);
        
        var data = {
            action: 'assign_communication',
            nonce: cah_doc_in_admin.nonce,
            communication_id: $('#communication-id').val(),
            case_id: $('#case-select').val()
        };
        
        $.post(cah_doc_in_admin.ajax_url, data, function(response) {
            if (response.success) {
                // Show success message
                showMessage('Communication assigned successfully!', 'success');
                
                // Hide modal
                $('#assignment-modal').hide();
                
                // Reload page or update UI
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showMessage('Error: ' + response.data, 'error');
            }
        }).fail(function() {
            showMessage('Network error occurred. Please try again.', 'error');
        }).always(function() {
            // Reset button state
            $submitBtn.text(originalText).prop('disabled', false);
        });
    });
    
    // Create new case button
    $('#create-new-case').on('click', function() {
        var communicationId = $('#communication-id').val();
        if (confirm('This will create a new case from the communication data. Continue?')) {
            // Implementation for creating new case
            var data = {
                action: 'create_case_from_communication',
                nonce: cah_doc_in_admin.nonce,
                communication_id: communicationId
            };
            
            $.post(cah_doc_in_admin.ajax_url, data, function(response) {
                if (response.success) {
                    showMessage('New case created successfully!', 'success');
                    $('#assignment-modal').hide();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Error creating case: ' + response.data, 'error');
                }
            });
        }
    });
    
    // Bulk actions handling
    $('#unassigned-communications-form').on('submit', function(e) {
        var selectedAction = $('#bulk-action-selector').val();
        var selectedComms = $('input[name="communications[]"]:checked');
        
        if (!selectedAction) {
            e.preventDefault();
            alert('Please select an action.');
            return;
        }
        
        if (selectedComms.length === 0) {
            e.preventDefault();
            alert('Please select at least one communication.');
            return;
        }
        
        if (selectedAction === 'assign') {
            e.preventDefault();
            // Handle bulk assignment
            handleBulkAssignment();
        } else if (selectedAction === 'create_case') {
            e.preventDefault();
            // Handle bulk case creation
            handleBulkCaseCreation();
        }
    });
    
    // Select all checkbox functionality
    $('#cb-select-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('input[name="communications[]"]').prop('checked', isChecked);
    });
    
    // Individual checkbox change
    $('input[name="communications[]"]').on('change', function() {
        var totalCheckboxes = $('input[name="communications[]"]').length;
        var checkedCheckboxes = $('input[name="communications[]"]:checked').length;
        
        $('#cb-select-all').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#cb-select-all').prop('checked', checkedCheckboxes === totalCheckboxes);
    });
    
    // Auto-refresh dashboard stats every 30 seconds
    if ($('.cah-doc-in-dashboard').length > 0) {
        setInterval(function() {
            refreshDashboardStats();
        }, 30000);
    }
    
    // Category management - real-time API testing
    $('#test-categories-api').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.text('Testing...').prop('disabled', true);
        
        $.get(cah_doc_in_admin.api_base_url + 'categories')
            .done(function(response) {
                if (response.success) {
                    showMessage('API test successful! Found ' + response.categories.length + ' categories.', 'success');
                } else {
                    showMessage('API test failed: ' + (response.error || 'Unknown error'), 'error');
                }
            })
            .fail(function() {
                showMessage('API test failed: Network error', 'error');
            })
            .always(function() {
                $btn.text(originalText).prop('disabled', false);
            });
    });
    
    // Helper functions
    function showMessage(message, type) {
        var messageClass = type === 'success' ? 'cah-success' : 'cah-error';
        var $message = $('<div class="' + messageClass + '">' + message + '</div>');
        
        // Insert after the first h1 or at the beginning of .wrap
        var $insertAfter = $('.wrap h1').first();
        if ($insertAfter.length) {
            $insertAfter.after($message);
        } else {
            $('.wrap').prepend($message);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 5000);
    }
    
    function handleBulkAssignment() {
        var selectedComms = [];
        $('input[name="communications[]"]:checked').each(function() {
            selectedComms.push($(this).val());
        });
        
        // Show case selection modal for bulk assignment
        var modal = '<div id="bulk-assignment-modal" style="display: none;">' +
                   '<div class="assignment-modal-content">' +
                   '<h3>Bulk Assign Communications</h3>' +
                   '<p>Assign ' + selectedComms.length + ' communications to:</p>' +
                   '<select id="bulk-case-select" style="width: 100%; margin: 10px 0;">' +
                   '<option value="">Select a case...</option>' +
                   // Cases will be loaded via AJAX or from existing dropdown
                   '</select>' +
                   '<p>' +
                   '<button type="button" class="button button-primary" id="bulk-assign-submit">Assign All</button>' +
                   '<button type="button" class="button" id="bulk-assign-cancel">Cancel</button>' +
                   '</p>' +
                   '</div></div>';
        
        $('body').append(modal);
        
        // Copy options from existing case select
        $('#case-select option').each(function() {
            $('#bulk-case-select').append($(this).clone());
        });
        
        $('#bulk-assignment-modal').show();
        
        // Handle bulk assignment submission
        $('#bulk-assign-submit').on('click', function() {
            var caseId = $('#bulk-case-select').val();
            if (!caseId) {
                alert('Please select a case.');
                return;
            }
            
            var data = {
                action: 'bulk_assign_communications',
                nonce: cah_doc_in_admin.nonce,
                communication_ids: selectedComms,
                case_id: caseId
            };
            
            $.post(cah_doc_in_admin.ajax_url, data, function(response) {
                if (response.success) {
                    showMessage('Communications assigned successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
                $('#bulk-assignment-modal').remove();
            });
        });
        
        // Handle cancel
        $('#bulk-assign-cancel').on('click', function() {
            $('#bulk-assignment-modal').remove();
        });
    }
    
    function handleBulkCaseCreation() {
        var selectedComms = [];
        $('input[name="communications[]"]:checked').each(function() {
            selectedComms.push($(this).val());
        });
        
        if (confirm('This will create ' + selectedComms.length + ' new cases. Continue?')) {
            var data = {
                action: 'bulk_create_cases_from_communications',
                nonce: cah_doc_in_admin.nonce,
                communication_ids: selectedComms
            };
            
            $.post(cah_doc_in_admin.ajax_url, data, function(response) {
                if (response.success) {
                    showMessage(response.data.created + ' new cases created successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            });
        }
    }
    
    function refreshDashboardStats() {
        $.get(cah_doc_in_admin.ajax_url, {
            action: 'get_dashboard_stats',
            nonce: cah_doc_in_admin.nonce
        }, function(response) {
            if (response.success) {
                // Update stat boxes
                $('.stats-grid .stat-box').each(function(index) {
                    var $statBox = $(this);
                    var statType = ['total_communications', 'auto_assigned', 'unassigned', 'new_cases'][index];
                    if (response.data[statType] !== undefined) {
                        $statBox.find('h3').text(response.data[statType]);
                    }
                });
            }
        });
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // ESC to close modal
        if (e.keyCode === 27) {
            $('#assignment-modal, #bulk-assignment-modal').hide().remove();
        }
        
        // Ctrl+A to select all communications (when on unassigned page)
        if (e.ctrlKey && e.keyCode === 65 && $('.wp-list-table').length > 0) {
            e.preventDefault();
            $('#cb-select-all').prop('checked', true).trigger('change');
        }
    });
    
    // Enhanced search functionality
    if ($('#communication-search').length > 0) {
        var searchTimeout;
        $('#communication-search').on('input', function() {
            clearTimeout(searchTimeout);
            var query = $(this).val();
            
            searchTimeout = setTimeout(function() {
                if (query.length >= 3) {
                    performCommunicationSearch(query);
                } else if (query.length === 0) {
                    resetCommunicationTable();
                }
            }, 500);
        });
    }
    
    function performCommunicationSearch(query) {
        var data = {
            action: 'search_communications',
            nonce: cah_doc_in_admin.nonce,
            query: query
        };
        
        $.post(cah_doc_in_admin.ajax_url, data, function(response) {
            if (response.success) {
                updateCommunicationTable(response.data);
            }
        });
    }
    
    function updateCommunicationTable(communications) {
        // Update table with search results
        var $tbody = $('.wp-list-table tbody');
        $tbody.empty();
        
        if (communications.length === 0) {
            $tbody.append('<tr><td colspan="8">No communications found.</td></tr>');
            return;
        }
        
        communications.forEach(function(comm) {
            var row = '<tr>' +
                     '<td class="check-column"><input type="checkbox" name="communications[]" value="' + comm.id + '"></td>' +
                     '<td>' + comm.created_at + '</td>' +
                     '<td>' + comm.email_sender + '</td>' +
                     '<td>' + comm.email_subject + '</td>' +
                     '<td>' + (comm.case_number || '-') + '</td>' +
                     '<td>' + (comm.debtor_name || '-') + '</td>' +
                     '<td><span class="category-tag">' + comm.category + '</span></td>' +
                     '<td><button type="button" class="button button-small assign-btn" data-id="' + comm.id + '">Assign</button></td>' +
                     '</tr>';
            $tbody.append(row);
        });
    }
    
    function resetCommunicationTable() {
        // Reload the page to reset table
        location.reload();
    }
    
    // Initialize tooltips if any
    if (typeof tippy !== 'undefined') {
        tippy('[title]', {
            content(reference) {
                const title = reference.getAttribute('title');
                reference.removeAttribute('title');
                return title;
            },
        });
    }
});