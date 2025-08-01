<?php
/**
 * Client Configurations Manager
 * Handles partner-specific configurations and field mappings
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Client_Configs {
    
    private $default_configs;
    
    public function __construct() {
        $this->init_default_configs();
        $this->add_hooks();
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        add_action('wp_ajax_lai_save_client_config', array($this, 'ajax_save_client_config'));
        add_action('wp_ajax_lai_load_client_config', array($this, 'ajax_load_client_config'));
        add_action('wp_ajax_lai_delete_client_config', array($this, 'ajax_delete_client_config'));
    }
    
    /**
     * Initialize default client configurations
     */
    private function init_default_configs() {
        $this->default_configs = array(
            'forderungen_com' => array(
                'name' => 'Forderungen.com',
                'description' => 'Import from Forderungen.com data export',
                'version' => '1.0',
                'field_mappings' => $this->get_forderungen_com_mapping(),
                'required_fields' => array('ID', 'Lawyer Case ID', 'User_First_Name', 'User_Last_Name'),
                'validation_rules' => array(
                    'User_Email' => 'email',
                    'Debtor_Email' => 'email',
                    'Created Date' => 'date',
                    'ART15_claim_damages' => 'decimal'
                )
            ),
            'generic_csv' => array(
                'name' => 'Generic CSV',
                'description' => 'Universal CSV import with custom field mapping',
                'version' => '1.0',
                'field_mappings' => array(),
                'required_fields' => array(),
                'validation_rules' => array()
            ),
            'airtable_basic' => array(
                'name' => 'Airtable Basic Setup',
                'description' => 'Standard Airtable integration configuration',
                'version' => '1.0',
                'field_mappings' => $this->get_airtable_basic_mapping(),
                'required_fields' => array('Email', 'Name'),
                'validation_rules' => array(
                    'Email' => 'email',
                    'Phone' => 'phone'
                )
            ),
            'pipedream_webhook' => array(
                'name' => 'Pipedream Webhook',
                'description' => 'Real-time webhook integration via Pipedream',
                'version' => '1.0',
                'field_mappings' => $this->get_pipedream_basic_mapping(),
                'required_fields' => array('email'),
                'validation_rules' => array(
                    'email' => 'email',
                    'amount' => 'decimal',
                    'created_at' => 'datetime'
                )
            )
        );
    }
    
    /**
     * Get Forderungen.com field mapping
     */
    private function get_forderungen_com_mapping() {
        return array(
            'ID' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'external_id',
                'data_type' => 'string',
                'required' => true
            ),
            'Lawyer Case ID' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_id',
                'data_type' => 'string',
                'required' => true
            ),
            'Status' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_status',
                'data_type' => 'string',
                'value_mapping' => array(
                    'Open' => 'processing',
                    'Closed' => 'completed',
                    'Pending' => 'pending',
                    'Draft' => 'draft'
                )
            ),
            'User_First_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string',
                'required' => true
            ),
            'User_Last_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string',
                'required' => true
            ),
            'User_Email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'required' => false
            ),
            'Debtor_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string',
                'required' => true,
                'contact_role' => 'debtor'
            ),
            'Debtor_Email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'contact_role' => 'debtor'
            ),
            'ART15_claim_damages' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal',
                'allow_empty' => true
            ),
            'Created Date' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_creation_date',
                'data_type' => 'datetime'
            )
        );
    }
    
    /**
     * Get Airtable basic field mapping
     */
    private function get_airtable_basic_mapping() {
        return array(
            'Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string',
                'required' => true
            ),
            'First Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string'
            ),
            'Email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'required' => true
            ),
            'Phone' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'phone',
                'data_type' => 'phone'
            ),
            'Company' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'company_name',
                'data_type' => 'string'
            ),
            'Case ID' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_id',
                'data_type' => 'string'
            ),
            'Status' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_status',
                'data_type' => 'string'
            ),
            'Amount' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal'
            ),
            'Notes' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_notes',
                'data_type' => 'text'
            )
        );
    }
    
    /**
     * Get Pipedream basic field mapping
     */
    private function get_pipedream_basic_mapping() {
        return array(
            'id' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'external_id',
                'data_type' => 'string'
            ),
            'email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'required' => true
            ),
            'first_name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string'
            ),
            'last_name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string'
            ),
            'company' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'company_name',
                'data_type' => 'string'
            ),
            'phone' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'phone',
                'data_type' => 'phone'
            ),
            'case_id' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_id',
                'data_type' => 'string'
            ),
            'status' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_status',
                'data_type' => 'string'
            ),
            'amount' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal'
            ),
            'subject' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_notes',
                'data_type' => 'text'
            ),
            'created_at' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_creation_date',
                'data_type' => 'datetime'
            )
        );
    }
    
    /**
     * Get default configuration for a client type
     */
    public function get_default_config($client_type) {
        return $this->default_configs[$client_type] ?? null;
    }
    
    /**
     * Get all default configurations
     */
    public function get_default_configs() {
        return $this->default_configs;
    }
    
    /**
     * Save custom client configuration
     */
    public function save_client_config($client_id, $config) {
        $custom_configs = get_option('lai_custom_client_configs', array());
        
        // Add metadata
        $config['created_at'] = current_time('mysql');
        $config['updated_at'] = current_time('mysql');
        $config['created_by'] = get_current_user_id();
        
        $custom_configs[$client_id] = $config;
        
        return update_option('lai_custom_client_configs', $custom_configs);
    }
    
    /**
     * Load client configuration
     */
    public function load_client_config($client_id) {
        // First check custom configurations
        $custom_configs = get_option('lai_custom_client_configs', array());
        if (isset($custom_configs[$client_id])) {
            return $custom_configs[$client_id];
        }
        
        // Fallback to default configurations
        return $this->get_default_config($client_id);
    }
    
    /**
     * Delete custom client configuration
     */
    public function delete_client_config($client_id) {
        $custom_configs = get_option('lai_custom_client_configs', array());
        
        if (isset($custom_configs[$client_id])) {
            unset($custom_configs[$client_id]);
            return update_option('lai_custom_client_configs', $custom_configs);
        }
        
        return false;
    }
    
    /**
     * Get all client configurations (default + custom)
     */
    public function get_all_client_configs() {
        $custom_configs = get_option('lai_custom_client_configs', array());
        
        return array_merge($this->default_configs, $custom_configs);
    }
    
    /**
     * Create configuration from field mappings
     */
    public function create_config_from_mappings($client_id, $client_name, $field_mappings, $options = array()) {
        $config = array(
            'name' => $client_name,
            'description' => $options['description'] ?? "Custom configuration for {$client_name}",
            'version' => '1.0',
            'field_mappings' => $field_mappings,
            'required_fields' => $options['required_fields'] ?? array(),
            'validation_rules' => $options['validation_rules'] ?? array(),
            'import_source' => $options['import_source'] ?? 'custom'
        );
        
        return $this->save_client_config($client_id, $config);
    }
    
    /**
     * Validate configuration
     */
    public function validate_config($config) {
        $errors = array();
        
        // Check required fields
        if (empty($config['name'])) {
            $errors[] = 'Configuration name is required';
        }
        
        if (empty($config['field_mappings'])) {
            $errors[] = 'Field mappings are required';
        }
        
        // Validate field mappings structure
        if (!empty($config['field_mappings'])) {
            foreach ($config['field_mappings'] as $field => $mapping) {
                if (empty($mapping['target_table'])) {
                    $errors[] = "Target table missing for field: {$field}";
                }
                
                if (empty($mapping['target_field'])) {
                    $errors[] = "Target field missing for field: {$field}";
                }
                
                if (empty($mapping['data_type'])) {
                    $errors[] = "Data type missing for field: {$field}";
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * AJAX: Save client configuration
     */
    public function ajax_save_client_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $client_id = sanitize_text_field($_POST['client_id']);
        $config = $_POST['config']; // Will be sanitized in save_client_config
        
        // Validate configuration
        $validation = $this->validate_config($config);
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => 'Configuration validation failed',
                'errors' => $validation['errors']
            ));
        }
        
        $result = $this->save_client_config($client_id, $config);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Configuration saved successfully'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to save configuration'
            ));
        }
    }
    
    /**
     * AJAX: Load client configuration
     */
    public function ajax_load_client_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $client_id = sanitize_text_field($_POST['client_id']);
        $config = $this->load_client_config($client_id);
        
        if ($config) {
            wp_send_json_success($config);
        } else {
            wp_send_json_error(array(
                'message' => 'Configuration not found'
            ));
        }
    }
    
    /**
     * AJAX: Delete client configuration
     */
    public function ajax_delete_client_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $client_id = sanitize_text_field($_POST['client_id']);
        $result = $this->delete_client_config($client_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Configuration deleted successfully'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Configuration not found or could not be deleted'
            ));
        }
    }
    
    /**
     * Export configuration to JSON
     */
    public function export_config($client_id) {
        $config = $this->load_client_config($client_id);
        
        if (!$config) {
            return false;
        }
        
        // Remove internal metadata for export
        unset($config['created_at'], $config['updated_at'], $config['created_by']);
        
        return json_encode($config, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import configuration from JSON
     */
    public function import_config($client_id, $json_config) {
        $config = json_decode($json_config, true);
        
        if (!$config) {
            return array('success' => false, 'error' => 'Invalid JSON format');
        }
        
        // Validate imported configuration
        $validation = $this->validate_config($config);
        if (!$validation['valid']) {
            return array('success' => false, 'errors' => $validation['errors']);
        }
        
        $result = $this->save_client_config($client_id, $config);
        
        return array('success' => $result);
    }
    
    /**
     * Get configuration templates for quick setup
     */
    public function get_config_templates() {
        return array(
            'basic_contact' => array(
                'name' => 'Basic Contact Import',
                'description' => 'Simple contact information import',
                'field_mappings' => array(
                    'email' => array(
                        'target_table' => 'klage_contacts',
                        'target_field' => 'email',
                        'data_type' => 'email',
                        'required' => true
                    ),
                    'first_name' => array(
                        'target_table' => 'klage_contacts',
                        'target_field' => 'first_name',
                        'data_type' => 'string'
                    ),
                    'last_name' => array(
                        'target_table' => 'klage_contacts',
                        'target_field' => 'last_name',
                        'data_type' => 'string'
                    )
                )
            ),
            'case_with_contact' => array(
                'name' => 'Case with Contact',
                'description' => 'Import cases with associated contact information',
                'field_mappings' => array(
                    'case_id' => array(
                        'target_table' => 'klage_cases',
                        'target_field' => 'case_id',
                        'data_type' => 'string',
                        'required' => true
                    ),
                    'email' => array(
                        'target_table' => 'klage_contacts',
                        'target_field' => 'email',
                        'data_type' => 'email',
                        'required' => true
                    ),
                    'amount' => array(
                        'target_table' => 'klage_cases',
                        'target_field' => 'claim_amount',
                        'data_type' => 'decimal'
                    )
                )
            )
        );
    }
}