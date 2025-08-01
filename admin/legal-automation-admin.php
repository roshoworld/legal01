<?php
/**
 * Plugin Name: Legal Automation - Admin Interface
 * Plugin URI: https://klage.click
 * Description: Advanced administrative interface for Legal Automation Core Plugin. Provides comprehensive case management, 9-tab interface, sorting, and import/export functionality.
 * Version: 212
 * Author: Legal Tech Team
 * License: Proprietary
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Text Domain: legal-automation-admin
 * Domain Path: /languages
 * 
 * Dependencies: legal-automation-core (v210+)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LAA_PLUGIN_VERSION', '212');
define('LAA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LAA_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Legal Automation Admin Plugin Class
 */
class LegalAutomationAdmin {
    
    private static $instance = null;
    
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
        
        // Initialize hooks
        $this->add_hooks();
        
        // Log successful initialization
        error_log('LAA v' . LAA_PLUGIN_VERSION . ': Advanced Admin Interface initialized');
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
            <p><strong>Legal Automation - Admin Interface</strong></p>
            <p>This plugin requires <strong>Legal Automation Core v210+</strong> to be installed and activated.</p>
            <p>Please install and activate the core plugin first.</p>
        </div>
        <?php
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load admin dashboard class
        require_once LAA_PLUGIN_PATH . 'includes/class-admin-dashboard-v210.php';
        
        // Load import/export handlers
        require_once LAA_PLUGIN_PATH . 'includes/class-import-export-handler.php';
        
        // Load UI components
        require_once LAA_PLUGIN_PATH . 'includes/class-ui-components.php';
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // Override core admin menus with advanced interface
        add_action('admin_menu', array($this, 'override_admin_menu'), 15);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_laa_advanced_search', array($this, 'ajax_advanced_search'));
        add_action('wp_ajax_laa_bulk_operations', array($this, 'ajax_bulk_operations'));
    }
    
    /**
     * Override core admin menu with advanced interface
     */
    public function override_admin_menu() {
        // Only override if user has admin capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Initialize advanced dashboard
        $dashboard = new LAA_Admin_Dashboard_v210();
        
        // PROPERLY override core menu items by using same slugs
        // This replaces the core callbacks with admin plugin versions
        
        // Override the main hub dashboard
        add_menu_page(
            'Klage.Click Hub - Erweitert',
            'Klage.Click Hub',
            'manage_options',
            'klage-click-hub',
            array($dashboard, 'admin_page_dashboard'),
            'dashicons-hammer',
            30
        );
        
        // Override the cases submenu
        add_submenu_page(
            'klage-click-hub',
            'Fälle - Erweitert',
            'Fälle',
            'manage_options',
            'klage-click-cases',
            array($dashboard, 'admin_page_cases')
        );
        
        // Override the settings submenu
        add_submenu_page(
            'klage-click-hub',
            'Einstellungen - Erweitert',
            'Einstellungen',
            'manage_options',
            'klage-click-settings',
            array($dashboard, 'admin_page_settings')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'legal-') === false) {
            return;
        }
        
        // Advanced UI CSS
        wp_enqueue_style(
            'laa-admin-style',
            LAA_PLUGIN_URL . 'assets/css/admin-advanced.css',
            array(),
            LAA_PLUGIN_VERSION
        );
        
        // Advanced UI JavaScript  
        wp_enqueue_script(
            'laa-admin-script',
            LAA_PLUGIN_URL . 'assets/js/admin-advanced.js',
            array('jquery'),
            LAA_PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('laa-admin-script', 'laa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('laa_ajax_nonce')
        ));
    }
    
    /**
     * AJAX handler for advanced search
     */
    public function ajax_advanced_search() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'laa_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Advanced search implementation
        $dashboard = new LAA_Admin_Dashboard_v210();
        $results = $dashboard->perform_advanced_search($_POST);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for bulk operations
     */
    public function ajax_bulk_operations() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'laa_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Bulk operations implementation
        $dashboard = new LAA_Admin_Dashboard_v210();
        $result = $dashboard->perform_bulk_operation($_POST);
        
        wp_send_json_success($result);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check core plugin dependency
        if (!$this->is_core_plugin_active()) {
            wp_die('Legal Automation Core v210+ is required for this plugin to work.');
        }
        
        // Create admin-specific options
        add_option('laa_version', LAA_PLUGIN_VERSION);
        add_option('laa_advanced_features_enabled', 1);
        
        // Log activation
        error_log('LAA v' . LAA_PLUGIN_VERSION . ': Advanced Admin Interface activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up admin-specific options if needed
        // Note: Don't delete data, just deactivate features
        
        // Log deactivation
        error_log('LAA v' . LAA_PLUGIN_VERSION . ': Advanced Admin Interface deactivated');
    }
}

// Initialize the plugin
LegalAutomationAdmin::getInstance();