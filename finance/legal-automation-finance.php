<?php
/**
 * Plugin Name: Legal Automation - Finance
 * Plugin URI: https://klage.click/finance-calculator
 * Description: Advanced RVG fee calculator for legal proceedings with template management and case integration
 * Version: 2.0.0
 * Author: Klage.Click
 * Text Domain: legal-automation-finance
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LAF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LAF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LAF_PLUGIN_VERSION', '2.0.0');

// Main plugin class
class Legal_Automation_Finance {
    
    public $db_manager;
    public $calculator;
    public $template_manager;
    public $admin;
    public $case_integration;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check if core plugin is active
        if (!$this->is_core_plugin_active()) {
            add_action('admin_notices', array($this, 'core_plugin_required_notice'));
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('legal-automation-finance', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Add hooks
        $this->add_hooks();
    }
    
    private function is_core_plugin_active() {
        return class_exists('CourtAutomationHub');
    }
    
    public function core_plugin_required_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>Legal Automation - Finance</strong> requires the "Court Automation Hub" core plugin to be installed and activated.</p>
        </div>
        <?php
    }
    
    private function includes() {
        require_once LAF_PLUGIN_PATH . 'includes/class-finance-db-manager.php';
        require_once LAF_PLUGIN_PATH . 'includes/class-rvg-calculator.php';
        require_once LAF_PLUGIN_PATH . 'includes/class-template-manager.php';
        require_once LAF_PLUGIN_PATH . 'includes/class-finance-admin.php';
        require_once LAF_PLUGIN_PATH . 'includes/class-case-integration.php';
    }
    
    private function init_components() {
        // Initialize database manager and create tables
        $this->db_manager = new LAF_Database_Manager();
        $this->db_manager->create_tables();
        
        // Initialize components
        $this->calculator = new LAF_RVG_Calculator();
        $this->template_manager = new LAF_Template_Manager();
        $this->admin = new LAF_Admin();
        $this->case_integration = new LAF_Case_Integration();
        
        // Hook into core plugin
        do_action('legal_automation_finance_ready');
    }
    
    private function add_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('laf-admin', LAF_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), LAF_PLUGIN_VERSION, true);
        wp_enqueue_style('laf-admin', LAF_PLUGIN_URL . 'assets/css/admin.css', array(), LAF_PLUGIN_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('laf-admin', 'laf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('laf_nonce')
        ));
    }
    
    public function activate() {
        // Include required files for activation
        require_once LAF_PLUGIN_PATH . 'includes/class-finance-db-manager.php';
        require_once LAF_PLUGIN_PATH . 'includes/class-template-manager.php';
        
        // Create database tables
        $db_manager = new LAF_Database_Manager();
        $result = $db_manager->create_tables();
        
        // Create default templates
        if ($result['success']) {
            $template_manager = new LAF_Template_Manager();
            $template_manager->create_default_templates();
        }
        
        // Update version
        update_option('legal_automation_finance_version', '2.0.0');
        
        // Log activation
        error_log("Legal Automation Finance v2.0.0 activated successfully");
    }
    
    public function deactivate() {
        // Clean up if needed
    }
}

// Initialize the plugin
new Legal_Automation_Finance();