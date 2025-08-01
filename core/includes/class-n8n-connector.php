<?php
/**
 * N8N Connector class - Simplified version
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_N8N_Connector {
    
    private $n8n_url;
    private $n8n_key;
    
    public function __construct() {
        $this->n8n_url = get_option('klage_click_n8n_url');
        $this->n8n_key = get_option('klage_click_n8n_key');
    }
    
    /**
     * Send case data to N8N
     */
    public function send_case_data($case_data) {
        if (empty($this->n8n_url) || empty($this->n8n_key)) {
            return new WP_Error('n8n_not_configured', 'N8N ist nicht konfiguriert');
        }
        
        // For now, just log that we would send to N8N
        error_log('Klage.Click: Would send case data to N8N: ' . json_encode($case_data));
        
        return array(
            'success' => true,
            'message' => 'N8N Integration bereit (Test-Modus)'
        );
    }
    
    /**
     * Test N8N connection
     */
    public function test_connection() {
        if (empty($this->n8n_url)) {
            return new WP_Error('n8n_not_configured', 'N8N URL nicht konfiguriert');
        }
        
        return array(
            'success' => true,
            'message' => 'N8N Verbindung konfiguriert'
        );
    }
}