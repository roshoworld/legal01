<?php
/**
 * Plugin Name: Legal Automation - Core
 * Plugin URI: https://klage.click
 * Description: Multi-purpose legal automation platform for German courts with AI-powered processing
 * Version: 220
 * Author: Klage.Click
 * Text Domain: legal-automation-core
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CAH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CAH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CAH_PLUGIN_VERSION', '220');

// Main plugin class
class CourtAutomationHub {
    
    // Declare all class properties to fix PHP 8.2+ warnings
    public $database;
    public $admin_dashboard;
    public $rest_api;
    public $audit_logger;
    public $case_manager;
    public $debtor_manager;
    public $email_evidence;
    public $legal_framework;
    public $court_manager;
    // Document Analysis Integration v1.8.7
    public $doc_in_integration;
    
    // Universal Import Manager v1.9.0
    public $universal_import_manager;
    public $universal_import_admin;
    
    // Core API for admin plugin integration
    public $core_api;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('legal-automation-core', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Add hooks
        $this->add_hooks();
    }
    
    private function includes() {
        // Core database management - SAFELY RE-ENABLED with comprehensive dbDelta solution
        require_once CAH_PLUGIN_PATH . 'includes/class-database.php';
        
        // Other core components
        require_once CAH_PLUGIN_PATH . 'includes/class-form-generator.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-import-export-manager.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-case-manager.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-audit-logger.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-debtor-manager.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-email-evidence.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-legal-framework.php';
        require_once CAH_PLUGIN_PATH . 'includes/class-court-manager.php';
        
        // Document Analysis Integration v1.8.7
        require_once CAH_PLUGIN_PATH . 'includes/class-doc-in-integration.php';
        
        // Universal Import Manager v1.9.0 - MUST load before admin classes
        require_once CAH_PLUGIN_PATH . 'includes/class-universal-import-manager.php';
        
        // Database v2.0.0 - Comprehensive Legal Practice Management
        require_once CAH_PLUGIN_PATH . 'includes/class-database-v200.php';
        
        // Core API for admin plugin integration
        require_once CAH_PLUGIN_PATH . 'includes/class-core-api.php';
        
        // Unified Menu System v2.2.0
        require_once CAH_PLUGIN_PATH . 'includes/class-unified-menu.php';
        
        // Admin classes (load only if admin plugin is NOT active)
        if (!$this->is_admin_plugin_active()) {
            require_once CAH_PLUGIN_PATH . 'admin/class-admin-dashboard.php';
            require_once CAH_PLUGIN_PATH . 'admin/class-universal-import-admin.php';
        }
        
        require_once CAH_PLUGIN_PATH . 'api/class-rest-api.php';
    }
    
    private function init_components() {
        // Initialize database with comprehensive schema integrity - Use v2.0.0 for consistency
        $this->database = new CAH_Database_v200();
        
        // Initialize core API
        $this->core_api = CAH_Core_API::getInstance();
        
        // Initialize core components
        $this->audit_logger = new CAH_Audit_Logger();
        $this->case_manager = new CAH_Case_Manager();
        $this->debtor_manager = new CAH_Debtor_Manager();
        $this->email_evidence = new CAH_Email_Evidence();
        $this->legal_framework = new CAH_Legal_Framework();
        $this->court_manager = new CAH_Court_Manager();
        $this->rest_api = new CAH_REST_API();
        
        // Document Analysis Integration v1.8.7
        $this->doc_in_integration = new CAH_DocIn_Integration();
        
        // Universal Import Manager v1.9.0
        $this->universal_import_manager = new CAH_Universal_Import_Manager();
        
        // Initialize admin components only if admin plugin is not active
        if (is_admin() && !$this->is_admin_plugin_active()) {
            if (class_exists('CAH_Admin_Dashboard')) {
                $this->admin_dashboard = new CAH_Admin_Dashboard();
            }
            if (class_exists('CAH_Universal_Import_Admin')) {
                $this->universal_import_admin = new CAH_Universal_Import_Admin();
            }
        }
    }
    
    /**
     * Check if admin plugin is active
     */
    private function is_admin_plugin_active() {
        return class_exists('LegalAutomationAdmin') && defined('LAA_PLUGIN_VERSION');
    }
    
    private function add_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Check database schema on admin pages
        add_action('admin_notices', array($this, 'check_database_schema'));
    }
    
    /**
     * Check if database schema is properly created
     */
    public function check_database_schema() {
        global $wpdb;
        
        // Only check on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Required tables for v2.0.0 contact-centric architecture
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
        
        if (!empty($missing_tables)) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Court Automation Hub:</strong> Fehlende Datenbank-Tabellen: ' . implode(', ', $missing_tables);
            echo '<br>Bitte deaktivieren und reaktivieren Sie das Plugin, um die Datenbank-Tabellen zu erstellen.';
            echo '</p></div>';
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('cah-frontend', CAH_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), CAH_PLUGIN_VERSION, true);
        wp_enqueue_style('cah-frontend', CAH_PLUGIN_URL . 'assets/css/frontend.css', array(), CAH_PLUGIN_VERSION);
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('cah-admin', CAH_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CAH_PLUGIN_VERSION, true);
        wp_enqueue_style('cah-admin', CAH_PLUGIN_URL . 'assets/css/admin.css', array(), CAH_PLUGIN_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('cah-admin', 'cah_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cah_admin_nonce')
        ));
    }
    
    public function activate() {
        // v2.1.0 ENHANCEMENT - Complete Dashboard & Case Management Redesign
        
        $results = array(
            'success' => true,
            'message' => 'v2.0.4 Legal Practice Management System activated',
            'tables_created' => 0,
            'demo_data' => array(),
            'errors' => array()
        );
        
        try {
            // Load v2.0.0 database class (needed for activation)
            require_once CAH_PLUGIN_PATH . 'includes/class-database-v200.php';
            
            // Create comprehensive v2.0.0 database schema
            $database_v200 = new CAH_Database_v200();
            $schema_results = $database_v200->execute_schema_creation();
            
            // Handle new result format with cleanup and creation
            if (isset($schema_results['cleanup']) && isset($schema_results['creation'])) {
                $results['tables_created'] = count($schema_results['creation']);
                $results['tables_cleaned'] = count($schema_results['cleanup']);
                $results['cleanup_details'] = $schema_results['cleanup'];
            } else {
                // Fallback for old format
                $results['tables_created'] = count($schema_results);
            }
            
            // TEMPORARILY DISABLED: Demo data population during development
            // Will be re-enabled with clean, unique sample data once data model is stable
            
            // Populate demo data for testing - DISABLED for development phase
            // $demo_results = $database_v200->populate_demo_data();
            // $results['demo_data'] = $demo_results;
            
            // Clean slate approach for development
            $results['demo_data'] = array('message' => 'Demo data disabled for development phase');
            
            error_log("CAH v2.0.4: Comprehensive Legal Practice Management System activated");
            if (isset($results['tables_cleaned'])) {
                error_log("CAH v2.0.4: Cleaned up " . $results['tables_cleaned'] . " old tables");
            }
            error_log("CAH v2.0.4: Created " . $results['tables_created'] . " database tables");
            error_log("CAH v2.0.4: Demo data - " . json_encode($results['demo_data']));
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            error_log("CAH v2.0.4: Activation error: " . $e->getMessage());
        }
        
        // Add user capabilities
        $self = new self();
        $self->add_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Store results
        update_option('cah_last_activation_results', $results);
        update_option('cah_database_version', '2.1.0');
        
        if ($results['success']) {
            error_log("CAH v2.0.4: Activation completed successfully");
        } else {
            error_log("CAH v2.0.4: Activation completed with errors: " . implode(', ', $results['errors']));
        }
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function add_capabilities() {
        $capabilities = array(
            'manage_klage_click_cases',
            'edit_klage_click_cases', 
            'view_klage_click_cases',
            'manage_klage_click_debtors',
            'manage_klage_click_documents',
            'manage_klage_click_templates',
            'manage_klage_click_settings'
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
$court_automation_hub = new CourtAutomationHub();