/**
 * Legal Automation Admin - Advanced Interface JavaScript
 * Version: 210
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initAdvancedInterface();
        initFinancialCalculations();
        initBulkOperations();
        initAdvancedSearch();
    });

    /**
     * Initialize advanced interface features
     */
    function initAdvancedInterface() {
        // Enhanced tab switching for case forms
        if (typeof switchCaseEditTab === 'undefined') {
            window.switchCaseEditTab = function(evt, tabName) {
                switchCaseTab(evt, tabName);
            };
        }
        
        if (typeof switchCaseCreateTab === 'undefined') {
            window.switchCaseCreateTab = function(evt, tabName) {
                switchCaseTab(evt, tabName);
            };
        }
    }

    /**
     * Enhanced tab switching functionality
     */
    function switchCaseTab(evt, tabName) {
        // Hide all tab contents
        $('.tab-content').removeClass('active');
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Show selected tab and mark as active
        $('#' + tabName).addClass('active');
        $(evt.currentTarget).addClass('nav-tab-active');
        
        // Prevent default link behavior
        evt.preventDefault();
        return false;
    }

    /**
     * Initialize financial calculations
     */
    function initFinancialCalculations() {
        // Real-time financial calculations for both create and edit forms
        const financialFields = [
            '#claim_amount', '#damage_amount', '#art15_claim_damages', 
            '#legal_fees', '#court_fees'
        ];
        
        financialFields.forEach(function(selector) {
            $(selector).on('input', updateFinancialCalculations);
        });
        
        // Initial calculation on page load
        updateFinancialCalculations();
    }

    /**
     * Update financial calculations
     */
    function updateFinancialCalculations() {
        const claimAmount = parseFloat($('#claim_amount').val() || 0);
        const damageAmount = parseFloat($('#damage_amount').val() || 0);
        const art15Damages = parseFloat($('#art15_claim_damages').val() || 0);
        const legalFees = parseFloat($('#legal_fees').val() || 0);
        const courtFees = parseFloat($('#court_fees').val() || 0);
        
        const totalClaim = claimAmount + damageAmount + art15Damages;
        const totalCosts = legalFees + courtFees;
        const netAmount = totalClaim - totalCosts;
        
        // Update display elements (both create and edit forms)
        updateDisplayElement('#total_claim', totalClaim);
        updateDisplayElement('#total_claim_create', totalClaim);
        updateDisplayElement('#total_costs', totalCosts);
        updateDisplayElement('#total_costs_create', totalCosts);
        updateDisplayElement('#net_amount', netAmount);
        updateDisplayElement('#net_amount_create', netAmount);
    }

    /**
     * Update display element with formatted currency
     */
    function updateDisplayElement(selector, amount) {
        const $element = $(selector);
        if ($element.length) {
            $element.text('€ ' + amount.toFixed(2).replace('.', ','));
            $element.css('color', amount >= 0 ? '#388e3c' : '#d32f2f');
        }
    }

    /**
     * Initialize bulk operations
     */
    function initBulkOperations() {
        // Bulk selection
        $('#cb-select-all').on('change', function() {
            const checked = $(this).prop('checked');
            $('tbody input[type="checkbox"]').prop('checked', checked);
            updateBulkActionState();
        });
        
        // Individual checkbox changes
        $(document).on('change', 'tbody input[type="checkbox"]', function() {
            updateBulkActionState();
        });
        
        // Bulk action execution
        $('#bulk-action-selector-top').on('change', function() {
            const action = $(this).val();
            if (action !== '-1') {
                executeBulkAction(action);
            }
        });
    }

    /**
     * Update bulk action button state
     */
    function updateBulkActionState() {
        const checkedCount = $('tbody input[type="checkbox"]:checked').length;
        const $bulkActions = $('.bulkactions select, .bulkactions input[type="submit"]');
        
        if (checkedCount > 0) {
            $bulkActions.prop('disabled', false);
        } else {
            $bulkActions.prop('disabled', true);
        }
    }

    /**
     * Execute bulk action
     */
    function executeBulkAction(action) {
        const selectedIds = [];
        $('tbody input[type="checkbox"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Bitte wählen Sie mindestens einen Fall aus.');
            return;
        }
        
        // Confirmation for destructive actions
        if (action === 'delete') {
            if (!confirm(`Sind Sie sicher, dass Sie ${selectedIds.length} Fälle löschen möchten?`)) {
                return;
            }
        }
        
        // Show loading state
        showLoadingState();
        
        // Execute via AJAX
        $.post(laa_ajax.ajax_url, {
            action: 'laa_bulk_operations',
            nonce: laa_ajax.nonce,
            bulk_action: action,
            selected_ids: selectedIds
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Fehler: ' + (response.data || 'Unbekannter Fehler'));
            }
        })
        .fail(function() {
            alert('Verbindungsfehler. Bitte versuchen Sie es erneut.');
        })
        .always(function() {
            hideLoadingState();
        });
    }

    /**
     * Initialize advanced search
     */
    function initAdvancedSearch() {
        // Toggle advanced search form
        window.toggleAdvancedSearch = function() {
            $('#advanced-search-form').toggle();
        };
        
        // Clear advanced search
        window.clearAdvancedSearch = function() {
            $('#advanced-search-form input, #advanced-search-form select').val('');
        };
        
        // Advanced search AJAX
        $('#advanced-search-form form').on('submit', function(e) {
            e.preventDefault();
            performAdvancedSearch();
        });
    }

    /**
     * Perform advanced search via AJAX
     */
    function performAdvancedSearch() {
        const searchData = $('#advanced-search-form form').serialize();
        
        showLoadingState();
        
        $.post(laa_ajax.ajax_url, {
            action: 'laa_advanced_search',
            nonce: laa_ajax.nonce,
            search_data: searchData
        })
        .done(function(response) {
            if (response.success) {
                // Update results table
                updateSearchResults(response.data);
            } else {
                alert('Suchfehler: ' + (response.data || 'Unbekannter Fehler'));
            }
        })
        .fail(function() {
            alert('Verbindungsfehler bei der Suche.');
        })
        .always(function() {
            hideLoadingState();
        });
    }

    /**
     * Update search results
     */
    function updateSearchResults(data) {
        // Replace table content with search results
        if (data.html) {
            $('.wp-list-table tbody').html(data.html);
        }
        
        // Update result count
        if (data.count !== undefined) {
            $('.displaying-num').text(data.count + ' Einträge');
        }
    }

    /**
     * Show loading state
     */
    function showLoadingState() {
        $('body').addClass('laa-loading');
        $('.wp-list-table').before('<div class="laa-spinner"></div>');
    }

    /**
     * Hide loading state
     */
    function hideLoadingState() {
        $('body').removeClass('laa-loading');
        $('.laa-spinner').remove();
    }

    /**
     * Enhanced form validation
     */
    function initFormValidation() {
        // Real-time case ID validation
        $('#case_id').on('blur', function() {
            const caseId = $(this).val();
            if (caseId) {
                validateCaseId(caseId);
            }
        });
    }

    /**
     * Validate case ID uniqueness
     */
    function validateCaseId(caseId) {
        $.post(laa_ajax.ajax_url, {
            action: 'laa_validate_case_id',
            nonce: laa_ajax.nonce,
            case_id: caseId
        })
        .done(function(response) {
            const $validation = $('#case_id_validation');
            if (response.success) {
                if (response.data.available) {
                    $validation.html('<span style="color: green;">✓ Fall-ID verfügbar</span>').show();
                } else {
                    $validation.html('<span style="color: red;">✗ Fall-ID bereits vergeben</span>').show();
                }
            }
        });
    }

    // Make functions globally available
    window.LAA = {
        switchCaseTab: switchCaseTab,
        updateFinancialCalculations: updateFinancialCalculations,
        toggleAdvancedSearch: window.toggleAdvancedSearch,
        clearAdvancedSearch: window.clearAdvancedSearch
    };

})(jQuery);