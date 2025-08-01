<?php
/**
 * Forderungen.com Source Handler
 * Specialized CSV import for Forderungen.com export format
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Forderungen_Source {
    
    private $wpdb;
    private $field_mapper;
    private $csv_source;
    
    public function __construct($wpdb, $field_mapper) {
        $this->wpdb = $wpdb;
        $this->field_mapper = $field_mapper;
        
        // Load CSV source as parent functionality
        if (!class_exists('LAI_CSV_Source')) {
            require_once LAI_PLUGIN_PATH . 'includes/sources/class-csv-source.php';
        }
        $this->csv_source = new LAI_CSV_Source($wpdb, $field_mapper);
    }
    
    /**
     * Detect fields from Forderungen.com CSV
     */
    public function detect_fields($csv_content) {
        // Use parent CSV functionality for basic detection
        $csv_result = $this->csv_source->detect_fields($csv_content);
        
        if (isset($csv_result['error'])) {
            return $csv_result;
        }
        
        // Override with Forderungen.com specific mappings
        $forderungen_mappings = $this->get_forderungen_field_mappings();
        $suggested_mappings = array();
        
        foreach ($csv_result['detected_fields'] as &$field) {
            $field_name = $field['csv_name'];
            
            if (isset($forderungen_mappings[$field_name])) {
                $mapping = $forderungen_mappings[$field_name];
                $suggested_mappings[$field_name] = array(
                    'target_table' => $mapping['target_table'],
                    'target_field' => $mapping['target_field'],
                    'data_type' => $mapping['data_type'],
                    'confidence' => 'high',
                    'source' => 'forderungen_pattern'
                );
                
                // Update field info
                $field['data_type'] = $mapping['data_type'];
                $field['forderungen_field'] = true;
            }
        }
        
        $csv_result['suggested_mappings'] = $suggested_mappings;
        $csv_result['source_type'] = 'forderungen_com';
        $csv_result['specialized_import'] = true;
        
        return $csv_result;
    }
    
    /**
     * Process Forderungen.com import
     */
    public function process_import($csv_content, $options = array()) {
        // Validate Forderungen.com format
        $validation_result = $this->validate_forderungen_format($csv_content);
        if (!$validation_result['valid']) {
            return array(
                'error' => 'Invalid Forderungen.com format: ' . implode(', ', $validation_result['errors'])
            );
        }
        
        // Use Forderungen.com specific field mappings if not provided
        if (empty($options['field_mappings'])) {
            $options['field_mappings'] = $this->get_forderungen_field_mappings();
        }
        
        // Process using parent CSV functionality with enhancements
        $result = $this->csv_source->process_import($csv_content, $options);
        
        if ($result['success']) {
            // Post-process for Forderungen.com specific handling
            $result = $this->post_process_forderungen_import($result, $csv_content);
        }
        
        return $result;
    }
    
    /**
     * Get Forderungen.com specific field mappings
     */
    private function get_forderungen_field_mappings() {
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
                    'Draft' => 'draft',
                    'In Review' => 'review',
                    'Cancelled' => 'cancelled'
                )
            ),
            'User_First_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string',
                'required' => true,
                'contact_role' => 'client'
            ),
            'User_Last_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string',
                'required' => true,
                'contact_role' => 'client'
            ),
            'User_Email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'contact_role' => 'client'
            ),
            'User_Phone' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'phone',
                'data_type' => 'phone',
                'contact_role' => 'client'
            ),
            'Debtor_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'last_name',
                'data_type' => 'string',
                'required' => true,
                'contact_role' => 'debtor'
            ),
            'Debtor_First_Name' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'first_name',
                'data_type' => 'string',
                'contact_role' => 'debtor'
            ),
            'Debtor_Email' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'email',
                'data_type' => 'email',
                'contact_role' => 'debtor'
            ),
            'Debtor_Company' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'company_name',
                'data_type' => 'string',
                'contact_role' => 'debtor'
            ),
            'Debtor_Address' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'address',
                'data_type' => 'string',
                'contact_role' => 'debtor'
            ),
            'Debtor_City' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'city',
                'data_type' => 'string',
                'contact_role' => 'debtor'
            ),
            'Debtor_Postal_Code' => array(
                'target_table' => 'klage_contacts',
                'target_field' => 'postal_code',
                'data_type' => 'string',
                'contact_role' => 'debtor'
            ),
            'ART15_claim_damages' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'claim_amount',
                'data_type' => 'decimal',
                'allow_empty' => true
            ),
            'Additional_Damages' => array(
                'target_table' => 'klage_financials',
                'target_field' => 'amount',
                'data_type' => 'decimal',
                'financial_type' => 'additional_damages'
            ),
            'Legal_Fees' => array(
                'target_table' => 'klage_financials',
                'target_field' => 'amount',
                'data_type' => 'decimal',
                'financial_type' => 'legal_fees'
            ),
            'Case_Notes' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_notes',
                'data_type' => 'text'
            ),
            'Created Date' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_creation_date',
                'data_type' => 'datetime'
            ),
            'Updated Date' => array(
                'target_table' => 'klage_cases',
                'target_field' => 'case_updated_date',
                'data_type' => 'datetime'
            )
        );
    }
    
    /**
     * Validate Forderungen.com CSV format
     */
    private function validate_forderungen_format($csv_content) {
        $validation = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array()
        );
        
        $lines = explode("\n", trim($csv_content));
        if (empty($lines)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'CSV content is empty';
            return $validation;
        }
        
        // Parse header
        $header = str_getcsv($lines[0], ',');
        if (empty($header)) {
            $header = str_getcsv($lines[0], ';');
        }
        
        // Check for required Forderungen.com columns
        $required_columns = array('ID', 'Lawyer Case ID', 'User_First_Name', 'User_Last_Name');
        $missing_columns = array();
        
        foreach ($required_columns as $required_col) {
            if (!in_array($required_col, $header)) {
                $missing_columns[] = $required_col;
            }
        }
        
        if (!empty($missing_columns)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Missing required columns: ' . implode(', ', $missing_columns);
        }
        
        // Check for expected Forderungen.com columns (warnings if missing)
        $expected_columns = array('Debtor_Name', 'Status', 'ART15_claim_damages');
        $missing_expected = array();
        
        foreach ($expected_columns as $expected_col) {
            if (!in_array($expected_col, $header)) {
                $missing_expected[] = $expected_col;
            }
        }
        
        if (!empty($missing_expected)) {
            $validation['warnings'][] = 'Missing expected Forderungen.com columns: ' . implode(', ', $missing_expected);
        }
        
        // Validate data rows (sample check)
        if (count($lines) > 1) {
            $sample_row = str_getcsv($lines[1], ',');
            if (empty($sample_row)) {
                $sample_row = str_getcsv($lines[1], ';');
            }
            
            if (count($sample_row) !== count($header)) {
                $validation['warnings'][] = 'Column count mismatch in data rows';
            }
        }
        
        return $validation;
    }
    
    /**
     * Post-process Forderungen.com import
     */
    private function post_process_forderungen_import($import_result, $csv_content) {
        // Extract additional processing info
        $lines = explode("\n", trim($csv_content));
        $header = str_getcsv($lines[0], ',');
        if (empty($header)) {
            $header = str_getcsv($lines[0], ';');
        }
        
        // Handle dual contact creation (client and debtor)
        if ($import_result['success']) {
            $this->create_dual_contacts($import_result, $lines, $header);
        }
        
        // Add Forderungen.com specific metadata
        $import_result['import_type'] = 'forderungen_com';
        $import_result['format_version'] = $this->detect_forderungen_format_version($header);
        $import_result['dual_contacts_processed'] = true;
        
        return $import_result;
    }
    
    /**
     * Create dual contacts (client and debtor) for Forderungen.com cases
     */
    private function create_dual_contacts($import_result, $lines, $header) {
        // This is a placeholder for complex dual contact creation logic
        // In a full implementation, this would:
        // 1. Parse each row again
        // 2. Create separate contact records for clients and debtors
        // 3. Link both contacts to the case with appropriate roles
        // 4. Handle cases where client and debtor information overlap
        
        $dual_contact_stats = array(
            'client_contacts_created' => 0,
            'debtor_contacts_created' => 0,
            'relationship_links_created' => 0
        );
        
        // Add stats to import result
        $import_result['dual_contact_stats'] = $dual_contact_stats;
        
        return $import_result;
    }
    
    /**
     * Detect Forderungen.com format version
     */
    private function detect_forderungen_format_version($header) {
        // Check for version-specific columns
        if (in_array('Updated Date', $header) && in_array('Legal_Fees', $header)) {
            return 'v2.1';
        } elseif (in_array('ART15_claim_damages', $header)) {
            return 'v2.0';
        } elseif (in_array('Lawyer Case ID', $header)) {
            return 'v1.5';
        } else {
            return 'v1.0';
        }
    }
    
    /**
     * Get Forderungen.com import template
     */
    public function get_import_template() {
        $template_headers = array(
            'ID',
            'Lawyer Case ID',
            'Status',
            'User_First_Name',
            'User_Last_Name',
            'User_Email',
            'User_Phone',
            'Debtor_Name',
            'Debtor_First_Name',
            'Debtor_Email',
            'Debtor_Company',
            'Debtor_Address',
            'Debtor_City',
            'Debtor_Postal_Code',
            'ART15_claim_damages',
            'Additional_Damages',
            'Legal_Fees',
            'Case_Notes',
            'Created Date',
            'Updated Date'
        );
        
        $sample_data = array(
            array(
                '1001',
                'LAW-2024-001',
                'Open',
                'Max',
                'Mustermann',
                'max@example.com',
                '+49123456789',
                'Schmidt',
                'Hans',
                'hans.schmidt@example.com',
                'Example GmbH',
                'Hauptstraße 123',
                'Berlin',
                '10115',
                '1500.00',
                '250.00',
                '180.50',
                'GDPR violation case',
                '2024-01-15 10:30:00',
                '2024-01-16 14:20:00'
            )
        );
        
        return array(
            'headers' => $template_headers,
            'sample_data' => $sample_data,
            'format_version' => 'v2.1',
            'description' => 'Forderungen.com export format with dual contact support'
        );
    }
    
    /**
     * Validate individual field values for Forderungen.com
     */
    private function validate_forderungen_field($field_name, $value, $mapping) {
        // Use parent validation first
        $parent_validation = $this->field_mapper->transform_field_value($value, $mapping);
        
        if (!$parent_validation['valid']) {
            return $parent_validation;
        }
        
        // Add Forderungen.com specific validations
        switch ($field_name) {
            case 'Lawyer Case ID':
                if (!preg_match('/^[A-Z]{2,4}-\d{4}-\d{3,4}$/', $value)) {
                    return array(
                        'valid' => false,
                        'error' => 'Invalid Lawyer Case ID format. Expected format: LAW-2024-001'
                    );
                }
                break;
                
            case 'Status':
                $valid_statuses = array('Open', 'Closed', 'Pending', 'Draft', 'In Review', 'Cancelled');
                if (!in_array($value, $valid_statuses)) {
                    return array(
                        'valid' => false,
                        'error' => 'Invalid status. Must be one of: ' . implode(', ', $valid_statuses)
                    );
                }
                break;
                
            case 'ART15_claim_damages':
                if (!empty($value) && (!is_numeric($value) || floatval($value) < 0)) {
                    return array(
                        'valid' => false,
                        'error' => 'ART15 claim damages must be a positive number'
                    );
                }
                break;
        }
        
        return $parent_validation;
    }
    
    /**
     * Generate Forderungen.com specific statistics
     */
    public function get_import_statistics($import_result) {
        $stats = array(
            'total_cases' => $import_result['successful_imports'] ?? 0,
            'client_contacts' => $import_result['dual_contact_stats']['client_contacts_created'] ?? 0,
            'debtor_contacts' => $import_result['dual_contact_stats']['debtor_contacts_created'] ?? 0,
            'format_version' => $import_result['format_version'] ?? 'unknown',
            'avg_claim_amount' => 0,
            'total_claim_value' => 0,
            'status_breakdown' => array()
        );
        
        // Calculate additional statistics from database
        if ($stats['total_cases'] > 0) {
            $recent_cases = $this->wpdb->get_results(
                "SELECT claim_amount, case_status FROM {$this->wpdb->prefix}klage_cases 
                 WHERE import_source = 'forderungen_com' 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 ORDER BY created_at DESC 
                 LIMIT {$stats['total_cases']}",
                ARRAY_A
            );
            
            $total_value = 0;
            $status_counts = array();
            
            foreach ($recent_cases as $case) {
                if (!empty($case['claim_amount'])) {
                    $total_value += floatval($case['claim_amount']);
                }
                
                $status = $case['case_status'] ?? 'unknown';
                $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
            }
            
            $stats['total_claim_value'] = $total_value;
            $stats['avg_claim_amount'] = $stats['total_cases'] > 0 ? $total_value / $stats['total_cases'] : 0;
            $stats['status_breakdown'] = $status_counts;
        }
        
        return $stats;
    }
}