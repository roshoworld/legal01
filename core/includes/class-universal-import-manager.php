<?php
/**
 * Universal Import Manager v1.9.0
 * Handles dynamic field mapping and import from multiple client sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Universal_Import_Manager {
    
    private $wpdb;
    private $supported_clients = array();
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->init_supported_clients();
        $this->add_hooks();
    }
    
    /**
     * Initialize supported client configurations
     */
    private function init_supported_clients() {
        $this->supported_clients = array(
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
                    'Received_Date' => 'date'
                )
            ),
            'generic_csv' => array(
                'name' => 'Generic CSV',
                'description' => 'Universal CSV import with custom field mapping',
                'version' => '1.0',
                'field_mappings' => array(), // Dynamic mapping
                'required_fields' => array(),
                'validation_rules' => array()
            )
        );
    }
    
    /**
     * Get Forderungen.com field mapping configuration
     */
    private function get_forderungen_com_mapping() {
        return array(
            // Forderungen.com CSV Column => Our Database Field
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
            'Status (from Outbound Letters)' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'outbound_letters_status',
                'data_type' => 'string',
                'allow_empty' => true
            ),
            'Outbound Letters Pdf' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'outbound_letters_pdf_url',
                'data_type' => 'url',
                'allow_empty' => true
            ),
            'User ID' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'external_user_id',
                'data_type' => 'integer',
                'required' => true
            ),
            'User_First_Name' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'users_first_name',
                'data_type' => 'string',
                'required' => true
            ),
            'User_Last_Name' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'users_last_name',
                'data_type' => 'string',
                'required' => true
            ),
            'User Street' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'users_street',
                'data_type' => 'string'
            ),
            'User_Street_Number' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'users_street_number',
                'data_type' => 'string'
            ),
            'User_Postal_Code' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'users_postal_code',
                'data_type' => 'string'
            ),
            'User City' => array(
                'target_table' => 'klage_clients',
                'target_field' => 'users_city',
                'data_type' => 'string'
            ),
            'ART15_claim_damages' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'art15_claim_damages',
                'data_type' => 'decimal',
                'allow_empty' => true
            ),
            'Debtor_Name' => array(
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_name',
                'data_type' => 'string',
                'required' => true
            ),
            'Debtor_Street' => array(
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_street',
                'data_type' => 'string'
            ),
            'Debtor_House_Number' => array(
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_house_number',
                'data_type' => 'string'
            ),
            'Debtor_Postal_Code' => array(
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_postal_code',
                'data_type' => 'string'
            ),
            'Debtor_City' => array(
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_city',
                'data_type' => 'string'
            ),
            'Debtor_Country' => array(
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_country',
                'data_type' => 'string'
            ),
            'Created Date' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_creation_date',
                'data_type' => 'datetime'
            ),
            'Case Documents Attachments ALL' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_documents_attachments',
                'data_type' => 'text',
                'allow_empty' => true
            ),
            'Received_Time (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_received_time',
                'data_type' => 'time',
                'allow_empty' => true
            ),
            'Received_Date (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_received_date',
                'data_type' => 'date',
                'allow_empty' => true
            ),
            'Sender_Email (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_sender_email',
                'data_type' => 'email',
                'allow_empty' => true
            ),
            'User_Email (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_user_email',
                'data_type' => 'email',
                'allow_empty' => true
            ),
            'Tracking_Pixel (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_tracking_pixel',
                'data_type' => 'url',
                'allow_empty' => true
            ),
            'Subject (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_subject',
                'data_type' => 'string',
                'allow_empty' => true
            ),
            'Content (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_emails',
                'target_field' => 'emails_content',
                'data_type' => 'text',
                'allow_empty' => true
            ),
            'Number of Spam Emails (from Linked_Emails_Primary)' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'number_of_spam_emails',
                'data_type' => 'integer',
                'allow_empty' => true
            )
        );
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        add_action('wp_ajax_cah_detect_csv_fields', array($this, 'ajax_detect_csv_fields'));
        add_action('wp_ajax_cah_preview_import', array($this, 'ajax_preview_import'));
        add_action('wp_ajax_cah_process_import', array($this, 'ajax_process_import'));
        add_action('wp_ajax_cah_save_field_mapping', array($this, 'ajax_save_field_mapping'));
        add_action('wp_ajax_cah_load_client_mapping', array($this, 'ajax_load_client_mapping'));
    }
    
    /**
     * Detect CSV fields and suggest mappings
     */
    public function detect_csv_fields($csv_content, $client_type = 'generic') {
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
            
            // Suggest mapping based on client type
            $suggestion = $this->suggest_field_mapping($field_name, $client_type);
            if ($suggestion) {
                $suggested_mappings[$field_name] = $suggestion;
            }
        }
        
        return array(
            'detected_fields' => $detected_fields,
            'suggested_mappings' => $suggested_mappings,
            'client_config' => $this->supported_clients[$client_type] ?? null,
            'total_rows' => count($lines) - 1 // Exclude header
        );
    }
    
    /**
     * Get sample data from CSV for field preview
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
        
        // Check for URL
        $url_count = 0;
        foreach ($sample_values as $value) {
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $url_count++;
            }
        }
        if ($url_count > count($sample_values) * 0.8) {
            return 'url';
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
        
        // Check for integer
        $int_count = 0;
        foreach ($sample_values as $value) {
            if (is_numeric($value) && intval($value) == $value) {
                $int_count++;
            }
        }
        if ($int_count > count($sample_values) * 0.8) {
            return 'integer';
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
        
        return 'string';
    }
    
    /**
     * Calculate percentage of empty values
     */
    private function calculate_empty_percentage($lines, $field_index) {
        $total_rows = count($lines) - 1; // Exclude header
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
     * Suggest field mapping based on field name and client type
     */
    private function suggest_field_mapping($csv_field_name, $client_type) {
        // Exact match for known clients
        if (isset($this->supported_clients[$client_type]['field_mappings'][$csv_field_name])) {
            return $this->supported_clients[$client_type]['field_mappings'][$csv_field_name];
        }
        
        // Fuzzy matching for generic imports
        $field_name_lower = strtolower($csv_field_name);
        
        // Common patterns
        $patterns = array(
            'email' => array(
                'pattern' => '/email|e-mail|mail/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_email',
                'data_type' => 'email'
            ),
            'first_name' => array(
                'pattern' => '/first.?name|vorname|firstname/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_first_name',
                'data_type' => 'string'
            ),
            'last_name' => array(
                'pattern' => '/last.?name|nachname|lastname|surname/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_last_name',
                'data_type' => 'string'
            ),
            'company' => array(
                'pattern' => '/company|firma|unternehmen/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_company',
                'data_type' => 'string'
            ),
            'phone' => array(
                'pattern' => '/phone|telefon|tel/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_phone',
                'data_type' => 'string'
            ),
            'address' => array(
                'pattern' => '/address|adresse|street|straße/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_address',
                'data_type' => 'string'
            ),
            'postal_code' => array(
                'pattern' => '/postal.?code|zip|plz/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_postal_code',
                'data_type' => 'string'
            ),
            'city' => array(
                'pattern' => '/city|stadt/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_city',
                'data_type' => 'string'
            ),
            'country' => array(
                'pattern' => '/country|land/i',
                'target_table' => 'klage_debtors',
                'target_field' => 'debtors_country',
                'data_type' => 'string'
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
     * Preview import with field mappings
     */
    public function preview_import($csv_content, $field_mappings, $max_preview_rows = 5) {
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
            'preview_rows' => count($preview_data)
        );
    }
    
    /**
     * Validate field value according to mapping configuration
     */
    private function validate_field_value($value, $mapping) {
        $data_type = $mapping['data_type'] ?? 'string';
        $allow_empty = $mapping['allow_empty'] ?? false;
        $required = $mapping['required'] ?? false;
        
        // Check if empty and handle accordingly
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
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return array('valid' => false, 'error' => 'Invalid URL format');
                }
                break;
                
            case 'integer':
                if (!is_numeric($value) || intval($value) != $value) {
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
                
            case 'time':
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
                    return array('valid' => false, 'error' => 'Invalid time format');
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
     * Process full import
     */
    public function process_import($csv_content, $field_mappings, $client_type = 'generic', $import_options = array()) {
        $import_results = array(
            'success' => false,
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_imports' => 0,
            'failed_imports' => 0,
            'errors' => array(),
            'created_records' => array(
                'cases' => array(),
                'debtors' => array(),
                'clients' => array(),
                'emails' => array()
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
        
        // Log import activity
        $this->log_import_activity($import_results, $client_type);
        
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
                'debtors' => array(),
                'clients' => array(),
                'emails' => array()
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
            
            // Insert in proper order: clients -> debtors -> cases -> emails
            $insert_order = array('klage_clients', 'klage_debtors', 'klage_cases', 'klage_emails');
            
            foreach ($insert_order as $table) {
                if (!isset($table_data[$table])) {
                    continue;
                }
                
                $data = $table_data[$table];
                
                // Add metadata
                $data['created_at'] = current_time('mysql');
                $data['import_source'] = 'universal_import';
                
                // Handle foreign key relationships
                if ($table === 'klage_cases') {
                    if (isset($created_ids['klage_clients'])) {
                        $data['client_id'] = $created_ids['klage_clients'];
                    }
                    if (isset($created_ids['klage_debtors'])) {
                        $data['debtor_id'] = $created_ids['klage_debtors'];
                    }
                } elseif ($table === 'klage_emails') {
                    if (isset($created_ids['klage_cases'])) {
                        $data['case_id'] = $created_ids['klage_cases'];
                    }
                }
                
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
     * Log import activity
     */
    private function log_import_activity($results, $client_type) {
        $log_entry = array(
            'import_type' => $client_type,
            'total_rows' => $results['total_rows'],
            'successful_imports' => $results['successful_imports'],
            'failed_imports' => $results['failed_imports'],
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        // Store in options table or custom log table
        $import_logs = get_option('cah_import_logs', array());
        $import_logs[] = $log_entry;
        
        // Keep only last 100 import logs
        if (count($import_logs) > 100) {
            $import_logs = array_slice($import_logs, -100);
        }
        
        update_option('cah_import_logs', $import_logs);
    }
    
    /**
     * Get supported clients
     */
    public function get_supported_clients() {
        return $this->supported_clients;
    }
    
    /**
     * AJAX: Detect CSV fields
     */
    public function ajax_detect_csv_fields() {
        if (!wp_verify_nonce($_POST['nonce'] ?? $_POST['universal_import_nonce'], 'cah_universal_import')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_klage_click_cases')) {
            wp_die('Insufficient permissions');
        }
        
        $csv_content = '';
        $client_type = sanitize_text_field($_POST['client_type'] ?? 'generic');
        
        // Handle file upload or direct content
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $csv_content = file_get_contents($_FILES['csv_file']['tmp_name']);
        } elseif (isset($_POST['csv_content']) && !empty($_POST['csv_content'])) {
            $csv_content = sanitize_textarea_field($_POST['csv_content']);
        }
        
        if (empty($csv_content)) {
            wp_send_json_error(array('message' => 'No CSV content provided'));
        }
        
        $result = $this->detect_csv_fields($csv_content, $client_type);
        
        if (isset($result['error'])) {
            wp_send_json_error($result);
        } else {
            // Store CSV content for later use
            $result['csv_content'] = $csv_content;
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX: Preview import
     */
    public function ajax_preview_import() {
        if (!wp_verify_nonce($_POST['nonce'] ?? $_POST['mapping_nonce'], 'cah_universal_import')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_klage_click_cases')) {
            wp_die('Insufficient permissions');
        }
        
        $csv_content = sanitize_textarea_field($_POST['csv_content']);
        $field_mappings = json_decode(stripslashes($_POST['field_mappings']), true);
        
        if (empty($csv_content) || empty($field_mappings)) {
            wp_send_json_error(array('message' => 'Missing CSV content or field mappings'));
        }
        
        $result = $this->preview_import($csv_content, $field_mappings);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Process import
     */
    public function ajax_process_import() {
        if (!wp_verify_nonce($_POST['nonce'] ?? $_POST['mapping_nonce'], 'cah_universal_import')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_klage_click_cases')) {
            wp_die('Insufficient permissions');
        }
        
        $csv_content = sanitize_textarea_field($_POST['csv_content']);
        $field_mappings = json_decode(stripslashes($_POST['field_mappings']), true);
        $client_type = sanitize_text_field($_POST['client_type'] ?? 'generic');
        
        if (empty($csv_content) || empty($field_mappings)) {
            wp_send_json_error(array('message' => 'Missing CSV content or field mappings'));
        }
        
        $result = $this->process_import($csv_content, $field_mappings, $client_type);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Load client mapping
     */
    public function ajax_load_client_mapping() {
        if (!wp_verify_nonce($_POST['nonce'] ?? $_POST['universal_import_nonce'], 'cah_universal_import')) {
            wp_die('Security check failed');
        }
        
        $client_type = sanitize_text_field($_POST['client_type']);
        
        $result = array(
            'success' => true,
            'client_config' => $this->supported_clients[$client_type] ?? null
        );
        
        wp_send_json_success($result);
    }
}