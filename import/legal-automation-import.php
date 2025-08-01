<?php
/**
 * Plugin Name: Legal Automation - Import
 * Plugin URI: https://klage.click
 * Description: Comprehensive import/export functionality for Legal Automation Suite. Handles CSV imports, field mapping, Forderungen.com integration, and data exports.
 * Version: 201
 * Author: Legal Tech Team
 * License: Proprietary
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Text Domain: legal-automation-import
 * Domain Path: /languages
 * 
 * Dependencies: legal-automation-core (v219+)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LAI_PLUGIN_VERSION', '201');
define('LAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LAI_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Legal Automation Import Plugin Class
 */
class LegalAutomationImport {
    
    private static $instance = null;
    private $import_manager;
    private $export_manager;
    private $admin_interface;
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if core plugin is active
        if (!$this->is_core_plugin_active()) {
            add_action('admin_notices', array($this, 'core_plugin_missing_notice'));
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Initialize hooks
        $this->add_hooks();
        
        // Log successful initialization
        error_log('LAI v' . LAI_PLUGIN_VERSION . ': Import/Export functionality initialized');
    }
    
    /**
     * Check if core plugin is active
     */
    private function is_core_plugin_active() {
        return class_exists('CourtAutomationHub') && defined('CAH_PLUGIN_VERSION');
    }
    
    /**
     * Show notice if core plugin is missing
     */
    public function core_plugin_missing_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>Legal Automation - Import</strong></p>
            <p>This plugin requires <strong>Legal Automation Core v219+</strong> to be installed and activated.</p>
            <p>Please install and activate the core plugin first.</p>
        </div>
        <?php
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load import manager
        require_once LAI_PLUGIN_PATH . 'includes/class-import-manager.php';
        
        // Load export manager
        require_once LAI_PLUGIN_PATH . 'includes/class-export-manager.php';
        
        // Load admin interface
        require_once LAI_PLUGIN_PATH . 'includes/class-admin-interface.php';
        
        // Load field mapping utilities
        require_once LAI_PLUGIN_PATH . 'includes/class-field-mapper.php';
        
        // Load client configurations
        require_once LAI_PLUGIN_PATH . 'includes/class-client-configs.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize import manager
        $this->import_manager = new LAI_Import_Manager();
        
        // Initialize export manager
        $this->export_manager = new LAI_Export_Manager();
        
        // Initialize admin interface only in admin
        if (is_admin()) {
            $this->admin_interface = new LAI_Admin_Interface($this->import_manager, $this->export_manager);
        }
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add integration hooks for admin plugin
        add_action('init', array($this, 'register_admin_integration'));
        
        // Add API endpoints for import/export
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }
    
    /**
     * Register admin plugin integration
     */
    public function register_admin_integration() {
        // Check if admin plugin is active
        if (class_exists('LegalAutomationAdmin') && defined('LAA_PLUGIN_VERSION')) {
            // Add hook for admin plugin to integrate import/export tab
            add_action('laa_admin_tabs', array($this, 'add_import_export_tab'));
            add_action('laa_admin_tab_content', array($this, 'render_import_export_tab_content'));
        }
    }
    
    /**
     * Add import/export tab to admin plugin
     */
    public function add_import_export_tab($tabs) {
        $tabs['import-export'] = array(
            'title' => '📊 Import/Export',
            'icon' => 'dashicons-database-import',
            'priority' => 30
        );
        return $tabs;
    }
    
    /**
     * Render import/export tab content
     */
    public function render_import_export_tab_content($tab_name) {
        if ($tab_name === 'import-export') {
            if ($this->admin_interface) {
                $this->admin_interface->render_integrated_interface();
            }
        }
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        // Import endpoints
        register_rest_route('legal-automation/v1', '/import/detect-fields', array(
            'methods' => 'POST',
            'callback' => array($this->import_manager, 'rest_detect_fields'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
        
        register_rest_route('legal-automation/v1', '/import/preview', array(
            'methods' => 'POST',
            'callback' => array($this->import_manager, 'rest_preview_import'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
        
        register_rest_route('legal-automation/v1', '/import/process', array(
            'methods' => 'POST',
            'callback' => array($this->import_manager, 'rest_process_import'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
        
        // Export endpoints
        register_rest_route('legal-automation/v1', '/export/csv', array(
            'methods' => 'GET',
            'callback' => array($this->export_manager, 'rest_export_csv'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
        
        register_rest_route('legal-automation/v1', '/export/template', array(
            'methods' => 'GET',
            'callback' => array($this->export_manager, 'rest_download_template'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
    }
    
    /**
     * REST API permission check
     */
    public function rest_permission_check() {
        return current_user_can('manage_options');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages or when integrated with admin plugin
        if (strpos($hook, 'legal-automation') === false && strpos($hook, 'klage-click') === false) {
            return;
        }
        
        // Import/Export CSS
        wp_enqueue_style(
            'lai-admin-style',
            LAI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LAI_PLUGIN_VERSION
        );
        
        // Import/Export JavaScript
        wp_enqueue_script(
            'lai-admin-script',
            LAI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-core', 'jquery-ui-tabs'),
            LAI_PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('lai-admin-script', 'lai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('legal-automation/v1/'),
            'nonce' => wp_create_nonce('lai_ajax_nonce'),
            'rest_nonce' => wp_create_nonce('wp_rest')
        ));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check core plugin dependency
        if (!$this->is_core_plugin_active()) {
            wp_die('Legal Automation Core v219+ is required for this plugin to work.');
        }
        
        // Create plugin-specific options
        add_option('lai_version', LAI_PLUGIN_VERSION);
        add_option('lai_import_history', array());
        add_option('lai_field_mappings', array());
        add_option('lai_client_configs', array());
        
        // Set default configurations
        $this->set_default_configurations();
        
        // Log activation
        error_log('LAI v' . LAI_PLUGIN_VERSION . ': Import/Export plugin activated');
    }
    
    /**
     * Set default configurations
     */
    private function set_default_configurations() {
        // Set default field mappings for common formats
        $default_mappings = array(
            'generic_csv' => array(
                'name' => 'Generic CSV',
                'description' => 'Universal CSV import with dynamic field mapping',
                'version' => '1.0'
            ),
            'forderungen_com' => array(
                'name' => 'Forderungen.com',
                'description' => 'Import from Forderungen.com data export',
                'version' => '1.0'
            )
        );
        
        update_option('lai_default_client_configs', $default_mappings);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary files and caches
        $this->cleanup_temp_files();
        
        // Log deactivation
        error_log('LAI v' . LAI_PLUGIN_VERSION . ': Import/Export plugin deactivated');
    }
    
    /**
     * Cleanup temporary files
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/lai-temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($temp_dir);
        }
    }
    
    /**
     * Get import manager instance
     */
    public function get_import_manager() {
        return $this->import_manager;
    }
    
    /**
     * Get export manager instance
     */
    public function get_export_manager() {
        return $this->export_manager;
    }
    
    /**
     * Get admin interface instance
     */
    public function get_admin_interface() {
        return $this->admin_interface;
    }
}

// Initialize the plugin
LegalAutomationImport::getInstance();