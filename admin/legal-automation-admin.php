<?php
/**
 * Plugin Name: Legal Automation - Admin
 * Plugin URI: https://klage.click
 * Description: Advanced admin interface and dashboard for the Legal Automation plugin suite
 * Version: 212
 * Author: Klage.Click
 * Text Domain: legal-automation-admin
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LAA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LAA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LAA_PLUGIN_VERSION', '212');

// Main plugin class
class LegalAutomationAdmin {
    
    private $dashboard;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        
        // Only run if core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            add_action('admin_notices', array($this, 'core_plugin_missing_notice'));
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('legal-automation-admin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components  
        $this->init_components();
        
        // Add hooks
        $this->add_hooks();
        
        // Debug log
        error_log('LAA v212: Advanced Admin Interface initialized');
    }
    
    private function includes() {
        require_once LAA_PLUGIN_PATH . 'includes/class-admin-dashboard-v210.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-case-manager.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-contact-manager.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-document-manager.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-analytics-dashboard.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-settings-manager.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-system-health.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-backup-manager.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-user-management.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-security-manager.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-performance-monitor.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-integration-hub.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-workflow-automation.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-notification-system.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-template-engine.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-report-generator.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-custom-fields.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-bulk-operations.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-data-import-export.php';
        require_once LAA_PLUGIN_PATH . 'includes/class-api-manager.php';
    }
    
    private function init_components() {
        // Initialize main dashboard 
        $this->dashboard = new LAA_Admin_Dashboard_v210();
    }
    
    private function add_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_laa_quick_stats', array($this, 'ajax_quick_stats'));
        add_action('wp_ajax_laa_system_health', array($this, 'ajax_system_health'));
    }
    
    public function add_admin_menu() {
        // Only add menu if not using unified menu system
        if (!class_exists('Legal_Automation_Unified_Menu')) {
            // Main dashboard page
            add_menu_page(
                __('Legal Automation Admin', 'legal-automation-admin'),
                __('Legal Admin', 'legal-automation-admin'),
                'manage_options',
                'legal-automation-admin',
                array($this->dashboard, 'admin_dashboard_page'),
                'dashicons-shield-alt',
                26
            );
            
            // Analytics submenu
            add_submenu_page(
                'legal-automation-admin',
                __('Analytics', 'legal-automation-admin'),
                __('Analytics', 'legal-automation-admin'),
                'manage_options',
                'legal-automation-analytics',
                array($this->dashboard, 'analytics_page')
            );
            
            // System Health submenu
            add_submenu_page(
                'legal-automation-admin',
                __('System Health', 'legal-automation-admin'),
                __('System Health', 'legal-automation-admin'),
                'manage_options',
                'legal-automation-health',
                array($this->dashboard, 'system_health_page')
            );
            
            // Settings submenu
            add_submenu_page(
                'legal-automation-admin',
                __('Advanced Settings', 'legal-automation-admin'),
                __('Settings', 'legal-automation-admin'),
                'manage_options',
                'legal-automation-admin-settings',
                array($this->dashboard, 'settings_page')
            );
        }
        
        // Add the cases menu - UPDATED to use correct page slug
        add_submenu_page(
            null, // Hide from menu since we're using unified menu
            'Fälle',
            'Fälle',
            'manage_options',
            'la-cases',
            array($dashboard, 'admin_page_cases')
        );
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'legal-automation') === false) {
            return;
        }
        
        // Scripts
        wp_enqueue_script('laa-admin', LAA_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-util'), LAA_PLUGIN_VERSION, true);
        wp_enqueue_script('chart-js', LAA_PLUGIN_URL . 'assets/js/chart.min.js', array(), '3.9.1', true);
        
        // Styles
        wp_enqueue_style('laa-admin', LAA_PLUGIN_URL . 'assets/css/admin.css', array(), LAA_PLUGIN_VERSION);
        wp_enqueue_style('laa-dashboard', LAA_PLUGIN_URL . 'assets/css/dashboard.css', array(), LAA_PLUGIN_VERSION);
        
        // Localize script
        wp_localize_script('laa-admin', 'laa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('laa_admin_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'legal-automation-admin'),
                'error' => __('An error occurred', 'legal-automation-admin'),
                'success' => __('Operation completed successfully', 'legal-automation-admin')
            )
        ));
    }
    
    public function ajax_quick_stats() {
        check_ajax_referer('laa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'legal-automation-admin'));
        }
        
        global $wpdb;
        
        $stats = array(
            'total_cases' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'") ?? 0,
            'pending_cases' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'pending' AND active_status = 'active'") ?? 0,
            'completed_cases' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'completed' AND active_status = 'active'") ?? 0,
            'total_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_contacts WHERE active_status = 1") ?? 0
        );
        
        wp_send_json_success($stats);
    }
    
    public function ajax_system_health() {
        check_ajax_referer('laa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'legal-automation-admin'));
        }
        
        $health = array(
            'database' => $this->check_database_health(),
            'files' => $this->check_file_permissions(),
            'memory' => $this->check_memory_usage(),
            'plugins' => $this->check_plugin_compatibility()
        );
        
        wp_send_json_success($health);
    }
    
    private function check_database_health() {
        global $wpdb;
        
        $required_tables = array(
            'klage_cases',
            'klage_contacts',
            'klage_case_contacts',
            'klage_courts',
            'klage_financials',
            'klage_audit'
        );
        
        $missing_tables = array();
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if (!$wpdb->get_var("SHOW TABLES LIKE '$table_name'")) {
                $missing_tables[] = $table;
            }
        }
        
        return array(
            'status' => empty($missing_tables) ? 'good' : 'warning',
            'missing_tables' => $missing_tables,
            'message' => empty($missing_tables) ? 'All database tables are present' : 'Some database tables are missing'
        );
    }
    
    private function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        $writable = wp_is_writable($upload_dir['basedir']);
        
        return array(
            'status' => $writable ? 'good' : 'critical',
            'message' => $writable ? 'Upload directory is writable' : 'Upload directory is not writable'
        );
    }
    
    private function check_memory_usage() {
        $limit = ini_get('memory_limit');
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        
        return array(
            'status' => 'good',
            'limit' => $limit,
            'usage' => size_format($usage),
            'peak' => size_format($peak),
            'message' => "Memory usage: " . size_format($usage) . " / " . $limit
        );
    }
    
    private function check_plugin_compatibility() {
        $active_plugins = get_option('active_plugins');
        $required_plugins = array(
            'court-automation-hub/court-automation-hub.php'
        );
        
        $missing = array();
        foreach ($required_plugins as $plugin) {
            if (!in_array($plugin, $active_plugins)) {
                $missing[] = $plugin;
            }
        }
        
        return array(
            'status' => empty($missing) ? 'good' : 'critical',
            'missing' => $missing,
            'message' => empty($missing) ? 'All required plugins are active' : 'Some required plugins are missing'
        );
    }
    
    public function core_plugin_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Legal Automation Admin', 'legal-automation-admin'); ?>:</strong>
                <?php _e('The Court Automation Hub core plugin is required for this plugin to function.', 'legal-automation-admin'); ?>
            </p>
        </div>
        <?php
    }
    
    public function activate() {
        // Check if core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('The Court Automation Hub core plugin must be active to use this plugin.', 'legal-automation-admin'));
        }
        
        // Add capabilities if needed
        $this->add_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
    }
    
    private function add_capabilities() {
        $capabilities = array(
            'manage_legal_automation_admin',
            'view_legal_automation_analytics',
            'manage_legal_automation_settings'
        );
        
        $administrator = get_role('administrator');
        if ($administrator) {
            foreach ($capabilities as $capability) {
                $administrator->add_cap($capability);
            }
        }
    }
}

// Initialize the plugin
$legal_automation_admin = new LegalAutomationAdmin();