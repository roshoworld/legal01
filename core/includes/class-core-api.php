<?php
/**
 * Core API for Legal Automation
 * Provides hooks and methods for admin plugins to interact with core functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CAH_Core_API {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize core API hooks
     */
    private function init_hooks() {
        // Provide core functionality to admin plugins
        add_action('init', array($this, 'register_core_capabilities'));
        
        // Case management API
        add_filter('cah_get_cases', array($this, 'get_cases'), 10, 2);
        add_filter('cah_get_case', array($this, 'get_case'), 10, 2);
        add_filter('cah_create_case', array($this, 'create_case'), 10, 2);
        add_filter('cah_update_case', array($this, 'update_case'), 10, 3);
        add_filter('cah_delete_case', array($this, 'delete_case'), 10, 2);
        
        // Contact management API
        add_filter('cah_get_contacts', array($this, 'get_contacts'), 10, 2);
        add_filter('cah_get_contact', array($this, 'get_contact'), 10, 2);
        
        // Court management API
        add_filter('cah_get_courts', array($this, 'get_courts'), 10, 2);
        
        // Financial API
        add_filter('cah_get_financials', array($this, 'get_financials'), 10, 2);
        
        // Audit API
        add_filter('cah_log_audit', array($this, 'log_audit'), 10, 2);
    }
    
    /**
     * Register core capabilities
     */
    public function register_core_capabilities() {
        // Admin plugin can check this to confirm core is loaded
        if (!defined('CAH_CORE_API_LOADED')) {
            define('CAH_CORE_API_LOADED', true);
        }
    }
    
    /**
     * Get cases with filtering and sorting
     */
    public function get_cases($args = array(), $format = 'objects') {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'search' => '',
            'sort_by' => 'case_creation_date',
            'sort_order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $where_conditions = array('c.active_status = 1');
        $query_params = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'c.case_status = %s';
            $query_params[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(c.case_id LIKE %s OR debtor_contact.email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Validate sort column
        $allowed_sort_columns = array(
            'case_id' => 'c.case_id',
            'case_status' => 'c.case_status', 
            'case_creation_date' => 'c.case_creation_date',
            'total_amount' => 'total_amount',
            'emails_sender_email' => 'debtor_contact.email'
        );
        
        $sort_column = isset($allowed_sort_columns[$args['sort_by']]) ? $allowed_sort_columns[$args['sort_by']] : 'c.case_creation_date';
        $sort_order = ($args['sort_order'] === 'ASC') ? 'ASC' : 'DESC';
        
        $query = "
            SELECT 
                c.id,
                c.case_id,
                c.case_creation_date,
                c.case_status,
                c.case_priority,
                COALESCE(CAST(c.claim_amount AS DECIMAL(10,2)), 0) as claim_amount,
                COALESCE(CAST(c.legal_fees AS DECIMAL(10,2)), 0) as legal_fees,
                COALESCE(CAST(c.court_fees AS DECIMAL(10,2)), 0) as court_fees,
                (COALESCE(CAST(c.claim_amount AS DECIMAL(10,2)), 0) + COALESCE(CAST(c.legal_fees AS DECIMAL(10,2)), 0) + COALESCE(CAST(c.court_fees AS DECIMAL(10,2)), 0)) as total_amount,
                debtor_contact.email as emails_sender_email
            FROM {$wpdb->prefix}klage_cases c
            LEFT JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.case_id AND cc.role = 'debtor' AND cc.active_status = 1
            LEFT JOIN {$wpdb->prefix}klage_contacts debtor_contact ON cc.contact_id = debtor_contact.id
            WHERE {$where_clause}
            ORDER BY {$sort_column} {$sort_order}
            LIMIT %d OFFSET %d
        ";
        
        $query_params[] = $args['limit'];
        $query_params[] = $args['offset'];
        
        if (!empty($query_params)) {
            $query = $wpdb->prepare($query, $query_params);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get single case by ID
     */
    public function get_case($case_id, $format = 'object') {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_cases WHERE id = %d
        ", $case_id));
    }
    
    /**
     * Create new case
     */
    public function create_case($case_data, $context = 'api') {
        // Delegate to core case manager
        if (class_exists('CAH_Case_Manager')) {
            $case_manager = new CAH_Case_Manager();
            return $case_manager->create_case($case_data);
        }
        
        return false;
    }
    
    /**
     * Update existing case
     */
    public function update_case($case_id, $case_data, $context = 'api') {
        // Delegate to core case manager
        if (class_exists('CAH_Case_Manager')) {
            $case_manager = new CAH_Case_Manager();
            return $case_manager->update_case($case_id, $case_data);
        }
        
        return false;
    }
    
    /**
     * Delete case
     */
    public function delete_case($case_id, $context = 'api') {
        // Delegate to core case manager
        if (class_exists('CAH_Case_Manager')) {
            $case_manager = new CAH_Case_Manager();
            return $case_manager->delete_case($case_id);
        }
        
        return false;
    }
    
    /**
     * Get contacts
     */
    public function get_contacts($args = array(), $format = 'objects') {
        global $wpdb;
        
        $defaults = array(
            'role' => '',
            'case_id' => '',
            'limit' => 50
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$wpdb->prefix}klage_contacts WHERE 1=1";
        $query_params = array();
        
        if (!empty($args['case_id'])) {
            $query .= " AND id IN (SELECT contact_id FROM {$wpdb->prefix}klage_case_contacts WHERE case_id = %d)";
            $query_params[] = $args['case_id'];
        }
        
        $query .= " ORDER BY last_name ASC LIMIT %d";
        $query_params[] = $args['limit'];
        
        if (!empty($query_params)) {
            $query = $wpdb->prepare($query, $query_params);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get single contact
     */
    public function get_contact($contact_id, $format = 'object') {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_contacts WHERE id = %d
        ", $contact_id));
    }
    
    /**
     * Get courts
     */
    public function get_courts($args = array(), $format = 'objects') {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}klage_courts 
            WHERE active_status = 1 
            ORDER BY court_name ASC
        ");
    }
    
    /**
     * Get financial records
     */
    public function get_financials($case_id, $format = 'objects') {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_financials 
            WHERE case_id = %d
            ORDER BY transaction_date DESC
        ", $case_id));
    }
    
    /**
     * Log audit entry
     */
    public function log_audit($audit_data, $context = 'api') {
        if (class_exists('CAH_Audit_Logger')) {
            $audit_logger = new CAH_Audit_Logger();
            return $audit_logger->log($audit_data);
        }
        
        return false;
    }
    
    /**
     * Check if admin plugin should override core menus
     */
    public function should_use_admin_plugin() {
        return class_exists('LegalAutomationAdmin') && defined('LAA_PLUGIN_VERSION');
    }
}