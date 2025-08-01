<?php
/**
 * Database Manager for Document Analysis Plugin
 * Handles database tables and schema management
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Document_in_DB_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create all necessary database tables
     */
    public function create_tables() {
        $this->create_communications_table();
        $this->create_attachments_table();
        $this->create_audit_table();
        
        // Update database version
        update_option('cah_doc_in_db_version', CAH_DOC_IN_PLUGIN_VERSION);
    }
    
    /**
     * Create communications table
     */
    private function create_communications_table() {
        $table_name = $this->wpdb->prefix . 'cah_document_in_communications';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            case_number varchar(50) DEFAULT NULL,
            debtor_name varchar(200) DEFAULT NULL,
            email_subject varchar(500) NOT NULL,
            email_sender varchar(255) NOT NULL,
            email_received_date datetime NOT NULL,
            has_attachment tinyint(1) DEFAULT 0,
            message_id varchar(255) NOT NULL,
            summary text,
            category varchar(100) DEFAULT NULL,
            is_new_case varchar(20) DEFAULT NULL,
            confidence_score tinyint(3) DEFAULT NULL,
            extracted_entities text,
            assignment_status enum('unassigned', 'assigned', 'new_case_created') DEFAULT 'unassigned',
            matched_case_id bigint(20) unsigned DEFAULT NULL,
            match_confidence tinyint(3) DEFAULT NULL,
            pipedream_execution_id varchar(100) DEFAULT NULL,
            processed_by_user_id bigint(20) unsigned DEFAULT NULL,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY case_number (case_number),
            KEY debtor_name (debtor_name),
            KEY email_sender (email_sender),
            KEY assignment_status (assignment_status),
            KEY matched_case_id (matched_case_id),
            KEY created_at (created_at)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create attachments table
     */
    private function create_attachments_table() {
        $table_name = $this->wpdb->prefix . 'cah_document_in_attachments';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            communication_id bigint(20) unsigned NOT NULL,
            wp_attachment_id bigint(20) unsigned NOT NULL,
            original_filename varchar(255) NOT NULL,
            file_size bigint(20) DEFAULT NULL,
            file_type varchar(100) DEFAULT NULL,
            attachment_url varchar(500) NOT NULL,
            upload_status enum('pending', 'completed', 'failed') DEFAULT 'pending',
            ocr_extracted tinyint(1) DEFAULT 0,
            ocr_content longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY communication_id (communication_id),
            KEY wp_attachment_id (wp_attachment_id),
            KEY upload_status (upload_status)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create audit table for tracking operations
     */
    private function create_audit_table() {
        $table_name = $this->wpdb->prefix . 'cah_document_in_audit';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            communication_id bigint(20) unsigned DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            action_details text DEFAULT NULL,
            pipedream_execution_id varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            execution_time decimal(10,4) DEFAULT NULL,
            api_response_code int(3) DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY communication_id (communication_id),
            KEY action_type (action_type),
            KEY pipedream_execution_id (pipedream_execution_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get communication by ID
     */
    public function get_communication($id) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_communications';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Insert new communication
     */
    public function insert_communication($data) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_communications';
        
        $result = $this->wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update communication
     */
    public function update_communication($id, $data) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_communications';
        
        return $this->wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    /**
     * Get unassigned communications
     */
    public function get_unassigned_communications($limit = 50, $offset = 0) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_communications';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE assignment_status = 'unassigned' 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * Get communications by case ID
     */
    public function get_communications_by_case($case_id) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_communications';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE matched_case_id = %d 
             ORDER BY created_at DESC",
            $case_id
        ));
    }
    
    /**
     * Log audit trail
     */
    public function log_audit($data) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_audit';
        
        // Add default values
        $defaults = array(
            'user_id' => get_current_user_id(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
        
        $data = array_merge($defaults, $data);
        
        return $this->wpdb->insert($table_name, $data);
    }
    
    /**
     * Get audit logs for communication
     */
    public function get_communication_audit($communication_id) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_audit';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE communication_id = %d 
             ORDER BY created_at DESC",
            $communication_id
        ));
    }
    
    /**
     * Insert attachment record
     */
    public function insert_attachment($data) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_attachments';
        
        $result = $this->wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get attachments for communication
     */
    public function get_communication_attachments($communication_id) {
        $table_name = $this->wpdb->prefix . 'cah_document_in_attachments';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE communication_id = %d 
             ORDER BY created_at DESC",
            $communication_id
        ));
    }
}