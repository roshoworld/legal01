<?php
/**
 * CRM Communication Manager
 * Handles all communication functionality (email, phone, meetings, etc.)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_Communication_Manager {
    
    private $db_manager;
    
    public function __construct() {
        $this->db_manager = new LA_CRM_Database();
    }
    
    /**
     * Create new communication record
     */
    public function create_communication($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        $defaults = array(
            'communication_type' => 'email',
            'direction' => 'outbound',
            'status' => 'draft',
            'priority' => 'normal',
            'active_status' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            $communication_id = $wpdb->insert_id;
            
            // Log in audit trail
            $this->db_manager->log_audit(
                'communication_created',
                sprintf('Communication created: %s - %s', $data['communication_type'], $data['subject']),
                $data['contact_id'],
                $data['case_id']
            );
            
            return $communication_id;
        }
        
        return false;
    }
    
    /**
     * Get communications for a contact
     */
    public function get_contact_communications($contact_id, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                comm.*,
                c.first_name,
                c.last_name,
                c.company_name,
                cases.case_id as case_reference
            FROM $table comm
            JOIN {$wpdb->prefix}klage_contacts c ON comm.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON comm.case_id = cases.id
            WHERE comm.contact_id = %d AND comm.active_status = 1
            ORDER BY comm.created_at DESC
            LIMIT %d
        ", $contact_id, $limit));
    }
    
    /**
     * Get communications for a case
     */
    public function get_case_communications($case_id, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                comm.*,
                c.first_name,
                c.last_name,
                c.company_name
            FROM $table comm
            JOIN {$wpdb->prefix}klage_contacts c ON comm.contact_id = c.id
            WHERE comm.case_id = %d AND comm.active_status = 1
            ORDER BY comm.created_at DESC
            LIMIT %d
        ", $case_id, $limit));
    }
    
    /**
     * Update communication status
     */
    public function update_status($communication_id, $status, $additional_data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        $update_data = array('status' => $status);
        
        // Add timestamp fields based on status
        switch ($status) {
            case 'sent':
                $update_data['sent_at'] = current_time('mysql');
                break;
            case 'delivered':
                // sent_at should already be set, don't override
                break;
            case 'read':
                $update_data['read_at'] = current_time('mysql');
                break;
            case 'replied':
                $update_data['replied_at'] = current_time('mysql');
                break;
        }
        
        $update_data = array_merge($update_data, $additional_data);
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $communication_id)
        );
        
        if ($result !== false) {
            // Log status update
            $communication = $this->get_communication($communication_id);
            if ($communication) {
                $this->db_manager->log_audit(
                    'communication_status_updated',
                    sprintf('Communication status updated to: %s', $status),
                    $communication->contact_id,
                    $communication->case_id
                );
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get single communication
     */
    public function get_communication($communication_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                comm.*,
                c.first_name,
                c.last_name,
                c.company_name,
                c.email,
                cases.case_id as case_reference
            FROM $table comm
            JOIN {$wpdb->prefix}klage_contacts c ON comm.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON comm.case_id = cases.id
            WHERE comm.id = %d AND comm.active_status = 1
        ", $communication_id));
    }
    
    /**
     * Get communication templates
     */
    public function get_templates($type = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_templates';
        
        $where_clause = "WHERE active_status = 1";
        $params = array();
        
        if ($type) {
            $where_clause .= " AND template_type = %s";
            $params[] = $type;
        }
        
        $sql = "SELECT * FROM $table $where_clause ORDER BY template_name";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }
    
    /**
     * Apply template to communication
     */
    public function apply_template($template_id, $placeholders = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_templates';
        
        $template = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table WHERE id = %d AND active_status = 1
        ", $template_id));
        
        if (!$template) {
            return false;
        }
        
        $subject = $template->subject;
        $content = $template->content;
        
        // Replace placeholders
        foreach ($placeholders as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Update template usage count
        $wpdb->query($wpdb->prepare("
            UPDATE $table SET usage_count = usage_count + 1 WHERE id = %d
        ", $template_id));
        
        return array(
            'subject' => $subject,
            'content' => $content,
            'template_id' => $template_id
        );
    }
    
    /**
     * Schedule communication for later sending
     */
    public function schedule_communication($communication_id, $scheduled_at) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        $result = $wpdb->update(
            $table,
            array(
                'scheduled_at' => $scheduled_at,
                'status' => 'draft'
            ),
            array('id' => $communication_id)
        );
        
        if ($result !== false) {
            // Log scheduling
            $communication = $this->get_communication($communication_id);
            if ($communication) {
                $this->db_manager->log_audit(
                    'communication_scheduled',
                    sprintf('Communication scheduled for: %s', $scheduled_at),
                    $communication->contact_id,
                    $communication->case_id
                );
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get scheduled communications ready to send
     */
    public function get_scheduled_communications() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        return $wpdb->get_results("
            SELECT 
                comm.*,
                c.first_name,
                c.last_name,
                c.company_name,
                c.email
            FROM $table comm
            JOIN {$wpdb->prefix}klage_contacts c ON comm.contact_id = c.id
            WHERE comm.scheduled_at <= NOW() 
                AND comm.status = 'draft' 
                AND comm.active_status = 1
            ORDER BY comm.scheduled_at ASC
        ");
    }
    
    /**
     * Get communication statistics for a contact
     */
    public function get_contact_stats($contact_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_communications,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count,
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_count,
                MAX(created_at) as last_communication
            FROM $table
            WHERE contact_id = %d AND active_status = 1
        ", $contact_id));
        
        return $stats;
    }
    
    /**
     * Delete communication (soft delete)
     */
    public function delete_communication($communication_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_communications';
        
        $result = $wpdb->update(
            $table,
            array('active_status' => 0),
            array('id' => $communication_id)
        );
        
        if ($result !== false) {
            // Log deletion
            $communication = $wpdb->get_row($wpdb->prepare("
                SELECT contact_id, case_id, subject FROM $table WHERE id = %d
            ", $communication_id));
            
            if ($communication) {
                $this->db_manager->log_audit(
                    'communication_deleted',
                    sprintf('Communication deleted: %s', $communication->subject),
                    $communication->contact_id,
                    $communication->case_id
                );
            }
            
            return true;
        }
        
        return false;
    }
}