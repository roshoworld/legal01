<?php
/**
 * Court Manager class - Simplified version
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Court_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get court by postal code
     */
    public function get_court_by_postal_code($postal_code) {
        // Simple court assignment based on postal code
        $court_mapping = array(
            '60' => 'Amtsgericht Frankfurt am Main', // Frankfurt
            '80' => 'Amtsgericht München',           // München
            '10' => 'Amtsgericht Berlin-Mitte',     // Berlin
            '20' => 'Amtsgericht Hamburg',          // Hamburg
        );
        
        $postal_prefix = substr($postal_code, 0, 2);
        $court_name = isset($court_mapping[$postal_prefix]) ? 
                      $court_mapping[$postal_prefix] : 
                      'Amtsgericht Frankfurt am Main'; // Default
        
        return (object) array(
            'id' => 1,
            'court_name' => $court_name,
            'court_address' => 'Gerichtsstraße 2, 60313 Frankfurt am Main',
            'court_egvp_id' => 'AG.FFM.001'
        );
    }
}