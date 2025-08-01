<?php
/**
 * Admin Dashboard Class
 * 
 * Handles the admin interface for document generation
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_Doc_Admin_Dashboard {
    
    private $template_manager;
    private $document_generator;
    private $core_integration;
    private $s3_storage;
    
    public function __construct() {
        // Hook into init to ensure global instance is available
        add_action('admin_init', array($this, 'init_dependencies'));
    }
    
    /**
     * Initialize dependencies
     */
    public function init_dependencies() {
        global $klage_click_doc_out;
        
        if ($klage_click_doc_out) {
            $this->template_manager = $klage_click_doc_out->template_manager;
            $this->document_generator = $klage_click_doc_out->document_generator;
            $this->core_integration = $klage_click_doc_out->core_integration;
            $this->s3_storage = $klage_click_doc_out->s3_storage;
        }
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Document Generator Dashboard', 'klage-click-doc-out'); ?></h1>
            
            <div class="klage-doc-dashboard">
                <div class="dashboard-widgets">
                    
                    <!-- Quick Stats -->
                    <div class="dashboard-widget">
                        <h3><?php _e('Quick Stats', 'klage-click-doc-out'); ?></h3>
                        <div class="stats-grid">
                            <?php $this->render_quick_stats(); ?>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="dashboard-widget">
                        <h3><?php _e('Recent Documents', 'klage-click-doc-out'); ?></h3>
                        <?php $this->render_recent_documents(); ?>
                    </div>
                    
                    <!-- System Status -->
                    <div class="dashboard-widget">
                        <h3><?php _e('System Status', 'klage-click-doc-out'); ?></h3>
                        <?php $this->render_system_status(); ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="dashboard-widget">
                        <h3><?php _e('Quick Actions', 'klage-click-doc-out'); ?></h3>
                        <div class="quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=klage-doc-templates'); ?>" class="button button-primary">
                                <?php _e('Manage Templates', 'klage-click-doc-out'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=klage-doc-generate'); ?>" class="button button-secondary">
                                <?php _e('Generate Document', 'klage-click-doc-out'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=klage-doc-settings'); ?>" class="button button-secondary">
                                <?php _e('Settings', 'klage-click-doc-out'); ?>
                            </a>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <style>
        .klage-doc-dashboard {
            margin-top: 20px;
        }
        
        .dashboard-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .dashboard-widget {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        
        .dashboard-widget h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .status-ok { background-color: #46b450; }
        .status-warning { background-color: #ffb900; }
        .status-error { background-color: #dc3232; }
        </style>
        <?php
    }
    
    /**
     * Render templates page
     */
    public function render_templates_page() {
        $action = $_GET['action'] ?? 'list';
        $template_id = $_GET['template_id'] ?? 0;
        
        switch ($action) {
            case 'edit':
                $this->render_edit_template_page($template_id);
                break;
            case 'new':
                $this->render_new_template_page();
                break;
            default:
                $this->render_templates_list();
                break;
        }
    }
    
    /**
     * Render generation page
     */
    public function render_generation_page() {
        $draft_id = $_GET['draft_id'] ?? 0;
        $case_id = $_GET['case_id'] ?? 0;
        
        if ($draft_id) {
            $this->render_edit_document_page($draft_id);
        } else {
            $this->render_new_document_page($case_id);
        }
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Document Generator Settings', 'klage-click-doc-out'); ?></h1>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('klage_doc_settings');
                do_settings_sections('klage_doc_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('PDF Engine', 'klage-click-doc-out'); ?></th>
                        <td>
                            <select name="klage_doc_pdf_engine">
                                <option value="mpdf" <?php selected(get_option('klage_doc_pdf_engine', 'mpdf'), 'mpdf'); ?>>mPDF</option>
                            </select>
                            <p class="description"><?php _e('PDF generation engine to use.', 'klage-click-doc-out'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Core Integration', 'klage-click-doc-out'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="klage_doc_core_integration" value="1" <?php checked(get_option('klage_doc_core_integration', true)); ?> />
                                <?php _e('Enable integration with Court Automation Hub core plugin', 'klage-click-doc-out'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Template Encryption', 'klage-click-doc-out'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="klage_doc_template_encryption" value="1" <?php checked(get_option('klage_doc_template_encryption', true)); ?> />
                                <?php _e('Enable template encryption for S3 storage', 'klage-click-doc-out'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('S3 Storage Configuration', 'klage-click-doc-out'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable S3 Storage', 'klage-click-doc-out'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="klage_doc_s3_enabled" value="1" <?php checked(get_option('klage_doc_s3_enabled', false)); ?> />
                                <?php _e('Enable encrypted template storage to S3 (IONOS)', 'klage-click-doc-out'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('S3 Access Key', 'klage-click-doc-out'); ?></th>
                        <td>
                            <input type="text" name="klage_doc_s3_access_key" value="<?php echo esc_attr(get_option('klage_doc_s3_access_key', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('S3 Secret Key', 'klage-click-doc-out'); ?></th>
                        <td>
                            <input type="password" name="klage_doc_s3_secret_key" value="<?php echo esc_attr(get_option('klage_doc_s3_secret_key', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('S3 Bucket', 'klage-click-doc-out'); ?></th>
                        <td>
                            <input type="text" name="klage_doc_s3_bucket" value="<?php echo esc_attr(get_option('klage_doc_s3_bucket', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('S3 Region', 'klage-click-doc-out'); ?></th>
                        <td>
                            <input type="text" name="klage_doc_s3_region" value="<?php echo esc_attr(get_option('klage_doc_s3_region', 'eu-central-1')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('S3 Endpoint', 'klage-click-doc-out'); ?></th>
                        <td>
                            <input type="text" name="klage_doc_s3_endpoint" value="<?php echo esc_attr(get_option('klage_doc_s3_endpoint', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('IONOS S3 endpoint URL', 'klage-click-doc-out'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Encryption Key', 'klage-click-doc-out'); ?></th>
                        <td>
                            <input type="text" name="klage_doc_encryption_key" value="<?php echo esc_attr(get_option('klage_doc_encryption_key', '')); ?>" class="regular-text" />
                            <button type="button" class="button" onclick="generateEncryptionKey()"><?php _e('Generate New Key', 'klage-click-doc-out'); ?></button>
                            <p class="description"><?php _e('Encryption key for template storage', 'klage-click-doc-out'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- S3 Connection Test -->
            <div class="s3-test-section">
                <h3><?php _e('Test S3 Connection', 'klage-click-doc-out'); ?></h3>
                <button type="button" id="test-s3-connection" class="button"><?php _e('Test Connection', 'klage-click-doc-out'); ?></button>
                <div id="s3-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        function generateEncryptionKey() {
            var key = '';
            var chars = '0123456789abcdef';
            for (var i = 0; i < 64; i++) {
                key += chars[Math.floor(Math.random() * chars.length)];
            }
            document.querySelector('input[name="klage_doc_encryption_key"]').value = key;
        }
        
        jQuery(document).ready(function($) {
            $('#test-s3-connection').on('click', function() {
                var button = $(this);
                var result = $('#s3-test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'klage-click-doc-out'); ?>');
                result.html('');
                
                $.post(ajaxurl, {
                    action: 'test_s3_connection',
                    nonce: klageDocAjax.nonce
                }, function(response) {
                    if (response.success) {
                        result.html('<div class="notice notice-success"><p><?php _e('S3 connection successful!', 'klage-click-doc-out'); ?></p></div>');
                    } else {
                        result.html('<div class="notice notice-error"><p><?php _e('S3 connection failed: ', 'klage-click-doc-out'); ?>' + response.data + '</p></div>');
                    }
                    
                    button.prop('disabled', false).text('<?php _e('Test Connection', 'klage-click-doc-out'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render quick stats
     */
    private function render_quick_stats() {
        if (!$this->template_manager) {
            echo '<p>' . __('Template manager not available.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        $total_templates = $this->template_manager->get_templates_count();
        $active_templates = $this->template_manager->get_templates_count(array('include_inactive' => false));
        
        // Get document drafts count
        global $wpdb;
        $drafts_table = $wpdb->prefix . 'klage_document_drafts';
        $total_drafts = $wpdb->get_var("SELECT COUNT(*) FROM {$drafts_table}");
        $final_docs = $wpdb->get_var("SELECT COUNT(*) FROM {$drafts_table} WHERE status = 'final'");
        
        ?>
        <div class="stat-item">
            <span class="stat-number"><?php echo (int) $total_templates; ?></span>
            <span class="stat-label"><?php _e('Total Templates', 'klage-click-doc-out'); ?></span>
        </div>
        
        <div class="stat-item">
            <span class="stat-number"><?php echo (int) $active_templates; ?></span>
            <span class="stat-label"><?php _e('Active Templates', 'klage-click-doc-out'); ?></span>
        </div>
        
        <div class="stat-item">
            <span class="stat-number"><?php echo (int) $total_drafts; ?></span>
            <span class="stat-label"><?php _e('Document Drafts', 'klage-click-doc-out'); ?></span>
        </div>
        
        <div class="stat-item">
            <span class="stat-number"><?php echo (int) $final_docs; ?></span>
            <span class="stat-label"><?php _e('Final Documents', 'klage-click-doc-out'); ?></span>
        </div>
        <?php
    }
    
    /**
     * Render recent documents
     */
    private function render_recent_documents() {
        global $wpdb;
        
        $drafts_table = $wpdb->prefix . 'klage_document_drafts';
        $templates_table = $wpdb->prefix . 'klage_document_templates';
        
        $recent_drafts = $wpdb->get_results("
            SELECT d.*, t.template_name
            FROM {$drafts_table} d
            LEFT JOIN {$templates_table} t ON d.template_id = t.id
            ORDER BY d.created_at DESC
            LIMIT 10
        ");
        
        if (empty($recent_drafts)) {
            echo '<p>' . __('No documents generated yet.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Template', 'klage-click-doc-out') . '</th>';
        echo '<th>' . __('Status', 'klage-click-doc-out') . '</th>';
        echo '<th>' . __('Case ID', 'klage-click-doc-out') . '</th>';
        echo '<th>' . __('Created', 'klage-click-doc-out') . '</th>';
        echo '<th>' . __('Actions', 'klage-click-doc-out') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($recent_drafts as $draft) {
            echo '<tr>';
            echo '<td>' . esc_html($draft->template_name ?: __('Unknown Template', 'klage-click-doc-out')) . '</td>';
            echo '<td>';
            
            $status_class = '';
            switch ($draft->status) {
                case 'draft':
                    $status_class = 'status-warning';
                    break;
                case 'edited':
                    $status_class = 'status-warning';
                    break;
                case 'final':
                    $status_class = 'status-ok';
                    break;
            }
            
            echo '<span class="status-indicator ' . $status_class . '"></span>';
            echo ucfirst($draft->status);
            echo '</td>';
            echo '<td>' . ($draft->case_id ?: '-') . '</td>';
            echo '<td>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($draft->created_at)) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('admin.php?page=klage-doc-generate&draft_id=' . $draft->id) . '" class="button button-small">' . __('Edit', 'klage-click-doc-out') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Render system status
     */
    private function render_system_status() {
        $statuses = array(
            'Core Plugin' => class_exists('CourtAutomationHub'),
            'Financial Plugin' => class_exists('CAH_Financial_Calculator_Plugin'),
            'mPDF Library' => $this->check_mpdf_availability(),
            'Template Manager' => !empty($this->template_manager),
            'Document Generator' => !empty($this->document_generator),
            'S3 Storage' => !empty($this->s3_storage) && $this->s3_storage->is_configured()
        );
        
        foreach ($statuses as $label => $status) {
            $indicator_class = $status ? 'status-ok' : 'status-error';
            $status_text = $status ? __('OK', 'klage-click-doc-out') : __('Error', 'klage-click-doc-out');
            
            echo '<div class="status-item">';
            echo '<span>' . esc_html($label) . '</span>';
            echo '<span>';
            echo '<span class="status-indicator ' . $indicator_class . '"></span>';
            echo $status_text;
            echo '</span>';
            echo '</div>';
        }
        
        // Use centralized database integration
        $this->render_database_status();
    }
    
    /**
     * Render database status using centralized model
     */
    private function render_database_status() {
        // Check if core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            echo '<div class="status-item">';
            echo '<span>Database Integration</span>';
            echo '<span>';
            echo '<span class="status-indicator status-error"></span>';
            echo 'Core Plugin Required';
            echo '</span>';
            echo '</div>';
            return;
        }
        
        // Use centralized database integration
        $db_integration = new CAH_DocOut_Database_Integration();
        $core_tables = $db_integration->check_core_tables();
        
        // Cases table status
        global $wpdb;
        $case_table_exists = $core_tables['klage_cases'];
        $case_count = 0;
        
        if ($case_table_exists) {
            $case_table = $wpdb->prefix . 'klage_cases';
            $case_count = $wpdb->get_var("SELECT COUNT(*) FROM $case_table");
        }
        
        echo '<div class="status-item">';
        echo '<span>Cases Table</span>';
        echo '<span>';
        $indicator_class = $case_table_exists ? 'status-ok' : 'status-error';
        echo '<span class="status-indicator ' . $indicator_class . '"></span>';
        echo $case_table_exists ? "$case_count cases" : 'Missing';
        echo '</span>';
        echo '</div>';
        
        // Debtors table status
        $debtor_table_exists = $core_tables['klage_debtors'];
        $debtor_count = 0;
        
        if ($debtor_table_exists) {
            $debtor_table = $wpdb->prefix . 'klage_debtors';
            $debtor_count = $wpdb->get_var("SELECT COUNT(*) FROM $debtor_table");
        }
        
        echo '<div class="status-item">';
        echo '<span>Debtors Table</span>';
        echo '<span>';
        $indicator_class = $debtor_table_exists ? 'status-ok' : 'status-error';
        echo '<span class="status-indicator ' . $indicator_class . '"></span>';
        echo $debtor_table_exists ? "$debtor_count debtors" : 'Missing';
        echo '</span>';
        echo '</div>';
    }
    
    /**
     * Check if mPDF is available
     */
    private function check_mpdf_availability() {
        // Check if mPDF is already loaded
        if (class_exists('Mpdf\Mpdf')) {
            return true;
        }
        
        // Check if mPDF package is installed
        $mpdf_autoloader = WP_CONTENT_DIR . '/uploads/klage-mpdf/vendor/autoload.php';
        if (file_exists($mpdf_autoloader)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Render templates list
     */
    private function render_templates_list() {
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Template Gallery', 'klage-click-doc-out'); ?>
                <a href="<?php echo admin_url('admin.php?page=klage-doc-templates&action=new'); ?>" class="page-title-action">
                    <?php _e('Add New Template', 'klage-click-doc-out'); ?>
                </a>
            </h1>
            
            <!-- Search and Filter -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="category" id="template-category-filter">
                        <option value=""><?php _e('All Categories', 'klage-click-doc-out'); ?></option>
                        <?php
                        if ($this->template_manager) {
                            $categories = $this->template_manager->get_template_categories();
                            foreach ($categories as $key => $label) {
                                echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <input type="submit" class="button" value="<?php _e('Filter', 'klage-click-doc-out'); ?>">
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php 
                        if ($this->template_manager) {
                            $total = $this->template_manager->get_templates_count();
                            printf(_n('%s item', '%s items', $total, 'klage-click-doc-out'), number_format_i18n($total));
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- Templates Table -->
            <?php $this->render_templates_table(); ?>
        </div>
        <?php
    }
    
    /**
     * Render templates table
     */
    private function render_templates_table() {
        if (!$this->template_manager) {
            echo '<p>' . __('Template manager not available.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        $templates = $this->template_manager->get_templates();
        
        if (empty($templates)) {
            echo '<p>' . __('No templates found. <a href="' . admin_url('admin.php?page=klage-doc-templates&action=new') . '">Create your first template</a>.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" />
                    </th>
                    <th scope="col" class="manage-column column-name column-primary">
                        <?php _e('Template Name', 'klage-click-doc-out'); ?>
                    </th>
                    <th scope="col" class="manage-column column-category">
                        <?php _e('Category', 'klage-click-doc-out'); ?>
                    </th>
                    <th scope="col" class="manage-column column-placeholders">
                        <?php _e('Placeholders', 'klage-click-doc-out'); ?>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <?php _e('Created', 'klage-click-doc-out'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="template[]" value="<?php echo esc_attr($template->id); ?>" />
                    </th>
                    <td class="column-name column-primary">
                        <strong>
                            <a href="<?php echo admin_url('admin.php?page=klage-doc-templates&action=edit&template_id=' . $template->id); ?>">
                                <?php echo esc_html($template->template_name); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo admin_url('admin.php?page=klage-doc-templates&action=edit&template_id=' . $template->id); ?>">
                                    <?php _e('Edit', 'klage-click-doc-out'); ?>
                                </a> |
                            </span>
                            <span class="duplicate">
                                <a href="#" onclick="duplicateTemplate(<?php echo $template->id; ?>)">
                                    <?php _e('Duplicate', 'klage-click-doc-out'); ?>
                                </a> |
                            </span>
                            <span class="delete">
                                <a href="#" onclick="deleteTemplate(<?php echo $template->id; ?>)" class="submitdelete">
                                    <?php _e('Delete', 'klage-click-doc-out'); ?>
                                </a>
                            </span>
                        </div>
                        <button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e('Show more details', 'klage-click-doc-out'); ?></span></button>
                    </td>
                    <td class="column-category" data-colname="<?php _e('Category', 'klage-click-doc-out'); ?>">
                        <?php echo esc_html(ucfirst($template->template_category)); ?>
                    </td>
                    <td class="column-placeholders" data-colname="<?php _e('Placeholders', 'klage-click-doc-out'); ?>">
                        <?php 
                        if (!empty($template->template_placeholders)) {
                            echo '<span class="placeholder-count">' . count($template->template_placeholders) . ' placeholders</span>';
                        } else {
                            echo '<span class="placeholder-count">0 placeholders</span>';
                        }
                        ?>
                    </td>
                    <td class="column-date" data-colname="<?php _e('Created', 'klage-click-doc-out'); ?>">
                        <?php echo date_i18n(get_option('date_format'), strtotime($template->created_at)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
        function duplicateTemplate(templateId) {
            if (!confirm(klageDocAjax.strings.confirm_duplicate)) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'duplicate_template',
                template_id: templateId,
                nonce: klageDocAjax.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        
        function deleteTemplate(templateId) {
            if (!confirm(klageDocAjax.strings.confirm_delete)) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'delete_template',
                template_id: templateId,
                nonce: klageDocAjax.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render new template page
     */
    private function render_new_template_page() {
        $this->render_template_form();
    }
    
    /**
     * Render edit template page
     */
    private function render_edit_template_page($template_id) {
        if (!$this->template_manager) {
            echo '<p>' . __('Template manager not available.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        $template = $this->template_manager->get_template($template_id);
        if (!$template) {
            echo '<p>' . __('Template not found.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        $this->render_template_form($template);
    }
    
    /**
     * Render template form
     */
    private function render_template_form($template = null) {
        $is_edit = !empty($template);
        $title = $is_edit ? __('Edit Template', 'klage-click-doc-out') : __('Add New Template', 'klage-click-doc-out');
        
        ?>
        <div class="wrap">
            <h1>
                <?php echo $title; ?>
                <a href="<?php echo admin_url('admin.php?page=klage-doc-templates'); ?>" class="page-title-action">
                    <?php _e('Back to Templates', 'klage-click-doc-out'); ?>
                </a>
            </h1>
            
            <form id="template-form" method="post">
                <?php wp_nonce_field('klage_doc_template', 'template_nonce'); ?>
                
                <?php if ($is_edit): ?>
                    <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>" />
                    <input type="hidden" name="action" value="update_template" />
                <?php else: ?>
                    <input type="hidden" name="action" value="create_template" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="template_name"><?php _e('Template Name', 'klage-click-doc-out'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="template_name" name="template_name" 
                                   value="<?php echo esc_attr($template->template_name ?? ''); ?>" 
                                   class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="template_description"><?php _e('Description', 'klage-click-doc-out'); ?></label>
                        </th>
                        <td>
                            <textarea id="template_description" name="template_description" 
                                      class="large-text" rows="3"><?php echo esc_textarea($template->template_description ?? ''); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="template_category"><?php _e('Category', 'klage-click-doc-out'); ?></label>
                        </th>
                        <td>
                            <select id="template_category" name="template_category">
                                <option value="general" <?php selected($template->template_category ?? 'general', 'general'); ?>><?php _e('General', 'klage-click-doc-out'); ?></option>
                                <option value="mahnung" <?php selected($template->template_category ?? '', 'mahnung'); ?>><?php _e('Mahnung', 'klage-click-doc-out'); ?></option>
                                <option value="contract" <?php selected($template->template_category ?? '', 'contract'); ?>><?php _e('Contract', 'klage-click-doc-out'); ?></option>
                                <option value="notice" <?php selected($template->template_category ?? '', 'notice'); ?>><?php _e('Notice', 'klage-click-doc-out'); ?></option>
                                <option value="court" <?php selected($template->template_category ?? '', 'court'); ?>><?php _e('Court Documents', 'klage-click-doc-out'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="template_html"><?php _e('Template HTML', 'klage-click-doc-out'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                $template->template_html ?? '',
                                'template_html',
                                array(
                                    'textarea_name' => 'template_html',
                                    'textarea_rows' => 20,
                                    'media_buttons' => false,
                                    'tinymce' => array(
                                        'height' => 400,
                                        'plugins' => 'lists,link,textcolor,fullscreen',
                                        'toolbar1' => 'bold,italic,underline,|,alignleft,aligncenter,alignright,alignjustify,|,bullist,numlist,|,link,unlink',
                                        'toolbar2' => 'formatselect,|,forecolor,backcolor,|,fullscreen,|,removeformat'
                                    ),
                                    'quicktags' => array(
                                        'buttons' => 'strong,em,link,block,del,ins,ul,ol,li'
                                    )
                                )
                            );
                            ?>
                            <p class="description">
                                <?php _e('Use placeholders like {{debtor_name}}, {{case_id}}, {{current_date}} in your template.', 'klage-click-doc-out'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="template-actions">
                    <input type="submit" class="button button-primary" 
                           value="<?php echo $is_edit ? __('Update Template', 'klage-click-doc-out') : __('Create Template', 'klage-click-doc-out'); ?>" />
                    
                    <?php if ($is_edit): ?>
                        <button type="button" id="preview-template" class="button">
                            <?php _e('Preview Template', 'klage-click-doc-out'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Placeholder Helper -->
            <div class="placeholder-helper">
                <h3><?php _e('Available Placeholders', 'klage-click-doc-out'); ?></h3>
                <div class="placeholder-categories">
                    <div class="placeholder-category">
                        <h4><?php _e('General', 'klage-click-doc-out'); ?></h4>
                        <ul>
                            <li><code>{{current_date}}</code> - <?php _e('Current Date', 'klage-click-doc-out'); ?></li>
                            <li><code>{{current_time}}</code> - <?php _e('Current Time', 'klage-click-doc-out'); ?></li>
                            <li><code>{{current_year}}</code> - <?php _e('Current Year', 'klage-click-doc-out'); ?></li>
                            <li><code>{{site_name}}</code> - <?php _e('Site Name', 'klage-click-doc-out'); ?></li>
                        </ul>
                    </div>
                    
                    <?php if (class_exists('CourtAutomationHub')): ?>
                    <div class="placeholder-category">
                        <h4><?php _e('Case Data', 'klage-click-doc-out'); ?></h4>
                        <ul>
                            <li><code>{{case_id}}</code> - <?php _e('Case ID', 'klage-click-doc-out'); ?></li>
                            <li><code>{{case_creation_date}}</code> - <?php _e('Case Creation Date', 'klage-click-doc-out'); ?></li>
                            <li><code>{{case_status}}</code> - <?php _e('Case Status', 'klage-click-doc-out'); ?></li>
                            <li><code>{{total_amount}}</code> - <?php _e('Total Amount', 'klage-click-doc-out'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="placeholder-category">
                        <h4><?php _e('Debtor Data', 'klage-click-doc-out'); ?></h4>
                        <ul>
                            <li><code>{{debtor_debtors_name}}</code> - <?php _e('Debtor Name', 'klage-click-doc-out'); ?></li>
                            <li><code>{{debtor_debtors_company}}</code> - <?php _e('Company Name', 'klage-click-doc-out'); ?></li>
                            <li><code>{{debtor_debtors_email}}</code> - <?php _e('Email', 'klage-click-doc-out'); ?></li>
                            <li><code>{{debtor_debtors_postal_code}}</code> - <?php _e('Postal Code', 'klage-click-doc-out'); ?></li>
                            <li><code>{{debtor_debtors_city}}</code> - <?php _e('City', 'klage-click-doc-out'); ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .required { color: #d54e21; }
        
        .template-actions {
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid #ddd;
        }
        
        .placeholder-helper {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .placeholder-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .placeholder-category h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        
        .placeholder-category ul {
            margin: 0;
        }
        
        .placeholder-category code {
            background: #fff;
            padding: 2px 4px;
            border-radius: 2px;
            font-size: 11px;
            cursor: pointer;
        }
        
        .placeholder-category code:hover {
            background: #0073aa;
            color: #fff;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Insert placeholder on click
            $('.placeholder-category code').on('click', function() {
                var placeholder = $(this).text();
                var editor = tinymce.get('template_html');
                
                if (editor) {
                    editor.insertContent(placeholder);
                } else {
                    var textarea = $('#template_html');
                    var pos = textarea[0].selectionStart;
                    var text = textarea.val();
                    textarea.val(text.substring(0, pos) + placeholder + text.substring(pos));
                }
            });
            
            // Form submission
            $('#template-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var formData = new FormData(form[0]);
                formData.append('nonce', klageDocAjax.nonce);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=klage-doc-templates'); ?>';
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred. Please try again.', 'klage-click-doc-out'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render new document page
     */
    private function render_new_document_page($case_id = 0) {
        ?>
        <div class="wrap">
            <h1><?php _e('Generate New Document', 'klage-click-doc-out'); ?></h1>
            
            <div class="document-generator-form">
                <form id="generate-document-form" method="post">
                    <?php wp_nonce_field('klage_doc_generate', 'generate_nonce'); ?>
                    
                    <table class="form-table">
                        <?php if (class_exists('CourtAutomationHub')): ?>
                        <tr>
                            <th scope="row">
                                <label for="case_select"><?php _e('Select Case', 'klage-click-doc-out'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="case_select" name="case_id" required>
                                    <option value=""><?php _e('Select a case...', 'klage-click-doc-out'); ?></option>
                                    <?php
                                    if ($this->core_integration) {
                                        $cases = $this->core_integration->get_available_cases(array('per_page' => 100));
                                        foreach ($cases as $case) {
                                            $selected = $case_id == $case['case_id'] ? 'selected' : '';
                                            echo '<option value="' . esc_attr($case['case_id']) . '" ' . $selected . '>';
                                            echo 'Case #' . esc_html($case['case_id']);
                                            if ($case['debtors_name']) {
                                                echo ' - ' . esc_html($case['debtors_name']);
                                            }
                                            if ($case['debtors_company']) {
                                                echo ' (' . esc_html($case['debtors_company']) . ')';
                                            }
                                            echo '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('All documents must be associated with a case for proper data integration.', 'klage-click-doc-out'); ?></p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="2">
                                <div class="notice notice-warning">
                                    <p><?php _e('Court Automation Hub core plugin is required for case integration. Document generation requires case data.', 'klage-click-doc-out'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <th scope="row">
                                <label for="template_select"><?php _e('Select Template', 'klage-click-doc-out'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="template_select" name="template_id" required>
                                    <option value=""><?php _e('Select a template...', 'klage-click-doc-out'); ?></option>
                                    <?php
                                    if ($this->template_manager) {
                                        $templates = $this->template_manager->get_templates();
                                        foreach ($templates as $template) {
                                            echo '<option value="' . esc_attr($template->id) . '">';
                                            echo esc_html($template->template_name) . ' (' . esc_html($template->template_category) . ')';
                                            echo '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Select the document template to use with the selected case data.', 'klage-click-doc-out'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="document-actions">
                        <input type="submit" class="button button-primary" value="<?php _e('Generate Document Draft', 'klage-click-doc-out'); ?>" />
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#generate-document-form').on('submit', function(e) {
                e.preventDefault();
                
                var templateId = $('#template_select').val();
                var caseId = $('#case_select').val();
                
                if (!caseId) {
                    alert('<?php _e('Please select a case. All documents must be associated with a case.', 'klage-click-doc-out'); ?>');
                    return;
                }
                
                if (!templateId) {
                    alert('<?php _e('Please select a template.', 'klage-click-doc-out'); ?>');
                    return;
                }
                
                var button = $('input[type="submit"]');
                button.prop('disabled', true).val('<?php _e('Generating...', 'klage-click-doc-out'); ?>');
                
                $.post(ajaxurl, {
                    action: 'generate_document_draft',
                    template_id: templateId,
                    case_id: caseId,
                    nonce: klageDocAjax.nonce
                }, function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo admin_url('admin.php?page=klage-doc-generate&draft_id='); ?>' + response.data.draft_id;
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false).val('<?php _e('Generate Document Draft', 'klage-click-doc-out'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render edit document page
     */
    private function render_edit_document_page($draft_id) {
        if (!$this->document_generator) {
            echo '<p>' . __('Document generator not available.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        $draft = $this->document_generator->get_document_draft($draft_id);
        if (!$draft) {
            echo '<p>' . __('Document draft not found.', 'klage-click-doc-out') . '</p>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Edit Document', 'klage-click-doc-out'); ?>
                <span class="template-info"> - <?php echo esc_html($draft->template_name); ?></span>
            </h1>
            
            <div class="document-editor">
                <div class="editor-toolbar">
                    <button type="button" id="save-draft" class="button button-secondary">
                        <?php _e('Save Draft', 'klage-click-doc-out'); ?>
                    </button>
                    
                    <button type="button" id="generate-pdf" class="button button-primary">
                        <?php _e('Generate Final PDF', 'klage-click-doc-out'); ?>
                    </button>
                    
                    <div class="draft-status">
                        <?php _e('Status:', 'klage-click-doc-out'); ?> 
                        <span class="status-<?php echo esc_attr($draft->status); ?>">
                            <?php echo esc_html(ucfirst($draft->status)); ?>
                        </span>
                    </div>
                </div>
                
                <div class="editor-container">
                    <?php
                    wp_editor(
                        $draft->draft_html,
                        'document_content',
                        array(
                            'textarea_name' => 'document_content',
                            'textarea_rows' => 25,
                            'media_buttons' => false,
                            'tinymce' => array(
                                'height' => 600,
                                'plugins' => 'lists,link,textcolor,fullscreen',
                                'toolbar1' => 'bold,italic,underline,|,alignleft,aligncenter,alignright,alignjustify,|,bullist,numlist,|,link,unlink',
                                'toolbar2' => 'formatselect,|,forecolor,backcolor,|,fullscreen,|,removeformat'
                            ),
                            'quicktags' => array(
                                'buttons' => 'strong,em,link,block,del,ins,ul,ol,li'
                            )
                        )
                    );
                    ?>
                </div>
            </div>
            
            <input type="hidden" id="draft_id" value="<?php echo esc_attr($draft_id); ?>" />
        </div>
        
        <style>
        .document-editor {
            margin-top: 20px;
        }
        
        .editor-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .draft-status {
            margin-left: auto;
            font-weight: 500;
        }
        
        .status-draft { color: #f56e28; }
        .status-edited { color: #f56e28; }
        .status-final { color: #46b450; }
        
        .template-info {
            font-weight: normal;
            color: #666;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var draftId = $('#draft_id').val();
            
            // Save draft
            $('#save-draft').on('click', function() {
                var button = $(this);
                var editor = tinymce.get('document_content');
                var content = editor ? editor.getContent() : $('#document_content').val();
                
                button.prop('disabled', true).text('<?php _e('Saving...', 'klage-click-doc-out'); ?>');
                
                $.post(ajaxurl, {
                    action: 'save_document_draft',
                    draft_id: draftId,
                    draft_html: content,
                    nonce: klageDocAjax.nonce
                }, function(response) {
                    if (response.success) {
                        button.text('<?php _e('Saved!', 'klage-click-doc-out'); ?>');
                        setTimeout(function() {
                            button.prop('disabled', false).text('<?php _e('Save Draft', 'klage-click-doc-out'); ?>');
                        }, 2000);
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false).text('<?php _e('Save Draft', 'klage-click-doc-out'); ?>');
                    }
                });
            });
            
            // Generate PDF
            $('#generate-pdf').on('click', function() {
                var button = $(this);
                var editor = tinymce.get('document_content');
                var content = editor ? editor.getContent() : $('#document_content').val();
                
                button.prop('disabled', true).text(klageDocAjax.strings.generating_pdf);
                
                // First save the current content
                $.post(ajaxurl, {
                    action: 'save_document_draft',
                    draft_id: draftId,
                    draft_html: content,
                    nonce: klageDocAjax.nonce
                }, function(saveResponse) {
                    if (saveResponse.success) {
                        // Then generate PDF
                        $.post(ajaxurl, {
                            action: 'generate_final_pdf',
                            draft_id: draftId,
                            nonce: klageDocAjax.nonce
                        }, function(pdfResponse) {
                            if (pdfResponse.success) {
                                // Trigger download
                                window.location.href = pdfResponse.data.download_url;
                                button.prop('disabled', false).text('<?php _e('Generate Final PDF', 'klage-click-doc-out'); ?>');
                            } else {
                                alert('PDF Error: ' + pdfResponse.data);
                                button.prop('disabled', false).text('<?php _e('Generate Final PDF', 'klage-click-doc-out'); ?>');
                            }
                        });
                    } else {
                        alert('Save Error: ' + saveResponse.data);
                        button.prop('disabled', false).text('<?php _e('Generate Final PDF', 'klage-click-doc-out'); ?>');
                    }
                });
            });
            
            // Auto-save every 2 minutes
            setInterval(function() {
                $('#save-draft').trigger('click');
            }, 120000);
        });
        </script>
        <?php
    }
}