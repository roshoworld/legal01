<?php
/**
 * Pipedream Webhook Source Handler
 * Real-time case feeds via Pipedream automation platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Pipedream_Source {
    
    private $wpdb;
    private $field_mapper;
    
    public function __construct($wpdb, $field_mapper) {
        $this->wpdb = $wpdb;
        $this->field_mapper = $field_mapper;
    }
    
    /**
     * Process webhook payload from Pipedream
     */
    public function process_webhook($payload, $config) {
        $result = array(
            'success' => false,
            'records_processed' => 0,
            'errors' => array(),
            'created_records' => array()
        );
        
        try {
            // Validate payload structure
            if (!$this->validate_payload($payload)) {
                throw new Exception('Invalid payload structure');
            }
            
            // Extract data based on Pipedream workflow structure
            $data = $this->extract_data_from_payload($payload, $config);
            
            if (empty($data)) {
                throw new Exception('No data found in payload');
            }
            
            // Process each record in the payload
            foreach ($data as $record) {
                $record_result = $this->process_single_record($record, $config);
                
                if ($record_result['success']) {
                    $result['records_processed']++;
                    
                    // Merge created records
                    foreach ($record_result['created_records'] as $type => $records) {
                        if (!isset($result['created_records'][$type])) {
                            $result['created_records'][$type] = array();
                        }
                        $result['created_records'][$type] = array_merge(
                            $result['created_records'][$type],
                            $records
                        );
                    }
                } else {
                    $result['errors'][] = $record_result['error'];
                }
            }
            
            $result['success'] = $result['records_processed'] > 0;
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate Pipedream payload structure
     */
    private function validate_payload($payload) {
        // Check for required Pipedream fields
        if (!isset($payload['workflow_id']) && !isset($payload['data'])) {
            return false;
        }
        
        // Check for timestamp
        if (!isset($payload['timestamp']) && !isset($payload['created_at'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Extract data from Pipedream payload
     */
    private function extract_data_from_payload($payload, $config) {
        $data = array();
        
        // Handle different Pipedream payload structures
        if (isset($payload['data'])) {
            // Standard Pipedream data structure
            if (is_array($payload['data'])) {
                $data = $payload['data'];
            } else {
                $data = array($payload['data']);
            }
        } elseif (isset($payload['records'])) {
            // Airtable via Pipedream
            $data = $payload['records'];
        } elseif (isset($payload['items'])) {
            // Generic items array
            $data = $payload['items'];
        } else {
            // Single record payload
            $data = array($payload);
        }
        
        return $data;
    }
    
    /**
     * Process single record from Pipedream
     */
    private function process_single_record($record, $config) {
        $result = array(
            'success' => false,
            'error' => '',
            'created_records' => array()
        );
        
        try {
            // Get field mappings for this source
            $field_mappings = $this->get_field_mappings($config);
            
            if (empty($field_mappings)) {
                throw new Exception('No field mappings configured for this source');
            }
            
            // Map fields to database structure
            $mapped_data = $this->map_record_fields($record, $field_mappings);
            
            if (empty($mapped_data)) {
                throw new Exception('No mappable data found in record');
            }
            
            // Insert into database
            $insert_result = $this->insert_mapped_record($mapped_data, $record, $config);
            
            if ($insert_result['success']) {
                $result['success'] = true;
                $result['created_records'] = $insert_result['created_records'];
            } else {
                throw new Exception($insert_result['error']);
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Get field mappings for Pipedream source
     */
    private function get_field_mappings($config) {
        $source_identifier = $config['source_identifier'];
        
        // Get stored field mappings
        $pipedream_mappings = get_option('lai_pipedream_mappings', array());
        
        if (isset($pipedream_mappings[$source_identifier])) {
            return $pipedream_mappings[$source_identifier];
        }
        
        // Default mappings based on common Pipedream patterns
        return $this->get_default_pipedream_mappings();
    }
    
    /**
     * Get default field mappings for Pipedream
     */
    private function get_default_pipedream_mappings() {
        return array(
            // Common Pipedream field patterns
            'id' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'external_id',
                'data_type' => 'string'
            ),
            'email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email'
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
                'data_type' => 'string'
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
            'description' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_documents_attachments',
                'data_type' => 'text'
            ),
            'created_at' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_creation_date',
                'data_type' => 'datetime'
            ),
            'updated_at' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_updated_date',
                'data_type' => 'datetime'
            )
        );
    }
    
    /**
     * Map record fields to database structure
     */
    private function map_record_fields($record, $field_mappings) {
        $mapped_data = array();
        
        foreach ($record as $field_name => $value) {
            // Handle nested objects (common in Pipedream)
            if (is_object($value) || is_array($value)) {
                $flattened_fields = $this->flatten_object($value, $field_name);
                
                foreach ($flattened_fields as $flat_field => $flat_value) {
                    if (isset($field_mappings[$flat_field])) {
                        $mapping = $field_mappings[$flat_field];
                        $converted_value = $this->convert_pipedream_value($flat_value, $mapping);
                        
                        if ($converted_value['valid']) {
                            $table = $mapping['target_table'];
                            $field = $mapping['target_field'];
                            
                            if (!isset($mapped_data[$table])) {
                                $mapped_data[$table] = array();
                            }
                            
                            $mapped_data[$table][$field] = $converted_value['value'];
                        }
                    }
                }
            } else {
                // Handle simple field mapping
                if (isset($field_mappings[$field_name])) {
                    $mapping = $field_mappings[$field_name];
                    $converted_value = $this->convert_pipedream_value($value, $mapping);
                    
                    if ($converted_value['valid']) {
                        $table = $mapping['target_table'];
                        $field = $mapping['target_field'];
                        
                        if (!isset($mapped_data[$table])) {
                            $mapped_data[$table] = array();
                        }
                        
                        $mapped_data[$table][$field] = $converted_value['value'];
                    }
                }
            }
        }
        
        return $mapped_data;
    }
    
    /**
     * Flatten nested object/array for field mapping
     */
    private function flatten_object($data, $prefix = '') {
        $result = array();
        
        if (is_object($data)) {
            $data = (array) $data;
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $new_key = $prefix ? $prefix . '.' . $key : $key;
                
                if (is_object($value) || is_array($value)) {
                    $result = array_merge($result, $this->flatten_object($value, $new_key));
                } else {
                    $result[$new_key] = $value;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Convert Pipedream value to database format
     */
    private function convert_pipedream_value($value, $mapping) {
        $data_type = $mapping['data_type'] ?? 'string';
        
        // Handle empty values
        if (empty($value)) {
            $allow_empty = $mapping['allow_empty'] ?? true;
            $required = $mapping['required'] ?? false;
            
            if ($required) {
                return array('valid' => false, 'error' => 'Required field is empty');
            }
            
            return array('valid' => true, 'value' => null);
        }
        
        // Convert based on data type
        switch ($data_type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return array('valid' => false, 'error' => 'Invalid email format');
                }
                break;
                
            case 'url':
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
                
            case 'text':
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                break;
        }
        
        return array('valid' => true, 'value' => $value);
    }
    
    /**
     * Insert mapped record into database
     */
    private function insert_mapped_record($mapped_data, $original_record, $config) {
        $result = array(
            'success' => false,
            'error' => '',
            'created_records' => array()
        );
        
        try {
            // Start transaction
            $this->wpdb->query('START TRANSACTION');
            
            $created_ids = array();
            
            // Insert in proper order: contacts -> cases -> case_contacts -> financials
            $insert_order = array('klage_contacts', 'klage_cases', 'klage_financials');
            
            foreach ($insert_order as $table) {
                if (!isset($mapped_data[$table])) {
                    continue;
                }
                
                $data = $mapped_data[$table];
                
                // Add metadata
                $data['created_at'] = current_time('mysql');
                $data['import_source'] = 'pipedream';
                
                // Add original record ID for tracking
                if (isset($original_record['id'])) {
                    $data['external_id'] = $original_record['id'];
                }
                
                // Add source identifier
                $data['source_identifier'] = $config['source_identifier'];
                
                // Check for duplicates if external_id exists
                if (isset($data['external_id'])) {
                    $existing = $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT id FROM {$this->wpdb->prefix}{$table} WHERE external_id = %s",
                        $data['external_id']
                    ));
                    
                    if ($existing) {
                        // Update existing record instead of creating new one
                        $update_result = $this->wpdb->update(
                            $this->wpdb->prefix . $table,
                            $data,
                            array('external_id' => $data['external_id'])
                        );
                        
                        if ($update_result === false) {
                            throw new Exception("Failed to update existing record in {$table}: " . $this->wpdb->last_error);
                        }
                        
                        $created_ids[$table] = $existing;
                        $result['created_records'][str_replace('klage_', '', $table)][] = $existing;
                        continue;
                    }
                }
                
                // Insert new record
                $insert_result = $this->wpdb->insert(
                    $this->wpdb->prefix . $table,
                    $data
                );
                
                if ($insert_result === false) {
                    throw new Exception("Failed to insert into {$table}: " . $this->wpdb->last_error);
                }
                
                $created_ids[$table] = $this->wpdb->insert_id;
                $result['created_records'][str_replace('klage_', '', $table)][] = $created_ids[$table];
            }
            
            // Create case-contact relationship if both exist
            if (isset($created_ids['klage_cases']) && isset($created_ids['klage_contacts'])) {
                $case_contact_data = array(
                    'case_id' => $created_ids['klage_cases'],
                    'contact_id' => $created_ids['klage_contacts'],
                    'role' => 'debtor', // Default role
                    'active_status' => 1,
                    'created_at' => current_time('mysql')
                );
                
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'klage_case_contacts',
                    $case_contact_data
                );
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            $result['success'] = true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Detect fields from Pipedream payload structure
     */
    public function detect_fields($payload_sample) {
        $detected_fields = array();
        
        // Flatten the sample payload to detect all possible fields
        $flattened_fields = $this->flatten_object($payload_sample);
        
        foreach ($flattened_fields as $field_name => $value) {
            $detected_fields[] = array(
                'csv_name' => $field_name,
                'pipedream_name' => $field_name,
                'sample_data' => array($value),
                'data_type' => $this->detect_pipedream_field_type($value),
                'empty_percentage' => empty($value) ? 100 : 0,
                'field_path' => $field_name
            );
        }
        
        // Generate suggested mappings
        $suggested_mappings = array();
        foreach ($detected_fields as $field) {
            $suggestion = $this->suggest_pipedream_mapping($field);
            if ($suggestion) {
                $suggested_mappings[$field['csv_name']] = $suggestion;
            }
        }
        
        return array(
            'detected_fields' => $detected_fields,
            'suggested_mappings' => $suggested_mappings,
            'source_type' => 'pipedream',
            'payload_structure' => $this->analyze_payload_structure($payload_sample)
        );
    }
    
    /**
     * Detect field type from Pipedream value
     */
    private function detect_pipedream_field_type($value) {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'decimal';
        } elseif (is_array($value) || is_object($value)) {
            return 'json';
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (strtotime($value) !== false) {
            return 'datetime';
        } else {
            return 'string';
        }
    }
    
    /**
     * Suggest field mapping for Pipedream field
     */
    private function suggest_pipedream_mapping($field_data) {
        $field_name = $field_data['csv_name'];
        $field_name_lower = strtolower($field_name);
        
        // Common Pipedream field patterns
        $patterns = array(
            'email' => array(
                'pattern' => '/email|mail/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email'
            ),
            'name' => array(
                'pattern' => '/^name$|full.?name/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string'
            ),
            'first_name' => array(
                'pattern' => '/first.?name/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string'
            ),
            'last_name' => array(
                'pattern' => '/last.?name/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string'
            ),
            'company' => array(
                'pattern' => '/company|organization/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'company_name',
                'data_type' => 'string'
            ),
            'phone' => array(
                'pattern' => '/phone|tel/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'phone',
                'data_type' => 'string'
            ),
            'case_id' => array(
                'pattern' => '/case.?id|id/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_id',
                'data_type' => 'string'
            ),
            'status' => array(
                'pattern' => '/status|state/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_status',
                'data_type' => 'string'
            ),
            'amount' => array(
                'pattern' => '/amount|value|sum/i',
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal'
            ),
            'description' => array(
                'pattern' => '/description|notes|details/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_notes',
                'data_type' => 'text'
            )
        );
        
        foreach ($patterns as $pattern_name => $pattern_config) {
            if (preg_match($pattern_config['pattern'], $field_name)) {
                return array(
                    'target_table' => $pattern_config['target_table'],
                    'target_field' => $pattern_config['target_field'],
                    'data_type' => $pattern_config['data_type'],
                    'confidence' => 'medium',
                    'source' => 'pipedream_pattern'
                );
            }
        }
        
        return null;
    }
    
    /**
     * Analyze payload structure for documentation
     */
    private function analyze_payload_structure($payload) {
        return array(
            'type' => gettype($payload),
            'keys' => is_array($payload) ? array_keys($payload) : array(),
            'nested_levels' => $this->count_nested_levels($payload),
            'total_fields' => count($this->flatten_object($payload))
        );
    }
    
    /**
     * Count nested levels in payload
     */
    private function count_nested_levels($data, $current_level = 0) {
        $max_level = $current_level;
        
        if (is_array($data) || is_object($data)) {
            foreach ($data as $value) {
                if (is_array($value) || is_object($value)) {
                    $nested_level = $this->count_nested_levels($value, $current_level + 1);
                    $max_level = max($max_level, $nested_level);
                }
            }
        }
        
        return $max_level;
    }
    
    /**
     * Process import (main entry point)
     */
    public function process_import($data, $options = array()) {
        // For webhook sources, data is the webhook payload
        $config = $options['config'] ?? array();
        
        return $this->process_webhook($data, $config);
    }
}