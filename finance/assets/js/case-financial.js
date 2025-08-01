/**
 * Case Financial Integration JavaScript
 */

jQuery(document).ready(function($) {
    let currentCaseId = null;
    let costItems = [];
    let currentTemplate = null;
    
    // Initialize when financial tab is clicked
    $(document).on('click', 'a[href="#financial"]', function() {
        initializeFinancialTab();
    });
    
    function initializeFinancialTab() {
        if ($('#case-financial-integration').is(':empty')) {
            $('#case-financial-integration').html($('#financial-tab-template').html());
            setupEventHandlers();
            loadTemplateOptions();
            loadExistingCaseData();
        }
    }
    
    function setupEventHandlers() {
        // Template selection
        $(document).on('change', '#financial-template-select', function() {
            const templateId = $(this).val();
            if (templateId) {
                $('#load-template-btn').prop('disabled', false);
            } else {
                $('#load-template-btn').prop('disabled', true);
            }
        });
        
        // Load template button
        $(document).on('click', '#load-template-btn', function() {
            const templateId = $('#financial-template-select').val();
            if (templateId) {
                loadTemplateItems(templateId);
            }
        });
        
        // Save case financial
        $(document).on('click', '#save-case-financial-btn', function() {
            saveCaseFinancial();
        });
    }
    
    function loadTemplateOptions() {
        $.ajax({
            url: cah_case_financial.ajax_url,
            type: 'POST',
            data: {
                action: 'load_financial_templates',
                nonce: cah_case_financial.nonce
            },
            success: function(response) {
                if (response.success) {
                    const select = $('#financial-template-select');
                    select.find('option:not(:first)').remove();
                    
                    $.each(response.data, function(i, template) {
                        select.append($('<option>', {
                            value: template.id,
                            text: template.name + (template.description ? ' - ' + template.description : '')
                        }));
                    });
                } else {
                    showNotice('Fehler beim Laden der Vorlagen', 'error');
                }
            },
            error: function() {
                showNotice('Fehler beim Laden der Vorlagen', 'error');
            }
        });
    }
    
    function loadTemplateItems(templateId) {
        showLoading();
        
        $.ajax({
            url: cah_case_financial.ajax_url,
            type: 'POST',
            data: {
                action: 'load_template_items',
                template_id: templateId,
                nonce: cah_case_financial.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    costItems = response.data.items || [];
                    currentTemplate = templateId;
                    renderCostItems();
                    updateCalculation(response.data.totals);
                    showNotice('Vorlage erfolgreich geladen', 'success');
                } else {
                    showNotice('Fehler beim Laden der Vorlage', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Fehler beim Laden der Vorlage', 'error');
            }
        });
    }
    
    function loadExistingCaseData() {
        // Try to get case ID from form or URL
        currentCaseId = $('input[name="case_id"]').val() || getUrlParameter('id');
    }
    
    function renderCostItems() {
        const tbody = $('#cost-items-tbody');
        tbody.empty();
        
        if (costItems.length === 0) {
            tbody.append('<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">Keine Kostenpunkte vorhanden. Laden Sie eine Vorlage.</td></tr>');
            return;
        }
        
        $.each(costItems, function(index, item) {
            const row = $('<tr>').html(
                '<td><strong>' + escapeHtml(item.name) + '</strong></td>' +
                '<td><span class="category-badge category-' + item.category + '">' + getCategoryName(item.category) + '</span></td>' +
                '<td><strong>' + formatCurrency(item.amount) + '</strong></td>' +
                '<td>' + escapeHtml(item.description || '-') + '</td>'
            );
            tbody.append(row);
        });
    }
    
    function updateCalculation(totals) {
        $('#calc-subtotal').text(formatCurrency(totals.subtotal));
        $('#calc-vat').text(formatCurrency(totals.vat_amount));
        $('#calc-total').text(formatCurrency(totals.total_amount));
    }
    
    function saveCaseFinancial() {
        if (!currentCaseId) {
            showNotice('Fall muss zuerst gespeichert werden', 'error');
            return;
        }
        
        const totals = {
            subtotal: parseFloat($('#calc-subtotal').text().replace(/[€\s,]/g, '').replace('.', '')/100),
            vat_rate: 19.00,
            vat_amount: parseFloat($('#calc-vat').text().replace(/[€\s,]/g, '').replace('.', '')/100),
            total_amount: parseFloat($('#calc-total').text().replace(/[€\s,]/g, '').replace('.', '')/100)
        };
        
        showLoading();
        
        $.ajax({
            url: cah_case_financial.ajax_url,
            type: 'POST',
            data: {
                action: 'save_case_financial',
                case_id: currentCaseId,
                template_id: currentTemplate,
                items: JSON.stringify(costItems),
                totals: JSON.stringify(totals),
                nonce: cah_case_financial.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotice('Erfolgreich gespeichert', 'success');
                } else {
                    showNotice('Fehler beim Speichern', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotice('Fehler beim Speichern', 'error');
            }
        });
    }
    
    // Utility functions
    function formatCurrency(amount) {
        return cah_case_financial.currency_symbol + ' ' + parseFloat(amount).toFixed(2).replace('.', ',');
    }
    
    function getCategoryName(category) {
        const names = {
            'grundkosten': 'Grundkosten',
            'gerichtskosten': 'Gerichtskosten',
            'anwaltskosten': 'Anwaltskosten',
            'sonstige': 'Sonstige'
        };
        return names[category] || category;
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.toString().replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }
    
    function showNotice(message, type) {
        const noticeClass = 'notice-' + type;
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    function showLoading() {
        if (!$('#cah-loading').length) {
            $('body').append('<div id="cah-loading" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 5px; z-index: 9999;">Laden...</div>');
        }
    }
    
    function hideLoading() {
        $('#cah-loading').remove();
    }
    
    function getUrlParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
});