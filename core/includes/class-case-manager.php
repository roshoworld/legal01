<?php
/**
 * Case Manager class - Simplified version
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Case_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create a new GDPR spam case
     */
    public function create_case($case_data) {
        // Basic case creation
        $case_insert_data = array(
            'case_id' => $case_data['case_id'] ?? $this->generate_case_id(),
            'case_creation_date' => current_time('mysql'),
            'case_status' => 'draft',
            'case_priority' => $case_data['case_priority'] ?? 'medium',
            'total_amount' => 548.11 // Standard GDPR amount
        );
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_cases',
            $case_insert_data,
            array('%s', '%s', '%s', '%s', '%f')
        );
        
        if ($result === false) {
            return new WP_Error('creation_failed', 'Fehler beim Erstellen des Falls');
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Generate case ID
     */
    public function generate_case_id() {
        return 'SPAM-' . date('Y') . '-' . str_pad(wp_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}