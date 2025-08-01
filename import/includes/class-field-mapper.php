<?php
/**
 * Field Mapper Utility
 * Handles field mapping between different data sources and database schema
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Field_Mapper {
    
    private $database_schema;
    private $mapping_rules;
    
    public function __construct() {
        $this->init_database_schema();
        $this->init_mapping_rules();
    }
    
    /**
     * Initialize database schema definition
     */
    private function init_database_schema() {
        $this->database_schema = array(
            'klage_contacts' => array(
                'id' => 'int(11) AUTO_INCREMENT PRIMARY KEY',
                'contact_type' => 'varchar(50)',
                'title' => 'varchar(20)',
                'first_name' => 'varchar(100)',
                'last_name' => 'varchar(100)',
                'company_name' => 'varchar(200)',
                'email' => 'varchar(100)',
                'phone' => 'varchar(50)',
                'mobile' => 'varchar(50)',
                'fax' => 'varchar(50)',
                'website' => 'varchar(200)',
                'address' => 'text',
                'street' => 'varchar(200)',
                'street_number' => 'varchar(20)',
                'postal_code' => 'varchar(20)',
                'city' => 'varchar(100)',
                'state' => 'varchar(100)',
                'country' => 'varchar(100)',
                'notes' => 'text',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
                'import_source' => 'varchar(50)',
                'external_id' => 'varchar(100)'
            ),
            'klage_cases' => array(
                'id' => 'int(11) AUTO_INCREMENT PRIMARY KEY',
                'case_id' => 'varchar(50) UNIQUE',
                'case_status' => 'varchar(50)',
                'case_type' => 'varchar(50)',
                'case_priority' => 'varchar(20)',
                'case_notes' => 'text',
                'claim_amount' => 'decimal(10,2)',
                'case_creation_date' => 'datetime',
                'case_updated_date' => 'datetime',
                'case_documents_attachments' => 'text',
                'external_id' => 'varchar(100)',
                'import_source' => 'varchar(50)',
                'created_at' => 'datetime',
                'updated_at' => 'datetime'
            ),
            'klage_case_contacts' => array(
                'id' => 'int(11) AUTO_INCREMENT PRIMARY KEY',
                'case_id' => 'int(11)',
                'contact_id' => 'int(11)',
                'role' => 'varchar(50)',
                'active_status' => 'tinyint(1)',
                'created_at' => 'datetime'
            ),
            'klage_financials' => array(
                'id' => 'int(11) AUTO_INCREMENT PRIMARY KEY',
                'case_id' => 'int(11)',
                'financial_type' => 'varchar(50)',
                'amount' => 'decimal(10,2)',
                'currency' => 'varchar(10)',
                'description' => 'text',
                'transaction_date' => 'datetime',
                'created_at' => 'datetime',
                'import_source' => 'varchar(50)'
            )
        );
    }
    
    /**
     * Initialize mapping rules
     */
    private function init_mapping_rules() {
        $this->mapping_rules = array(
            'data_type_conversion' => array(
                'string' => array('varchar', 'char', 'text'),
                'integer' => array('int', 'smallint', 'bigint'),
                'decimal' => array('decimal', 'float', 'double'),
                'date' => array('date'),
                'datetime' => array('datetime', 'timestamp'),
                'time' => array('time'),
                'boolean' => array('tinyint', 'boolean'),
                'email' => array('varchar'),
                'url' => array('varchar', 'text'),
                'phone' => array('varchar'),
                'json' => array('text', 'json'),
                'array' => array('text', 'json')
            ),
            'validation_rules' => array(
                'email' => array(
                    'filter' => FILTER_VALIDATE_EMAIL,
                    'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'
                ),
                'url' => array(
                    'filter' => FILTER_VALIDATE_URL,
                    'regex' => '/^https?:\/\/[^\s]+$/'
                ),
                'phone' => array(
                    'regex' => '/^[\+\d\s\-\(\)]{7,20}$/'
                ),
                'postal_code' => array(
                    'regex' => '/^[\d\-\s]{3,10}$/'
                )
            )
        );
    }
    
    /**
     * Get database schema for a table
     */
    public function get_table_schema($table_name) {
        return $this->database_schema[$table_name] ?? array();
    }
    
    /**
     * Get all available database tables
     */
    public function get_available_tables() {
        return array_keys($this->database_schema);
    }
    
    /**
     * Get fields for a specific table
     */
    public function get_table_fields($table_name) {
        $schema = $this->get_table_schema($table_name);
        return array_keys($schema);
    }
    
    /**
     * Validate field mapping
     */
    public function validate_field_mapping($source_field, $target_table, $target_field, $data_type = 'string') {
        $validation_result = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array()
        );
        
        // Check if target table exists
        if (!isset($this->database_schema[$target_table])) {
            $validation_result['valid'] = false;
            $validation_result['errors'][] = "Target table '{$target_table}' does not exist";
            return $validation_result;
        }
        
        // Check if target field exists
        if (!isset($this->database_schema[$target_table][$target_field])) {
            $validation_result['valid'] = false;
            $validation_result['errors'][] = "Target field '{$target_field}' does not exist in table '{$target_table}'";
            return $validation_result;
        }
        
        // Check data type compatibility
        $field_definition = $this->database_schema[$target_table][$target_field];
        $is_compatible = $this->check_data_type_compatibility($data_type, $field_definition);
        
        if (!$is_compatible) {
            $validation_result['warnings'][] = "Data type '{$data_type}' may not be compatible with field definition '{$field_definition}'";
        }
        
        return $validation_result;
    }
    
    /**
     * Check data type compatibility
     */
    private function check_data_type_compatibility($source_type, $field_definition) {
        $field_definition = strtolower($field_definition);
        
        if (!isset($this->mapping_rules['data_type_conversion'][$source_type])) {
            return false;
        }
        
        $compatible_types = $this->mapping_rules['data_type_conversion'][$source_type];
        
        foreach ($compatible_types as $type) {
            if (strpos($field_definition, $type) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Suggest field mappings based on field names
     */
    public function suggest_mappings($source_fields, $source_type = 'generic') {
        $suggestions = array();
        
        foreach ($source_fields as $field) {
            $field_name = is_array($field) ? $field['name'] : $field;
            $suggestion = $this->suggest_single_field_mapping($field_name, $source_type);
            
            if ($suggestion) {
                $suggestions[$field_name] = $suggestion;
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Suggest mapping for a single field
     */
    private function suggest_single_field_mapping($field_name, $source_type) {
        $field_name_lower = strtolower($field_name);
        
        // Get source-specific patterns
        $patterns = $this->get_field_patterns($source_type);
        
        foreach ($patterns as $pattern_name => $pattern_config) {
            if (preg_match($pattern_config['pattern'], $field_name_lower)) {
                return array(
                    'target_table' => $pattern_config['target_table'],
                    'target_field' => $pattern_config['target_field'],
                    'data_type' => $pattern_config['data_type'],
                    'confidence' => $pattern_config['confidence'] ?? 'medium',
                    'pattern_matched' => $pattern_name
                );
            }
        }
        
        return null;
    }
    
    /**
     * Get field patterns for different source types
     */
    private function get_field_patterns($source_type) {
        $base_patterns = array(
            'email' => array(
                'pattern' => '/email|e-mail|mail/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'confidence' => 'high'
            ),
            'first_name' => array(
                'pattern' => '/first.?name|vorname|firstname|fname/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'last_name' => array(
                'pattern' => '/last.?name|nachname|lastname|surname|lname/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'company' => array(
                'pattern' => '/company|firma|unternehmen|organization|org/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'company_name',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'phone' => array(
                'pattern' => '/phone|telefon|tel|mobile|handy/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'phone',
                'data_type' => 'phone',
                'confidence' => 'high'
            ),
            'address' => array(
                'pattern' => '/address|adresse|street|straße|strasse/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'address',
                'data_type' => 'string',
                'confidence' => 'medium'
            ),
            'city' => array(
                'pattern' => '/city|stadt|ort/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'city',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'postal_code' => array(
                'pattern' => '/postal.?code|zip|plz|postleitzahl/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'postal_code',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'country' => array(
                'pattern' => '/country|land|nation/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'country',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'case_id' => array(
                'pattern' => '/case.?id|fall.?id|id/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_id',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'status' => array(
                'pattern' => '/status|zustand|state/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_status',
                'data_type' => 'string',
                'confidence' => 'high'
            ),
            'amount' => array(
                'pattern' => '/amount|betrag|summe|claim|value/i',
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal',
                'confidence' => 'high'
            ),
            'notes' => array(
                'pattern' => '/notes|notizen|description|beschreibung|details/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_notes',
                'data_type' => 'string',
                'confidence' => 'medium'
            ),
            'date' => array(
                'pattern' => '/date|datum|created|updated/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_creation_date',
                'data_type' => 'datetime',
                'confidence' => 'medium'
            )
        );
        
        // Add source-specific patterns
        switch ($source_type) {
            case 'airtable':
                $base_patterns['airtable_id'] = array(
                    'pattern' => '/^id$|airtable.?id/i',
                    'target_table' => 'klage_cases',
                    'target_field' => 'external_id',
                    'data_type' => 'string',
                    'confidence' => 'high'
                );
                break;
                
            case 'pipedream':
                $base_patterns['workflow_id'] = array(
                    'pattern' => '/workflow.?id|pipedream.?id/i',
                    'target_table' => 'klage_cases',
                    'target_field' => 'external_id',
                    'data_type' => 'string',
                    'confidence' => 'high'
                );
                break;
                
            case 'forderungen_com':
                $base_patterns = array_merge($base_patterns, array(
                    'lawyer_case_id' => array(
                        'pattern' => '/lawyer.?case.?id/i',
                        'target_table' => 'klage_cases',
                        'target_field' => 'case_id',
                        'data_type' => 'string',
                        'confidence' => 'high'
                    ),
                    'debtor_name' => array(
                        'pattern' => '/debtor.?name/i',
                        'target_table' => 'klage_contacts',
                        'target_field' => 'last_name',
                        'data_type' => 'string',
                        'confidence' => 'high'
                    ),
                    'user_first_name' => array(
                        'pattern' => '/user.?first.?name/i',
                        'target_table' => 'klage_contacts',
                        'target_field' => 'first_name',
                        'data_type' => 'string',
                        'confidence' => 'high'
                    ),
                    'art15_claim_damages' => array(
                        'pattern' => '/art15.?claim.?damages/i',
                        'target_table' => 'klage_cases',
                        'target_field' => 'claim_amount',
                        'data_type' => 'decimal',
                        'confidence' => 'high'
                    )
                ));
                break;
        }
        
        return $base_patterns;
    }
    
    /**
     * Generate field mapping configuration
     */
    public function generate_mapping_config($source_fields, $target_mappings) {
        $config = array(
            'source_type' => 'custom',
            'created_at' => current_time('mysql'),
            'field_mappings' => array()
        );
        
        foreach ($source_fields as $source_field) {
            $field_name = is_array($source_field) ? $source_field['name'] : $source_field;
            
            if (isset($target_mappings[$field_name])) {
                $mapping = $target_mappings[$field_name];
                
                // Validate mapping
                $validation = $this->validate_field_mapping(
                    $field_name,
                    $mapping['target_table'],
                    $mapping['target_field'],
                    $mapping['data_type']
                );
                
                if ($validation['valid']) {
                    $config['field_mappings'][$field_name] = $mapping;
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Apply field transformation
     */
    public function transform_field_value($value, $mapping) {
        $data_type = $mapping['data_type'] ?? 'string';
        $allow_empty = $mapping['allow_empty'] ?? true;
        $required = $mapping['required'] ?? false;
        
        // Handle empty values
        if (empty($value)) {
            if ($required) {
                return array('valid' => false, 'error' => 'Required field is empty');
            }
            return array('valid' => true, 'value' => null);
        }
        
        // Apply transformations based on data type
        switch ($data_type) {
            case 'email':
                $value = strtolower(trim($value));
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return array('valid' => false, 'error' => 'Invalid email format');
                }
                break;
                
            case 'phone':
                $value = preg_replace('/[^\d\+\-\(\)\s]/', '', $value);
                if (!preg_match($this->mapping_rules['validation_rules']['phone']['regex'], $value)) {
                    return array('valid' => false, 'error' => 'Invalid phone format');
                }
                break;
                
            case 'url':
                $value = trim($value);
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return array('valid' => false, 'error' => 'Invalid URL format');
                }
                break;
                
            case 'integer':
                if (!is_numeric($value)) {
                    return array('valid' => false, 'error' => 'Invalid integer format');
                }
                $value = intval($value);
                break;
                
            case 'decimal':
                if (!is_numeric($value)) {
                    return array('valid' => false, 'error' => 'Invalid decimal format');
                }
                $value = floatval($value);
                break;
                
            case 'date':
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return array('valid' => false, 'error' => 'Invalid date format');
                }
                $value = date('Y-m-d', $timestamp);
                break;
                
            case 'datetime':
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return array('valid' => false, 'error' => 'Invalid datetime format');
                }
                $value = date('Y-m-d H:i:s', $timestamp);
                break;
                
            case 'boolean':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
                
            case 'json':
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                break;
        }
        
        // Apply value mapping if configured
        if (isset($mapping['value_mapping'][$value])) {
            $value = $mapping['value_mapping'][$value];
        }
        
        return array('valid' => true, 'value' => $value);
    }
    
    /**
     * Get field mapping templates
     */
    public function get_mapping_templates() {
        return array(
            'contact_basic' => array(
                'name' => 'Basic Contact Information',
                'fields' => array(
                    'email' => array('target_table' => 'klage_contacts', 'target_field' => 'email', 'data_type' => 'email'),
                    'first_name' => array('target_table' => 'klage_contacts', 'target_field' => 'first_name', 'data_type' => 'string'),
                    'last_name' => array('target_table' => 'klage_contacts', 'target_field' => 'last_name', 'data_type' => 'string'),
                    'company' => array('target_table' => 'klage_contacts', 'target_field' => 'company_name', 'data_type' => 'string'),
                    'phone' => array('target_table' => 'klage_contacts', 'target_field' => 'phone', 'data_type' => 'phone')
                )
            ),
            'case_basic' => array(
                'name' => 'Basic Case Information',
                'fields' => array(
                    'case_id' => array('target_table' => 'klage_cases', 'target_field' => 'case_id', 'data_type' => 'string'),
                    'status' => array('target_table' => 'klage_cases', 'target_field' => 'case_status', 'data_type' => 'string'),
                    'amount' => array('target_table' => 'klage_cases', 'target_field' => 'claim_amount', 'data_type' => 'decimal'),
                    'notes' => array('target_table' => 'klage_cases', 'target_field' => 'case_notes', 'data_type' => 'string')
                )
            )
        );
    }
}