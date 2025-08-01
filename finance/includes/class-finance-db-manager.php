<?php
/**
 * Database Manager for Legal Automation Finance Plugin
 * Handles all database operations and schema management
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAF_Database_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create all financial calculator tables
     */
    public function create_tables() {
        $results = array(
            'success' => true,
            'message' => '',
            'details' => array()
        );
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Define table schemas
        $table_schemas = array(
            
            // Templates table - stores calculation templates (Scenario 1, 2, custom)
            'laf_templates' => "CREATE TABLE {$this->wpdb->prefix}laf_templates (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                template_name varchar(100) NOT NULL,
                template_type enum('scenario_1','scenario_2','custom') DEFAULT 'custom',
                description text DEFAULT NULL,
                is_default tinyint(1) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                created_by bigint(20) unsigned DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY template_type (template_type),
                KEY is_active (is_active)
            ) $charset_collate;",
            
            // Template items - stores cost items for each template
            'laf_template_items' => "CREATE TABLE {$this->wpdb->prefix}laf_template_items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                template_id bigint(20) unsigned NOT NULL,
                item_type enum('base_damage','partner_fees','communication_fees','vat','court_fees','interest','custom') NOT NULL,
                item_name varchar(200) NOT NULL,
                base_amount decimal(10,2) DEFAULT 0.00,
                percentage decimal(5,2) DEFAULT 0.00,
                calculation_method enum('fixed','percentage','formula') DEFAULT 'fixed',
                rvg_reference varchar(50) DEFAULT NULL,
                description text DEFAULT NULL,
                sort_order int(10) DEFAULT 0,
                is_configurable tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY template_id (template_id),
                KEY item_type (item_type)
            ) $charset_collate;",
            
            // Case calculations - stores applied calculations for each case
            'laf_case_calculations' => "CREATE TABLE {$this->wpdb->prefix}laf_case_calculations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                template_id bigint(20) unsigned DEFAULT NULL,
                calculation_status enum('draft','calculated','approved') DEFAULT 'draft',
                base_damage decimal(10,2) DEFAULT 350.00,
                dsgvo_damage decimal(10,2) DEFAULT 0.00,
                total_damage decimal(10,2) DEFAULT 350.00,
                partner_fees decimal(10,2) DEFAULT 0.00,
                communication_fees decimal(10,2) DEFAULT 0.00,
                court_fees decimal(10,2) DEFAULT 0.00,
                vat_amount decimal(10,2) DEFAULT 0.00,
                interest_amount decimal(10,2) DEFAULT 0.00,
                interest_start_date date DEFAULT NULL,
                interest_end_date date DEFAULT NULL,
                interest_rate decimal(5,2) DEFAULT 5.12,
                total_amount decimal(10,2) DEFAULT 0.00,
                notes text DEFAULT NULL,
                calculated_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY template_id (template_id),
                KEY calculation_status (calculation_status)
            ) $charset_collate;",
            
            // Case calculation items - detailed breakdown for each case
            'laf_case_items' => "CREATE TABLE {$this->wpdb->prefix}laf_case_items (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                calculation_id bigint(20) unsigned NOT NULL,
                item_type enum('base_damage','partner_fees','communication_fees','vat','court_fees','interest','custom') NOT NULL,
                item_name varchar(200) NOT NULL,
                amount decimal(10,2) DEFAULT 0.00,
                base_amount decimal(10,2) DEFAULT 0.00,
                percentage decimal(5,2) DEFAULT 0.00,
                calculation_method enum('fixed','percentage','formula') DEFAULT 'fixed',
                rvg_reference varchar(50) DEFAULT NULL,
                description text DEFAULT NULL,
                sort_order int(10) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY calculation_id (calculation_id),
                KEY item_type (item_type),
                FOREIGN KEY (calculation_id) REFERENCES {$this->wpdb->prefix}laf_case_calculations(id) ON DELETE CASCADE
            ) $charset_collate;",
            
            // Configuration table - stores system settings
            'laf_config' => "CREATE TABLE {$this->wpdb->prefix}laf_config (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                config_key varchar(100) NOT NULL UNIQUE,
                config_value text NOT NULL,
                config_type enum('string','number','decimal','boolean','json') DEFAULT 'string',
                description text DEFAULT NULL,
                is_user_configurable tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY config_key (config_key)
            ) $charset_collate;"
        );
        
        // Create each table using dbDelta
        foreach ($table_schemas as $table_name => $schema_sql) {
            try {
                $result = dbDelta($schema_sql);
                
                if (!empty($result)) {
                    $results['details'][] = "✅ $table_name: " . implode(', ', $result);
                } else {
                    $results['details'][] = "✅ $table_name: Schema already current";
                }
            } catch (Exception $e) {
                $results['success'] = false;
                $results['details'][] = "❌ $table_name: Error - " . $e->getMessage();
            }
        }
        
        // Insert default configuration if tables were created
        if ($results['success']) {
            $this->insert_default_config();
        }
        
        $results['message'] = $results['success'] 
            ? 'Database tables created successfully' 
            : 'Error creating some database tables';
        
        return $results;
    }
    
    /**
     * Insert default configuration values
     */
    private function insert_default_config() {
        $default_configs = array(
            array(
                'config_key' => 'base_interest_rate',
                'config_value' => '5.12',
                'config_type' => 'decimal',
                'description' => 'Base interest rate (Basiszinssatz + 5%)',
                'is_user_configurable' => 1
            ),
            array(
                'config_key' => 'vat_rate',
                'config_value' => '19.00',
                'config_type' => 'decimal', 
                'description' => 'VAT rate percentage',
                'is_user_configurable' => 1
            ),
            array(
                'config_key' => 'partner_fee_factor',
                'config_value' => '1.3',
                'config_type' => 'decimal',
                'description' => 'Partner fee factor (1,3 Geschäftsgebühr)',
                'is_user_configurable' => 1
            ),
            array(
                'config_key' => 'communication_fee_percentage',
                'config_value' => '20.00',
                'config_type' => 'decimal',
                'description' => 'Communication fee percentage of partner fees',
                'is_user_configurable' => 1
            ),
            array(
                'config_key' => 'court_fee_ranges',
                'config_value' => json_encode(array(
                    array('min' => 0, 'max' => 500, 'fee' => 32.00),
                    array('min' => 500, 'max' => 1000, 'fee' => 44.00),
                    array('min' => 1000, 'max' => 1500, 'fee' => 66.00)
                )),
                'config_type' => 'json',
                'description' => 'Court fee ranges based on dispute value',
                'is_user_configurable' => 1
            )
        );
        
        foreach ($default_configs as $config) {
            $this->wpdb->replace(
                $this->wpdb->prefix . 'laf_config',
                $config,
                array('%s', '%s', '%s', '%s', '%d')
            );
        }
    }
    
    /**
     * Get configuration value
     */
    public function get_config($key, $default = null) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT config_value, config_type FROM {$this->wpdb->prefix}laf_config WHERE config_key = %s",
                $key
            )
        );
        
        if (!$result) {
            return $default;
        }
        
        // Convert value based on type
        switch ($result->config_type) {
            case 'number':
                return (int) $result->config_value;
            case 'decimal':
                return (float) $result->config_value;
            case 'boolean':
                return (bool) $result->config_value;
            case 'json':
                return json_decode($result->config_value, true);
            default:
                return $result->config_value;
        }
    }
    
    /**
     * Set configuration value
     */
    public function set_config($key, $value, $type = 'string') {
        // Convert value based on type
        $store_value = $value;
        if ($type === 'json') {
            $store_value = json_encode($value);
        }
        
        return $this->wpdb->replace(
            $this->wpdb->prefix . 'laf_config',
            array(
                'config_key' => $key,
                'config_value' => $store_value,
                'config_type' => $type
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Update case total in core cases table
     */
    public function update_case_total($case_id, $total_amount) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_cases',
            array('total_amount' => $total_amount),
            array('id' => $case_id),
            array('%f'),
            array('%d')
        );
    }
    
    /**
     * Get table status for validation
     */
    public function get_table_status() {
        $tables = array('laf_templates', 'laf_template_items', 'laf_case_calculations', 'laf_case_items', 'laf_config');
        $status = array();
        
        foreach ($tables as $table) {
            $full_table_name = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            $count = $exists ? $this->wpdb->get_var("SELECT COUNT(*) FROM $full_table_name") : 0;
            
            $status[$table] = array(
                'exists' => !empty($exists),
                'count' => $count
            );
        }
        
        return $status;
    }
}