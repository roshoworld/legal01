<?php
/**
 * Admin Interface for Document Analysis Plugin
 * Handles admin dashboard, menus, and user interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Document_in_Admin {
    
    private $db_manager;
    private $case_matcher;
    
    public function __construct() {
        error_log('CAH Doc-In: Admin class constructor called');
        
        $this->db_manager = new CAH_Document_in_DB_Manager();
        $this->case_matcher = new CAH_Document_in_Case_Matcher();
        
        // Always add admin menu - integration mode will be checked later
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        add_action('admin_init', array($this, 'admin_init'));
        
        // Handle CSV export before any output
        add_action('admin_init', array($this, 'handle_csv_export_request'));
        
        add_action('wp_ajax_assign_communication', array($this, 'ajax_assign_communication'));
        add_action('wp_ajax_create_case_from_communication', array($this, 'ajax_create_case_from_communication'));
        add_action('wp_ajax_bulk_assign_communications', array($this, 'ajax_bulk_assign_communications'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add custom columns to communications list
        add_filter('manage_cah_communication_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_cah_communication_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        
        error_log('CAH Doc-In: Admin class constructor completed');
    }
    
    /**
     * Check if running in integrated mode with core plugin
     */
    private function is_integrated_mode() {
        return class_exists('CAH_DocIn_Integration');
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Debug: Log menu creation
        error_log('CAH Doc-In: Adding admin menu');
        
        // Always register the menu items - integration should be handled by core plugin
        error_log('CAH Doc-In: Registering admin menu items');
        
        // Main menu page
        add_menu_page(
            __('Document Analysis', CAH_DOC_IN_TEXT_DOMAIN),
            __('Doc Analysis', CAH_DOC_IN_TEXT_DOMAIN),
            'manage_options',
            'cah-doc-in',
            array($this, 'dashboard_page'),
            'dashicons-media-text',
            26
        );
        
        // Submenu pages
        add_submenu_page(
            'cah-doc-in',
            __('Dashboard', CAH_DOC_IN_TEXT_DOMAIN),
            __('Dashboard', CAH_DOC_IN_TEXT_DOMAIN),
            'manage_options',
            'cah-doc-in',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'cah-doc-in',
            __('Unassigned Communications', CAH_DOC_IN_TEXT_DOMAIN),
            __('Unassigned', CAH_DOC_IN_TEXT_DOMAIN),
            'manage_options',
            'cah-doc-in-unassigned',
            array($this, 'unassigned_page')
        );
        
        add_submenu_page(
            'cah-doc-in',
            __('All Communications', CAH_DOC_IN_TEXT_DOMAIN),
            __('All Communications', CAH_DOC_IN_TEXT_DOMAIN),
            'manage_options',
            'edit.php?post_type=cah_communication'
        );
        
        $categories_hook = add_submenu_page(
            'cah-doc-in',
            __('Categories', CAH_DOC_IN_TEXT_DOMAIN),
            __('Categories', CAH_DOC_IN_TEXT_DOMAIN),
            'manage_options',
            'cah-doc-in-categories',
            array($this, 'categories_page')
        );
        
        // Debug: Log categories page hook
        error_log('CAH Doc-In: Categories page hook: ' . $categories_hook);
        
        add_submenu_page(
            'cah-doc-in',
            __('Settings', CAH_DOC_IN_TEXT_DOMAIN),
            __('Settings', CAH_DOC_IN_TEXT_DOMAIN),
            'manage_options',
            'cah-doc-in-settings',
            array($this, 'settings_page')
        );
        
        // Debug: Log all menu items added
        error_log('CAH Doc-In: All menu items added successfully');
    }
    
    /**
     * Handle CSV export request during admin_init
     */
    public function handle_csv_export_request() {
        // Check if this is a CSV export request
        if (isset($_POST['export_csv']) && 
            isset($_POST['_wpnonce_export_csv']) && 
            wp_verify_nonce($_POST['_wpnonce_export_csv'], 'export_csv') &&
            current_user_can('manage_options')) {
            
            error_log('CAH Doc-In: CSV export request detected');
            $this->handle_csv_export();
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('cah_doc_in_settings', 'cah_doc_in_options');
        
        // Add settings sections and fields
        add_settings_section(
            'cah_doc_in_pipedream_section',
            __('Pipedream Integration', CAH_DOC_IN_TEXT_DOMAIN),
            array($this, 'pipedream_section_callback'),
            'cah_doc_in_settings'
        );
        
        add_settings_field(
            'pipedream_webhook_url',
            __('Pipedream Webhook URL', CAH_DOC_IN_TEXT_DOMAIN),
            array($this, 'webhook_url_callback'),
            'cah_doc_in_settings',
            'cah_doc_in_pipedream_section'
        );
        
        add_settings_field(
            'auto_assign_threshold',
            __('Auto-Assignment Confidence Threshold (%)', CAH_DOC_IN_TEXT_DOMAIN),
            array($this, 'auto_assign_threshold_callback'),
            'cah_doc_in_settings',
            'cah_doc_in_pipedream_section'
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Note: Integration mode detection removed to allow standalone access
        error_log('CAH Doc-In: Dashboard page - showing full functionality');
        
        $stats = $this->get_dashboard_statistics();
        ?>
        <div class="wrap">
            <h1><?php _e('Document Analysis Dashboard', CAH_DOC_IN_TEXT_DOMAIN); ?></h1>
            
            <div class="cah-doc-in-dashboard">
                <div class="dashboard-widgets-wrap">
                    <div id="dashboard-widgets" class="metabox-holder">
                        
                        <!-- Statistics Cards -->
                        <div class="postbox-container" style="width: 100%;">
                            <div class="meta-box-sortables ui-sortable">
                                
                                <!-- Statistics Overview -->
                                <div class="postbox">
                                    <div class="postbox-header">
                                        <h2><?php _e('Communication Statistics (Last 30 Days)', CAH_DOC_IN_TEXT_DOMAIN); ?></h2>
                                    </div>
                                    <div class="inside">
                                        <div class="main">
                                            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($stats->total_communications ?? 0); ?></h3>
                                                    <p><?php _e('Total Communications', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                                </div>
                                                <div class="stat-box">
                                                    <h3 style="color: green;"><?php echo number_format($stats->auto_assigned ?? 0); ?></h3>
                                                    <p><?php _e('Auto-Assigned', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                                </div>
                                                <div class="stat-box">
                                                    <h3 style="color: orange;"><?php echo number_format($stats->unassigned ?? 0); ?></h3>
                                                    <p><?php _e('Unassigned', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                                </div>
                                                <div class="stat-box">
                                                    <h3 style="color: blue;"><?php echo number_format($stats->new_cases ?? 0); ?></h3>
                                                    <p><?php _e('New Cases Created', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quick Actions -->
                                <div class="postbox">
                                    <div class="postbox-header">
                                        <h2><?php _e('Quick Actions', CAH_DOC_IN_TEXT_DOMAIN); ?></h2>
                                    </div>
                                    <div class="inside">
                                        <div class="main">
                                            <p>
                                                <a href="<?php echo admin_url('admin.php?page=cah-doc-in-unassigned'); ?>" class="button button-primary">
                                                    <?php _e('Review Unassigned Communications', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                                    <?php if ($stats->unassigned > 0): ?>
                                                        <span class="count">(<?php echo $stats->unassigned; ?>)</span>
                                                    <?php endif; ?>
                                                </a>
                                                <a href="<?php echo admin_url('edit.php?post_type=cah_communication'); ?>" class="button">
                                                    <?php _e('View All Communications', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                                </a>
                                                <a href="<?php echo admin_url('admin.php?page=cah-doc-in-categories'); ?>" class="button">
                                                    <?php _e('Manage Categories', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Recent Communications -->
                                <div class="postbox">
                                    <div class="postbox-header">
                                        <h2><?php _e('Recent Communications', CAH_DOC_IN_TEXT_DOMAIN); ?></h2>
                                    </div>
                                    <div class="inside">
                                        <div class="main">
                                            <?php $this->display_recent_communications(); ?>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .stats-grid .stat-box {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
        }
        .stats-grid .stat-box h3 {
            font-size: 2em;
            margin: 0 0 10px 0;
        }
        .stats-grid .stat-box p {
            margin: 0;
            color: #646970;
        }
        .count {
            background: #d63638;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Unassigned communications page
     */
    public function unassigned_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Note: Integration mode detection removed to allow standalone access
        error_log('CAH Doc-In: Unassigned page - showing full functionality');
        
        $unassigned = $this->db_manager->get_unassigned_communications();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Unassigned Communications', CAH_DOC_IN_TEXT_DOMAIN); ?></h1>
            
            <?php if (empty($unassigned)): ?>
                <div class="notice notice-success">
                    <p><?php _e('Great! No unassigned communications at the moment.', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                </div>
            <?php else: ?>
                <form id="unassigned-communications-form" method="post">
                    <?php wp_nonce_field('bulk_assign_communications', 'bulk_assign_nonce'); ?>
                    
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select name="bulk_action" id="bulk-action-selector">
                                <option value=""><?php _e('Bulk Actions', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                                <option value="assign"><?php _e('Assign to Case', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                                <option value="create_case"><?php _e('Create New Case', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                            </select>
                            <input type="submit" class="button action" value="<?php _e('Apply', CAH_DOC_IN_TEXT_DOMAIN); ?>">
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all">
                                </td>
                                <th><?php _e('Date', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Sender', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Subject', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Case Number', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Debtor Name', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Category', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Actions', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unassigned as $communication): ?>
                                <tr>
                                    <td class="check-column">
                                        <input type="checkbox" name="communications[]" value="<?php echo $communication->id; ?>">
                                    </td>
                                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($communication->created_at))); ?></td>
                                    <td><?php echo esc_html($communication->email_sender); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($communication->email_subject); ?></strong>
                                        <?php if ($communication->has_attachment): ?>
                                            <span class="dashicons dashicons-paperclip" title="<?php _e('Has Attachment', CAH_DOC_IN_TEXT_DOMAIN); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($communication->case_number ?: '-'); ?></td>
                                    <td><?php echo esc_html($communication->debtor_name ?: '-'); ?></td>
                                    <td><span class="category-tag"><?php echo esc_html($communication->category); ?></span></td>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $communication->post_id . '&action=edit'); ?>" class="button button-small">
                                            <?php _e('Edit', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                        </a>
                                        <button type="button" class="button button-small assign-btn" data-id="<?php echo $communication->id; ?>">
                                            <?php _e('Assign', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Assignment Modal -->
        <div id="assignment-modal" style="display: none;">
            <div class="assignment-modal-content">
                <h3><?php _e('Assign Communication to Case', CAH_DOC_IN_TEXT_DOMAIN); ?></h3>
                <form id="assignment-form">
                    <input type="hidden" id="communication-id" name="communication_id" value="">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Select Case', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                            <td>
                                <select id="case-select" name="case_id" style="width: 400px;">
                                    <option value=""><?php _e('Select a case...', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                                    <?php $this->render_cases_dropdown(); ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary"><?php _e('Assign', CAH_DOC_IN_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button" id="cancel-assignment"><?php _e('Cancel', CAH_DOC_IN_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button" id="create-new-case"><?php _e('Create New Case Instead', CAH_DOC_IN_TEXT_DOMAIN); ?></button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .category-tag {
            background: #f0f0f1;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        #assignment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
        }
        .assignment-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.assign-btn').click(function() {
                var communicationId = $(this).data('id');
                $('#communication-id').val(communicationId);
                $('#assignment-modal').show();
            });
            
            $('#cancel-assignment').click(function() {
                $('#assignment-modal').hide();
            });
            
            $('#assignment-form').submit(function(e) {
                e.preventDefault();
                // Handle assignment via AJAX
                var data = {
                    action: 'assign_communication',
                    nonce: '<?php echo wp_create_nonce("assign_communication"); ?>',
                    communication_id: $('#communication-id').val(),
                    case_id: $('#case-select').val()
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Categories management page
     */
    public function categories_page() {
        // Debug: Check if we can access this page
        error_log('CAH Doc-In: Categories page accessed');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log('CAH Doc-In: Categories page - insufficient permissions');
            wp_die(__('You do not have sufficient permissions to access this page.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Note: Integration mode detection removed to allow standalone access
        // The integration should be handled by the core plugin if it's working properly
        error_log('CAH Doc-In: Categories page - showing full functionality');
        
        try {
            // Process form submissions
            if (isset($_POST['add_category']) && wp_verify_nonce($_POST['_wpnonce_add-tag'], 'add-tag')) {
                $category_name = sanitize_text_field($_POST['tag-name']);
                $category_description = sanitize_textarea_field($_POST['tag-description']);
                
                if (!empty($category_name)) {
                    $result = wp_insert_term($category_name, 'communication_category', array(
                        'description' => $category_description
                    ));
                    
                    if (!is_wp_error($result)) {
                        echo '<div class="notice notice-success"><p>' . 
                             sprintf(__('Category "%s" added successfully.', CAH_DOC_IN_TEXT_DOMAIN), $category_name) . 
                             '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' . 
                             sprintf(__('Error adding category: %s', CAH_DOC_IN_TEXT_DOMAIN), $result->get_error_message()) . 
                             '</p></div>';
                    }
                }
            }
            
            // Handle CSV import
            if (isset($_POST['import_csv']) && wp_verify_nonce($_POST['_wpnonce_import_csv'], 'import_csv')) {
                $this->handle_csv_import();
            }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Communication Categories', CAH_DOC_IN_TEXT_DOMAIN); ?></h1>
            <p><?php _e('Manage GDPR SPAM categories used by the AI to classify incoming communications. These categories are sent to Pipedream on each workflow run with German descriptions for better AI training.', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
            
            <div class="category-management">
                <div id="col-container" class="wp-clearfix">
                    <div id="col-left">
                        <div class="col-wrap">
                            <div class="form-wrap">
                                <h2><?php _e('Add New Category', CAH_DOC_IN_TEXT_DOMAIN); ?></h2>
                                <form method="post" action="">
                                    <?php wp_nonce_field('add-tag', '_wpnonce_add-tag'); ?>
                                    
                                    <div class="form-field form-required term-name-wrap">
                                        <label for="tag-name"><?php _e('Category Name (English)', CAH_DOC_IN_TEXT_DOMAIN); ?></label>
                                        <input name="tag-name" id="tag-name" type="text" value="" size="40" aria-required="true" />
                                        <p><?php _e('The technical name of the category as it will appear in Pipedream (e.g., "consent_express_claimed").', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                    </div>
                                    
                                    <div class="form-field term-description-wrap">
                                        <label for="tag-description"><?php _e('German Description', CAH_DOC_IN_TEXT_DOMAIN); ?></label>
                                        <textarea name="tag-description" id="tag-description" rows="5" cols="50"></textarea>
                                        <p><?php _e('Detailed German description for the AI training and admin interface (e.g., "Behauptung der ausdrücklichen Einwilligung...").', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                    </div>
                                    
                                    <input type="submit" name="add_category" class="button button-primary" value="<?php _e('Add New Category', CAH_DOC_IN_TEXT_DOMAIN); ?>" />
                                </form>
                            </div>
                            
                            <!-- CSV Import/Export -->
                            <div class="form-wrap" style="margin-top: 30px;">
                                <h2><?php _e('CSV Import/Export', CAH_DOC_IN_TEXT_DOMAIN); ?></h2>
                                
                                <!-- CSV Export -->
                                <div class="csv-export" style="margin-bottom: 20px;">
                                    <h3><?php _e('Export Categories', CAH_DOC_IN_TEXT_DOMAIN); ?></h3>
                                    <p><?php _e('Download all categories as CSV file for external editing.', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                    <form method="post" action="">
                                        <?php wp_nonce_field('export_csv', '_wpnonce_export_csv'); ?>
                                        <input type="submit" name="export_csv" class="button button-secondary" value="<?php _e('Export Categories as CSV', CAH_DOC_IN_TEXT_DOMAIN); ?>" />
                                    </form>
                                </div>
                                
                                <!-- CSV Import -->
                                <div class="csv-import">
                                    <h3><?php _e('Import Categories', CAH_DOC_IN_TEXT_DOMAIN); ?></h3>
                                    <p><?php _e('Upload a CSV file to bulk import categories. Format: name,description_en,description_de,slug', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                    <form method="post" action="" enctype="multipart/form-data">
                                        <?php wp_nonce_field('import_csv', '_wpnonce_import_csv'); ?>
                                        
                                        <div class="form-field">
                                            <label for="csv-file"><?php _e('CSV File', CAH_DOC_IN_TEXT_DOMAIN); ?></label>
                                            <input type="file" name="csv_file" id="csv-file" accept=".csv" required />
                                            <p><?php _e('Select a CSV file with categories to import.', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                        </div>
                                        
                                        <div class="form-field">
                                            <label>
                                                <input type="checkbox" name="overwrite_existing" value="1" />
                                                <?php _e('Overwrite existing categories', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                            </label>
                                            <p><?php _e('If checked, existing categories with the same name will be updated.', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                        </div>
                                        
                                        <input type="submit" name="import_csv" class="button button-primary" value="<?php _e('Import Categories from CSV', CAH_DOC_IN_TEXT_DOMAIN); ?>" />
                                    </form>
                                </div>
                                
                                <!-- CSV Format Help -->
                                <div class="csv-format-help" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                                    <h4><?php _e('CSV Format', CAH_DOC_IN_TEXT_DOMAIN); ?></h4>
                                    <p><?php _e('Your CSV file should have the following columns:', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                    <ul>
                                        <li><strong>name</strong> - <?php _e('Category name (English)', CAH_DOC_IN_TEXT_DOMAIN); ?></li>
                                        <li><strong>description_en</strong> - <?php _e('English description', CAH_DOC_IN_TEXT_DOMAIN); ?></li>
                                        <li><strong>description_de</strong> - <?php _e('German description', CAH_DOC_IN_TEXT_DOMAIN); ?></li>
                                        <li><strong>slug</strong> - <?php _e('URL slug (optional, will be generated from name)', CAH_DOC_IN_TEXT_DOMAIN); ?></li>
                                    </ul>
                                    <p><strong><?php _e('Example:', CAH_DOC_IN_TEXT_DOMAIN); ?></strong></p>
                                    <code>name,description_en,description_de,slug<br>
                                    "Express Consent Claimed","Express Consent Claimed","Behauptung der ausdrücklichen Einwilligung","consent_express_claimed"</code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="col-right">
                        <div class="col-wrap">
                            <div class="current-categories">
                                <h2><?php _e('Current GDPR SPAM Categories', CAH_DOC_IN_TEXT_DOMAIN); ?></h2>
                                <?php
                                $terms = get_terms(array(
                                    'taxonomy' => 'communication_category',
                                    'hide_empty' => false,
                                    'orderby' => 'name'
                                ));
                                
                                if ($terms && !is_wp_error($terms)):
                                ?>
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th style="width: 25%;"><?php _e('Technical Name', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                                <th style="width: 50%;"><?php _e('German Description', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                                <th style="width: 10%;"><?php _e('Count', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                                <th style="width: 15%;"><?php _e('Actions', CAH_DOC_IN_TEXT_DOMAIN); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($terms as $term): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo esc_html($term->name); ?></strong>
                                                        <br><small><code><?php echo esc_html($term->slug); ?></code></small>
                                                    </td>
                                                    <td>
                                                        <div class="category-description">
                                                            <?php 
                                                            $description = !empty($term->description) ? $term->description : $term->name;
                                                            echo '<span class="description-text">' . esc_html(wp_trim_words($description, 20)) . '</span>';
                                                            if (strlen($description) > 100) {
                                                                echo '<br><small><a href="#" class="show-full-description">' . __('Show full description', CAH_DOC_IN_TEXT_DOMAIN) . '</a></small>';
                                                                echo '<div class="full-description" style="display:none; margin-top:5px; padding:10px; background:#f9f9f9; border-left:4px solid #0073aa;">' . esc_html($description) . '</div>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="count"><?php echo $term->count; ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo get_edit_term_link($term->term_id, 'communication_category', 'cah_communication'); ?>" class="button button-small">
                                                            <?php _e('Edit', CAH_DOC_IN_TEXT_DOMAIN); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="notice notice-warning">
                                        <p><?php _e('No categories found. Click "Reset to GDPR Defaults" to load the predefined GDPR SPAM categories.', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 20px;">
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('reset_categories', '_wpnonce_reset_categories'); ?>
                                        <input type="submit" name="reset_to_gdpr_defaults" class="button button-secondary" 
                                               value="<?php _e('Reset to GDPR Defaults', CAH_DOC_IN_TEXT_DOMAIN); ?>" 
                                               onclick="return confirm('<?php _e('This will reset all categories to the default GDPR SPAM categories. Continue?', CAH_DOC_IN_TEXT_DOMAIN); ?>')" />
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="category-api-info" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                    <h3><?php _e('API Information for Pipedream Integration', CAH_DOC_IN_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Pipedream fetches categories with German descriptions from:', CAH_DOC_IN_TEXT_DOMAIN); ?></p>
                    <p><strong>Enhanced API Endpoint:</strong></p>
                    <code style="background: white; padding: 5px; display: block; margin: 10px 0;"><?php echo home_url('/wp-json/cah-doc-in/v1/categories'); ?></code>
                    
                    <p><strong><?php _e('API Response Format:', CAH_DOC_IN_TEXT_DOMAIN); ?></strong></p>
                    <pre style="background: white; padding: 10px; font-size: 11px; overflow-x: auto;">{
  "success": true,
  "categories": ["Express Consent Claimed", "..."], // Simple format for backwards compatibility
  "categories_detailed": [
    {
      "slug": "consent_express_claimed",
      "name": "Express Consent Claimed", 
      "description_en": "Express Consent Claimed",
      "description_de": "Behauptung der ausdrücklichen Einwilligung...",
      "count": 5
    }
  ],
  "api_version": "1.1.0"
}</pre>
                    
                    <p><a href="<?php echo home_url('/wp-json/cah-doc-in/v1/categories'); ?>" target="_blank" class="button button-primary">
                        <?php _e('Test Enhanced API Endpoint', CAH_DOC_IN_TEXT_DOMAIN); ?>
                    </a></p>
                </div>
            </div>
        </div>
        
        <style>
        .category-description .show-full-description {
            color: #0073aa;
            text-decoration: none;
        }
        .category-description .show-full-description:hover {
            text-decoration: underline;
        }
        .count {
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .wp-list-table th {
            vertical-align: top;
        }
        .wp-list-table td {
            vertical-align: top;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.show-full-description').click(function(e) {
                e.preventDefault();
                var $this = $(this);
                var $fullDesc = $this.closest('.category-description').find('.full-description');
                
                if ($fullDesc.is(':visible')) {
                    $fullDesc.hide();
                    $this.text('<?php echo esc_js(__('Show full description', CAH_DOC_IN_TEXT_DOMAIN)); ?>');
                } else {
                    $fullDesc.show();
                    $this.text('<?php echo esc_js(__('Hide description', CAH_DOC_IN_TEXT_DOMAIN)); ?>');
                }
            });
        });
        </script>
        <?php
        
        // Handle reset to defaults
        if (isset($_POST['reset_to_gdpr_defaults']) && wp_verify_nonce($_POST['_wpnonce_reset_categories'], 'reset_categories')) {
            $this->reset_to_gdpr_defaults();
            echo '<div class="notice notice-success"><p>' . __('Categories reset to GDPR defaults successfully.', CAH_DOC_IN_TEXT_DOMAIN) . '</p></div>';
            echo '<script>location.reload();</script>';
        }
        
        } catch (Exception $e) {
            error_log('CAH Doc-In: Categories page error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>' . sprintf(__('An error occurred: %s', CAH_DOC_IN_TEXT_DOMAIN), $e->getMessage()) . '</p></div>';
        }
    }
    
    /**
     * Handle CSV export
     */
    private function handle_csv_export() {
        // Get all categories
        $terms = get_terms(array(
            'taxonomy' => 'communication_category',
            'hide_empty' => false,
            'orderby' => 'name'
        ));
        
        if (empty($terms) || is_wp_error($terms)) {
            wp_die(__('No categories found to export.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Clean any output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for CSV download
        $filename = 'categories_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        header('Pragma: public');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (helps with Excel compatibility)
        fputs($output, "\xEF\xBB\xBF");
        
        // Add CSV header
        fputcsv($output, array('name', 'description_en', 'description_de', 'slug'));
        
        // Add category data
        foreach ($terms as $term) {
            fputcsv($output, array(
                $term->name,
                $term->name, // English description (same as name for simplicity)
                $term->description,
                $term->slug
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        if (empty($_FILES['csv_file']['tmp_name'])) {
            echo '<div class="notice notice-error"><p>' . __('No file uploaded.', CAH_DOC_IN_TEXT_DOMAIN) . '</p></div>';
            return;
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $overwrite = isset($_POST['overwrite_existing']) && $_POST['overwrite_existing'] === '1';
        
        // Validate file type
        $file_info = pathinfo($_FILES['csv_file']['name']);
        if (strtolower($file_info['extension']) !== 'csv') {
            echo '<div class="notice notice-error"><p>' . __('Please upload a CSV file.', CAH_DOC_IN_TEXT_DOMAIN) . '</p></div>';
            return;
        }
        
        // Read CSV file
        $csv_data = array();
        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle);
            
            // Validate CSV header
            $required_columns = array('name', 'description_en', 'description_de');
            $missing_columns = array_diff($required_columns, $header);
            
            if (!empty($missing_columns)) {
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(__('Missing required columns: %s', CAH_DOC_IN_TEXT_DOMAIN), implode(', ', $missing_columns)) . 
                     '</p></div>';
                fclose($handle);
                return;
            }
            
            // Read data rows
            while (($data = fgetcsv($handle)) !== false) {
                $csv_data[] = array_combine($header, $data);
            }
            fclose($handle);
        } else {
            echo '<div class="notice notice-error"><p>' . __('Could not read CSV file.', CAH_DOC_IN_TEXT_DOMAIN) . '</p></div>';
            return;
        }
        
        if (empty($csv_data)) {
            echo '<div class="notice notice-error"><p>' . __('No data found in CSV file.', CAH_DOC_IN_TEXT_DOMAIN) . '</p></div>';
            return;
        }
        
        // Process CSV data
        $imported = 0;
        $updated = 0;
        $errors = array();
        
        foreach ($csv_data as $row) {
            $name = sanitize_text_field($row['name']);
            $description_de = sanitize_textarea_field($row['description_de']);
            $slug = !empty($row['slug']) ? sanitize_title($row['slug']) : sanitize_title($name);
            
            if (empty($name)) {
                continue;
            }
            
            // Check if category exists
            $existing_term = get_term_by('slug', $slug, 'communication_category');
            
            if ($existing_term) {
                if ($overwrite) {
                    // Update existing category
                    $result = wp_update_term($existing_term->term_id, 'communication_category', array(
                        'name' => $name,
                        'description' => $description_de,
                        'slug' => $slug
                    ));
                    
                    if (!is_wp_error($result)) {
                        $updated++;
                    } else {
                        $errors[] = sprintf(__('Error updating category "%s": %s', CAH_DOC_IN_TEXT_DOMAIN), $name, $result->get_error_message());
                    }
                } else {
                    $errors[] = sprintf(__('Category "%s" already exists (use overwrite option to update)', CAH_DOC_IN_TEXT_DOMAIN), $name);
                }
            } else {
                // Create new category
                $result = wp_insert_term($name, 'communication_category', array(
                    'description' => $description_de,
                    'slug' => $slug
                ));
                
                if (!is_wp_error($result)) {
                    $imported++;
                } else {
                    $errors[] = sprintf(__('Error creating category "%s": %s', CAH_DOC_IN_TEXT_DOMAIN), $name, $result->get_error_message());
                }
            }
        }
        
        // Show results
        if ($imported > 0) {
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('%d categories imported successfully.', CAH_DOC_IN_TEXT_DOMAIN), $imported) . 
                 '</p></div>';
        }
        
        if ($updated > 0) {
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('%d categories updated successfully.', CAH_DOC_IN_TEXT_DOMAIN), $updated) . 
                 '</p></div>';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p>' . 
                 __('Some errors occurred:', CAH_DOC_IN_TEXT_DOMAIN) . '<br>' . 
                 implode('<br>', $errors) . 
                 '</p></div>';
        }
        
        // Update stored category descriptions for API usage
        $this->update_category_descriptions_cache();
    }
    
    /**
     * Update category descriptions cache for API usage
     */
    private function update_category_descriptions_cache() {
        $terms = get_terms(array(
            'taxonomy' => 'communication_category',
            'hide_empty' => false
        ));
        
        $category_descriptions = array();
        
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $category_descriptions[$term->slug] = array(
                    'name' => $term->name,
                    'description_de' => $term->description
                );
            }
        }
        
        update_option('cah_doc_in_category_descriptions', $category_descriptions);
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Note: Integration mode detection removed to allow standalone access
        error_log('CAH Doc-In: Settings page - showing full functionality');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Document Analysis Settings', CAH_DOC_IN_TEXT_DOMAIN); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cah_doc_in_settings');
                do_settings_sections('cah_doc_in_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Settings section callback
     */
    public function pipedream_section_callback() {
        echo '<p>' . __('Configure Pipedream integration settings.', CAH_DOC_IN_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Webhook URL field callback
     */
    public function webhook_url_callback() {
        $options = get_option('cah_doc_in_options');
        $value = $options['pipedream_webhook_url'] ?? '';
        echo '<input type="url" name="cah_doc_in_options[pipedream_webhook_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('URL for sending notifications back to Pipedream (optional).', CAH_DOC_IN_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Auto-assign threshold callback
     */
    public function auto_assign_threshold_callback() {
        $options = get_option('cah_doc_in_options');
        $value = $options['auto_assign_threshold'] ?? 85;
        echo '<input type="number" name="cah_doc_in_options[auto_assign_threshold]" value="' . esc_attr($value) . '" min="50" max="100" />';
        echo '<p class="description">' . __('Minimum confidence score for automatic case assignment (50-100%).', CAH_DOC_IN_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        return $this->case_matcher->get_matching_statistics(30);
    }
    
    /**
     * Display recent communications
     */
    private function display_recent_communications() {
        $recent = $this->db_manager->get_unassigned_communications(5);
        
        if (empty($recent)) {
            echo '<p>' . __('No recent communications.', CAH_DOC_IN_TEXT_DOMAIN) . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat">';
        echo '<thead><tr><th>' . __('Date', CAH_DOC_IN_TEXT_DOMAIN) . '</th><th>' . __('Sender', CAH_DOC_IN_TEXT_DOMAIN) . '</th><th>' . __('Category', CAH_DOC_IN_TEXT_DOMAIN) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($recent as $communication) {
            echo '<tr>';
            echo '<td>' . esc_html(date('M j, Y H:i', strtotime($communication->created_at))) . '</td>';
            echo '<td>' . esc_html($communication->email_sender) . '</td>';
            echo '<td>' . esc_html($communication->category) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    
    /**
     * Render cases dropdown for assignment
     */
    private function render_cases_dropdown() {
        global $wpdb;
        $cases_table = $wpdb->prefix . 'klage_cases';
        $debtors_table = $wpdb->prefix . 'klage_debtors';
        
        $cases = $wpdb->get_results("
            SELECT c.case_id, c.case_number, d.debtors_name, d.debtors_company 
            FROM $cases_table c 
            LEFT JOIN $debtors_table d ON c.debtor_id = d.id 
            ORDER BY c.case_creation_date DESC 
            LIMIT 100
        ");
        
        foreach ($cases as $case) {
            $debtor_display = !empty($case->debtors_company) ? $case->debtors_company : $case->debtors_name;
            echo '<option value="' . esc_attr($case->case_id) . '">' . 
                 esc_html($case->case_number . ' - ' . $debtor_display) . '</option>';
        }
    }
    
    /**
     * Add custom columns to communications list
     */
    public function add_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['case_number'] = __('Case Number', CAH_DOC_IN_TEXT_DOMAIN);
        $new_columns['debtor_name'] = __('Debtor', CAH_DOC_IN_TEXT_DOMAIN);
        $new_columns['category'] = __('Category', CAH_DOC_IN_TEXT_DOMAIN);
        $new_columns['assignment_status'] = __('Status', CAH_DOC_IN_TEXT_DOMAIN);
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'case_number':
                echo esc_html(get_post_meta($post_id, '_case_number', true) ?: '-');
                break;
            case 'debtor_name':
                echo esc_html(get_post_meta($post_id, '_debtor_name', true) ?: '-');
                break;
            case 'category':
                echo esc_html(get_post_meta($post_id, '_category', true) ?: '-');
                break;
            case 'assignment_status':
                $status = get_post_meta($post_id, '_assignment_status', true);
                $class = $status === 'assigned' ? 'success' : ($status === 'unassigned' ? 'warning' : 'info');
                echo '<span class="status-' . $class . '">' . esc_html(ucfirst(str_replace('_', ' ', $status))) . '</span>';
                break;
        }
    }
    
    /**
     * AJAX handler for assigning communication to case
     */
    public function ajax_assign_communication() {
        check_ajax_referer('assign_communication', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        $communication_id = intval($_POST['communication_id']);
        $case_id = intval($_POST['case_id']);
        
        if (empty($communication_id) || empty($case_id)) {
            wp_send_json_error(__('Missing required parameters.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Update database record
        $result = $this->db_manager->update_communication($communication_id, array(
            'matched_case_id' => $case_id,
            'assignment_status' => 'assigned',
            'match_confidence' => 100, // Manual assignment gets 100% confidence
            'processed_by_user_id' => get_current_user_id(),
            'processed_at' => current_time('mysql')
        ));
        
        if ($result !== false) {
            // Update post meta as well
            $communication = $this->db_manager->get_communication($communication_id);
            if ($communication) {
                update_post_meta($communication->post_id, '_matched_case_id', $case_id);
                update_post_meta($communication->post_id, '_assignment_status', 'assigned');
                update_post_meta($communication->post_id, '_match_confidence', 100);
            }
            
            // Log audit
            $this->db_manager->log_audit(array(
                'communication_id' => $communication_id,
                'action_type' => 'manual_assignment',
                'action_details' => 'Communication manually assigned to case ID: ' . $case_id
            ));
            
            wp_send_json_success(__('Communication assigned successfully.', CAH_DOC_IN_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to assign communication.', CAH_DOC_IN_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX handler for creating case from communication
     */
    public function ajax_create_case_from_communication() {
        check_ajax_referer('create_case_from_communication', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        $communication_id = intval($_POST['communication_id']);
        
        if (empty($communication_id)) {
            wp_send_json_error(__('Missing required parameters.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Get communication details
        $communication = $this->db_manager->get_communication($communication_id);
        if (!$communication) {
            wp_send_json_error(__('Communication not found.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Here you would implement case creation logic
        // For now, we'll just mark as new case created
        $result = $this->db_manager->update_communication($communication_id, array(
            'assignment_status' => 'new_case_created',
            'processed_by_user_id' => get_current_user_id(),
            'processed_at' => current_time('mysql')
        ));
        
        if ($result !== false) {
            // Update post meta as well
            update_post_meta($communication->post_id, '_assignment_status', 'new_case_created');
            
            // Log audit
            $this->db_manager->log_audit(array(
                'communication_id' => $communication_id,
                'action_type' => 'new_case_created',
                'action_details' => 'New case created from communication: ' . $communication->email_sender
            ));
            
            wp_send_json_success(__('New case created successfully.', CAH_DOC_IN_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to create new case.', CAH_DOC_IN_TEXT_DOMAIN));
        }
    }
    
    /**
     * Reset categories to GDPR defaults
     */
    private function reset_to_gdpr_defaults() {
        // Get all existing terms
        $existing_terms = get_terms(array(
            'taxonomy' => 'communication_category',
            'hide_empty' => false
        ));
        
        // Delete existing terms
        foreach ($existing_terms as $term) {
            wp_delete_term($term->term_id, 'communication_category');
        }
        
        // Create GDPR default categories
        $default_categories = array(
            // Consent-related categories
            'consent_express_claimed' => array(
                'name' => 'Express Consent Claimed',
                'description_de' => 'Behauptung der ausdrücklichen Einwilligung: Der mutmaßliche Spammer behauptet, der Empfänger (Ihr Mandant) habe zuvor eine ausdrückliche Einwilligung erteilt (z.B. Double Opt-in, Ankreuzfeld).'
            ),
            'consent_existing_business_relationship' => array(
                'name' => 'Existing Business Relationship',
                'description_de' => 'Bestehende Geschäftsbeziehung (§ 7 Abs. 3 UWG): Der mutmaßliche Spammer behauptet, es habe eine vorherige Geschäftsbeziehung bestanden, die Direktmarketing unter spezifischen Bedingungen erlaubt (§ 7 Abs. 3 UWG).'
            ),
            'consent_withdrawn_before_sending' => array(
                'name' => 'Consent Withdrawn Before Sending',
                'description_de' => 'Einwilligung vor Versand widerrufen: Der mutmaßliche Spammer behauptet, die Einwilligung sei bereits vor dem Versand der beanstandeten E-Mail widerrufen worden.'
            ),
            'consent_checkbox_preticked' => array(
                'name' => 'Checkbox Consent Claimed',
                'description_de' => 'Der mutmaßliche Spammer behauptet, unser Mandant habe durch Ankreuzen einer Checkbox seine Einwilligung erteilt.'
            ),
            
            // Technical categories
            'technical_email_not_sent_by_them' => array(
                'name' => 'Email Not Sent By Them',
                'description_de' => 'E-Mail nicht von ihnen gesendet: Der mutmaßliche Spammer bestreitet den Versand der E-Mail und behauptet, diese sei gefälscht, ein technischer Fehler oder ohne deren Genehmigung von einem Dritten versendet worden.'
            ),
            'technical_delivery_issue' => array(
                'name' => 'Technical Delivery Issue',
                'description_de' => 'Technisches Zustellungsproblem: Der mutmaßliche Spammer behauptet, die E-Mail sei niemals tatsächlich beim Empfänger zugestellt worden (z.B. Bounce-Meldungen, Fehlermeldungen vom Mailserver).'
            ),
            'technical_proof_of_consent_requested' => array(
                'name' => 'Proof of Consent Requested',
                'description_de' => 'Nachweis der Einwilligung angefordert: Der mutmaßliche Spammer verlangt einen dokumentierten Nachweis der Einwilligung vom Empfänger.'
            ),
            'technical_not_commercial_no_marketing' => array(
                'name' => 'Not Commercial / No Marketing',
                'description_de' => 'Nicht kommerziell / Kein Marketing: Der mutmaßliche Spammer argumentiert, die E-Mail sei rein informativer, transaktionaler Natur oder stelle kein "Marketing" dar.'
            ),
            
            // Legal categories
            'legal_no_gdpr_violation_claimed' => array(
                'name' => 'No GDPR Violation Claimed',
                'description_de' => 'Keine DSGVO-Verletzung geltend gemacht: Der mutmaßliche Spammer argumentiert, es sei keine DSGVO-Verletzung erfolgt (z.B. berechtigtes Interesse nach Art. 6 Abs. 1 lit. f DSGVO, Notwendigkeit zur Vertragserfüllung).'
            ),
            'legal_no_uwg_violation_claimed' => array(
                'name' => 'No UWG Violation Claimed',
                'description_de' => 'Keine UWG-Verletzung geltend gemacht: Der mutmaßliche Spammer argumentiert, es sei keine Verletzung des deutschen Gesetzes gegen den unlauteren Wettbewerb (UWG) erfolgt.'
            ),
            'legal_legitimate_interest_claimed' => array(
                'name' => 'Legitimate Interest Claimed',
                'description_de' => 'Der mutmaßliche Spammer beruft sich auf berechtigte Interessen nach Art. 6 Abs. 1 lit. f DSGVO'
            ),
            'legal_no_damage_minimal_damage' => array(
                'name' => 'No/Minimal Damage',
                'description_de' => 'Kein/Minimaler Schaden (Art. 82 DSGVO): Der mutmaßliche Spammer argumentiert, es sei kein quantifizierbarer Schaden (materieller Schaden) entstanden, oder der immaterielle Schaden (z.B. Gefühl des Datenverlusts) sei minimal und rechtfertige keinen Schadensersatz.'
            ),
            'legal_claimed_damages_overstated' => array(
                'name' => 'Claimed Damages Overstated',
                'description_de' => 'Forderungssumme überhöht: Der mutmaßliche Spammer bestreitet die Höhe des geforderten Schadensersatzes oder der Anwaltskosten als unverhältnismäßig oder nicht durch gesetzliche Bestimmungen (z.B. RVG) gerechtfertigt.'
            ),
            'legal_statute_of_limitations_claimed' => array(
                'name' => 'Statute of Limitations Claimed',
                'description_de' => 'Verjährung geltend gemacht: Der mutmaßliche Spammer behauptet, die Forderung sei verjährt.'
            ),
            'legal_lack_of_legal_standing' => array(
                'name' => 'Lack of Legal Standing',
                'description_de' => 'Fehlende Aktivlegitimation: Der mutmaßliche Spammer stellt das Recht Ihres Mandanten, die Klage zu erheben, in Frage (z.B. nicht direkt betroffen oder nicht der tatsächliche Empfänger).'
            ),
            'legal_abuse_of_rights_warning_letter' => array(
                'name' => 'Abuse of Rights Warning Letter',
                'description_de' => 'Rechtsmissbräuchlichkeit der Abmahnung: Der mutmaßliche Spammer behauptet, die Abmahnung sei rechtsmissbräuchlich, z.B. wenn es dem Abmahnenden primär um das Generieren von Gebühren geht (§ 8c UWG).'
            ),
            
            // Process categories
            'process_formal_defect_warning_letter' => array(
                'name' => 'Formal Defect Warning Letter',
                'description_de' => 'Formeller Mangel in der Abmahnung: Der mutmaßliche Spammer behauptet, die Abmahnung selbst weise formelle Fehler auf (z.B. unzureichende Details, unklare Forderung).'
            ),
            'process_cease_and_desist_provided' => array(
                'name' => 'Cease and Desist Provided',
                'description_de' => 'Unterlassungserklärung abgegeben: Der mutmaßliche Spammer hat bereits eine (ggf. modifizierte) Unterlassungserklärung abgegeben.'
            ),
            'process_extension_requested' => array(
                'name' => 'Extension Requested',
                'description_de' => 'Nachfrist angefordert: Der mutmaßliche Spammer bittet um eine Verlängerung der Frist zur Stellungnahme oder Abgabe einer Erklärung.'
            ),
            
            // General categories
            'general_inquiry_request_for_clarification' => array(
                'name' => 'General Inquiry / Request for Clarification',
                'description_de' => 'Allgemeine Anfrage/Bitte um Klärung: Eine allgemeine Frage zur Forderung, zum Sachverhalt oder Wunsch nach weiterer Erläuterung, die keine spezifische rechtliche Verteidigung darstellt.'
            ),
            'general_willingness_to_negotiate_settle' => array(
                'name' => 'Willingness to Negotiate/Settle',
                'description_de' => 'Verhandlungs-/Vergleichsbereitschaft: Der mutmaßliche Spammer signalisiert Bereitschaft zu Verhandlungen oder schlägt einen Vergleich vor.'
            ),
            'general_contact_information_updated' => array(
                'name' => 'Contact Information Updated',
                'description_de' => 'Kontaktinformationen aktualisiert: Der mutmaßliche Spammer übermittelt neue Kontaktinformationen (Adresse, E-Mail, Telefonnummer).'
            ),
            'general_legal_representation_notified' => array(
                'name' => 'Legal Representation Notified',
                'description_de' => 'Hinweis auf Rechtsbeistand: Der mutmaßliche Spammer teilt mit, dass er bereits anwaltlich vertreten ist oder Rechtsbeistand einholen wird.'
            ),
            'general_threat_of_counter_action' => array(
                'name' => 'Threat of Counter Action',
                'description_de' => 'Drohung mit Gegenmaßnahme: Der mutmaßliche Spammer droht mit einer Gegenklage, negativer Presse oder anderen rechtlichen Schritten.'
            ),
            'general_mistaken_delivery_not_responsible' => array(
                'name' => 'Mistaken Delivery / Not Responsible',
                'description_de' => 'Irrtümliche Zustellung / Nicht zuständig: Der Empfänger der E-Mail behauptet, die E-Mail sei fälschlicherweise an ihn gesendet worden oder er sei nicht der richtige Ansprechpartner.'
            ),
            'general_uncategorized_other' => array(
                'name' => 'Uncategorized/Other',
                'description_de' => 'Unkategorisiert/Sonstiges: Für E-Mails, die in keine vordefinierte Kategorie passen und eine manuelle Überprüfung erfordern.'
            ),
            'general_reception_unterlassungserklaerung' => array(
                'name' => 'Received Cease and Desist Declaration',
                'description_de' => 'Wir haben eine Unterlassungserklärung bekommen.'
            ),
            'general_non_reception_unterlassungserklaerung' => array(
                'name' => 'No Cease and Desist Declaration Received',
                'description_de' => 'Wir haben keine Unterlassungserklärung bekommen.'
            )
        );
        
        foreach ($default_categories as $slug => $category_data) {
            wp_insert_term($category_data['name'], 'communication_category', array(
                'slug' => $slug,
                'description' => $category_data['description_de']
            ));
        }
        
        // Store German descriptions for API usage
        update_option('cah_doc_in_category_descriptions', $default_categories);
    }
    public function admin_notices() {
        // Check if core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            echo '<div class="notice notice-error"><p>';
            echo __('Document Analysis Plugin requires the Court Automation Hub Core Plugin to be installed and activated.', CAH_DOC_IN_TEXT_DOMAIN);
            echo '</p></div>';
        }
        
        // Show unassigned communications count
        if (isset($_GET['page']) && $_GET['page'] === 'cah-doc-in') {
            $unassigned_count = count($this->db_manager->get_unassigned_communications(1000));
            if ($unassigned_count > 0) {
                echo '<div class="notice notice-warning"><p>';
                printf(__('You have %d unassigned communications that need review. <a href="%s">Review them now</a>.', CAH_DOC_IN_TEXT_DOMAIN), 
                    $unassigned_count, 
                    admin_url('admin.php?page=cah-doc-in-unassigned')
                );
                echo '</p></div>';
            }
        }
    }
}