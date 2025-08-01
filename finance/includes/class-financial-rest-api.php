<?php
/**
 * Financial Calculator REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Financial_REST_API {
    
    private $db_manager;
    private $calculator;
    
    public function __construct() {
        $this->db_manager = new CAH_Financial_DB_Manager();
        $this->calculator = new CAH_Financial_Calculator_Engine();
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        $namespace = 'cah-financial/v1';
        
        // Templates endpoints
        register_rest_route($namespace, '/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($namespace, '/templates/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_template'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Cost items endpoints
        register_rest_route($namespace, '/cost-items/template/(?P<template_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_template_cost_items'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Calculator endpoints
        register_rest_route($namespace, '/calculate', array(
            'methods' => 'POST',
            'callback' => array($this, 'calculate_totals'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    public function get_templates(WP_REST_Request $request) {
        $templates = $this->db_manager->get_templates();
        return new WP_REST_Response($templates, 200);
    }
    
    public function get_template(WP_REST_Request $request) {
        $template_id = $request->get_param('id');
        $template = $this->db_manager->get_template($template_id);
        
        if (!$template) {
            return new WP_Error('template_not_found', 'Template not found', array('status' => 404));
        }
        
        return new WP_REST_Response($template, 200);
    }
    
    public function get_template_cost_items(WP_REST_Request $request) {
        $template_id = $request->get_param('template_id');
        $items = $this->db_manager->get_cost_items_by_template($template_id);
        
        return new WP_REST_Response($items, 200);
    }
    
    public function calculate_totals(WP_REST_Request $request) {
        $items = $request->get_param('items');
        $vat_rate = $request->get_param('vat_rate') ?: 19.00;
        
        if (!is_array($items)) {
            return new WP_Error('invalid_items', 'Items must be an array', array('status' => 400));
        }
        
        // Convert array data to objects
        $cost_items = array();
        foreach ($items as $item) {
            $cost_items[] = (object) $item;
        }
        
        $totals = $this->calculator->calculate_totals($cost_items, $vat_rate);
        
        return new WP_REST_Response($totals, 200);
    }
}