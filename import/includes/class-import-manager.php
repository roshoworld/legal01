<?php
/**
 * Import Manager - Multi-Source Data Integration
 * Handles CSV, Airtable, Pipedream, and other data sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Import_Manager {
    
    private $wpdb;
    private $data_sources;
    private $field_mapper;
    private $client_configs;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->init_data_sources();
        $this->add_hooks();
        
        // Initialize field mapper
        if (class_exists('LAI_Field_Mapper')) {
            $this->field_mapper = new LAI_Field_Mapper();
        }
        
        // Initialize client configs
        if (class_exists('LAI_Client_Configs')) {
            $this->client_configs = new LAI_Client_Configs();
        }
    }
    
    /**
     * Initialize supported data sources
     */
    private function init_data_sources() {
        $this->data_sources = array(
            'csv' => array(
                'name' => 'CSV File Import',
                'description' => 'Traditional CSV file upload and processing',
                'class' => 'LAI_CSV_Source',
                'enabled' => true,
                'icon' => 'dashicons-media-spreadsheet'
            ),
            'airtable' => array(
                'name' => 'Airtable Integration',
                'description' => 'Direct sync with Airtable databases',
                'class' => 'LAI_Airtable_Source',
                'enabled' => true,
                'icon' => 'dashicons-database',
                'auth_required' => true,
                'auth_fields' => array(
                    'api_key' => 'Airtable API Key',
                    'base_id' => 'Base ID',
                    'table_name' => 'Table Name'
                )
            ),
            'pipedream' => array(
                'name' => 'Pipedream Webhooks',
                'description' => 'Real-time case feeds via Pipedream automation',
                'class' => 'LAI_Pipedream_Source',
                'enabled' => true,
                'icon' => 'dashicons-networking',
                'webhook_enabled' => true,
                'auth_required' => true,
                'auth_fields' => array(
                    'webhook_secret' => 'Webhook Secret Key',
                    'source_identifier' => 'Source Identifier'
                )
            ),
            'api' => array(
                'name' => 'REST API Integration',
                'description' => 'Generic REST API data source',
                'class' => 'LAI_API_Source',
                'enabled' => true,
                'icon' => 'dashicons-rest-api',
                'auth_required' => true,
                'auth_fields' => array(
                    'api_url' => 'API Endpoint URL',
                    'auth_token' => 'Authentication Token',
                    'auth_method' => 'Authentication Method'
                )
            ),
            'forderungen_com' => array(
                'name' => 'Forderungen.com',
                'description' => 'Specialized integration for Forderungen.com exports',
                'class' => 'LAI_Forderungen_Source',
                'enabled' => true,
                'icon' => 'dashicons-businessman',
                'parent_source' => 'csv' // Extends CSV functionality
            )
        );
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // AJAX handlers for different import sources
        add_action('wp_ajax_lai_detect_fields', array($this, 'ajax_detect_fields'));
        add_action('wp_ajax_lai_preview_import', array($this, 'ajax_preview_import'));
        add_action('wp_ajax_lai_process_import', array($this, 'ajax_process_import'));
        
        // Airtable specific handlers
        add_action('wp_ajax_lai_airtable_connect', array($this, 'ajax_airtable_connect'));
        add_action('wp_ajax_lai_airtable_sync', array($this, 'ajax_airtable_sync'));
        add_action('wp_ajax_lai_airtable_test', array($this, 'ajax_airtable_test'));
        
        // Pipedream webhook handlers
        add_action('wp_ajax_nopriv_lai_pipedream_webhook', array($this, 'handle_pipedream_webhook'));
        add_action('wp_ajax_lai_pipedream_webhook', array($this, 'handle_pipedream_webhook'));
        add_action('wp_ajax_lai_pipedream_setup', array($this, 'ajax_pipedream_setup'));
        
        // Real-time sync scheduler
        add_action('lai_scheduled_sync', array($this, 'run_scheduled_sync'));
        
        // Register webhook endpoints
        add_action('init', array($this, 'register_webhook_endpoints'));
    }
    
    /**
     * Register webhook endpoints for external integrations
     */
    public function register_webhook_endpoints() {
        // Pipedream webhook endpoint
        add_rewrite_rule(
            '^lai-webhook/pipedream/([^/]+)/?$',
            'index.php?lai_webhook=pipedream&lai_source=$matches[1]',
            'top'
        );
        
        // Airtable webhook endpoint (for real-time updates)
        add_rewrite_rule(
            '^lai-webhook/airtable/([^/]+)/?$',
            'index.php?lai_webhook=airtable&lai_source=$matches[1]',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'lai_webhook';
            $vars[] = 'lai_source';
            return $vars;
        });
        
        // Handle webhook requests
        add_action('template_redirect', array($this, 'handle_webhook_requests'));
    }
    
    /**
     * Handle webhook requests
     */
    public function handle_webhook_requests() {
        $webhook_type = get_query_var('lai_webhook');
        $source_id = get_query_var('lai_source');
        
        if (!$webhook_type || !$source_id) {
            return;
        }
        
        switch ($webhook_type) {
            case 'pipedream':
                $this->handle_pipedream_webhook($source_id);
                break;
            case 'airtable':
                $this->handle_airtable_webhook($source_id);
                break;
        }
    }
    
    /**
     * Get supported data sources
     */
    public function get_data_sources() {
        return $this->data_sources;
    }
    
    /**
     * Get specific data source configuration
     */
    public function get_data_source($source_id) {
        return $this->data_sources[$source_id] ?? null;
    }
    
    /**
     * Import data from any supported source
     */
    public function import_from_source($source_id, $data, $options = array()) {
        $source_config = $this->get_data_source($source_id);
        
        if (!$source_config) {
            return array('error' => 'Unsupported data source: ' . $source_id);
        }
        
        // Load source-specific handler
        $source_class = $source_config['class'];
        if (!class_exists($source_class)) {
            require_once LAI_PLUGIN_PATH . 'includes/sources/class-' . strtolower(str_replace('LAI_', '', str_replace('_Source', '', $source_class))) . '-source.php';
        }
        
        if (!class_exists($source_class)) {
            return array('error' => 'Source handler not found: ' . $source_class);
        }
        
        $source_handler = new $source_class($this->wpdb, $this->field_mapper);
        
        // Process import
        $result = $source_handler->process_import($data, $options);
        
        // Log import activity
        $this->log_import_activity($source_id, $result);
        
        return $result;
    }
    
    /**
     * AJAX: Detect fields from data source
     */
    public function ajax_detect_fields() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $source_id = sanitize_text_field($_POST['source_id']);
        $data = $_POST['data']; // Source-specific data
        
        $result = $this->detect_fields_from_source($source_id, $data);
        
        if (isset($result['error'])) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Detect fields from specific data source
     */
    private function detect_fields_from_source($source_id, $data) {
        $source_config = $this->get_data_source($source_id);
        
        if (!$source_config) {
            return array('error' => 'Unsupported data source');
        }
        
        // Load source-specific handler
        $source_class = $source_config['class'];
        if (!class_exists($source_class)) {
            require_once LAI_PLUGIN_PATH . 'includes/sources/class-' . strtolower(str_replace('LAI_', '', str_replace('_Source', '', $source_class))) . '-source.php';
        }
        
        if (!class_exists($source_class)) {
            return array('error' => 'Source handler not found');
        }
        
        $source_handler = new $source_class($this->wpdb, $this->field_mapper);
        
        return $source_handler->detect_fields($data);
    }
    
    /**
     * AJAX: Airtable connection test
     */
    public function ajax_airtable_connect() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $base_id = sanitize_text_field($_POST['base_id']);
        $table_name = sanitize_text_field($_POST['table_name']);
        
        // Load Airtable source handler
        require_once LAI_PLUGIN_PATH . 'includes/sources/class-airtable-source.php';
        
        $airtable_source = new LAI_Airtable_Source($this->wpdb, $this->field_mapper);
        $result = $airtable_source->test_connection($api_key, $base_id, $table_name);
        
        if ($result['success']) {
            // Store configuration
            $config = array(
                'api_key' => $api_key,
                'base_id' => $base_id,
                'table_name' => $table_name,
                'connected_at' => current_time('mysql'),
                'last_sync' => null
            );
            
            update_option('lai_airtable_config', $config);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Airtable sync
     */
    public function ajax_airtable_sync() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sync_type = sanitize_text_field($_POST['sync_type'] ?? 'incremental');
        $filter_options = $_POST['filter_options'] ?? array();
        
        // Load Airtable source handler
        require_once LAI_PLUGIN_PATH . 'includes/sources/class-airtable-source.php';
        
        $airtable_source = new LAI_Airtable_Source($this->wpdb, $this->field_mapper);
        $result = $airtable_source->sync_data($sync_type, $filter_options);
        
        if ($result['success']) {
            // Update last sync time
            $config = get_option('lai_airtable_config', array());
            $config['last_sync'] = current_time('mysql');
            update_option('lai_airtable_config', $config);
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle Pipedream webhook
     */
    public function handle_pipedream_webhook($source_id = null) {
        if (!$source_id) {
            $source_id = $_POST['source_id'] ?? get_query_var('lai_source');
        }
        
        // Get webhook configuration
        $webhook_config = get_option('lai_pipedream_webhooks', array());
        
        if (!isset($webhook_config[$source_id])) {
            wp_die('Webhook not configured', 'Webhook Error', array('response' => 404));
        }
        
        $config = $webhook_config[$source_id];
        
        // Verify webhook secret
        $signature = $_SERVER['HTTP_X_PIPEDREAM_SIGNATURE'] ?? '';
        if (!$this->verify_webhook_signature($signature, $config['webhook_secret'])) {
            wp_die('Invalid webhook signature', 'Security Error', array('response' => 401));
        }
        
        // Get webhook payload
        $payload = json_decode(file_get_contents('php://input'), true);
        
        if (!$payload) {
            wp_die('Invalid payload', 'Payload Error', array('response' => 400));
        }
        
        // Load Pipedream source handler
        require_once LAI_PLUGIN_PATH . 'includes/sources/class-pipedream-source.php';
        
        $pipedream_source = new LAI_Pipedream_Source($this->wpdb, $this->field_mapper);
        $result = $pipedream_source->process_webhook($payload, $config);
        
        // Log webhook activity
        $this->log_webhook_activity($source_id, $payload, $result);
        
        // Return response
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($signature, $secret) {
        $payload = file_get_contents('php://input');
        $expected_signature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($signature, $expected_signature);
    }
    
    /**
     * AJAX: Setup Pipedream webhook
     */
    public function ajax_pipedream_setup() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $source_id = sanitize_text_field($_POST['source_id']);
        $webhook_secret = sanitize_text_field($_POST['webhook_secret']);
        $source_identifier = sanitize_text_field($_POST['source_identifier']);
        
        // Generate webhook URL
        $webhook_url = home_url('/lai-webhook/pipedream/' . $source_id);
        
        // Store webhook configuration
        $webhook_config = get_option('lai_pipedream_webhooks', array());
        $webhook_config[$source_id] = array(
            'webhook_secret' => $webhook_secret,
            'source_identifier' => $source_identifier,
            'webhook_url' => $webhook_url,
            'created_at' => current_time('mysql'),
            'status' => 'active'
        );
        
        update_option('lai_pipedream_webhooks', $webhook_config);
        
        wp_send_json_success(array(
            'webhook_url' => $webhook_url,
            'message' => 'Webhook configured successfully'
        ));
    }
    
    /**
     * Run scheduled sync for all connected sources
     */
    public function run_scheduled_sync() {
        // Sync Airtable if configured
        $airtable_config = get_option('lai_airtable_config');
        if ($airtable_config && isset($airtable_config['api_key'])) {
            require_once LAI_PLUGIN_PATH . 'includes/sources/class-airtable-source.php';
            
            $airtable_source = new LAI_Airtable_Source($this->wpdb, $this->field_mapper);
            $result = $airtable_source->sync_data('incremental');
            
            if ($result['success']) {
                $airtable_config['last_sync'] = current_time('mysql');
                update_option('lai_airtable_config', $airtable_config);
            }
        }
        
        // Add other scheduled syncs here
        
        error_log('LAI: Scheduled sync completed');
    }
    
    /**
     * Log import activity
     */
    private function log_import_activity($source_id, $result) {
        $log_entry = array(
            'source_id' => $source_id,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'total_rows' => $result['total_rows'] ?? 0,
            'successful_imports' => $result['successful_imports'] ?? 0,
            'failed_imports' => $result['failed_imports'] ?? 0,
            'errors' => $result['errors'] ?? array()
        );
        
        $import_history = get_option('lai_import_history', array());
        $import_history[] = $log_entry;
        
        // Keep only last 100 imports
        if (count($import_history) > 100) {
            $import_history = array_slice($import_history, -100);
        }
        
        update_option('lai_import_history', $import_history);
    }
    
    /**
     * Log webhook activity
     */
    private function log_webhook_activity($source_id, $payload, $result) {
        $log_entry = array(
            'source_id' => $source_id,
            'webhook_type' => 'pipedream',
            'timestamp' => current_time('mysql'),
            'payload_size' => strlen(json_encode($payload)),
            'success' => $result['success'] ?? false,
            'records_processed' => $result['records_processed'] ?? 0,
            'errors' => $result['errors'] ?? array()
        );
        
        $webhook_history = get_option('lai_webhook_history', array());
        $webhook_history[] = $log_entry;
        
        // Keep only last 200 webhook calls
        if (count($webhook_history) > 200) {
            $webhook_history = array_slice($webhook_history, -200);
        }
        
        update_option('lai_webhook_history', $webhook_history);
    }
    
    /**
     * REST API: Detect fields
     */
    public function rest_detect_fields($request) {
        $source_id = $request->get_param('source_id');
        $data = $request->get_param('data');
        
        $result = $this->detect_fields_from_source($source_id, $data);
        
        if (isset($result['error'])) {
            return new WP_Error('detection_failed', $result['error'], array('status' => 400));
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * REST API: Preview import
     */
    public function rest_preview_import($request) {
        $source_id = $request->get_param('source_id');
        $data = $request->get_param('data');
        $field_mappings = $request->get_param('field_mappings');
        
        $result = $this->import_from_source($source_id, $data, array(
            'preview_only' => true,
            'field_mappings' => $field_mappings,
            'max_rows' => 5
        ));
        
        if (isset($result['error'])) {
            return new WP_Error('preview_failed', $result['error'], array('status' => 400));
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * REST API: Process import
     */
    public function rest_process_import($request) {
        $source_id = $request->get_param('source_id');
        $data = $request->get_param('data');
        $field_mappings = $request->get_param('field_mappings');
        $options = $request->get_param('options', array());
        
        $options['field_mappings'] = $field_mappings;
        
        $result = $this->import_from_source($source_id, $data, $options);
        
        if (isset($result['error'])) {
            return new WP_Error('import_failed', $result['error'], array('status' => 400));
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Get import history
     */
    public function get_import_history($limit = 20) {
        $history = get_option('lai_import_history', array());
        return array_slice(array_reverse($history), 0, $limit);
    }
    
    /**
     * Get webhook history  
     */
    public function get_webhook_history($limit = 50) {
        $history = get_option('lai_webhook_history', array());
        return array_slice(array_reverse($history), 0, $limit);
    }
}