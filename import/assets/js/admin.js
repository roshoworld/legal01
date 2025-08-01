/**
 * Legal Automation Import - Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        LAI_Admin.init();
    });

    // Main admin object
    window.LAI_Admin = {
        
        // Current state
        currentSource: null,
        currentStep: 1,
        detectedFields: [],
        fieldMappings: {},
        
        // Initialize admin interface
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initAirtableInterface();
            this.initPipedreamInterface();
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Source selection
            $(document).on('click', '.lai-select-source', this.handleSourceSelection.bind(this));
            
            // Step navigation
            $(document).on('click', '.lai-next-step', this.handleNextStep.bind(this));
            $(document).on('click', '.lai-prev-step', this.handlePrevStep.bind(this));
            
            // CSV upload
            $(document).on('change', '#lai-csv-file', this.handleCSVUpload.bind(this));
            $(document).on('click', '#lai-detect-fields', this.handleDetectFields.bind(this));
            
            // Field mapping
            $(document).on('change', '.lai-field-mapping-select', this.handleFieldMappingChange.bind(this));
            $(document).on('click', '.lai-preview-import', this.handlePreviewImport.bind(this));
            $(document).on('click', '.lai-process-import', this.handleProcessImport.bind(this));
            
            // Export
            $(document).on('submit', '#lai-export-cases-form', this.handleExportCases.bind(this));
            $(document).on('click', '.lai-download-template', this.handleDownloadTemplate.bind(this));
            
            // Airtable
            $(document).on('submit', '#lai-airtable-connect-form', this.handleAirtableConnect.bind(this));
            $(document).on('click', '#lai-airtable-sync-now', this.handleAirtableSync.bind(this));
            $(document).on('submit', '#lai-airtable-sync-form', this.handleAirtableCustomSync.bind(this));
            
            // Pipedream
            $(document).on('submit', '#lai-pipedream-setup-form', this.handlePipedreamSetup.bind(this));
            $(document).on('click', '#generate_webhook_secret', this.generateWebhookSecret.bind(this));
            $(document).on('click', '.lai-copy-webhook', this.copyWebhookURL.bind(this));
            $(document).on('click', '.lai-test-webhook', this.testWebhook.bind(this));
        },
        
        // Initialize tabs
        initTabs: function() {
            // Handle tab switching in integrated interface
            $(document).on('click', '.lai-tab-navigation .nav-tab', function(e) {
                e.preventDefault();
                
                var tabId = $(this).data('tab');
                var $container = $(this).closest('.lai-integrated-interface');
                
                // Update active tab
                $container.find('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show corresponding content
                $container.find('.lai-tab-pane').removeClass('active');
                $container.find('#lai-' + tabId).addClass('active');
            });
            
            // History tabs
            $(document).on('click', '.lai-history-tabs .nav-tab', function(e) {
                e.preventDefault();
                
                var tabId = $(this).attr('href').substring(1);
                
                // Update active tab
                $('.lai-history-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show corresponding content
                $('.lai-history-tab').removeClass('active');
                $('#' + tabId).addClass('active');
            });
        },
        
        // Handle source selection
        handleSourceSelection: function(e) {
            e.preventDefault();
            
            var source = $(e.target).data('source');
            this.currentSource = source;
            
            // Update UI
            $('.lai-source-card').removeClass('selected');
            $('[data-source="' + source + '"]').closest('.lai-source-card').addClass('selected');
            
            // Show import process
            $('#lai-import-process').show();
            this.loadSourceConfig(source);
        },
        
        // Load source configuration
        loadSourceConfig: function(source) {
            var $config = $('#lai-source-config');
            
            switch(source) {
                case 'csv':
                    $config.html(this.getCSVConfigHTML());
                    break;
                case 'airtable':
                    $config.html(this.getAirtableConfigHTML());
                    break;
                case 'pipedream':
                    $config.html(this.getPipedreamConfigHTML());
                    break;
                default:
                    $config.html('<p>Configuration for ' + source + ' source.</p>');
            }
        },
        
        // Get CSV configuration HTML
        getCSVConfigHTML: function() {
            return `
                <div class="lai-csv-config">
                    <h3>CSV File Upload</h3>
                    <div class="lai-form-section">
                        <label for="lai-csv-file">Select CSV File:</label>
                        <input type="file" id="lai-csv-file" accept=".csv" />
                        <p class="description">Select a CSV file to upload and analyze.</p>
                    </div>
                    <div class="lai-form-section">
                        <label for="lai-csv-content">Or paste CSV content:</label>
                        <textarea id="lai-csv-content" rows="6" cols="50" placeholder="Paste CSV content here..."></textarea>
                    </div>
                    <button class="button button-primary lai-next-step" id="lai-detect-fields">Detect Fields</button>
                </div>
            `;
        },
        
        // Get Airtable configuration HTML
        getAirtableConfigHTML: function() {
            return `
                <div class="lai-airtable-config">
                    <h3>Airtable Connection</h3>
                    <div class="lai-form-section">
                        <label for="lai-airtable-api-key">API Key:</label>
                        <input type="password" id="lai-airtable-api-key" class="regular-text" />
                    </div>
                    <div class="lai-form-section">
                        <label for="lai-airtable-base-id">Base ID:</label>
                        <input type="text" id="lai-airtable-base-id" class="regular-text" />
                    </div>
                    <div class="lai-form-section">
                        <label for="lai-airtable-table-name">Table Name:</label>
                        <input type="text" id="lai-airtable-table-name" class="regular-text" />
                    </div>
                    <button class="button button-primary lai-next-step" id="lai-airtable-connect">Connect & Detect Fields</button>
                </div>
            `;
        },
        
        // Get Pipedream configuration HTML
        getPipedreamConfigHTML: function() {
            return `
                <div class="lai-pipedream-config">
                    <h3>Pipedream Webhook Configuration</h3>
                    <p>This source receives data via webhook. Configure the webhook endpoint first.</p>
                    <div class="lai-form-section">
                        <label for="lai-pipedream-source-id">Source ID:</label>
                        <input type="text" id="lai-pipedream-source-id" class="regular-text" />
                    </div>
                    <div class="lai-form-section">
                        <label for="lai-pipedream-webhook-secret">Webhook Secret:</label>
                        <input type="password" id="lai-pipedream-webhook-secret" class="regular-text" />
                    </div>
                    <button class="button button-primary lai-next-step" id="lai-pipedream-setup">Setup Webhook</button>
                </div>
            `;
        },
        
        // Handle CSV upload
        handleCSVUpload: function(e) {
            var file = e.target.files[0];
            if (!file) return;
            
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#lai-csv-content').val(e.target.result);
            };
            reader.readAsText(file);
        },
        
        // Handle field detection
        handleDetectFields: function(e) {
            e.preventDefault();
            
            var csvContent = $('#lai-csv-content').val();
            if (!csvContent) {
                alert('Please provide CSV content');
                return;
            }
            
            var $button = $(e.target);
            $button.prop('disabled', true).text('Detecting...');
            
            // AJAX request to detect fields
            $.ajax({
                url: lai_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lai_detect_fields',
                    nonce: lai_ajax.nonce,
                    source_id: this.currentSource,
                    data: csvContent
                },
                success: function(response) {
                    if (response.success) {
                        this.detectedFields = response.data.detected_fields;
                        this.renderFieldMapping(response.data);
                        this.goToStep(2);
                    } else {
                        alert('Error: ' + (response.data.message || 'Field detection failed'));
                    }
                }.bind(this),
                error: function() {
                    alert('AJAX error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Detect Fields');
                }
            });
        },
        
        // Render field mapping interface
        renderFieldMapping: function(data) {
            var html = '<div class="lai-field-mapping-container">';
            html += '<h3>Field Mapping Configuration</h3>';
            html += '<p>Map detected fields to database fields:</p>';
            
            html += '<table class="lai-field-mapping-table">';
            html += '<thead><tr>';
            html += '<th>Source Field</th>';
            html += '<th>Sample Data</th>';
            html += '<th>Data Type</th>';
            html += '<th>Map To</th>';
            html += '</tr></thead>';
            html += '<tbody>';
            
            data.detected_fields.forEach(function(field) {
                var suggestion = data.suggested_mappings[field.csv_name] || {};
                var sampleData = field.sample_data.slice(0, 2).join(', ');
                if (sampleData.length > 30) sampleData = sampleData.substring(0, 30) + '...';
                
                html += '<tr>';
                html += '<td><strong>' + field.csv_name + '</strong></td>';
                html += '<td><code class="sample-data">' + sampleData + '</code></td>';
                html += '<td><span class="lai-data-type-badge lai-data-type-' + field.data_type + '">' + field.data_type + '</span></td>';
                html += '<td>';
                html += '<select class="lai-field-mapping-select" data-field="' + field.csv_name + '">';
                html += '<option value="">-- Skip Field --</option>';
                
                if (suggestion.target_table) {
                    html += '<option value="' + suggestion.target_table + '.' + suggestion.target_field + '" selected>';
                    html += suggestion.target_table + ' → ' + suggestion.target_field;
                    html += '</option>';
                }
                
                // Add common mapping options
                html += '<option value="klage_contacts.email">Contacts → Email</option>';
                html += '<option value="klage_contacts.first_name">Contacts → First Name</option>';
                html += '<option value="klage_contacts.last_name">Contacts → Last Name</option>';
                html += '<option value="klage_cases.case_id">Cases → Case ID</option>';
                html += '<option value="klage_cases.case_status">Cases → Status</option>';
                html += '<option value="klage_cases.claim_amount">Cases → Amount</option>';
                
                html += '</select>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            html += '<div class="lai-mapping-actions">';
            html += '<button class="button lai-prev-step">Previous</button>';
            html += '<button class="button button-primary lai-preview-import">Preview Import</button>';
            html += '</div>';
            html += '</div>';
            
            $('#lai-field-mapping').html(html);
        },
        
        // Handle field mapping change
        handleFieldMappingChange: function(e) {
            var field = $(e.target).data('field');
            var mapping = $(e.target).val();
            
            if (mapping) {
                var parts = mapping.split('.');
                this.fieldMappings[field] = {
                    target_table: parts[0],
                    target_field: parts[1],
                    data_type: 'string' // Default
                };
            } else {
                delete this.fieldMappings[field];
            }
        },
        
        // Handle preview import
        handlePreviewImport: function(e) {
            e.preventDefault();
            
            if (Object.keys(this.fieldMappings).length === 0) {
                alert('Please configure at least one field mapping');
                return;
            }
            
            var $button = $(e.target);
            $button.prop('disabled', true).text('Previewing...');
            
            $.ajax({
                url: lai_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lai_preview_import',
                    nonce: lai_ajax.nonce,
                    source_id: this.currentSource,
                    data: $('#lai-csv-content').val(),
                    field_mappings: JSON.stringify(this.fieldMappings)
                },
                success: function(response) {
                    if (response.success) {
                        this.renderImportPreview(response.data);
                        this.goToStep(3);
                    } else {
                        alert('Error: ' + (response.data.message || 'Preview failed'));
                    }
                }.bind(this),
                error: function() {
                    alert('AJAX error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Preview Import');
                }
            });
        },
        
        // Render import preview
        renderImportPreview: function(data) {
            var html = '<div class="lai-import-preview-container">';
            html += '<h3>Import Preview (' + data.preview_rows + ' of ' + data.total_rows + ' rows)</h3>';
            
            if (data.validation_errors.length > 0) {
                html += '<div class="lai-notification error">';
                html += '<strong>Validation Issues:</strong><ul>';
                data.validation_errors.slice(0, 5).forEach(function(error) {
                    html += '<li>' + error + '</li>';
                });
                if (data.validation_errors.length > 5) {
                    html += '<li>... and ' + (data.validation_errors.length - 5) + ' more issues</li>';
                }
                html += '</ul></div>';
            }
            
            html += '<table class="lai-field-mapping-table">';
            html += '<thead><tr><th>Row</th><th>Status</th><th>Data Preview</th><th>Issues</th></tr></thead>';
            html += '<tbody>';
            
            data.preview_data.forEach(function(row) {
                html += '<tr class="' + (row.valid ? 'valid-row' : 'invalid-row') + '">';
                html += '<td>' + row.row_number + '</td>';
                html += '<td>' + (row.valid ? '<span class="lai-text-success">✓ Valid</span>' : '<span class="lai-text-error">✗ Invalid</span>') + '</td>';
                html += '<td><details><summary>View Data</summary><pre>' + JSON.stringify(row.mapped_data, null, 2) + '</pre></details></td>';
                html += '<td>' + (row.errors.length > 0 ? row.errors.join('<br>') : 'None') + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            html += '<div class="lai-preview-actions">';
            html += '<button class="button lai-prev-step">Previous</button>';
            html += '<button class="button button-primary lai-process-import">Process Full Import</button>';
            html += '</div>';
            html += '</div>';
            
            $('#lai-import-preview').html(html);
        },
        
        // Handle process import
        handleProcessImport: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to process the full import? This will create new records in the database.')) {
                return;
            }
            
            var $button = $(e.target);
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: lai_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lai_process_import',
                    nonce: lai_ajax.nonce,
                    source_id: this.currentSource,
                    data: $('#lai-csv-content').val(),
                    field_mappings: JSON.stringify(this.fieldMappings)
                },
                success: function(response) {
                    if (response.success) {
                        this.showImportResults(response.data);
                    } else {
                        alert('Error: ' + (response.data.message || 'Import failed'));
                    }
                }.bind(this),
                error: function() {
                    alert('AJAX error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Process Full Import');
                }
            });
        },
        
        // Show import results
        showImportResults: function(data) {
            var html = '<div class="lai-import-results">';
            html += '<h3>Import Complete!</h3>';
            
            // Summary stats
            html += '<div class="lai-import-stats">';
            html += '<div class="lai-stat-card"><h4>' + data.total_rows + '</h4><p>Total Rows</p></div>';
            html += '<div class="lai-stat-card"><h4>' + data.successful_imports + '</h4><p>Successful</p></div>';
            html += '<div class="lai-stat-card"><h4>' + data.failed_imports + '</h4><p>Failed</p></div>';
            
            var successRate = data.total_rows > 0 ? Math.round((data.successful_imports / data.total_rows) * 100) : 0;
            html += '<div class="lai-stat-card"><h4>' + successRate + '%</h4><p>Success Rate</p></div>';
            html += '</div>';
            
            // Show success notification
            if (data.successful_imports > 0) {
                html += '<div class="lai-notification success">';
                html += '<strong>Import completed successfully!</strong> ' + data.successful_imports + ' records imported.';
                html += '</div>';
            }
            
            // Show errors if any
            if (data.errors.length > 0) {
                html += '<div class="lai-notification error">';
                html += '<strong>Import Errors:</strong><ul>';
                data.errors.slice(0, 10).forEach(function(error) {
                    html += '<li>' + error + '</li>';
                });
                if (data.errors.length > 10) {
                    html += '<li>... and ' + (data.errors.length - 10) + ' more errors</li>';
                }
                html += '</ul></div>';
            }
            
            html += '<div class="lai-import-actions">';
            html += '<button class="button button-primary" onclick="location.reload();">Import Another File</button>';
            html += '</div>';
            html += '</div>';
            
            $('#lai-import-preview').html(html);
        },
        
        // Navigation methods
        goToStep: function(step) {
            this.currentStep = step;
            
            // Update step indicators
            $('.lai-step').removeClass('lai-step-active');
            $('.lai-step[data-step="' + step + '"]').addClass('lai-step-active');
            
            // Show corresponding content
            $('.lai-step-content > div').hide();
            $('.lai-step-content-' + step).show();
        },
        
        handleNextStep: function(e) {
            e.preventDefault();
            if (this.currentStep < 3) {
                this.goToStep(this.currentStep + 1);
            }
        },
        
        handlePrevStep: function(e) {
            e.preventDefault();
            if (this.currentStep > 1) {
                this.goToStep(this.currentStep - 1);
            }
        },
        
        // Initialize Airtable interface
        initAirtableInterface: function() {
            // Auto-expand sync options when sync now is clicked
            $(document).on('click', '#lai-airtable-sync-now', function() {
                $('#lai-sync-options').toggle();
            });
        },
        
        // Handle Airtable connection
        handleAirtableConnect: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $button = $form.find('button[type="submit"]');
            $button.prop('disabled', true).text('Connecting...');
            
            var data = {
                action: 'lai_airtable_connect',
                nonce: lai_ajax.nonce,
                api_key: $('#airtable_api_key').val(),
                base_id: $('#airtable_base_id').val(),
                table_name: $('#airtable_table_name').val()
            };
            
            $.ajax({
                url: lai_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        this.showNotification('success', 'Airtable connected successfully!');
                        location.reload(); // Refresh to show connected state
                    } else {
                        this.showNotification('error', 'Connection failed: ' + response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Connection error occurred');
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        // Handle Airtable sync
        handleAirtableSync: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: lai_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lai_airtable_sync',
                    nonce: lai_ajax.nonce,
                    sync_type: 'incremental'
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotification('success', 'Sync completed successfully!');
                    } else {
                        this.showNotification('error', 'Sync failed: ' + response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Sync error occurred');
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text('Sync Now');
                }
            });
        },
        
        // Initialize Pipedream interface
        initPipedreamInterface: function() {
            // Nothing specific to initialize yet
        },
        
        // Handle Pipedream setup
        handlePipedreamSetup: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $button = $form.find('button[type="submit"]');
            $button.prop('disabled', true).text('Setting up...');
            
            var data = {
                action: 'lai_pipedream_setup',
                nonce: lai_ajax.nonce,
                source_id: $('#source_identifier').val(),
                webhook_secret: $('#webhook_secret').val()
            };
            
            $.ajax({
                url: lai_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        this.showNotification('success', 'Webhook created successfully!');
                        location.reload(); // Refresh to show new webhook
                    } else {
                        this.showNotification('error', 'Setup failed: ' + response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Setup error occurred');
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text('Create Webhook');
                }
            });
        },
        
        // Generate webhook secret
        generateWebhookSecret: function(e) {
            e.preventDefault();
            
            var secret = this.generateRandomString(32);
            $('#webhook_secret').val(secret);
        },
        
        // Copy webhook URL
        copyWebhookURL: function(e) {
            e.preventDefault();
            
            var url = $(e.target).data('url');
            navigator.clipboard.writeText(url).then(function() {
                alert('Webhook URL copied to clipboard!');
            });
        },
        
        // Test webhook
        testWebhook: function(e) {
            e.preventDefault();
            
            var sourceId = $(e.target).data('source');
            alert('Testing webhook for source: ' + sourceId);
            // Implementation for webhook testing
        },
        
        // Utility methods
        generateRandomString: function(length) {
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var result = '';
            for (var i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },
        
        showNotification: function(type, message) {
            var html = '<div class="lai-notification ' + type + '">' + message + '</div>';
            $('.lai-integrated-interface, .wrap').first().prepend(html);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.lai-notification').fadeOut();
            }, 5000);
        },
        
        // Export handlers
        handleExportCases: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $button = $form.find('button[type="submit"]');
            $button.prop('disabled', true).text('Exporting...');
            
            // Create download link
            var params = new URLSearchParams($form.serialize());
            var downloadUrl = lai_ajax.ajax_url + '?' + params.toString() + '&action=lai_export_cases&nonce=' + lai_ajax.nonce;
            
            // Trigger download
            window.location.href = downloadUrl;
            
            setTimeout(function() {
                $button.prop('disabled', false).text('Export Data');
            }, 2000);
        },
        
        handleDownloadTemplate: function(e) {
            e.preventDefault();
            
            var template = $(e.target).data('template');
            var downloadUrl = lai_ajax.ajax_url + '?action=lai_download_template&template=' + template + '&nonce=' + lai_ajax.nonce;
            
            window.location.href = downloadUrl;
        }
    };

})(jQuery);