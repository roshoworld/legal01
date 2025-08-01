<?php
/**
 * Admin Interface for Import Plugin
 * Handles both standalone and integrated admin interfaces
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Admin_Interface {
    
    private $import_manager;
    private $export_manager;
    private $is_integrated = false;
    
    public function __construct($import_manager, $export_manager) {
        $this->import_manager = $import_manager;
        $this->export_manager = $export_manager;
        
        // Check if running as integrated interface
        $this->is_integrated = $this->is_admin_plugin_integration();
        
        if (!$this->is_integrated) {
            // Add standalone admin menu
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * Check if admin plugin integration is active
     */
    private function is_admin_plugin_integration() {
        return class_exists('LegalAutomationAdmin') && defined('LAA_PLUGIN_VERSION');
    }
    
    /**
     * Add standalone admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Legal Automation - Import/Export',
            'Import/Export',
            'manage_options',
            'legal-automation-import',
            array($this, 'render_admin_page'),
            'dashicons-database-import',
            30
        );
        
        // Add submenus
        add_submenu_page(
            'legal-automation-import',
            'Data Sources',
            'Data Sources',
            'manage_options',
            'legal-automation-import-sources',
            array($this, 'render_data_sources_page')
        );
        
        add_submenu_page(
            'legal-automation-import',
            'Field Mappings',
            'Field Mappings',
            'manage_options',
            'legal-automation-import-mappings',
            array($this, 'render_field_mappings_page')
        );
        
        add_submenu_page(
            'legal-automation-import',
            'History',
            'History',
            'manage_options',
            'legal-automation-import-history',
            array($this, 'render_history_page')
        );
    }
    
    /**
     * Render main admin page
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'import';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Legal Automation - Import/Export v' . LAI_PLUGIN_VERSION, 'legal-automation-import'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=legal-automation-import&tab=import" class="nav-tab <?php echo $current_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('📊 Import Data', 'legal-automation-import'); ?>
                </a>
                <a href="?page=legal-automation-import&tab=export" class="nav-tab <?php echo $current_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('📤 Export Data', 'legal-automation-import'); ?>
                </a>
                <a href="?page=legal-automation-import&tab=airtable" class="nav-tab <?php echo $current_tab === 'airtable' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('🗂️ Airtable Integration', 'legal-automation-import'); ?>
                </a>
                <a href="?page=legal-automation-import&tab=pipedream" class="nav-tab <?php echo $current_tab === 'pipedream' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('🔄 Pipedream Webhooks', 'legal-automation-import'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'import':
                        $this->render_import_tab();
                        break;
                    case 'export':
                        $this->render_export_tab();
                        break;
                    case 'airtable':
                        $this->render_airtable_tab();
                        break;
                    case 'pipedream':
                        $this->render_pipedream_tab();
                        break;
                    default:
                        $this->render_import_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render integrated interface (for admin plugin)
     */
    public function render_integrated_interface() {
        ?>
        <div class="lai-integrated-interface">
            <div class="lai-tab-navigation">
                <nav class="nav-tab-wrapper">
                    <a href="#lai-import" class="nav-tab nav-tab-active" data-tab="import">
                        <?php echo esc_html__('📊 Import Data', 'legal-automation-import'); ?>
                    </a>
                    <a href="#lai-export" class="nav-tab" data-tab="export">
                        <?php echo esc_html__('📤 Export Data', 'legal-automation-import'); ?>
                    </a>
                    <a href="#lai-airtable" class="nav-tab" data-tab="airtable">
                        <?php echo esc_html__('🗂️ Airtable', 'legal-automation-import'); ?>
                    </a>
                    <a href="#lai-pipedream" class="nav-tab" data-tab="pipedream">
                        <?php echo esc_html__('🔄 Pipedream', 'legal-automation-import'); ?>
                    </a>
                </nav>
            </div>
            
            <div class="lai-tab-content">
                <div id="lai-import" class="lai-tab-pane active">
                    <?php $this->render_import_tab(); ?>
                </div>
                <div id="lai-export" class="lai-tab-pane">
                    <?php $this->render_export_tab(); ?>
                </div>
                <div id="lai-airtable" class="lai-tab-pane">
                    <?php $this->render_airtable_tab(); ?>
                </div>
                <div id="lai-pipedream" class="lai-tab-pane">
                    <?php $this->render_pipedream_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render import tab
     */
    private function render_import_tab() {
        $data_sources = $this->import_manager->get_data_sources();
        ?>
        <div class="lai-import-container">
            <div class="lai-data-source-selection">
                <h2><?php echo esc_html__('Select Data Source', 'legal-automation-import'); ?></h2>
                <div class="lai-source-cards">
                    <?php foreach ($data_sources as $source_id => $source_config): ?>
                        <?php if ($source_config['enabled']): ?>
                            <div class="lai-source-card" data-source="<?php echo esc_attr($source_id); ?>">
                                <div class="lai-source-icon">
                                    <span class="dashicons <?php echo esc_attr($source_config['icon']); ?>"></span>
                                </div>
                                <h3><?php echo esc_html($source_config['name']); ?></h3>
                                <p><?php echo esc_html($source_config['description']); ?></p>
                                <button class="button button-primary lai-select-source" data-source="<?php echo esc_attr($source_id); ?>">
                                    <?php echo esc_html__('Select', 'legal-automation-import'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="lai-import-process" id="lai-import-process" style="display: none;">
                <div class="lai-import-steps">
                    <div class="lai-step lai-step-active" data-step="1">
                        <span class="lai-step-number">1</span>
                        <span class="lai-step-title"><?php echo esc_html__('Configure Source', 'legal-automation-import'); ?></span>
                    </div>
                    <div class="lai-step" data-step="2">
                        <span class="lai-step-number">2</span>
                        <span class="lai-step-title"><?php echo esc_html__('Map Fields', 'legal-automation-import'); ?></span>
                    </div>
                    <div class="lai-step" data-step="3">
                        <span class="lai-step-number">3</span>
                        <span class="lai-step-title"><?php echo esc_html__('Preview & Import', 'legal-automation-import'); ?></span>
                    </div>
                </div>
                
                <div class="lai-step-content">
                    <div class="lai-step-content-1">
                        <div id="lai-source-config"></div>
                    </div>
                    <div class="lai-step-content-2" style="display: none;">
                        <div id="lai-field-mapping"></div>
                    </div>
                    <div class="lai-step-content-3" style="display: none;">
                        <div id="lai-import-preview"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render export tab
     */
    private function render_export_tab() {
        ?>
        <div class="lai-export-container">
            <h2><?php echo esc_html__('Export Data', 'legal-automation-import'); ?></h2>
            
            <div class="lai-export-options">
                <div class="lai-export-card">
                    <h3><?php echo esc_html__('Export Cases to CSV', 'legal-automation-import'); ?></h3>
                    <p><?php echo esc_html__('Export all cases and related data to CSV format', 'legal-automation-import'); ?></p>
                    
                    <form id="lai-export-cases-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Date Range', 'legal-automation-import'); ?></th>
                                <td>
                                    <input type="date" name="export_date_from" id="export_date_from">
                                    <span><?php echo esc_html__('to', 'legal-automation-import'); ?></span>
                                    <input type="date" name="export_date_to" id="export_date_to">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Include Fields', 'legal-automation-import'); ?></th>
                                <td>
                                    <fieldset>
                                        <label><input type="checkbox" name="include_contacts" value="1" checked> <?php echo esc_html__('Contact Information', 'legal-automation-import'); ?></label><br>
                                        <label><input type="checkbox" name="include_financials" value="1" checked> <?php echo esc_html__('Financial Data', 'legal-automation-import'); ?></label><br>
                                        <label><input type="checkbox" name="include_notes" value="1"> <?php echo esc_html__('Case Notes', 'legal-automation-import'); ?></label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Format', 'legal-automation-import'); ?></th>
                                <td>
                                    <select name="export_format">
                                        <option value="csv"><?php echo esc_html__('CSV (Comma Separated)', 'legal-automation-import'); ?></option>
                                        <option value="csv_semicolon"><?php echo esc_html__('CSV (Semicolon Separated)', 'legal-automation-import'); ?></option>
                                        <option value="excel"><?php echo esc_html__('Excel (XLSX)', 'legal-automation-import'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Export Data', 'legal-automation-import'); ?></button>
                        </p>
                    </form>
                </div>
                
                <div class="lai-export-card">
                    <h3><?php echo esc_html__('Download Templates', 'legal-automation-import'); ?></h3>
                    <p><?php echo esc_html__('Download CSV templates for data import', 'legal-automation-import'); ?></p>
                    
                    <div class="lai-template-downloads">
                        <a href="#" class="button lai-download-template" data-template="cases">
                            <?php echo esc_html__('Cases Template', 'legal-automation-import'); ?>
                        </a>
                        <a href="#" class="button lai-download-template" data-template="contacts">
                            <?php echo esc_html__('Contacts Template', 'legal-automation-import'); ?>
                        </a>
                        <a href="#" class="button lai-download-template" data-template="forderungen">
                            <?php echo esc_html__('Forderungen.com Template', 'legal-automation-import'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Airtable tab
     */
    private function render_airtable_tab() {
        $airtable_config = get_option('lai_airtable_config', array());
        $is_connected = !empty($airtable_config['api_key']);
        ?>
        <div class="lai-airtable-container">
            <h2><?php echo esc_html__('Airtable Integration', 'legal-automation-import'); ?></h2>
            
            <?php if (!$is_connected): ?>
                <div class="lai-airtable-setup">
                    <div class="lai-setup-card">
                        <h3><?php echo esc_html__('Connect to Airtable', 'legal-automation-import'); ?></h3>
                        <p><?php echo esc_html__('Connect your Airtable base to sync data directly with the legal automation system.', 'legal-automation-import'); ?></p>
                        
                        <form id="lai-airtable-connect-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('API Key', 'legal-automation-import'); ?></th>
                                    <td>
                                        <input type="password" name="airtable_api_key" id="airtable_api_key" class="regular-text" required>
                                        <p class="description">
                                            <?php echo sprintf(
                                                esc_html__('Get your API key from %s', 'legal-automation-import'),
                                                '<a href="https://airtable.com/account" target="_blank">Airtable Account</a>'
                                            ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Base ID', 'legal-automation-import'); ?></th>
                                    <td>
                                        <input type="text" name="airtable_base_id" id="airtable_base_id" class="regular-text" required>
                                        <p class="description"><?php echo esc_html__('Found in your Airtable base URL (e.g., appXXXXXXXXXXXXXX)', 'legal-automation-import'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Table Name', 'legal-automation-import'); ?></th>
                                    <td>
                                        <input type="text" name="airtable_table_name" id="airtable_table_name" class="regular-text" required>
                                        <p class="description"><?php echo esc_html__('Name of the table containing your data', 'legal-automation-import'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php echo esc_html__('Test Connection', 'legal-automation-import'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="lai-airtable-connected">
                    <div class="lai-connection-status">
                        <span class="lai-status-indicator lai-status-connected"></span>
                        <strong><?php echo esc_html__('Connected to Airtable', 'legal-automation-import'); ?></strong>
                        <p><?php echo sprintf(
                            esc_html__('Base: %s | Table: %s | Last sync: %s', 'legal-automation-import'),
                            esc_html($airtable_config['base_id']),
                            esc_html($airtable_config['table_name']),
                            $airtable_config['last_sync'] ? esc_html(date('Y-m-d H:i:s', strtotime($airtable_config['last_sync']))) : esc_html__('Never', 'legal-automation-import')
                        ); ?></p>
                    </div>
                    
                    <div class="lai-airtable-controls">
                        <button class="button button-primary" id="lai-airtable-sync-now">
                            <?php echo esc_html__('Sync Now', 'legal-automation-import'); ?>
                        </button>
                        <button class="button" id="lai-airtable-configure-fields">
                            <?php echo esc_html__('Configure Field Mapping', 'legal-automation-import'); ?>
                        </button>
                        <button class="button" id="lai-airtable-disconnect">
                            <?php echo esc_html__('Disconnect', 'legal-automation-import'); ?>
                        </button>
                    </div>
                    
                    <div class="lai-sync-options" id="lai-sync-options" style="display: none;">
                        <h3><?php echo esc_html__('Sync Options', 'legal-automation-import'); ?></h3>
                        <form id="lai-airtable-sync-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Sync Type', 'legal-automation-import'); ?></th>
                                    <td>
                                        <select name="sync_type">
                                            <option value="incremental"><?php echo esc_html__('Incremental (new/updated records only)', 'legal-automation-import'); ?></option>
                                            <option value="full"><?php echo esc_html__('Full sync (all records)', 'legal-automation-import'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('View Filter', 'legal-automation-import'); ?></th>
                                    <td>
                                        <input type="text" name="view_filter" placeholder="Grid view">
                                        <p class="description"><?php echo esc_html__('Optional: Sync only records from specific view', 'legal-automation-import'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Custom Formula', 'legal-automation-import'); ?></th>
                                    <td>
                                        <textarea name="custom_formula" rows="3" cols="50" placeholder="e.g., NOT({Status} = 'Archived')"></textarea>
                                        <p class="description"><?php echo esc_html__('Optional: Airtable formula to filter records', 'legal-automation-import'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php echo esc_html__('Start Sync', 'legal-automation-import'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="lai-airtable-help">
                <h3><?php echo esc_html__('Setup Instructions', 'legal-automation-import'); ?></h3>
                <ol>
                    <li><?php echo esc_html__('Go to your Airtable account and create a Personal Access Token', 'legal-automation-import'); ?></li>
                    <li><?php echo esc_html__('Copy your Base ID from the Airtable API documentation', 'legal-automation-import'); ?></li>
                    <li><?php echo esc_html__('Enter the exact table name from your Airtable base', 'legal-automation-import'); ?></li>
                    <li><?php echo esc_html__('Test the connection and configure field mappings', 'legal-automation-import'); ?></li>
                    <li><?php echo esc_html__('Set up scheduled sync or use manual sync as needed', 'legal-automation-import'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Pipedream tab
     */
    private function render_pipedream_tab() {
        $pipedream_webhooks = get_option('lai_pipedream_webhooks', array());
        ?>
        <div class="lai-pipedream-container">
            <h2><?php echo esc_html__('Pipedream Webhooks', 'legal-automation-import'); ?></h2>
            
            <div class="lai-pipedream-setup">
                <div class="lai-setup-card">
                    <h3><?php echo esc_html__('Create New Webhook', 'legal-automation-import'); ?></h3>
                    <p><?php echo esc_html__('Set up a webhook endpoint to receive real-time case data from Pipedream workflows.', 'legal-automation-import'); ?></p>
                    
                    <form id="lai-pipedream-setup-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Source Identifier', 'legal-automation-import'); ?></th>
                                <td>
                                    <input type="text" name="source_identifier" id="source_identifier" class="regular-text" required>
                                    <p class="description"><?php echo esc_html__('Unique identifier for this webhook source (e.g., partner-name)', 'legal-automation-import'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Webhook Secret', 'legal-automation-import'); ?></th>
                                <td>
                                    <input type="password" name="webhook_secret" id="webhook_secret" class="regular-text" required>
                                    <button type="button" class="button" id="generate_webhook_secret"><?php echo esc_html__('Generate', 'legal-automation-import'); ?></button>
                                    <p class="description"><?php echo esc_html__('Secret key for webhook authentication', 'legal-automation-import'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Create Webhook', 'legal-automation-import'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($pipedream_webhooks)): ?>
                <div class="lai-existing-webhooks">
                    <h3><?php echo esc_html__('Existing Webhooks', 'legal-automation-import'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Source ID', 'legal-automation-import'); ?></th>
                                <th><?php echo esc_html__('Webhook URL', 'legal-automation-import'); ?></th>
                                <th><?php echo esc_html__('Status', 'legal-automation-import'); ?></th>
                                <th><?php echo esc_html__('Created', 'legal-automation-import'); ?></th>
                                <th><?php echo esc_html__('Actions', 'legal-automation-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pipedream_webhooks as $source_id => $webhook): ?>
                                <tr>
                                    <td><code><?php echo esc_html($source_id); ?></code></td>
                                    <td>
                                        <input type="text" value="<?php echo esc_attr($webhook['webhook_url']); ?>" readonly class="regular-text">
                                        <button type="button" class="button lai-copy-webhook" data-url="<?php echo esc_attr($webhook['webhook_url']); ?>"><?php echo esc_html__('Copy', 'legal-automation-import'); ?></button>
                                    </td>
                                    <td>
                                        <span class="lai-status-indicator lai-status-<?php echo esc_attr($webhook['status']); ?>"></span>
                                        <?php echo esc_html(ucfirst($webhook['status'])); ?>
                                    </td>
                                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($webhook['created_at']))); ?></td>
                                    <td>
                                        <button type="button" class="button lai-test-webhook" data-source="<?php echo esc_attr($source_id); ?>"><?php echo esc_html__('Test', 'legal-automation-import'); ?></button>
                                        <button type="button" class="button lai-delete-webhook" data-source="<?php echo esc_attr($source_id); ?>"><?php echo esc_html__('Delete', 'legal-automation-import'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="lai-pipedream-help">
                <h3><?php echo esc_html__('Pipedream Integration Guide', 'legal-automation-import'); ?></h3>
                <div class="lai-help-sections">
                    <div class="lai-help-section">
                        <h4><?php echo esc_html__('1. Set up Pipedream Workflow', 'legal-automation-import'); ?></h4>
                        <ul>
                            <li><?php echo esc_html__('Create a new workflow in Pipedream', 'legal-automation-import'); ?></li>
                            <li><?php echo esc_html__('Add your trigger (e.g., Airtable, Google Sheets, API)', 'legal-automation-import'); ?></li>
                            <li><?php echo esc_html__('Add an HTTP request step at the end', 'legal-automation-import'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="lai-help-section">
                        <h4><?php echo esc_html__('2. Configure HTTP Request', 'legal-automation-import'); ?></h4>
                        <ul>
                            <li><?php echo esc_html__('Method: POST', 'legal-automation-import'); ?></li>
                            <li><?php echo esc_html__('URL: Use the webhook URL from above', 'legal-automation-import'); ?></li>
                            <li><?php echo esc_html__('Headers: Add X-Pipedream-Signature with your secret', 'legal-automation-import'); ?></li>
                            <li><?php echo esc_html__('Body: Send your data as JSON', 'legal-automation-import'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="lai-help-section">
                        <h4><?php echo esc_html__('3. Data Format', 'legal-automation-import'); ?></h4>
                        <pre><code>{
  "workflow_id": "your-workflow-id",
  "timestamp": "2024-01-01T12:00:00Z",
  "data": {
    "email": "contact@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "case_id": "CASE-001",
    "status": "new",
    "amount": 1000.00
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render data sources page
     */
    public function render_data_sources_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Data Sources Configuration', 'legal-automation-import'); ?></h1>
            <!-- Data sources configuration content -->
        </div>
        <?php
    }
    
    /**
     * Render field mappings page
     */
    public function render_field_mappings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Field Mappings', 'legal-automation-import'); ?></h1>
            <!-- Field mappings configuration content -->
        </div>
        <?php
    }
    
    /**
     * Render history page
     */
    public function render_history_page() {
        $import_history = $this->import_manager->get_import_history();
        $webhook_history = $this->import_manager->get_webhook_history();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Import/Export History', 'legal-automation-import'); ?></h1>
            
            <div class="lai-history-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#import-history" class="nav-tab nav-tab-active"><?php echo esc_html__('Import History', 'legal-automation-import'); ?></a>
                    <a href="#webhook-history" class="nav-tab"><?php echo esc_html__('Webhook History', 'legal-automation-import'); ?></a>
                </nav>
                
                <div class="lai-history-content">
                    <div id="import-history" class="lai-history-tab active">
                        <h2><?php echo esc_html__('Recent Imports', 'legal-automation-import'); ?></h2>
                        <?php if (!empty($import_history)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Date/Time', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Source', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Total Rows', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Success', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Failed', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Success Rate', 'legal-automation-import'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($import_history as $entry): ?>
                                        <tr>
                                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($entry['timestamp']))); ?></td>
                                            <td><?php echo esc_html(ucfirst($entry['source_id'])); ?></td>
                                            <td><?php echo esc_html($entry['total_rows']); ?></td>
                                            <td><?php echo esc_html($entry['successful_imports']); ?></td>
                                            <td><?php echo esc_html($entry['failed_imports']); ?></td>
                                            <td>
                                                <?php
                                                $success_rate = $entry['total_rows'] > 0 ? round(($entry['successful_imports'] / $entry['total_rows']) * 100, 1) : 0;
                                                $color = $success_rate >= 90 ? 'green' : ($success_rate >= 70 ? 'orange' : 'red');
                                                ?>
                                                <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                                    <?php echo $success_rate; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php echo esc_html__('No import history available.', 'legal-automation-import'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div id="webhook-history" class="lai-history-tab">
                        <h2><?php echo esc_html__('Recent Webhook Calls', 'legal-automation-import'); ?></h2>
                        <?php if (!empty($webhook_history)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Date/Time', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Source', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Type', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Status', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Records', 'legal-automation-import'); ?></th>
                                        <th><?php echo esc_html__('Payload Size', 'legal-automation-import'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($webhook_history as $entry): ?>
                                        <tr>
                                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($entry['timestamp']))); ?></td>
                                            <td><?php echo esc_html($entry['source_id']); ?></td>
                                            <td><?php echo esc_html(ucfirst($entry['webhook_type'])); ?></td>
                                            <td>
                                                <span class="lai-status-indicator lai-status-<?php echo $entry['success'] ? 'success' : 'error'; ?>"></span>
                                                <?php echo $entry['success'] ? esc_html__('Success', 'legal-automation-import') : esc_html__('Failed', 'legal-automation-import'); ?>
                                            </td>
                                            <td><?php echo esc_html($entry['records_processed']); ?></td>
                                            <td><?php echo esc_html(size_format($entry['payload_size'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php echo esc_html__('No webhook history available.', 'legal-automation-import'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}