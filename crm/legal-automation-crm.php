<?php
/**
 * Plugin Name: Legal Automation - CRM
 * Plugin URI: https://klage.click/crm
 * Description: Customer Relationship Management plugin for legal automation suite - handles events, communication, and document links
 * Version: 1.0.0
 * Author: Klage.Click
 * Text Domain: legal-automation-crm
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LA_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LA_CRM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LA_CRM_PLUGIN_VERSION', '1.0.0');
define('LA_CRM_TEXT_DOMAIN', 'legal-automation-crm');

// Main plugin class
class LegalAutomationCRM {
    
    // Declare all class properties
    public $db_manager;
    public $communication_manager;
    public $event_manager;
    public $audience_manager;
    public $admin;
    public $rest_api;
    public $core_integration;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Debug: Plugin initialization
        error_log('LA CRM: Plugin initialization started');
        
        // Check if core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            error_log('LA CRM: Core plugin not found - showing notice');
            add_action('admin_notices', array($this, 'core_plugin_required_notice'));
            return;
        }
        
        error_log('LA CRM: Core plugin found - continuing initialization');
        
        // Load text domain
        load_plugin_textdomain(LA_CRM_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Add hooks
        $this->add_hooks();
        
        error_log('LA CRM: Plugin initialization completed');
    }
    
    private function includes() {
        // Core CRM classes
        require_once LA_CRM_PLUGIN_PATH . 'includes/class-crm-database.php';
        require_once LA_CRM_PLUGIN_PATH . 'includes/class-communication-manager.php';
        require_once LA_CRM_PLUGIN_PATH . 'includes/class-event-manager.php';
        require_once LA_CRM_PLUGIN_PATH . 'includes/class-audience-manager.php';
        require_once LA_CRM_PLUGIN_PATH . 'includes/class-core-integration.php';
        
        // Admin classes
        if (is_admin()) {
            require_once LA_CRM_PLUGIN_PATH . 'admin/class-crm-admin.php';
        }
        
        // API classes
        require_once LA_CRM_PLUGIN_PATH . 'api/class-crm-rest-api.php';
    }
    
    private function init_components() {
        // Initialize database manager first
        $this->db_manager = new LA_CRM_Database();
        
        // Initialize other components
        $this->communication_manager = new LA_CRM_Communication_Manager();
        $this->event_manager = new LA_CRM_Event_Manager();
        $this->audience_manager = new LA_CRM_Audience_Manager();
        $this->core_integration = new LA_CRM_Core_Integration();
        $this->rest_api = new LA_CRM_REST_API();
        
        // Initialize admin if in admin
        if (is_admin()) {
            $this->admin = new LA_CRM_Admin();
        }
    }
    
    private function add_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Hook into core plugin case view tabs
        add_action('cah_case_view_tabs', array($this, 'add_crm_tabs'));
        add_action('cah_case_view_content', array($this, 'add_crm_tab_content'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('la-crm-frontend', LA_CRM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), LA_CRM_PLUGIN_VERSION, true);
        wp_enqueue_style('la-crm-frontend', LA_CRM_PLUGIN_URL . 'assets/css/frontend.css', array(), LA_CRM_PLUGIN_VERSION);
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages or core plugin pages
        if (strpos($hook, 'la-crm') !== false || strpos($hook, 'court-automation') !== false) {
            wp_enqueue_script('la-crm-admin', LA_CRM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), LA_CRM_PLUGIN_VERSION, true);
            wp_enqueue_style('la-crm-admin', LA_CRM_PLUGIN_URL . 'assets/css/admin.css', array(), LA_CRM_PLUGIN_VERSION);
            
            // Localize script for AJAX
            wp_localize_script('la-crm-admin', 'la_crm_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('la_crm_admin_nonce'),
                'text_domain' => LA_CRM_TEXT_DOMAIN,
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', LA_CRM_TEXT_DOMAIN),
                    'loading' => __('Loading...', LA_CRM_TEXT_DOMAIN),
                    'error_occurred' => __('An error occurred. Please try again.', LA_CRM_TEXT_DOMAIN)
                )
            ));
        }
    }
    
    /**
     * Add CRM tabs to core plugin case view
     */
    public function add_crm_tabs() {
        ?>
        <a href="#communications" class="nav-tab" onclick="switchCaseTab(event, 'communications')">📧 Kommunikation</a>
        <a href="#events" class="nav-tab" onclick="switchCaseTab(event, 'events')">📅 Termine</a>
        <a href="#audience" class="nav-tab" onclick="switchCaseTab(event, 'audience')">🎯 Zielgruppe</a>
        <?php
    }
    
    /**
     * Add CRM tab content to core plugin case view
     */
    public function add_crm_tab_content() {
        if ($this->admin) {
            $this->admin->render_tab_contents();
        }
    }
    
    public function activate() {
        // Ensure core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Legal Automation CRM requires the Court Automation Hub core plugin to be installed and activated.', LA_CRM_TEXT_DOMAIN));
        }
        
        // Include database manager for activation
        require_once LA_CRM_PLUGIN_PATH . 'includes/class-crm-database.php';
        
        // Create database tables
        $db_manager = new LA_CRM_Database();
        $db_manager->create_tables();
        
        // Set activation flag and version
        update_option('la_crm_activated', true);
        update_option('la_crm_version', LA_CRM_PLUGIN_VERSION);
        
        // Log activation in core audit table
        global $wpdb;
        $audit_table = $wpdb->prefix . 'klage_audit';
        if ($wpdb->get_var("SHOW TABLES LIKE '$audit_table'") == $audit_table) {
            $wpdb->insert(
                $audit_table,
                array(
                    'action_type' => 'plugin_activation',
                    'action_details' => 'Legal Automation CRM Plugin v' . LA_CRM_PLUGIN_VERSION . ' activated',
                    'performed_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                )
            );
        }
        
        error_log('LA CRM: Plugin v' . LA_CRM_PLUGIN_VERSION . ' activated successfully');
    }
    
    public function deactivate() {
        // Clean up scheduled events if any
        wp_clear_scheduled_hook('la_crm_cleanup');
        
        // Set deactivation flag
        update_option('la_crm_activated', false);
        
        // Log deactivation in core audit table
        global $wpdb;
        $audit_table = $wpdb->prefix . 'klage_audit';
        if ($wpdb->get_var("SHOW TABLES LIKE '$audit_table'") == $audit_table) {
            $wpdb->insert(
                $audit_table,
                array(
                    'action_type' => 'plugin_deactivation',
                    'action_details' => 'Legal Automation CRM Plugin v' . LA_CRM_PLUGIN_VERSION . ' deactivated',
                    'performed_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                )
            );
        }
        
        error_log('LA CRM: Plugin v' . LA_CRM_PLUGIN_VERSION . ' deactivated');
    }
    
    public function core_plugin_required_notice() {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . __('Legal Automation CRM requires the Court Automation Hub core plugin to be installed and activated.', LA_CRM_TEXT_DOMAIN) . '</p>';
        echo '</div>';
    }
}

// Initialize plugin
new LegalAutomationCRM();