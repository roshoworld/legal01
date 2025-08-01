<?php
/**
 * REST API class - Simplified version
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Rest_API {
    
    private $namespace = 'klage-click/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Status endpoint
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => '__return_true'
        ));
        
        // Cases endpoint
        register_rest_route($this->namespace, '/cases', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cases'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Get system status
     */
    public function get_status($request) {
        $status = array(
            'plugin_version' => CAH_PLUGIN_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'timestamp' => current_time('mysql')
        );
        
        return rest_ensure_response($status);
    }
    
    /**
     * Get cases
     */
    public function get_cases($request) {
        global $wpdb;
        
        $cases = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}klage_cases ORDER BY case_creation_date DESC LIMIT 10");
        
        return rest_ensure_response($cases);
    }
    
    /**
     * Check permissions
     */
    public function check_permissions($request) {
        return current_user_can('manage_options');
    }
}