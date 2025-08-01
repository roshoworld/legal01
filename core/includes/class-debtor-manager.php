<?php
/**
 * Debtor Manager class - Simplified version
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Debtor_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create debtor
     */
    public function create_debtor($debtor_data) {
        $insert_data = array(
            'debtors_name' => sanitize_text_field($debtor_data['debtors_name']),
            'debtors_street' => sanitize_text_field($debtor_data['debtors_street']),
            'debtors_house_number' => sanitize_text_field($debtor_data['debtors_house_number']),
            'debtors_postal_code' => sanitize_text_field($debtor_data['debtors_postal_code']),
            'debtors_city' => sanitize_text_field($debtor_data['debtors_city']),
            'debtors_country' => 'DE',
            'debtors_email' => sanitize_email($debtor_data['debtors_email'])
        );
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_debtors',
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }
}