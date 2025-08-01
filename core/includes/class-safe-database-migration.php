<?php
/**
 * EMERGENCY DATABASE RECOVERY v1.9.0
 * Safe migration that only adds new fields without data loss
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Safe_Database_Migration {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * SAFE MIGRATION - Only adds new v1.9.0 fields to existing tables
     * NEVER drops or recreates tables
     */
    public function safe_migrate_to_v190() {
        $results = array(
            'success' => true,
            'message' => '',
            'columns_added' => 0,
            'errors' => array()
        );
        
        try {
            // Add new fields to cases table (if they don't exist)
            $this->safe_add_column('klage_cases', 'external_id', 'varchar(100) DEFAULT NULL');
            $this->safe_add_column('klage_cases', 'outbound_letters_status', 'varchar(50) DEFAULT NULL');
            $this->safe_add_column('klage_cases', 'outbound_letters_pdf_url', 'text DEFAULT NULL');
            $this->safe_add_column('klage_cases', 'art15_claim_damages', 'decimal(10,2) DEFAULT NULL');
            $this->safe_add_column('klage_cases', 'case_documents_attachments', 'text DEFAULT NULL');
            $this->safe_add_column('klage_cases', 'number_of_spam_emails', 'int(5) DEFAULT NULL');
            
            // Add indexes for new fields
            $this->safe_add_index('klage_cases', 'external_id');
            $this->safe_add_index('klage_cases', 'import_source');
            
            // Add new fields to clients table
            $this->safe_add_column('klage_clients', 'external_user_id', 'varchar(100) DEFAULT NULL');
            $this->safe_add_column('klage_clients', 'users_street', 'varchar(150) DEFAULT NULL');
            $this->safe_add_column('klage_clients', 'users_street_number', 'varchar(20) DEFAULT NULL');
            $this->safe_add_column('klage_clients', 'users_postal_code', 'varchar(20) DEFAULT NULL');
            $this->safe_add_column('klage_clients', 'users_city', 'varchar(100) DEFAULT NULL');
            
            // Add index for external_user_id
            $this->safe_add_index('klage_clients', 'external_user_id');
            
            // Add new field to emails table
            $this->safe_add_column('klage_emails', 'emails_tracking_pixel', 'text DEFAULT NULL');
            
            // Create new tables ONLY if they don't exist
            $this->safe_create_table('klage_import_configs', "
                CREATE TABLE {$this->wpdb->prefix}klage_import_configs (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    config_name varchar(100) NOT NULL,
                    client_type varchar(50) NOT NULL,
                    field_mappings text NOT NULL,
                    validation_rules text DEFAULT NULL,
                    default_values text DEFAULT NULL,
                    is_active tinyint(1) DEFAULT 1,
                    created_by bigint(20) unsigned NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY client_type (client_type),
                    KEY is_active (is_active)
                ) {$this->wpdb->get_charset_collate()}
            ");
            
            $this->safe_create_table('klage_import_history', "
                CREATE TABLE {$this->wpdb->prefix}klage_import_history (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    import_type varchar(50) NOT NULL,
                    client_type varchar(50) NOT NULL,
                    filename varchar(255) DEFAULT NULL,
                    total_rows int(10) unsigned DEFAULT 0,
                    successful_imports int(10) unsigned DEFAULT 0,
                    failed_imports int(10) unsigned DEFAULT 0,
                    import_status varchar(20) DEFAULT 'completed',
                    error_log text DEFAULT NULL,
                    config_used text DEFAULT NULL,
                    imported_by bigint(20) unsigned NOT NULL,
                    import_duration int(10) unsigned DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY import_type (import_type),
                    KEY client_type (client_type),
                    KEY import_status (import_status),
                    KEY created_at (created_at)
                ) {$this->wpdb->get_charset_collate()}
            ");
            
            $results['message'] = 'Safe migration to v1.9.0 completed successfully';
            $results['columns_added'] = $this->columns_added_count;
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $results['message'] = 'Migration failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    private $columns_added_count = 0;
    
    /**
     * Safely add column only if it doesn't exist
     */
    private function safe_add_column($table, $column, $definition) {
        $table_name = $this->wpdb->prefix . $table;
        
        // Check if column exists
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
        
        if (empty($columns)) {
            $sql = "ALTER TABLE $table_name ADD COLUMN $column $definition";
            $result = $this->wpdb->query($sql);
            
            if ($result !== false) {
                $this->columns_added_count++;
                error_log("CAH Safe Migration: Added column $column to $table");
            } else {
                error_log("CAH Safe Migration: Failed to add column $column to $table: " . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Safely add index only if it doesn't exist
     */
    private function safe_add_index($table, $column) {
        $table_name = $this->wpdb->prefix . $table;
        
        // Check if index exists
        $indexes = $this->wpdb->get_results("SHOW INDEX FROM $table_name WHERE Column_name = '$column'");
        
        if (empty($indexes)) {
            $sql = "ALTER TABLE $table_name ADD INDEX $column ($column)";
            $result = $this->wpdb->query($sql);
            
            if ($result !== false) {
                error_log("CAH Safe Migration: Added index on $column for $table");
            }
        }
    }
    
    /**
     * Safely create table only if it doesn't exist
     */
    private function safe_create_table($table, $create_sql) {
        $table_name = $this->wpdb->prefix . $table;
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            $result = $this->wpdb->query($create_sql);
            
            if ($result !== false) {
                error_log("CAH Safe Migration: Created table $table");
            } else {
                error_log("CAH Safe Migration: Failed to create table $table: " . $this->wpdb->last_error);
            }
        }
    }
}