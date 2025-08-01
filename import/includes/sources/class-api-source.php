<?php
/**
 * Generic API Source Handler
 * Handles REST API integrations for custom data sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_API_Source {
    
    private $wpdb;
    private $field_mapper;
    
    public function __construct($wpdb, $field_mapper) {
        $this->wpdb = $wpdb;
        $this->field_mapper = $field_mapper;
    }
    
    /**
     * Test API connection
     */
    public function test_connection($api_url, $auth_token, $auth_method = 'bearer') {
        $headers = $this->get_auth_headers($auth_token, $auth_method);
        
        $response = wp_remote_get($api_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return array(
                'success' => true,
                'message' => 'Connection successful',
                'data_preview' => $data
            );
        } else {
            return array(
                'success' => false,
                'error' => 'API returned status code: ' . $response_code,
                'response_body' => wp_remote_retrieve_body($response)
            );
        }
    }
    
    /**
     * Fetch data from API
     */
    public function fetch_data($api_url, $auth_token, $auth_method = 'bearer', $params = array()) {
        $headers = $this->get_auth_headers($auth_token, $auth_method);
        
        // Add query parameters if provided
        if (!empty($params)) {
            $api_url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($api_url, array(
            'headers' => $headers,
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return array('error' => 'Request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $data = json_decode($body, true);
            
            if ($data === null) {
                return array('error' => 'Invalid JSON response');
            }
            
            return $data;
        } else {
            return array('error' => 'API error (HTTP ' . $response_code . '): ' . $body);
        }
    }
    
    /**
     * Detect fields from API data
     */
    public function detect_fields($api_data) {
        // Extract first record for field analysis
        $sample_record = null;
        
        if (isset($api_data['data']) && is_array($api_data['data'])) {
            $sample_record = !empty($api_data['data']) ? $api_data['data'][0] : null;
        } elseif (isset($api_data['records']) && is_array($api_data['records'])) {
            $sample_record = !empty($api_data['records']) ? $api_data['records'][0] : null;
        } elseif (is_array($api_data) && !empty($api_data)) {
            $sample_record = $api_data[0];
        }
        
        if (!$sample_record) {
            return array('error' => 'No data records found for field detection');
        }
        
        $detected_fields = array();
        $suggested_mappings = array();
        
        // Flatten nested objects for field detection
        $flattened_fields = $this->flatten_object($sample_record);
        
        foreach ($flattened_fields as $field_name => $value) {
            $detected_fields[] = array(
                'csv_name' => $field_name,
                'api_name' => $field_name,
                'sample_data' => array($value),
                'data_type' => $this->detect_api_field_type($value),
                'empty_percentage' => empty($value) ? 100 : 0,
                'field_path' => $field_name
            );
            
            // Suggest mapping
            $suggestion = $this->suggest_field_mapping($field_name);
            if ($suggestion) {
                $suggested_mappings[$field_name] = $suggestion;
            }
        }
        
        return array(
            'detected_fields' => $detected_fields,
            'suggested_mappings' => $suggested_mappings,
            'total_records' => $this->count_records($api_data),
            'source_type' => 'api'
        );
    }
    
    /**
     * Process API import
     */
    public function process_import($api_data, $options = array()) {
        $field_mappings = $options['field_mappings'] ?? array();
        $preview_only = $options['preview_only'] ?? false;
        
        if ($preview_only) {
            return $this->preview_api_import($api_data, $field_mappings, $options);
        }
        
        return $this->process_full_api_import($api_data, $field_mappings, $options);
    }
    
    /**
     * Preview API import
     */
    private function preview_api_import($api_data, $field_mappings, $options) {
        $max_rows = $options['max_rows'] ?? 5;
        $records = $this->extract_records($api_data);
        
        if (isset($records['error'])) {
            return $records;
        }
        
        $preview_data = array();
        $validation_errors = array();
        
        foreach (array_slice($records, 0, $max_rows) as $index => $record) {
            $row_result = $this->process_api_record($record, $field_mappings, true);
            
            $preview_data[] = array(
                'row_number' => $index + 1,
                'original_data' => $record,
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
            'source_type' => 'api'
        );
    }
    
    /**
     * Process full API import
     */
    private function process_full_api_import($api_data, $field_mappings, $options) {
        $records = $this->extract_records($api_data);
        
        if (isset($records['error'])) {
            return array(
                'success' => false,
                'error' => $records['error']
            );
        }
        
        $import_results = array(
            'success' => false,
            'total_rows' => count($records),
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
        
        foreach ($records as $record) {
            $row_result = $this->process_api_record($record, $field_mappings, false);
            
            $import_results['processed_rows']++;
            
            if ($row_result['success']) {
                $import_results['successful_imports']++;
                
                // Merge created records
                foreach ($row_result['created_records'] as $type => $record_ids) {
                    $import_results['created_records'][$type] = array_merge(
                        $import_results['created_records'][$type] ?? array(),
                        $record_ids
                    );
                }
            } else {
                $import_results['failed_imports']++;
                $import_results['errors'][] = 'Record ' . ($import_results['processed_rows']) . ': ' . implode(', ', $row_result['errors']);
            }
        }
        
        $import_results['success'] = $import_results['successful_imports'] > 0;
        
        return $import_results;
    }
    
    /**
     * Process single API record
     */
    private function process_api_record($record, $field_mappings, $preview_only = false) {
        $result = array(
            'success' => false,
            'errors' => array(),
            'mapped_data' => array(),
            'created_records' => array()
        );
        
        // Flatten record for field mapping
        $flattened_record = $this->flatten_object($record);
        $table_data = array();
        
        // Map API fields to database fields
        foreach ($flattened_record as $api_field => $value) {
            if (isset($field_mappings[$api_field])) {
                $mapping = $field_mappings[$api_field];
                
                // Convert API value to database format
                $converted_value = $this->convert_api_value($value, $mapping);
                
                if ($converted_value['valid']) {
                    $table = $mapping['target_table'];
                    $field = $mapping['target_field'];
                    
                    if (!isset($table_data[$table])) {
                        $table_data[$table] = array();
                    }
                    
                    $table_data[$table][$field] = $converted_value['value'];
                } else {
                    $result['errors'][] = "Field '{$api_field}': " . $converted_value['error'];
                }
            }
        }
        
        $result['mapped_data'] = $table_data;
        
        if (empty($result['errors'])) {
            if (!$preview_only) {
                // Insert into database
                $insert_result = $this->insert_mapped_data($table_data);
                
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
     * Get authentication headers
     */
    private function get_auth_headers($auth_token, $auth_method) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        switch ($auth_method) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $auth_token;
                break;
            case 'token':
                $headers['Authorization'] = 'Token ' . $auth_token;
                break;
            case 'api_key':
                $headers['X-API-Key'] = $auth_token;
                break;
            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode($auth_token);
                break;
        }
        
        return $headers;
    }
    
    /**
     * Flatten nested object for field mapping
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
     * Extract records from API response
     */
    private function extract_records($api_data) {
        if (isset($api_data['error'])) {
            return $api_data;
        }
        
        // Try different common API response structures
        if (isset($api_data['data']) && is_array($api_data['data'])) {
            return $api_data['data'];
        } elseif (isset($api_data['records']) && is_array($api_data['records'])) {
            return $api_data['records'];
        } elseif (isset($api_data['items']) && is_array($api_data['items'])) {
            return $api_data['items'];
        } elseif (isset($api_data['results']) && is_array($api_data['results'])) {
            return $api_data['results'];
        } elseif (is_array($api_data) && $this->is_record_array($api_data)) {
            return $api_data;
        }
        
        return array('error' => 'Could not extract records from API response');
    }
    
    /**
     * Check if array contains record objects
     */
    private function is_record_array($data) {
        if (!is_array($data) || empty($data)) {
            return false;
        }
        
        // Check if first element looks like a record (has string keys)
        $first_element = $data[0];
        return is_array($first_element) && !empty(array_filter(array_keys($first_element), 'is_string'));
    }
    
    /**
     * Count total records in API response
     */
    private function count_records($api_data) {
        $records = $this->extract_records($api_data);
        return is_array($records) ? count($records) : 0;
    }
    
    /**
     * Detect API field type
     */
    private function detect_api_field_type($value) {
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
     * Suggest field mapping for API field
     */
    private function suggest_field_mapping($field_name) {
        $field_name_lower = strtolower($field_name);
        
        // Common API field patterns
        $patterns = array(
            'email' => array(
                'pattern' => '/email|mail/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email'
            ),
            'first_name' => array(
                'pattern' => '/first.?name|fname/i',
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string'
            ),
            'last_name' => array(
                'pattern' => '/last.?name|lname|surname/i',
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
                'data_type' => 'phone'
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
                'pattern' => '/amount|value|sum|total/i',
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
                    'confidence' => 'medium',
                    'source' => 'api_pattern'
                );
            }
        }
        
        return null;
    }
    
    /**
     * Convert API value to database format
     */
    private function convert_api_value($value, $mapping) {
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
        }
        
        return array('valid' => true, 'value' => $value);
    }
    
    /**
     * Insert mapped data into database
     */
    private function insert_mapped_data($table_data) {
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
                $data['import_source'] = 'api';
                
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
                    'role' => 'debtor',
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
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
}