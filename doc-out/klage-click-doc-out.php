<?php
/**
 * Plugin Name: Legal Automation - Document Generator
 * Plugin URI: https://klage.click/document-generator
 * Description: Advanced document generation system with PHP-to-PDF templating engine, WYSIWYG editing, and encrypted template management
 * Version: 1.0.9
 * Author: Klage.Click
 * Text Domain: legal-automation-doc-out
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KCDO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KCDO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KCDO_PLUGIN_VERSION', '1.0.9');

// Main plugin class
class KlageClickDocOut {
    
    // Declare all class properties to fix PHP 8.2+ warnings
    public $template_manager;
    public $document_generator;
    public $pdf_engine;
    public $admin_dashboard;
    public $rest_api;
    public $core_integration;
    public $s3_storage;
    
    public function __construct() {
        // Set global instance for other classes to access
        global $klage_click_doc_out;
        $klage_click_doc_out = $this;
        
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
        load_plugin_textdomain('legal-automation-doc-out', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->initialize_components();
        
        // Add admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // Add frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once KCDO_PLUGIN_PATH . 'includes/class-template-manager.php';
        require_once KCDO_PLUGIN_PATH . 'includes/class-document-generator.php';
        require_once KCDO_PLUGIN_PATH . 'includes/class-pdf-engine.php';
        require_once KCDO_PLUGIN_PATH . 'includes/class-simple-pdf-generator.php';
        require_once KCDO_PLUGIN_PATH . 'includes/class-s3-storage.php';
        require_once KCDO_PLUGIN_PATH . 'includes/class-core-integration.php';
        require_once KCDO_PLUGIN_PATH . 'includes/class-doc-rest-api.php';
        
        // Admin classes
        if (is_admin()) {
            require_once KCDO_PLUGIN_PATH . 'admin/class-doc-admin-dashboard.php';
        }
        
        // Core integration classes
        require_once KCDO_PLUGIN_PATH . 'includes/class-cah-database-integration.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function initialize_components() {
        $this->template_manager = new KCDO_Template_Manager();
        $this->document_generator = new KCDO_Document_Generator();
        $this->pdf_engine = new KCDO_PDF_Engine();
        $this->s3_storage = new KCDO_S3_Storage();
        $this->core_integration = new KCDO_Core_Integration();
        $this->rest_api = new KCDO_Doc_REST_API();
        
        if (is_admin()) {
            $this->admin_dashboard = new KCDO_Doc_Admin_Dashboard();
        }
    }
    
    /**
     * Check if core plugin is active
     */
    private function is_core_plugin_active() {
        return class_exists('CourtAutomationHub');
    }
    
    /**
     * Display notice when core plugin is required
     */
    public function core_plugin_required_notice() {
        $message = __('Legal Automation Document Generator requires the Court Automation Hub core plugin to be active.', 'legal-automation-doc-out');
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Document Generator', 'legal-automation-doc-out'),
            __('Document Generator', 'legal-automation-doc-out'),
            'manage_options',
            'klage-doc-generator',
            array($this->admin_dashboard, 'render_main_page'),
            'dashicons-media-document',
            30
        );
        
        // Template Gallery submenu
        add_submenu_page(
            'klage-doc-generator',
            __('Template Gallery', 'legal-automation-doc-out'),
            __('Template Gallery', 'legal-automation-doc-out'),
            'manage_options',
            'klage-doc-templates',
            array($this->admin_dashboard, 'render_templates_page')
        );
        
        // Document Generation submenu
        add_submenu_page(
            'klage-doc-generator',
            __('Generate Documents', 'legal-automation-doc-out'),
            __('Generate Documents', 'legal-automation-doc-out'),
            'manage_options',
            'klage-doc-generate',
            array($this->admin_dashboard, 'render_generation_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'klage-doc-generator',
            __('Settings', 'klage-click-doc-out'),
            __('Settings', 'klage-click-doc-out'),
            'manage_options',
            'klage-doc-settings',
            array($this->admin_dashboard, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'klage-doc') === false) {
            return;
        }
        
        wp_enqueue_style(
            'klage-doc-admin-css',
            KCDO_PLUGIN_URL . 'assets/css/doc-admin.css',
            array(),
            KCDO_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'klage-doc-admin-js',
            KCDO_PLUGIN_URL . 'assets/js/doc-admin.js',
            array('jquery', 'wp-tinymce'),
            KCDO_PLUGIN_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('klage-doc-admin-js', 'klageDocAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('klage_doc_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this template?', 'klage-click-doc-out'),
                'generating_pdf' => __('Generating PDF...', 'klage-click-doc-out'),
                'error_occurred' => __('An error occurred. Please try again.', 'klage-click-doc-out')
            )
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'klage-doc-frontend-css',
            KCDO_PLUGIN_URL . 'assets/css/doc-frontend.css',
            array(),
            KCDO_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'klage-doc-frontend-js',
            KCDO_PLUGIN_URL . 'assets/js/doc-frontend.js',
            array('jquery'),
            KCDO_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Check if core plugin is active
        if (!$this->is_core_plugin_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Klage.Click Document Generator requires the Court Automation Hub core plugin to be active.', 'klage-click-doc-out'),
                __('Plugin Activation Error', 'klage-click-doc-out'),
                array('back_link' => true)
            );
        }
        
        // Create database tables if needed
        $this->create_database_tables();
        
        // Create templates directory if it doesn't exist
        $templates_dir = KCDO_PLUGIN_PATH . 'templates/';
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }
        
        // Set default options
        $default_options = array(
            'pdf_engine' => 'mpdf',
            's3_enabled' => false,
            'template_encryption' => true,
            'core_integration' => true
        );
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option('klage_doc_' . $option_name) === false) {
                update_option('klage_doc_' . $option_name, $default_value);
            }
        }
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Clean up temporary files
        $this->cleanup_temp_files();
    }
    
    /**
     * Create database tables for templates and document drafts
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Templates table
        $templates_table = $wpdb->prefix . 'klage_document_templates';
        
        $sql1 = "CREATE TABLE $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            template_slug varchar(255) NOT NULL,
            template_description text,
            template_category varchar(100) DEFAULT 'general',
            template_html longtext NOT NULL,
            template_placeholders text,
            s3_url varchar(500),
            encryption_key varchar(255),
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY template_slug (template_slug),
            KEY template_category (template_category),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Document drafts table
        $drafts_table = $wpdb->prefix . 'klage_document_drafts';
        
        $sql2 = "CREATE TABLE $drafts_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_id mediumint(9) NOT NULL,
            case_id bigint(20) UNSIGNED NULL,
            draft_html longtext NOT NULL,
            template_data longtext,
            status varchar(50) DEFAULT 'draft',
            pdf_generated_at datetime NULL,
            pdf_filename varchar(255) NULL,
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY case_id (case_id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        $temp_dir = KCDO_PLUGIN_PATH . 'temp/';
        if (file_exists($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}

// Initialize the plugin
global $klage_click_doc_out;
$klage_click_doc_out = new KlageClickDocOut();