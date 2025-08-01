<?php
/**
 * CRM Audience Manager
 * Handles audience segmentation and targeting functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_Audience_Manager {
    
    private $db_manager;
    
    public function __construct() {
        $this->db_manager = new LA_CRM_Database();
    }
    
    /**
     * Create new audience
     */
    public function create_audience($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_audience';
        
        $defaults = array(
            'contact_count' => 0,
            'active_status' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['audience_name']) || empty($data['criteria'])) {
            return false;
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            $audience_id = $wpdb->insert_id;
            
            // Calculate and update contact count
            $this->update_contact_count($audience_id);
            
            // Log in audit trail
            $this->db_manager->log_audit(
                'audience_created',
                sprintf('Audience created: %s', $data['audience_name'])
            );
            
            return $audience_id;
        }
        
        return false;
    }
    
    /**
     * Get all audiences
     */
    public function get_audiences() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_audience';
        
        return $wpdb->get_results("
            SELECT * FROM $table 
            WHERE active_status = 1 
            ORDER BY audience_name ASC
        ");
    }
    
    /**
     * Get single audience
     */
    public function get_audience($audience_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_audience';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND active_status = 1
        ", $audience_id));
    }
    
    /**
     * Update audience
     */
    public function update_audience($audience_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_audience';
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $audience_id)
        );
        
        if ($result !== false) {
            // Recalculate contact count if criteria changed
            if (isset($data['criteria'])) {
                $this->update_contact_count($audience_id);
            }
            
            // Log update
            $this->db_manager->log_audit(
                'audience_updated',
                sprintf('Audience updated: ID %d', $audience_id)
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update contact count for audience
     */
    private function update_contact_count($audience_id) {
        $contacts = $this->get_audience_contacts($audience_id);
        $count = count($contacts);
        
        global $wpdb;
        $table = $wpdb->prefix . 'klage_crm_audience';
        
        $wpdb->update(
            $table,
            array(
                'contact_count' => $count,
                'last_updated' => current_time('mysql')
            ),
            array('id' => $audience_id)
        );
        
        return $count;
    }
    
    /**
     * Get contacts that match audience criteria
     */
    public function get_audience_contacts($audience_id) {
        $audience = $this->get_audience($audience_id);
        
        if (!$audience) {
            return array();
        }
        
        $criteria = json_decode($audience->criteria, true);
        
        if (!$criteria) {
            return array();
        }
        
        return $this->filter_contacts_by_criteria($criteria);
    }
    
    /**
     * Filter contacts based on criteria
     */
    private function filter_contacts_by_criteria($criteria) {
        global $wpdb;
        
        $where_conditions = array();
        $params = array();
        
        // Base query
        $sql = "SELECT DISTINCT c.* FROM {$wpdb->prefix}klage_contacts c";
        $joins = array();
        
        // Contact type filter
        if (!empty($criteria['contact_type'])) {
            $where_conditions[] = "c.contact_type = %s";
            $params[] = $criteria['contact_type'];
        }
        
        // Company/person filter
        if (!empty($criteria['has_company'])) {
            if ($criteria['has_company'] === 'yes') {
                $where_conditions[] = "c.company_name IS NOT NULL AND c.company_name != ''";
            } elseif ($criteria['has_company'] === 'no') {
                $where_conditions[] = "(c.company_name IS NULL OR c.company_name = '')";
            }
        }
        
        // Case-related filters
        if (!empty($criteria['case_role']) || !empty($criteria['case_status'])) {
            $joins[] = "LEFT JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.contact_id";
            $joins[] = "LEFT JOIN {$wpdb->prefix}klage_cases cases ON cc.case_id = cases.id";
            
            if (!empty($criteria['case_role'])) {
                $where_conditions[] = "cc.role = %s";
                $params[] = $criteria['case_role'];
            }
            
            if (!empty($criteria['case_status'])) {
                $where_conditions[] = "cases.case_status = %s";
                $params[] = $criteria['case_status'];
            }
        }
        
        // Communication activity filters
        if (!empty($criteria['last_communication_days'])) {
            $joins[] = "LEFT JOIN {$wpdb->prefix}klage_crm_communications comm ON c.id = comm.contact_id";
            
            $days = intval($criteria['last_communication_days']);
            $where_conditions[] = "comm.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $params[] = $days;
        }
        
        // Email domain filter
        if (!empty($criteria['email_domain'])) {
            $where_conditions[] = "c.email LIKE %s";
            $params[] = '%@' . $criteria['email_domain'];
        }
        
        // Date range filters
        if (!empty($criteria['created_after'])) {
            $where_conditions[] = "c.created_at >= %s";
            $params[] = $criteria['created_after'];
        }
        
        if (!empty($criteria['created_before'])) {
            $where_conditions[] = "c.created_at <= %s";
            $params[] = $criteria['created_before'];
        }
        
        // Build final query
        if (!empty($joins)) {
            $sql .= " " . implode(" ", $joins);
        }
        
        // Always filter for active contacts
        $where_conditions[] = "c.active_status = 1";
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        $sql .= " ORDER BY c.last_name, c.first_name";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }
    
    /**
     * Get predefined audience templates
     */
    public function get_audience_templates() {
        return array(
            'all_clients' => array(
                'name' => 'Alle Mandanten',
                'description' => 'Alle aktiven Mandanten',
                'criteria' => json_encode(array('contact_type' => 'client'))
            ),
            'active_cases' => array(
                'name' => 'Mandanten mit aktiven Fällen',
                'description' => 'Mandanten mit laufenden Verfahren',
                'criteria' => json_encode(array(
                    'contact_type' => 'client',
                    'case_status' => 'active'
                ))
            ),
            'recent_contacts' => array(
                'name' => 'Kürzliche Kontakte',
                'description' => 'Kontakte der letzten 30 Tage',
                'criteria' => json_encode(array(
                    'last_communication_days' => 30
                ))
            ),
            'companies' => array(
                'name' => 'Unternehmen',
                'description' => 'Alle Firmenkontakte',
                'criteria' => json_encode(array(
                    'has_company' => 'yes'
                ))
            ),
            'individuals' => array(
                'name' => 'Privatpersonen',
                'description' => 'Alle Privatkontakte',
                'criteria' => json_encode(array(
                    'has_company' => 'no'
                ))
            )
        );
    }
    
    /**
     * Create audience from template
     */
    public function create_from_template($template_key, $custom_name = null) {
        $templates = $this->get_audience_templates();
        
        if (!isset($templates[$template_key])) {
            return false;
        }
        
        $template = $templates[$template_key];
        
        $data = array(
            'audience_name' => $custom_name ?: $template['name'],
            'description' => $template['description'],
            'criteria' => $template['criteria']
        );
        
        return $this->create_audience($data);
    }
    
    /**
     * Delete audience (soft delete)
     */
    public function delete_audience($audience_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_audience';
        
        $result = $wpdb->update(
            $table,
            array('active_status' => 0),
            array('id' => $audience_id)
        );
        
        if ($result !== false) {
            // Log deletion
            $this->db_manager->log_audit(
                'audience_deleted',
                sprintf('Audience deleted: ID %d', $audience_id)
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get audience statistics
     */
    public function get_audience_stats($audience_id) {
        $audience = $this->get_audience($audience_id);
        
        if (!$audience) {
            return false;
        }
        
        $contacts = $this->get_audience_contacts($audience_id);
        
        $stats = array(
            'total_contacts' => count($contacts),
            'companies' => 0,
            'individuals' => 0,
            'with_cases' => 0,
            'recent_activity' => 0
        );
        
        foreach ($contacts as $contact) {
            if (!empty($contact->company_name)) {
                $stats['companies']++;
            } else {
                $stats['individuals']++;
            }
        }
        
        return $stats;
    }
}