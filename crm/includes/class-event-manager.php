<?php
/**
 * CRM Event Manager
 * Handles all event functionality (meetings, deadlines, tasks, etc.)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_Event_Manager {
    
    private $db_manager;
    
    public function __construct() {
        $this->db_manager = new LA_CRM_Database();
    }
    
    /**
     * Create new event
     */
    public function create_event($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        $defaults = array(
            'event_type' => 'meeting',
            'status' => 'scheduled',
            'priority' => 'normal',
            'reminder_minutes' => 15,
            'active_status' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['contact_id']) || empty($data['title']) || empty($data['start_datetime'])) {
            return false;
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            $event_id = $wpdb->insert_id;
            
            // Log in audit trail
            $this->db_manager->log_audit(
                'event_created',
                sprintf('Event created: %s - %s', $data['event_type'], $data['title']),
                $data['contact_id'],
                $data['case_id']
            );
            
            return $event_id;
        }
        
        return false;
    }
    
    /**
     * Get events for a contact
     */
    public function get_contact_events($contact_id, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name,
                cases.case_id as case_reference
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON e.case_id = cases.id
            WHERE e.contact_id = %d AND e.active_status = 1
            ORDER BY e.start_datetime ASC
            LIMIT %d
        ", $contact_id, $limit));
    }
    
    /**
     * Get events for a case
     */
    public function get_case_events($case_id, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            WHERE e.case_id = %d AND e.active_status = 1
            ORDER BY e.start_datetime ASC
            LIMIT %d
        ", $case_id, $limit));
    }
    
    /**
     * Get upcoming events
     */
    public function get_upcoming_events($days_ahead = 30, $limit = 20) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        $end_date = date('Y-m-d H:i:s', strtotime("+{$days_ahead} days"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name,
                cases.case_id as case_reference
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON e.case_id = cases.id
            WHERE e.start_datetime BETWEEN NOW() AND %s 
                AND e.active_status = 1 
                AND e.status IN ('scheduled', 'confirmed')
            ORDER BY e.start_datetime ASC
            LIMIT %d
        ", $end_date, $limit));
    }
    
    /**
     * Get overdue events
     */
    public function get_overdue_events($limit = 20) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name,
                cases.case_id as case_reference
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON e.case_id = cases.id
            WHERE e.start_datetime < NOW() 
                AND e.active_status = 1 
                AND e.status IN ('scheduled', 'confirmed')
            ORDER BY e.start_datetime DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Update event status
     */
    public function update_status($event_id, $status, $outcome = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        $update_data = array('status' => $status);
        
        if ($outcome !== null) {
            $update_data['outcome'] = $outcome;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $event_id)
        );
        
        if ($result !== false) {
            // Log status update
            $event = $this->get_event($event_id);
            if ($event) {
                $this->db_manager->log_audit(
                    'event_status_updated',
                    sprintf('Event status updated to: %s', $status),
                    $event->contact_id,
                    $event->case_id
                );
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get single event
     */
    public function get_event($event_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name,
                c.email,
                cases.case_id as case_reference
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON e.case_id = cases.id
            WHERE e.id = %d AND e.active_status = 1
        ", $event_id));
    }
    
    /**
     * Get events that need reminders
     */
    public function get_reminder_events() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        return $wpdb->get_results("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name,
                c.email
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            WHERE DATE_SUB(e.start_datetime, INTERVAL e.reminder_minutes MINUTE) <= NOW()
                AND e.start_datetime > NOW()
                AND e.active_status = 1 
                AND e.status IN ('scheduled', 'confirmed')
            ORDER BY e.start_datetime ASC
        ");
    }
    
    /**
     * Get event statistics for a contact
     */
    public function get_contact_stats($contact_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN start_datetime < NOW() AND status IN ('scheduled', 'confirmed') THEN 1 ELSE 0 END) as overdue_count,
                MAX(start_datetime) as next_event_date
            FROM $table
            WHERE contact_id = %d AND active_status = 1
        ", $contact_id));
        
        return $stats;
    }
    
    /**
     * Delete event (soft delete)
     */
    public function delete_event($event_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        $result = $wpdb->update(
            $table,
            array('active_status' => 0),
            array('id' => $event_id)
        );
        
        if ($result !== false) {
            // Log deletion
            $event = $wpdb->get_row($wpdb->prepare("
                SELECT contact_id, case_id, title FROM $table WHERE id = %d
            ", $event_id));
            
            if ($event) {
                $this->db_manager->log_audit(
                    'event_deleted',
                    sprintf('Event deleted: %s', $event->title),
                    $event->contact_id,
                    $event->case_id
                );
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get calendar view data for a date range
     */
    public function get_calendar_events($start_date, $end_date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'klage_crm_events';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                e.*,
                c.first_name,
                c.last_name,
                c.company_name,
                cases.case_id as case_reference
            FROM $table e
            JOIN {$wpdb->prefix}klage_contacts c ON e.contact_id = c.id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON e.case_id = cases.id
            WHERE DATE(e.start_datetime) BETWEEN %s AND %s 
                AND e.active_status = 1
            ORDER BY e.start_datetime ASC
        ", $start_date, $end_date));
    }
}