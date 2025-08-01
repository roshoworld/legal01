<?php
/**
 * Airtable Data Source Handler
 * Direct integration with Airtable API for real-time data sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Airtable_Source {
    
    private $wpdb;
    private $field_mapper;
    private $api_base_url = 'https://api.airtable.com/v0/';
    private $rate_limit_delay = 200; // milliseconds between requests
    
    public function __construct($wpdb, $field_mapper) {
        $this->wpdb = $wpdb;
        $this->field_mapper = $field_mapper;
    }
    
    /**
     * Test Airtable connection
     */
    public function test_connection($api_key, $base_id, $table_name) {
        $url = $this->api_base_url . $base_id . '/' . urlencode($table_name);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Connection successful',
                'table_info' => array(
                    'total_records' => count($data['records'] ?? array()),
                    'fields_detected' => $this->extract_field_names($data['records'] ?? array()),
                    'sample_record' => isset($data['records'][0]) ? $data['records'][0] : null
                )
            );
        } else {
            return array(
                'success' => false,
                'error' => 'API Error: ' . ($data['error']['message'] ?? 'Unknown error'),
                'error_code' => $response_code
            );
        }
    }
    
    /**
     * Detect fields from Airtable data
     */
    public function detect_fields($connection_data) {
        $api_key = $connection_data['api_key'];
        $base_id = $connection_data['base_id'];
        $table_name = $connection_data['table_name'];
        
        // Get sample records to analyze fields
        $records = $this->fetch_records($api_key, $base_id, $table_name, array(
            'maxRecords' => 10,
            'view' => 'Grid view'
        ));
        
        if (isset($records['error'])) {
            return $records;
        }
        
        $detected_fields = array();
        $field_stats = array();
        
        // Analyze fields from sample records
        foreach ($records as $record) {
            $fields = $record['fields'] ?? array();
            
            foreach ($fields as $field_name => $field_value) {
                if (!isset($field_stats[$field_name])) {
                    $field_stats[$field_name] = array(
                        'name' => $field_name,
                        'sample_values' => array(),
                        'data_types' => array(),
                        'empty_count' => 0,
                        'total_count' => 0
                    );
                }
                
                $field_stats[$field_name]['total_count']++;
                
                if (empty($field_value)) {
                    $field_stats[$field_name]['empty_count']++;
                } else {
                    if (count($field_stats[$field_name]['sample_values']) < 3) {
                        $field_stats[$field_name]['sample_values'][] = $field_value;
                    }
                    
                    $field_stats[$field_name]['data_types'][] = $this->detect_airtable_field_type($field_value);
                }
            }
        }
        
        // Convert stats to detected fields format
        foreach ($field_stats as $field_name => $stats) {
            $detected_fields[] = array(
                'csv_name' => $field_name,
                'airtable_name' => $field_name,
                'sample_data' => $stats['sample_values'],
                'data_type' => $this->determine_primary_data_type($stats['data_types']),
                'empty_percentage' => $stats['total_count'] > 0 ? round(($stats['empty_count'] / $stats['total_count']) * 100, 1) : 0,
                'airtable_field_type' => $this->get_airtable_field_type($field_name, $stats['sample_values'])
            );
        }
        
        // Generate suggested mappings
        $suggested_mappings = array();
        foreach ($detected_fields as $field) {
            $suggestion = $this->suggest_field_mapping($field);
            if ($suggestion) {
                $suggested_mappings[$field['csv_name']] = $suggestion;
            }
        }
        
        return array(
            'detected_fields' => $detected_fields,
            'suggested_mappings' => $suggested_mappings,
            'total_records' => count($records),
            'source_type' => 'airtable',
            'connection_info' => array(
                'base_id' => $base_id,
                'table_name' => $table_name,
                'api_status' => 'connected'
            )
        );
    }
    
    /**
     * Sync data from Airtable
     */
    public function sync_data($sync_type = 'incremental', $filter_options = array()) {
        $config = get_option('lai_airtable_config');
        
        if (!$config || !isset($config['api_key'])) {
            return array('success' => false, 'error' => 'Airtable not configured');
        }
        
        $api_key = $config['api_key'];
        $base_id = $config['base_id'];
        $table_name = $config['table_name'];
        
        $fetch_options = array();
        
        // Configure sync based on type
        if ($sync_type === 'incremental' && isset($config['last_sync'])) {
            $fetch_options['filterByFormula'] = 'IS_AFTER({Last Modified}, "' . $config['last_sync'] . '")';
        }
        
        // Add custom filters
        if (!empty($filter_options['formula'])) {
            $fetch_options['filterByFormula'] = $filter_options['formula'];
        }
        
        if (!empty($filter_options['view'])) {
            $fetch_options['view'] = $filter_options['view'];
        }
        
        // Fetch all records (handle pagination)
        $all_records = $this->fetch_all_records($api_key, $base_id, $table_name, $fetch_options);
        
        if (isset($all_records['error'])) {
            return array('success' => false, 'error' => $all_records['error']);
        }
        
        // Get field mappings
        $field_mappings = get_option('lai_airtable_field_mappings', array());
        
        if (empty($field_mappings)) {
            return array('success' => false, 'error' => 'Field mappings not configured');
        }
        
        // Process records
        $import_results = array(
            'success' => true,
            'total_rows' => count($all_records),
            'processed_rows' => 0,
            'successful_imports' => 0,
            'failed_imports' => 0,
            'errors' => array(),
            'created_records' => array(
                'cases' => array(),
                'contacts' => array(),
                'financials' => array()
            )
        );
        
        foreach ($all_records as $record) {
            $row_result = $this->process_airtable_record($record, $field_mappings);
            
            $import_results['processed_rows']++;
            
            if ($row_result['success']) {
                $import_results['successful_imports']++;
                
                // Merge created records
                foreach ($row_result['created_records'] as $type => $records) {
                    $import_results['created_records'][$type] = array_merge(
                        $import_results['created_records'][$type] ?? array(),
                        $records
                    );
                }
            } else {
                $import_results['failed_imports']++;
                $import_results['errors'][] = 'Record ID ' . $record['id'] . ': ' . implode(', ', $row_result['errors']);
            }
        }
        
        return $import_results;
    }
    
    /**
     * Process import (main entry point)
     */
    public function process_import($data, $options = array()) {
        $preview_only = $options['preview_only'] ?? false;
        $field_mappings = $options['field_mappings'] ?? array();
        
        if ($preview_only) {
            return $this->preview_airtable_import($data, $field_mappings, $options);
        }
        
        return $this->sync_data('manual', $data);
    }
    
    /**
     * Preview Airtable import
     */
    private function preview_airtable_import($data, $field_mappings, $options) {
        $max_rows = $options['max_rows'] ?? 5;
        
        // Get sample records
        $records = $this->fetch_records($data['api_key'], $data['base_id'], $data['table_name'], array(
            'maxRecords' => $max_rows
        ));
        
        if (isset($records['error'])) {
            return array('error' => $records['error']);
        }
        
        $preview_data = array();
        $validation_errors = array();
        
        foreach ($records as $index => $record) {
            $row_result = $this->process_airtable_record($record, $field_mappings, true);
            
            $preview_data[] = array(
                'row_number' => $index + 1,
                'airtable_id' => $record['id'],
                'original_data' => $record['fields'],
                'mapped_data' => $row_result['mapped_data'] ?? array(),
                'errors' => $row_result['errors'] ?? array(),
                'valid' => $row_result['success'] ?? false
            );
            
            if (!empty($row_result['errors'])) {
                $validation_errors = array_merge($validation_errors, $row_result['errors']);
            }
        }
        
        return array(
            'preview_data' => $preview_data,
            'validation_errors' => $validation_errors,
            'total_records' => count($records),
            'preview_rows' => count($preview_data),
            'source_type' => 'airtable'
        );
    }
    
    /**
     * Fetch records from Airtable
     */
    private function fetch_records($api_key, $base_id, $table_name, $options = array()) {
        $url = $this->api_base_url . $base_id . '/' . urlencode($table_name);
        
        // Add query parameters
        if (!empty($options)) {
            $url .= '?' . http_build_query($options);
        }
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('error' => 'Connection failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code === 200) {
            return $data['records'] ?? array();
        } else {
            return array('error' => 'API Error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }
    }
    
    /**
     * Fetch all records with pagination
     */
    private function fetch_all_records($api_key, $base_id, $table_name, $options = array()) {
        $all_records = array();
        $offset = null;
        
        do {
            $request_options = $options;
            if ($offset) {
                $request_options['offset'] = $offset;
            }
            
            $response = $this->fetch_records($api_key, $base_id, $table_name, $request_options);
            
            if (isset($response['error'])) {
                return $response;
            }
            
            $all_records = array_merge($all_records, $response);
            
            // Check for pagination
            $offset = $response['offset'] ?? null;
            
            // Rate limiting
            if ($offset) {
                usleep($this->rate_limit_delay * 1000);
            }
            
        } while ($offset);
        
        return $all_records;
    }
    
    /**
     * Process single Airtable record
     */
    private function process_airtable_record($record, $field_mappings, $preview_only = false) {
        $result = array(
            'success' => false,
            'errors' => array(),
            'mapped_data' => array(),
            'created_records' => array()
        );
        
        $fields = $record['fields'] ?? array();
        $table_data = array();
        
        // Map Airtable fields to database fields
        foreach ($fields as $airtable_field => $value) {
            if (isset($field_mappings[$airtable_field])) {
                $mapping = $field_mappings[$airtable_field];
                
                // Convert Airtable value to database format
                $converted_value = $this->convert_airtable_value($value, $mapping);
                
                if ($converted_value['valid']) {
                    $table = $mapping['target_table'];
                    $field = $mapping['target_field'];
                    
                    if (!isset($table_data[$table])) {
                        $table_data[$table] = array();
                    }
                    
                    $table_data[$table][$field] = $converted_value['value'];
                } else {
                    $result['errors'][] = "Field '{$airtable_field}': " . $converted_value['error'];
                }
            }
        }
        
        $result['mapped_data'] = $table_data;
        
        if (empty($result['errors'])) {
            if (!$preview_only) {
                // Insert into database
                $insert_result = $this->insert_mapped_data($table_data, $record['id']);
                
                if ($insert_result['success']) {
                    $result['success'] = true;
                    $result['created_records'] = $insert_result['created_records'];
                } else {
                    $result['errors'] = $insert_result['errors'];
                }
            } else {
                $result['success'] = true;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert Airtable value to database format
     */
    private function convert_airtable_value($value, $mapping) {
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
        
        // Handle Airtable-specific field types
        if (is_array($value)) {
            // Airtable arrays (multiple select, attachments, etc.)
            if ($data_type === 'array' || $data_type === 'text') {
                return array('valid' => true, 'value' => implode(', ', $value));
            } elseif ($data_type === 'json') {
                return array('valid' => true, 'value' => json_encode($value));
            } else {
                return array('valid' => true, 'value' => $value[0] ?? '');
            }
        }
        
        // Standard data type conversion
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
                if (!is_string($value) || !strtotime($value)) {
                    return array('valid' => false, 'error' => 'Invalid date format');
                }
                $value = date('Y-m-d', strtotime($value));
                break;
                
            case 'datetime':
                if (!is_string($value) || !strtotime($value)) {
                    return array('valid' => false, 'error' => 'Invalid datetime format');
                }
                $value = date('Y-m-d H:i:s', strtotime($value));
                break;
        }
        
        return array('valid' => true, 'value' => $value);
    }
    
    /**
     * Insert mapped data into database
     */
    private function insert_mapped_data($table_data, $airtable_id) {
        $result = array(
            'success' => false,
            'errors' => array(),
            'created_records' => array()
        );
        
        try {
            // Start transaction
            $this->wpdb->query('START TRANSACTION');
            
            $created_ids = array();
            
            // Insert in proper order: contacts -> cases -> financials
            $insert_order = array('klage_contacts', 'klage_cases', 'klage_financials');
            
            foreach ($insert_order as $table) {
                if (!isset($table_data[$table])) {
                    continue;
                }
                
                $data = $table_data[$table];
                
                // Add metadata
                $data['created_at'] = current_time('mysql');
                $data['import_source'] = 'airtable';
                $data['external_id'] = $airtable_id;
                
                // Handle foreign key relationships
                if ($table === 'klage_cases' && isset($created_ids['klage_contacts'])) {
                    // Link case to contact using new v2.0.0 structure
                    $case_contact_data = array(
                        'case_id' => null, // Will be filled after case creation
                        'contact_id' => $created_ids['klage_contacts'],
                        'role' => 'debtor',
                        'active_status' => 1,
                        'created_at' => current_time('mysql')
                    );
                }
                
                $insert_result = $this->wpdb->insert(
                    $this->wpdb->prefix . $table,
                    $data
                );
                
                if ($insert_result === false) {
                    throw new Exception("Failed to insert into {$table}: " . $this->wpdb->last_error);
                }
                
                $created_ids[$table] = $this->wpdb->insert_id;
                $result['created_records'][str_replace('klage_', '', $table)][] = $created_ids[$table];
                
                // Create case-contact relationship if needed
                if ($table === 'klage_cases' && isset($case_contact_data)) {
                    $case_contact_data['case_id'] = $created_ids[$table];
                    
                    $this->wpdb->insert(
                        $this->wpdb->prefix . 'klage_case_contacts',
                        $case_contact_data
                    );
                }
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            $result['success'] = true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Detect Airtable field type
     */
    private function detect_airtable_field_type($value) {
        if (is_array($value)) {
            return 'array';
        } elseif (is_bool($value)) {
            return 'boolean';
        } elseif (is_numeric($value)) {
            return is_float($value) ? 'decimal' : 'integer';
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (strtotime($value) !== false) {
            return 'date';
        } else {
            return 'string';
        }
    }
    
    /**
     * Get Airtable field type from field name and samples
     */
    private function get_airtable_field_type($field_name, $sample_values) {
        // This could be enhanced with Airtable API field type detection
        // For now, we'll use field name patterns
        
        $field_name_lower = strtolower($field_name);
        
        if (strpos($field_name_lower, 'email') !== false) {
            return 'Email';
        } elseif (strpos($field_name_lower, 'phone') !== false) {
            return 'Phone number';
        } elseif (strpos($field_name_lower, 'url') !== false || strpos($field_name_lower, 'link') !== false) {
            return 'URL';
        } elseif (strpos($field_name_lower, 'date') !== false) {
            return 'Date';
        } elseif (strpos($field_name_lower, 'attachment') !== false) {
            return 'Attachment';
        } else {
            return 'Single line text';
        }
    }
    
    /**
     * Determine primary data type from samples
     */
    private function determine_primary_data_type($data_types) {
        if (empty($data_types)) {
            return 'string';
        }
        
        // Get most common type
        $type_counts = array_count_values($data_types);
        arsort($type_counts);
        
        return array_keys($type_counts)[0];
    }
    
    /**
     * Extract field names from records
     */
    private function extract_field_names($records) {
        $field_names = array();
        
        foreach ($records as $record) {
            $fields = $record['fields'] ?? array();
            $field_names = array_merge($field_names, array_keys($fields));
        }
        
        return array_unique($field_names);
    }
    
    /**
     * Suggest field mapping based on Airtable field
     */
    private function suggest_field_mapping($field_data) {
        $field_name = $field_data['csv_name'];
        $field_name_lower = strtolower($field_name);
        
        // Common Airtable field mappings
        $patterns = array(
            'email' => array(
                'pattern' => '/email|e-mail|mail/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email'
            ),
            'first_name' => array(
                'pattern' => '/first.?name|vorname|firstname/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string'
            ),
            'last_name' => array(
                'pattern' => '/last.?name|nachname|lastname|surname/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string'
            ),
            'company' => array(
                'pattern' => '/company|firma|unternehmen|organization/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'company_name',
                'data_type' => 'string'
            ),
            'phone' => array(
                'pattern' => '/phone|telefon|tel/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'phone',
                'data_type' => 'string'
            ),
            'case_id' => array(
                'pattern' => '/case.?id|fall.?id|id/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_id',
                'data_type' => 'string'
            ),
            'status' => array(
                'pattern' => '/status|zustand/i',
                'target_table' => 'klage_cases',
                'target_field' => 'case_status',
                'data_type' => 'string'
            ),
            'amount' => array(
                'pattern' => '/amount|betrag|summe|claim/i',
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal'
            )
        );
        
        foreach ($patterns as $pattern_name => $pattern_config) {
            if (preg_match($pattern_config['pattern'], $field_name)) {
                return array(
                    'target_table' => $pattern_config['target_table'],
                    'target_field' => $pattern_config['target_field'],
                    'data_type' => $pattern_config['data_type'],
                    'confidence' => 'high',
                    'source' => 'airtable_pattern'
                );
            }
        }
        
        return null;
    }
}