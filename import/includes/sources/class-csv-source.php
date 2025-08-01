<?php
/**
 * CSV Source Handler
 * Traditional CSV file import functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_CSV_Source {
    
    private $wpdb;
    private $field_mapper;
    
    public function __construct($wpdb, $field_mapper) {
        $this->wpdb = $wpdb;
        $this->field_mapper = $field_mapper;
    }
    
    /**
     * Detect fields from CSV content
     */
    public function detect_fields($csv_content) {
        $lines = explode("\n", trim($csv_content));
        if (empty($lines)) {
            return array('error' => 'CSV content is empty');
        }
        
        // Parse header row
        $header = str_getcsv($lines[0], ',');
        if (empty($header)) {
            // Try semicolon delimiter
            $header = str_getcsv($lines[0], ';');
        }
        
        if (empty($header)) {
            return array('error' => 'Could not parse CSV header');
        }
        
        $detected_fields = array();
        $suggested_mappings = array();
        
        foreach ($header as $index => $field_name) {
            $field_name = trim($field_name);
            
            $detected_fields[] = array(
                'index' => $index,
                'csv_name' => $field_name,
                'sample_data' => $this->get_sample_data($lines, $index, $field_name),
                'data_type' => $this->detect_data_type($lines, $index),
                'empty_percentage' => $this->calculate_empty_percentage($lines, $index)
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
            'total_rows' => count($lines) - 1,
            'source_type' => 'csv'
        );
    }
    
    /**
     * Process CSV import
     */
    public function process_import($csv_content, $options = array()) {
        $field_mappings = $options['field_mappings'] ?? array();
        $preview_only = $options['preview_only'] ?? false;
        $max_rows = $options['max_rows'] ?? null;
        
        if ($preview_only) {
            return $this->preview_import($csv_content, $field_mappings, $max_rows);
        }
        
        return $this->process_full_import($csv_content, $field_mappings, $options);
    }
    
    /**
     * Preview CSV import
     */
    private function preview_import($csv_content, $field_mappings, $max_preview_rows = 5) {
        $lines = explode("\n", trim($csv_content));
        if (empty($lines)) {
            return array('error' => 'CSV content is empty');
        }
        
        $header = str_getcsv($lines[0], ',');
        if (empty($header)) {
            $header = str_getcsv($lines[0], ';');
        }
        
        $preview_data = array();
        $validation_errors = array();
        
        for ($i = 1; $i <= min($max_preview_rows, count($lines) - 1); $i++) {
            $row = str_getcsv($lines[$i], ',');
            if (empty($row)) {
                $row = str_getcsv($lines[$i], ';');
            }
            
            $mapped_row = array();
            $row_errors = array();
            
            foreach ($header as $index => $csv_field) {
                if (isset($field_mappings[$csv_field]) && isset($row[$index])) {
                    $mapping = $field_mappings[$csv_field];
                    $value = trim($row[$index]);
                    
                    // Validate and convert value
                    $validation_result = $this->validate_field_value($value, $mapping);
                    
                    if ($validation_result['valid']) {
                        $mapped_row[$mapping['target_table']][$mapping['target_field']] = $validation_result['value'];
                    } else {
                        $row_errors[] = "Field '{$csv_field}': " . $validation_result['error'];
                    }
                }
            }
            
            $preview_data[] = array(
                'row_number' => $i,
                'original_data' => $row,
                'mapped_data' => $mapped_row,
                'errors' => $row_errors,
                'valid' => empty($row_errors)
            );
            
            if (!empty($row_errors)) {
                $validation_errors = array_merge($validation_errors, $row_errors);
            }
        }
        
        return array(
            'preview_data' => $preview_data,
            'validation_errors' => $validation_errors,
            'total_rows' => count($lines) - 1,
            'preview_rows' => count($preview_data),
            'source_type' => 'csv'
        );
    }
    
    /**
     * Process full CSV import
     */
    private function process_full_import($csv_content, $field_mappings, $options) {
        $import_results = array(
            'success' => false,
            'total_rows' => 0,
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
        
        $lines = explode("\n", trim($csv_content));
        if (empty($lines)) {
            $import_results['errors'][] = 'CSV content is empty';
            return $import_results;
        }
        
        $header = str_getcsv($lines[0], ',');
        if (empty($header)) {
            $header = str_getcsv($lines[0], ';');
        }
        
        $import_results['total_rows'] = count($lines) - 1;
        
        // Process each row
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i], ',');
            if (empty($row)) {
                $row = str_getcsv($lines[$i], ';');
            }
            
            if (empty(trim(implode('', $row)))) {
                continue; // Skip empty rows
            }
            
            $import_results['processed_rows']++;
            
            $row_result = $this->process_single_row($header, $row, $field_mappings, $i);
            
            if ($row_result['success']) {
                $import_results['successful_imports']++;
                
                // Merge created records
                foreach ($row_result['created_records'] as $table => $records) {
                    $import_results['created_records'][$table] = array_merge(
                        $import_results['created_records'][$table],
                        $records
                    );
                }
            } else {
                $import_results['failed_imports']++;
                $import_results['errors'][] = "Row {$i}: " . implode(', ', $row_result['errors']);
            }
        }
        
        $import_results['success'] = $import_results['successful_imports'] > 0;
        
        return $import_results;
    }
    
    /**
     * Process single CSV row
     */
    private function process_single_row($header, $row, $field_mappings, $row_number) {
        $row_result = array(
            'success' => false,
            'errors' => array(),
            'created_records' => array(
                'cases' => array(),
                'contacts' => array(),
                'financials' => array()
            )
        );
        
        // Group mapped data by target table
        $table_data = array();
        
        foreach ($header as $index => $csv_field) {
            if (isset($field_mappings[$csv_field]) && isset($row[$index])) {
                $mapping = $field_mappings[$csv_field];
                $value = trim($row[$index]);
                
                $validation_result = $this->validate_field_value($value, $mapping);
                
                if ($validation_result['valid']) {
                    $table = $mapping['target_table'];
                    $field = $mapping['target_field'];
                    
                    if (!isset($table_data[$table])) {
                        $table_data[$table] = array();
                    }
                    
                    $table_data[$table][$field] = $validation_result['value'];
                } else {
                    $row_result['errors'][] = "Field '{$csv_field}': " . $validation_result['error'];
                }
            }
        }
        
        if (!empty($row_result['errors'])) {
            return $row_result;
        }
        
        // Insert data into database
        try {
            // Start transaction
            $this->wpdb->query('START TRANSACTION');
            
            $created_ids = array();
            
            // Insert in proper order: contacts -> cases -> case_contacts -> financials
            $insert_order = array('klage_contacts', 'klage_cases', 'klage_financials');
            
            foreach ($insert_order as $table) {
                if (!isset($table_data[$table])) {
                    continue;
                }
                
                $data = $table_data[$table];
                
                // Add metadata
                $data['created_at'] = current_time('mysql');
                $data['import_source'] = 'csv';
                
                // Generate case_id if not provided
                if ($table === 'klage_cases' && empty($data['case_id'])) {
                    $data['case_id'] = 'CSV-' . date('Y') . '-' . str_pad(wp_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                }
                
                // Insert record
                $insert_result = $this->wpdb->insert(
                    $this->wpdb->prefix . $table,
                    $data
                );
                
                if ($insert_result === false) {
                    throw new Exception("Failed to insert into {$table}: " . $this->wpdb->last_error);
                }
                
                $created_ids[$table] = $this->wpdb->insert_id;
                $row_result['created_records'][str_replace('klage_', '', $table)][] = $created_ids[$table];
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
            $row_result['success'] = true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            $row_result['errors'][] = $e->getMessage();
        }
        
        return $row_result;
    }
    
    /**
     * Get sample data from CSV
     */
    private function get_sample_data($lines, $field_index, $field_name, $max_samples = 3) {
        $samples = array();
        $count = 0;
        
        for ($i = 1; $i < count($lines) && $count < $max_samples; $i++) {
            $row = str_getcsv($lines[$i], ',');
            if (empty($row)) {
                $row = str_getcsv($lines[$i], ';');
            }
            
            if (isset($row[$field_index]) && !empty(trim($row[$field_index]))) {
                $samples[] = trim($row[$field_index]);
                $count++;
            }
        }
        
        return $samples;
    }
    
    /**
     * Detect data type based on sample values
     */
    private function detect_data_type($lines, $field_index) {
        $sample_values = array();
        
        for ($i = 1; $i < min(10, count($lines)); $i++) {
            $row = str_getcsv($lines[$i], ',');
            if (empty($row)) {
                $row = str_getcsv($lines[$i], ';');
            }
            
            if (isset($row[$field_index]) && !empty(trim($row[$field_index]))) {
                $sample_values[] = trim($row[$field_index]);
            }
        }
        
        if (empty($sample_values)) {
            return 'empty';
        }
        
        // Check for email
        $email_count = 0;
        foreach ($sample_values as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $email_count++;
            }
        }
        if ($email_count > count($sample_values) * 0.8) {
            return 'email';
        }
        
        // Check for decimal
        $decimal_count = 0;
        foreach ($sample_values as $value) {
            if (is_numeric($value)) {
                $decimal_count++;
            }
        }
        if ($decimal_count > count($sample_values) * 0.8) {
            return 'decimal';
        }
        
        // Check for date
        $date_count = 0;
        foreach ($sample_values as $value) {
            if (strtotime($value) !== false) {
                $date_count++;
            }
        }
        if ($date_count > count($sample_values) * 0.8) {
            return 'date';
        }
        
        return 'string';
    }
    
    /**
     * Calculate empty percentage
     */
    private function calculate_empty_percentage($lines, $field_index) {
        $total_rows = count($lines) - 1;
        $empty_count = 0;
        
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i], ',');
            if (empty($row)) {
                $row = str_getcsv($lines[$i], ';');
            }
            
            if (!isset($row[$field_index]) || empty(trim($row[$field_index]))) {
                $empty_count++;
            }
        }
        
        return $total_rows > 0 ? round(($empty_count / $total_rows) * 100, 1) : 0;
    }
    
    /**
     * Suggest field mapping
     */
    private function suggest_field_mapping($csv_field_name) {
        $field_name_lower = strtolower($csv_field_name);
        
        // Common patterns
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
                'pattern' => '/company|firma|unternehmen/i',
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
                'pattern' => '/case.?id|fall.?id/i',
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
            if (preg_match($pattern_config['pattern'], $csv_field_name)) {
                return array(
                    'target_table' => $pattern_config['target_table'],
                    'target_field' => $pattern_config['target_field'],
                    'data_type' => $pattern_config['data_type'],
                    'confidence' => 'high'
                );
            }
        }
        
        return null;
    }
    
    /**
     * Validate field value
     */
    private function validate_field_value($value, $mapping) {
        $data_type = $mapping['data_type'] ?? 'string';
        $allow_empty = $mapping['allow_empty'] ?? false;
        $required = $mapping['required'] ?? false;
        
        // Check if empty
        if (empty($value)) {
            if ($required) {
                return array('valid' => false, 'error' => 'Required field is empty');
            }
            if (!$allow_empty) {
                return array('valid' => false, 'error' => 'Empty value not allowed');
            }
            return array('valid' => true, 'value' => null);
        }
        
        // Validate by data type
        switch ($data_type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return array('valid' => false, 'error' => 'Invalid email format');
                }
                break;
                
            case 'decimal':
                if (!is_numeric($value)) {
                    return array('valid' => false, 'error' => 'Invalid decimal format');
                }
                $value = floatval($value);
                break;
                
            case 'integer':
                if (!is_numeric($value) || intval($value) != $value) {
                    return array('valid' => false, 'error' => 'Invalid integer format');
                }
                $value = intval($value);
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
        }
        
        return array('valid' => true, 'value' => $value);
    }
}