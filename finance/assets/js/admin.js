/**
 * Admin JavaScript for Legal Automation Finance Plugin
 */

(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeCalculator();
        initializeCaseIntegration();
        initializeTemplateManagement();
    });
    
    /**
     * Initialize main calculator functionality
     */
    function initializeCalculator() {
        // Scenario change handler
        $('#scenario').on('change', function() {
            const scenario = $(this).val();
            
            if (scenario === 'scenario_2') {
                $('#dsgvo_row').slideDown();
                $('#dsgvo_damage').attr('required', true);
            } else {
                $('#dsgvo_row').slideUp();
                $('#dsgvo_damage').removeAttr('required');
            }
            
            // Clear previous results
            $('#calculator-results').hide();
        });
        
        // Calculator form submission
        $('#laf-calculator-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'laf_calculate_scenario',
                scenario: $('#scenario').val(),
                base_damage: $('#base_damage').val(),
                dsgvo_damage: $('#dsgvo_damage').val() || 0,
                interest_start_date: $('#interest_start_date').val(),
                interest_end_date: $('#interest_end_date').val(),
                nonce: $('#laf_nonce').val()
            };
            
            // Show loading state
            const $submitBtn = $(this).find('input[type="submit"]');
            const originalText = $submitBtn.val();
            $submitBtn.val('Berechne...').prop('disabled', true);
            
            // Make AJAX request
            $.post(ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        displayCalculationResults(response.data);
                        $('#calculator-results').slideDown();
                    } else {
                        showError('Fehler bei der Berechnung: ' + response.data);
                    }
                })
                .fail(function() {
                    showError('Netzwerkfehler bei der Berechnung');
                })
                .always(function() {
                    $submitBtn.val(originalText).prop('disabled', false);
                });
        });
    }
    
    /**
     * Initialize case integration functionality
     */
    function initializeCaseIntegration() {
        // Case scenario change handler
        $('#case_scenario').on('change', function() {
            if ($(this).val() === 'scenario_2') {
                $('#case_dsgvo_row').slideDown();
            } else {
                $('#case_dsgvo_row').slideUp();
            }
        });
        
        // Case form submission
        $('#laf-case-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const caseId = $form.data('case-id');
            
            const formData = {
                action: 'laf_calculate_case',
                case_id: caseId,
                scenario: $('#case_scenario').val(),
                base_damage: $('#case_base_damage').val(),
                dsgvo_damage: $('#case_dsgvo_damage').val() || 0,
                interest_start_date: $('#case_interest_start').val(),
                interest_end_date: $('#case_interest_end').val(),
                nonce: $('#laf_case_nonce').val()
            };
            
            // Validate form
            if (!formData.scenario) {
                showError('Bitte wählen Sie ein Template aus');
                return;
            }
            
            if (!formData.base_damage || formData.base_damage <= 0) {
                showError('Bitte geben Sie einen gültigen Grundschaden ein');
                return;
            }
            
            // Show loading state
            const $calculateBtn = $('#calculate-btn');
            $calculateBtn.text('Berechne...').prop('disabled', true);
            
            // Make AJAX request
            $.post(ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        window.currentCalculation = response.data;
                        displayCaseResults(response.data);
                        $('#case-results').slideDown();
                        $('#save-calculation-btn').show();
                        showSuccess('Berechnung erfolgreich');
                    } else {
                        showError('Fehler bei der Berechnung: ' + response.data);
                    }
                })
                .fail(function() {
                    showError('Netzwerkfehler bei der Berechnung');
                })
                .always(function() {
                    $calculateBtn.text('Berechnen').prop('disabled', false);
                });
        });
        
        // Save calculation handler
        $('#save-calculation-btn').on('click', function() {
            if (!window.currentCalculation) {
                showError('Keine Berechnung zum Speichern vorhanden');
                return;
            }
            
            const $saveBtn = $(this);
            const caseId = $('#laf-case-form').data('case-id');
            
            const saveData = {
                action: 'laf_save_case_calculation',
                case_id: caseId,
                calculation_data: JSON.stringify(window.currentCalculation),
                template_id: getTemplateIdFromScenario($('#case_scenario').val()),
                nonce: $('#laf_case_nonce').val()
            };
            
            // Show loading state
            $saveBtn.text('Speichere...').prop('disabled', true);
            
            // Make AJAX request
            $.post(ajaxurl, saveData)
                .done(function(response) {
                    if (response.success) {
                        showSuccess('Berechnung erfolgreich gespeichert!');
                        $saveBtn.hide();
                        
                        // Update page to reflect saved state
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showError('Fehler beim Speichern: ' + response.data);
                    }
                })
                .fail(function() {
                    showError('Netzwerkfehler beim Speichern');
                })
                .always(function() {
                    $saveBtn.text('Berechnung speichern').prop('disabled', false);
                });
        });
    }
    
    /**
     * Initialize template management functionality
     */
    function initializeTemplateManagement() {
        // Make delete template function globally available
        window.deleteTemplate = function(templateId) {
            if (!confirm('Template wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'laf_delete_template',
                template_id: templateId,
                nonce: $('#template-delete-nonce').val() || '<?php echo wp_create_nonce("laf_delete_template"); ?>'
            })
            .done(function(response) {
                if (response.success) {
                    showSuccess('Template erfolgreich gelöscht');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showError('Fehler beim Löschen: ' + response.data);
                }
            })
            .fail(function() {
                showError('Netzwerkfehler beim Löschen');
            });
        };
    }
    
    /**
     * Display calculation results
     */
    function displayCalculationResults(calculation) {
        let html = '<div class="laf-results-table">';
        html += '<h3>Kostenaufstellung</h3>';
        html += '<table class="wp-list-table widefat striped">';
        html += '<thead><tr><th>Position</th><th>Betrag (EUR)</th><th>RVG-Referenz</th><th>Berechnung</th></tr></thead>';
        html += '<tbody>';
        
        $.each(calculation.items, function(key, item) {
            html += '<tr>';
            html += '<td>' + escapeHtml(item.name) + '</td>';
            html += '<td style="text-align:right;">' + parseFloat(item.amount).toFixed(2) + '</td>';
            html += '<td>' + escapeHtml(item.rvg_reference || '-') + '</td>';
            html += '<td><small>' + escapeHtml(item.calculation || '-') + '</small></td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '<tfoot>';
        html += '<tr class="total-row"><th><strong>Gesamtbetrag</strong></th>';
        html += '<th style="text-align:right;"><strong>' + parseFloat(calculation.totals.total_amount).toFixed(2) + ' EUR</strong></th>';
        html += '<th></th><th></th></tr>';
        html += '</tfoot>';
        html += '</table></div>';
        
        $('#results-content').html(html);
    }
    
    /**
     * Display case results (same as calculation results but for case context)
     */
    function displayCaseResults(calculation) {
        displayCalculationResults(calculation);
        // Copy the content to case results area
        $('#case-results-content').html($('#results-content').html());
    }
    
    /**
     * Get template ID from scenario string
     */
    function getTemplateIdFromScenario(scenario) {
        switch(scenario) {
            case 'scenario_1': return 1;
            case 'scenario_2': return 2;
            default:
                if (scenario.startsWith('custom_')) {
                    return parseInt(scenario.replace('custom_', ''));
                }
                return null;
        }
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        const $notice = $('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
        
        // Scroll to top to show error
        $('html, body').animate({scrollTop: 0}, 300);
    }
    
    /**
     * Show success message
     */
    function showSuccess(message) {
        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 3000);
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Format currency values
     */
    function formatCurrency(amount, currency = 'EUR') {
        const formatted = parseFloat(amount).toFixed(2);
        return formatted + ' ' + currency;
    }
    
    /**
     * Validate date range
     */
    function validateDateRange(startDate, endDate) {
        if (!startDate || !endDate) {
            return true; // Optional dates
        }
        
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (start >= end) {
            showError('Zinsende muss nach dem Zinsbeginn liegen');
            return false;
        }
        
        return true;
    }
    
    // Initialize date validation
    $('#interest_start_date, #interest_end_date, #case_interest_start, #case_interest_end').on('change', function() {
        const isCase = $(this).attr('id').includes('case_');
        const startId = isCase ? '#case_interest_start' : '#interest_start_date';
        const endId = isCase ? '#case_interest_end' : '#interest_end_date';
        
        validateDateRange($(startId).val(), $(endId).val());
    });
    
})(jQuery);