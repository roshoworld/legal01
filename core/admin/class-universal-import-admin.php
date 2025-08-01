<?php
/**
 * Universal Import Admin Interface v1.9.3
 * Enhanced CSV import with field mapping and client configurations
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Universal_Import_Admin {
    
    private $universal_import_manager;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 15);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Get the universal import manager from the main plugin instance
        // It will be available since this admin class is instantiated after the manager
        global $court_automation_hub;
        if (isset($court_automation_hub) && isset($court_automation_hub->universal_import_manager)) {
            $this->universal_import_manager = $court_automation_hub->universal_import_manager;
        } else {
            // Fallback: try to get from globals or show error
            if (isset($GLOBALS['cah_universal_import_manager'])) {
                $this->universal_import_manager = $GLOBALS['cah_universal_import_manager'];
            } else {
                add_action('admin_notices', array($this, 'show_dependency_error'));
            }
        }
    }
    
    /**
     * Show dependency error if Universal Import Manager is not available
     */
    public function show_dependency_error() {
        ?>
        <div class="notice notice-error">
            <p><strong>Universal Import Error:</strong> CAH_Universal_Import_Manager class not found. Please ensure the plugin is properly installed.</p>
        </div>
        <?php
    }
    
    /**
     * Add admin menu for universal import
     */
    public function add_admin_menu() {
        add_submenu_page(
            'klage-click-hub',
            __('Universal Import', 'court-automation-hub'),
            __('🔄 Universal Import', 'court-automation-hub'),
            'manage_options',
            'klage-click-universal-import',
            array($this, 'admin_page_universal_import')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'klage-click-universal-import') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-tabs');
        
        // Localize script for AJAX
        wp_localize_script('jquery', 'cah_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cah_universal_import')
        ));
        
        // Custom script for universal import
        wp_add_inline_script('jquery', $this->get_inline_javascript());
        
        // Custom styles
        wp_add_inline_style('wp-admin', $this->get_inline_css());
    }
    
    /**
     * Universal import admin page
     */
    public function admin_page_universal_import() {
        // Check if Universal Import Manager is available
        if (!$this->universal_import_manager) {
            ?>
            <div class="wrap">
            <h1><?php echo esc_html__('Universal Import System v1.9.3', 'court-automation-hub'); ?></h1>
                <div class="notice notice-error">
                    <p><strong>Error:</strong> Universal Import Manager is not available. Please check plugin installation.</p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Handle form submissions
        $this->handle_form_submissions();
        
        $supported_clients = $this->universal_import_manager->get_supported_clients();
        ?>
        
        <div class="wrap">
                <h1><?php echo esc_html__('Universal Import System v1.9.3', 'court-automation-hub'); ?></h1>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>🚨 v1.9.3 - Emergency Fixes Applied!</strong></p>
                <p>Import cases from multiple client sources with intelligent field mapping and validation.</p>
            </div>
            
            <div id="universal-import-tabs">
                <ul class="nav-tab-wrapper">
                    <li><a href="#tab-import" class="nav-tab nav-tab-active">📊 Import Data</a></li>
                    <li><a href="#tab-mappings" class="nav-tab">🔗 Field Mappings</a></li>
                    <li><a href="#tab-history" class="nav-tab">📈 Import History</a></li>
                    <li><a href="#tab-clients" class="nav-tab">👥 Client Configs</a></li>
                </ul>
                
                <!-- Import Data Tab -->
                <div id="tab-import" class="tab-content">
                    <div class="postbox">
                        <h2 class="hndle">Step 1: Select Client & Upload CSV</h2>
                        <div class="inside">
                            <form id="upload-csv-form" method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('cah_universal_import', 'universal_import_nonce'); ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="client_type">Client Type</label></th>
                                        <td>
                                            <select id="client_type" name="client_type" class="regular-text">
                                                <option value="generic">Generic CSV</option>
                                                <?php foreach ($supported_clients as $key => $client): ?>
                                                    <option value="<?php echo esc_attr($key); ?>">
                                                        <?php echo esc_html($client['name']); ?> - <?php echo esc_html($client['description']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Select the source of your CSV data for automatic field mapping.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="csv_file">CSV File</label></th>
                                        <td>
                                            <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                            <p class="description">Upload your CSV file. Supported formats: UTF-8 encoded CSV with comma or semicolon delimiters.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">CSV Content (Alternative)</th>
                                        <td>
                                            <textarea id="csv_content" name="csv_content" class="large-text" rows="8" placeholder="Or paste your CSV content directly here..."></textarea>
                                            <p class="description">Alternative: Paste CSV content directly if you don't have a file.</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p class="submit">
                                    <button type="button" id="detect-fields-btn" class="button button-primary">🔍 Detect Fields & Suggest Mappings</button>
                                </p>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Field Detection Results -->
                    <div id="field-detection-results" class="postbox" style="display: none;">
                        <h2 class="hndle">Step 2: Review Detected Fields & Mappings</h2>
                        <div class="inside">
                            <div id="detection-summary"></div>
                            <div id="field-mapping-interface"></div>
                            
                            <p class="submit">
                                <button type="button" id="preview-import-btn" class="button button-secondary">👁️ Preview Import (5 rows)</button>
                                <button type="button" id="process-import-btn" class="button button-primary" style="margin-left: 10px;">⚡ Process Full Import</button>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Import Preview -->
                    <div id="import-preview" class="postbox" style="display: none;">
                        <h2 class="hndle">Step 3: Import Preview</h2>
                        <div class="inside">
                            <div id="preview-results"></div>
                        </div>
                    </div>
                    
                    <!-- Import Results -->
                    <div id="import-results" class="postbox" style="display: none;">
                        <h2 class="hndle">Import Results</h2>
                        <div class="inside">
                            <div id="final-results"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Field Mappings Tab -->
                <div id="tab-mappings" class="tab-content" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle">Field Mapping Configurations</h2>
                        <div class="inside">
                            <p>Manage and create custom field mapping configurations for different client types.</p>
                            
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Client Type</th>
                                        <th>Configuration Name</th>
                                        <th>Fields Mapped</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supported_clients as $key => $client): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($client['name']); ?></strong></td>
                                        <td>Default Configuration</td>
                                        <td><?php echo count($client['field_mappings'] ?? array()); ?> fields</td>
                                        <td>Built-in</td>
                                        <td>
                                            <button class="button button-small" onclick="viewMappingConfig('<?php echo esc_js($key); ?>')">View</button>
                                            <button class="button button-small" onclick="editMappingConfig('<?php echo esc_js($key); ?>')">Edit</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <p class="submit">
                                <button type="button" class="button button-primary" onclick="createNewMappingConfig()">➕ Create New Mapping</button>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Import History Tab -->
                <div id="tab-history" class="tab-content" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle">Import History</h2>
                        <div class="inside">
                            <?php $this->display_import_history(); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Client Configurations Tab -->
                <div id="tab-clients" class="tab-content" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle">Supported Client Configurations</h2>
                        <div class="inside">
                            <?php foreach ($supported_clients as $key => $client): ?>
                            <div class="client-config-card" style="border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px;">
                                <h3><?php echo esc_html($client['name']); ?> <span class="client-version">v<?php echo esc_html($client['version']); ?></span></h3>
                                <p><strong>Description:</strong> <?php echo esc_html($client['description']); ?></p>
                                <p><strong>Fields Supported:</strong> <?php echo count($client['field_mappings'] ?? array()); ?> fields</p>
                                <p><strong>Required Fields:</strong> <?php echo implode(', ', $client['required_fields'] ?? array()); ?></p>
                                
                                <details>
                                    <summary><strong>Field Mappings Details</strong></summary>
                                    <div style="margin-top: 10px;">
                                        <?php if (!empty($client['field_mappings'])): ?>
                                            <table class="wp-list-table widefat">
                                                <thead>
                                                    <tr>
                                                        <th>CSV Field</th>
                                                        <th>Target Table</th>
                                                        <th>Target Field</th>
                                                        <th>Data Type</th>
                                                        <th>Required</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($client['field_mappings'] as $csv_field => $mapping): ?>
                                                    <tr>
                                                        <td><code><?php echo esc_html($csv_field); ?></code></td>
                                                        <td><?php echo esc_html($mapping['target_table']); ?></td>
                                                        <td><?php echo esc_html($mapping['target_field']); ?></td>
                                                        <td><?php echo esc_html($mapping['data_type']); ?></td>
                                                        <td><?php echo isset($mapping['required']) && $mapping['required'] ? '✅' : ''; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p><em>No pre-configured mappings. Uses dynamic field detection.</em></p>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hidden forms for AJAX -->
        <div id="hidden-forms" style="display: none;">
            <form id="field-mapping-form">
                <?php wp_nonce_field('cah_universal_import', 'mapping_nonce'); ?>
                <input type="hidden" id="csv_data" name="csv_data">
                <input type="hidden" id="selected_client_type" name="client_type">
                <input type="hidden" id="field_mappings_json" name="field_mappings">
            </form>
        </div>
        
        <?php
    }
    
    /**
     * Display import history
     */
    private function display_import_history() {
        $import_logs = get_option('cah_import_logs', array());
        
        if (empty($import_logs)) {
            echo '<p>No import history available.</p>';
            return;
        }
        
        // Reverse to show most recent first
        $import_logs = array_reverse($import_logs);
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Import Type</th>
                    <th>Total Rows</th>
                    <th>Successful</th>
                    <th>Failed</th>
                    <th>Success Rate</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($import_logs, 0, 20) as $log): ?>
                <tr>
                    <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                    <td>
                        <span class="import-type-badge import-type-<?php echo esc_attr($log['import_type']); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $log['import_type']))); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($log['total_rows']); ?></td>
                    <td><span class="success-count"><?php echo esc_html($log['successful_imports']); ?></span></td>
                    <td><span class="failed-count"><?php echo esc_html($log['failed_imports']); ?></span></td>
                    <td>
                        <?php 
                        $success_rate = $log['total_rows'] > 0 ? round(($log['successful_imports'] / $log['total_rows']) * 100, 1) : 0;
                        $color = $success_rate >= 90 ? 'green' : ($success_rate >= 70 ? 'orange' : 'red');
                        ?>
                        <span style="color: <?php echo $color; ?>; font-weight: bold;">
                            <?php echo $success_rate; ?>%
                        </span>
                    </td>
                    <td><?php echo esc_html(get_userdata($log['user_id'])->display_name ?? 'Unknown'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
    }
    
    /**
     * Handle form submissions
     */
    private function handle_form_submissions() {
        // Form submission handling will be done via AJAX
        // This method is kept for any direct form submissions if needed
    }
    
    /**
     * Get inline JavaScript for universal import functionality
     */
    private function get_inline_javascript() {
        return "
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
            
            // Detect fields button
            $('#detect-fields-btn').click(function() {
                var clientType = $('#client_type').val();
                var csvContent = $('#csv_content').val();
                var fileInput = $('#csv_file')[0];
                
                if (!csvContent && (!fileInput.files || !fileInput.files[0])) {
                    alert('Please upload a CSV file or paste CSV content.');
                    return;
                }
                
                var formData = new FormData();
                formData.append('action', 'cah_detect_csv_fields');
                formData.append('nonce', $('#universal_import_nonce').val());
                formData.append('client_type', clientType);
                
                if (csvContent) {
                    formData.append('csv_content', csvContent);
                } else if (fileInput.files[0]) {
                    formData.append('csv_file', fileInput.files[0]);
                }
                
                $(this).prop('disabled', true).text('🔄 Detecting...');
                
                $.ajax({
                    url: cah_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            displayFieldDetectionResults(response.data);
                            $('#field-detection-results').show();
                        } else {
                            alert('Error: ' + (response.data.message || 'Field detection failed'));
                        }
                    },
                    error: function() {
                        alert('AJAX error occurred');
                    },
                    complete: function() {
                        $('#detect-fields-btn').prop('disabled', false).text('🔍 Detect Fields & Suggest Mappings');
                    }
                });
            });
            
            // Preview import button
            $('#preview-import-btn').click(function() {
                processImportAction('preview');
            });
            
            // Process import button
            $('#process-import-btn').click(function() {
                if (confirm('Are you sure you want to process the full import? This will create new records in the database.')) {
                    processImportAction('process');
                }
            });
            
            function processImportAction(action) {
                var fieldMappings = collectFieldMappings();
                var csvContent = $('#csv_data').val();
                var clientType = $('#selected_client_type').val();
                
                if (!csvContent || Object.keys(fieldMappings).length === 0) {
                    alert('Please detect fields first and configure mappings.');
                    return;
                }
                
                var buttonId = action === 'preview' ? '#preview-import-btn' : '#process-import-btn';
                var originalText = $(buttonId).text();
                $(buttonId).prop('disabled', true).text(action === 'preview' ? '🔄 Previewing...' : '⚡ Processing...');
                
                $.ajax({
                    url: cah_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: action === 'preview' ? 'cah_preview_import' : 'cah_process_import',
                        nonce: cah_ajax.nonce,
                        csv_content: csvContent,
                        field_mappings: JSON.stringify(fieldMappings),
                        client_type: clientType
                    },
                    success: function(response) {
                        if (response.success) {
                            if (action === 'preview') {
                                displayImportPreview(response.data);
                                $('#import-preview').show();
                            } else {
                                displayImportResults(response.data);
                                $('#import-results').show();
                            }
                        } else {
                            alert('Error: ' + (response.data.message || action + ' failed'));
                        }
                    },
                    error: function() {
                        alert('AJAX error occurred');
                    },
                    complete: function() {
                        $(buttonId).prop('disabled', false).text(originalText);
                    }
                });
            }
            
            function displayFieldDetectionResults(data) {
                // Store client field mappings globally for use in collectFieldMappings
                if (data.client_config && data.client_config.field_mappings) {
                    window.clientFieldMappings = data.client_config.field_mappings;
                } else {
                    window.clientFieldMappings = {};
                }
                
                var html = '<div class=\"detection-summary\">';
                html += '<h4>📊 Detection Summary</h4>';
                html += '<p><strong>Client Type:</strong> ' + (data.client_config ? data.client_config.name : 'Generic') + '</p>';
                html += '<p><strong>Total Rows:</strong> ' + data.total_rows + '</p>';
                html += '<p><strong>Detected Fields:</strong> ' + data.detected_fields.length + '</p>';
                html += '</div>';
                
                // Field mapping interface
                html += '<div class=\"field-mapping-interface\">';
                html += '<h4>🔗 Field Mapping Configuration</h4>';
                html += '<table class=\"wp-list-table widefat\">';
                html += '<thead><tr><th>CSV Field</th><th>Sample Data</th><th>Detected Type</th><th>Empty %</th><th>Map To</th><th>Actions</th></tr></thead>';
                html += '<tbody>';
                
                data.detected_fields.forEach(function(field, index) {
                    var suggestedMapping = data.suggested_mappings[field.csv_name] || {};
                    var sampleData = field.sample_data.slice(0, 2).join(', ');
                    if (sampleData.length > 50) sampleData = sampleData.substring(0, 50) + '...';
                    
                    html += '<tr>';
                    html += '<td><strong>' + field.csv_name + '</strong></td>';
                    html += '<td><code>' + sampleData + '</code></td>';
                    html += '<td><span class=\"data-type-' + field.data_type + '\">' + field.data_type + '</span></td>';
                    html += '<td>' + field.empty_percentage + '%</td>';
                    html += '<td>';
                    
                    if (suggestedMapping.target_table) {
                        html += '<select class=\"field-mapping-select\" data-csv-field=\"' + field.csv_name + '\">';
                        html += '<option value=\"\">-- Skip Field --</option>';
                        html += '<option value=\"' + suggestedMapping.target_table + '.' + suggestedMapping.target_field + '\" selected>';
                        html += suggestedMapping.target_table + ' → ' + suggestedMapping.target_field;
                        html += '</option>';
                        html += '</select>';
                    } else {
                        html += '<select class=\"field-mapping-select\" data-csv-field=\"' + field.csv_name + '\">';
                        html += '<option value=\"\" selected>-- Skip Field --</option>';
                        html += '<option value=\"klage_cases.case_notes\">Cases → Notes</option>';
                        html += '<option value=\"klage_debtors.debtors_name\">Debtors → Name</option>';
                        html += '<option value=\"klage_debtors.debtors_email\">Debtors → Email</option>';
                        html += '</select>';
                    }
                    
                    html += '</td>';
                    html += '<td><button type=\"button\" class=\"button button-small\" onclick=\"customizeMapping(\\''+field.csv_name+'\\')\">\uD83D\uDD27 Customize</button></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                html += '</div>';
                
                $('#field-mapping-interface').html(html);
                
                // Store CSV data for later use
                $('#csv_data').val(data.csv_content || $('#csv_content').val());
                $('#selected_client_type').val($('#client_type').val());
            }
            
            function collectFieldMappings() {
                var mappings = {};
                var clientType = $('#selected_client_type').val();
                
                $('.field-mapping-select').each(function() {
                    var csvField = $(this).data('csv-field');
                    var mapping = $(this).val();
                    if (mapping) {
                        var parts = mapping.split('.');
                        var basicMapping = {
                            target_table: parts[0],
                            target_field: parts[1],
                            data_type: 'string' // Default, can be enhanced
                        };
                        
                        // If this is a known client type, use the full configuration
                        if (window.clientFieldMappings && window.clientFieldMappings[csvField]) {
                            mappings[csvField] = Object.assign({}, window.clientFieldMappings[csvField], basicMapping);
                        } else {
                            // Default configuration for unknown mappings
                            mappings[csvField] = Object.assign({
                                allow_empty: true, // Default to allowing empty values
                                required: false
                            }, basicMapping);
                        }
                    }
                });
                return mappings;
            }
            
            function displayImportPreview(data) {
                var html = '<h4>👁️ Import Preview (' + data.preview_rows + ' of ' + data.total_rows + ' rows)</h4>';
                
                if (data.validation_errors.length > 0) {
                    html += '<div class=\"notice notice-warning\"><p><strong>Validation Issues Found:</strong></p><ul>';
                    data.validation_errors.slice(0, 10).forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    if (data.validation_errors.length > 10) {
                        html += '<li>... and ' + (data.validation_errors.length - 10) + ' more issues</li>';
                    }
                    html += '</ul></div>';
                }
                
                html += '<table class=\"wp-list-table widefat\">';
                html += '<thead><tr><th>Row</th><th>Status</th><th>Mapped Data</th><th>Issues</th></tr></thead>';
                html += '<tbody>';
                
                data.preview_data.forEach(function(row) {
                    html += '<tr class=\"' + (row.valid ? 'valid-row' : 'invalid-row') + '\">';
                    html += '<td>' + row.row_number + '</td>';
                    html += '<td>' + (row.valid ? '<span style=\"color: green;\">✅ Valid</span>' : '<span style=\"color: red;\">❌ Invalid</span>') + '</td>';
                    html += '<td><details><summary>View Data</summary><pre>' + JSON.stringify(row.mapped_data, null, 2) + '</pre></details></td>';
                    html += '<td>' + (row.errors.length > 0 ? row.errors.join('<br>') : 'None') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                
                $('#preview-results').html(html);
            }
            
            function displayImportResults(data) {
                var html = '<h4>⚡ Import Complete!</h4>';
                
                // Summary stats
                html += '<div class=\"import-stats\" style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;\">';
                html += '<div class=\"stat-card\" style=\"background: #fff; padding: 15px; border-radius: 5px; text-align: center; border-left: 4px solid #0073aa;\">';
                html += '<h3 style=\"margin: 0; color: #0073aa;\">' + data.total_rows + '</h3>';
                html += '<p style=\"margin: 5px 0 0 0;\">Total Rows</p></div>';
                
                html += '<div class=\"stat-card\" style=\"background: #fff; padding: 15px; border-radius: 5px; text-align: center; border-left: 4px solid #46b450;\">';
                html += '<h3 style=\"margin: 0; color: #46b450;\">' + data.successful_imports + '</h3>';
                html += '<p style=\"margin: 5px 0 0 0;\">Successful</p></div>';
                
                html += '<div class=\"stat-card\" style=\"background: #fff; padding: 15px; border-radius: 5px; text-align: center; border-left: 4px solid #dc3232;\">';
                html += '<h3 style=\"margin: 0; color: #dc3232;\">' + data.failed_imports + '</h3>';
                html += '<p style=\"margin: 5px 0 0 0;\">Failed</p></div>';
                
                var successRate = data.total_rows > 0 ? Math.round((data.successful_imports / data.total_rows) * 100) : 0;
                var rateColor = successRate >= 90 ? '#46b450' : (successRate >= 70 ? '#ffb900' : '#dc3232');
                html += '<div class=\"stat-card\" style=\"background: #fff; padding: 15px; border-radius: 5px; text-align: center; border-left: 4px solid ' + rateColor + ';\">';
                html += '<h3 style=\"margin: 0; color: ' + rateColor + ';\">' + successRate + '%</h3>';
                html += '<p style=\"margin: 5px 0 0 0;\">Success Rate</p></div>';
                html += '</div>';
                
                // Created records summary
                if (data.successful_imports > 0) {
                    html += '<h4>📋 Created Records</h4>';
                    html += '<ul>';
                    if (data.created_records.cases.length > 0) {
                        html += '<li><strong>Cases:</strong> ' + data.created_records.cases.length + ' created</li>';
                    }
                    if (data.created_records.debtors.length > 0) {
                        html += '<li><strong>Debtors:</strong> ' + data.created_records.debtors.length + ' created</li>';
                    }
                    if (data.created_records.clients.length > 0) {
                        html += '<li><strong>Clients:</strong> ' + data.created_records.clients.length + ' created</li>';
                    }
                    if (data.created_records.emails.length > 0) {
                        html += '<li><strong>Emails:</strong> ' + data.created_records.emails.length + ' created</li>';
                    }
                    html += '</ul>';
                }
                
                // Errors
                if (data.errors.length > 0) {
                    html += '<h4>❌ Import Errors</h4>';
                    html += '<div style=\"max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 5px;\">';
                    html += '<ul>';
                    data.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul></div>';
                }
                
                $('#final-results').html(html);
                
                // Show success message
                if (data.success) {
                    $('<div class=\"notice notice-success is-dismissible\"><p><strong>Import completed successfully!</strong> ' + data.successful_imports + ' records imported.</p></div>')
                        .insertAfter('.wrap h1');
                }
            }
        });
        
        // Global functions
        function customizeMapping(csvFieldName) {
            // Create a modal dialog for field customization
            var modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
            
            var modalContent = document.createElement('div');
            modalContent.style.cssText = 'background: white; padding: 20px; border-radius: 5px; max-width: 500px; width: 90%;';
            
            modalContent.innerHTML = '<h3>Customize Field Mapping: ' + csvFieldName + '</h3>' +
            '<p>Select target table and field:</p>' +
            '<table style=\"width: 100%; margin: 10px 0;\">' +
            '<tr><td>Target Table:</td><td><select id=\"modal-table\"><option value=\"klage_cases\">Cases</option><option value=\"klage_debtors\">Debtors</option><option value=\"klage_clients\">Clients</option><option value=\"klage_emails\">Emails</option></select></td></tr>' +
            '<tr><td>Target Field:</td><td><input type=\"text\" id=\"modal-field\" placeholder=\"field_name\" style=\"width: 100%;\"></td></tr>' +
            '<tr><td>Data Type:</td><td><select id=\"modal-type\"><option value=\"string\">String</option><option value=\"email\">Email</option><option value=\"url\">URL</option><option value=\"date\">Date</option><option value=\"integer\">Integer</option><option value=\"decimal\">Decimal</option></select></td></tr>' +
            '</table>' +
            '<p><button onclick=\"applyCustomMapping(\\''+csvFieldName+'\\')\" class=\"button button-primary\">Apply</button> ' +
            '<button onclick=\"closeCustomMappingModal()\" class=\"button\">Cancel</button></p>';
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            window.currentMappingModal = modal;
        }
        
        function applyCustomMapping(csvFieldName) {
            var table = document.getElementById('modal-table').value;
            var field = document.getElementById('modal-field').value;
            var type = document.getElementById('modal-type').value;
            
            if (field) {
                // Update the dropdown for this field
                var select = document.querySelector('[data-csv-field=\"' + csvFieldName + '\"]');
                if (select) {
                    // Add new option if not exists
                    var newValue = table + '.' + field;
                    var existingOption = select.querySelector('[value=\"' + newValue + '\"]');
                    if (!existingOption) {
                        var option = document.createElement('option');
                        option.value = newValue;
                        option.text = table + ' → ' + field;
                        select.appendChild(option);
                    }
                    select.value = newValue;
                }
            }
            
            closeCustomMappingModal();
        }
        
        function closeCustomMappingModal() {
            if (window.currentMappingModal) {
                document.body.removeChild(window.currentMappingModal);
                window.currentMappingModal = null;
            }
        }
        
        function viewMappingConfig(clientType) {
            // Create modal to view mapping configuration
            var modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
            
            var modalContent = document.createElement('div');
            modalContent.style.cssText = 'background: white; padding: 20px; border-radius: 5px; max-width: 800px; width: 90%; max-height: 80%; overflow-y: auto;';
            
            // Load config via AJAX
            $.ajax({
                url: cah_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cah_load_client_mapping',
                    nonce: cah_ajax.nonce,
                    client_type: clientType
                },
                success: function(response) {
                    if (response.success && response.data.client_config) {
                        var config = response.data.client_config;
                        var html = '<h3>Mapping Configuration: ' + config.name + '</h3>';
                        html += '<p>' + config.description + '</p>';
                        
                        if (config.field_mappings) {
                            html += '<table class=\"wp-list-table widefat\" style=\"margin: 15px 0;\">';
                            html += '<thead><tr><th>CSV Field</th><th>Target Table</th><th>Target Field</th><th>Data Type</th></tr></thead><tbody>';
                            
                            Object.keys(config.field_mappings).forEach(function(csvField) {
                                var mapping = config.field_mappings[csvField];
                                html += '<tr>';
                                html += '<td><code>' + csvField + '</code></td>';
                                html += '<td>' + mapping.target_table + '</td>';
                                html += '<td>' + mapping.target_field + '</td>';
                                html += '<td>' + (mapping.data_type || 'string') + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                        }
                        
                        html += '<p><button onclick=\"closeViewConfigModal()\" class=\"button button-primary\">Close</button></p>';
                        modalContent.innerHTML = html;
                    } else {
                        modalContent.innerHTML = '<h3>Error</h3><p>Could not load configuration for ' + clientType + '</p><p><button onclick=\"closeViewConfigModal()\" class=\"button\">Close</button></p>';
                    }
                },
                error: function() {
                    modalContent.innerHTML = '<h3>Error</h3><p>Failed to load configuration</p><p><button onclick=\"closeViewConfigModal()\" class=\"button\">Close</button></p>';
                }
            });
            
            modalContent.innerHTML = '<p>Loading configuration...</p>';
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            window.currentViewModal = modal;
        }
        
        function closeViewConfigModal() {
            if (window.currentViewModal) {
                document.body.removeChild(window.currentViewModal);
                window.currentViewModal = null;
            }
        }
        
        function editMappingConfig(clientType) {
            alert('Edit mapping configuration for: ' + clientType + '\\n\\nThis advanced feature allows modifying field mappings and will be available in v1.9.4');
        }
        
        function createNewMappingConfig() {
            alert('Create new mapping configuration\\n\\nThis feature allows creating custom import templates and will be available in v1.9.4');
        }
        ";
    }
    
    /**
     * Get inline CSS for universal import interface
     */
    private function get_inline_css() {
        return "
        #universal-import-tabs .nav-tab-wrapper {
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
        }
        
        .tab-content {
            padding: 0;
        }
        
        .client-config-card {
            background: #f9f9f9;
        }
        
        .client-version {
            color: #666;
            font-size: 0.9em;
        }
        
        .import-type-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .import-type-forderungen_com {
            background: #e7f3ff;
            color: #0073aa;
        }
        
        .import-type-generic {
            background: #f0f0f0;
            color: #666;
        }
        
        .success-count {
            color: #46b450;
            font-weight: bold;
        }
        
        .failed-count {
            color: #dc3232;
            font-weight: bold;
        }
        
        .data-type-email {
            background: #e7f3ff;
            color: #0073aa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        
        .data-type-date {
            background: #fff2e7;
            color: #d63638;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        
        .data-type-integer, .data-type-decimal {
            background: #f0f6fc;
            color: #0969da;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        
        .data-type-string {
            background: #f6f8fa;
            color: #656d76;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        
        .valid-row {
            background-color: #f0f9ff;
        }
        
        .invalid-row {
            background-color: #fef2f2;
        }
        
        .field-mapping-select {
            width: 100%;
            max-width: 250px;
        }
        
        .detection-summary {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .import-stats {
            margin: 20px 0;
        }
        
        .stat-card {
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        ";
    }
}