<?php
/**
 * Import/Export Manager - Handles dynamic CSV templates and data processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Import_Export_Manager {
    
    private $schema_manager;
    private $form_generator;
    
    public function __construct() {
        $this->schema_manager = new CAH_Schema_Manager();
        $this->form_generator = new CAH_Form_Generator();
    }
    
    /**
     * Generate CSV template based on current table schema
     */
    public function generate_csv_template($table_name, $template_type = 'full') {
        $schema = $this->schema_manager->get_complete_schema_definition()[$table_name] ?? null;
        
        if (!$schema) {
            return false;
        }
        
        $columns = $schema['columns'];
        $exclude_fields = array('id', 'created_at', 'updated_at');
        
        // Get field mappings
        $field_mappings = $this->get_field_mappings($table_name, $template_type);
        
        $header = array();
        $sample_data = array();
        
        foreach ($field_mappings as $field_name => $field_config) {
            if (in_array($field_name, $exclude_fields)) {
                continue;
            }
            
            if (!isset($columns[$field_name])) {
                continue;
            }
            
            $header[] = $field_config['csv_label'];
            $sample_data[] = $field_config['sample_value'] ?? '';
        }
        
        // Generate CSV content
        $csv_content = '';
        
        // Add header
        $csv_content .= implode(';', $header) . "\n";
        
        // Add sample data rows
        for ($i = 0; $i < 3; $i++) {
            $csv_content .= implode(';', $sample_data) . "\n";
        }
        
        return $csv_content;
    }
    
    /**
     * Get field mappings for different template types
     */
    private function get_field_mappings($table_name, $template_type) {
        $mappings = array();
        
        if ($table_name === 'klage_cases' && $template_type === 'forderungen_com') {
            $mappings = array(
                'case_id' => array('csv_label' => 'Fall-ID', 'sample_value' => 'SPAM-2024-001'),
                'case_status' => array('csv_label' => 'Fall-Status', 'sample_value' => 'draft'),
                'brief_status' => array('csv_label' => 'Brief-Status', 'sample_value' => 'pending'),
                'briefe' => array('csv_label' => 'Briefe', 'sample_value' => '1'),
                'mandant' => array('csv_label' => 'Mandant', 'sample_value' => 'Ihre Kanzlei'),
                'schuldner' => array('csv_label' => 'Schuldner', 'sample_value' => 'Max Mustermann'),
                'submission_date' => array('csv_label' => 'Einreichungsdatum', 'sample_value' => '2024-01-15'),
                'beweise' => array('csv_label' => 'Beweise', 'sample_value' => 'E-Mail Screenshots'),
                'dokumente' => array('csv_label' => 'Dokumente', 'sample_value' => 'Spam-E-Mail.pdf'),
                'links_zu_dokumenten' => array('csv_label' => 'Links zu Dokumenten', 'sample_value' => 'https://example.com/docs'),
                'debtors_company' => array('csv_label' => 'Firmenname', 'sample_value' => 'Mustermann GmbH'),
                'debtors_first_name' => array('csv_label' => 'Vorname', 'sample_value' => 'Max'),
                'debtors_last_name' => array('csv_label' => 'Nachname', 'sample_value' => 'Mustermann'),
                'debtors_address' => array('csv_label' => 'Adresse', 'sample_value' => 'Musterstraße 123'),
                'debtors_postal_code' => array('csv_label' => 'PLZ', 'sample_value' => '12345'),
                'debtors_city' => array('csv_label' => 'Stadt', 'sample_value' => 'Berlin'),
                'debtors_email' => array('csv_label' => 'E-Mail', 'sample_value' => 'max@mustermann.com')
            );
        } elseif ($table_name === 'klage_cases' && $template_type === 'full') {
            $schema = $this->schema_manager->get_complete_schema_definition()[$table_name];
            foreach ($schema['columns'] as $field_name => $field_def) {
                if (in_array($field_name, array('id', 'created_at', 'updated_at'))) {
                    continue;
                }
                
                $mappings[$field_name] = array(
                    'csv_label' => $this->get_german_field_label($field_name),
                    'sample_value' => $this->get_sample_value($field_name, $field_def)
                );
            }
        } elseif ($table_name === 'klage_debtors') {
            $schema = $this->schema_manager->get_complete_schema_definition()[$table_name];
            foreach ($schema['columns'] as $field_name => $field_def) {
                if (in_array($field_name, array('id', 'created_at', 'updated_at'))) {
                    continue;
                }
                
                $mappings[$field_name] = array(
                    'csv_label' => $this->get_german_field_label($field_name),
                    'sample_value' => $this->get_sample_value($field_name, $field_def)
                );
            }
        }
        
        return $mappings;
    }
    
    /**
     * Get German field label for CSV
     */
    private function get_german_field_label($field_name) {
        $labels = array(
            'case_id' => 'Fall-ID',
            'case_status' => 'Fall-Status',
            'case_priority' => 'Priorität',
            'mandant' => 'Mandant',
            'submission_date' => 'Einreichungsdatum',
            'case_notes' => 'Notizen',
            'brief_status' => 'Brief-Status',
            'briefe' => 'Briefe',
            'schuldner' => 'Schuldner',
            'beweise' => 'Beweise',
            'dokumente' => 'Dokumente',
            'links_zu_dokumenten' => 'Links zu Dokumenten',
            'verfahrensart' => 'Verfahrensart',
            'rechtsgrundlage' => 'Rechtsgrundlage',
            'schadenhoehe' => 'Schadenhöhe',
            'verfahrenswert' => 'Verfahrenswert',
            'erfolgsaussicht' => 'Erfolgsaussicht',
            'risiko_bewertung' => 'Risiko-Bewertung',
            'komplexitaet' => 'Komplexität',
            'kommunikation_sprache' => 'Kommunikationssprache',
            'bearbeitungsstatus' => 'Bearbeitungsstatus',
            'debtors_first_name' => 'Vorname',
            'debtors_last_name' => 'Nachname',
            'debtors_company' => 'Firmenname',
            'debtors_email' => 'E-Mail',
            'debtors_phone' => 'Telefon',
            'debtors_address' => 'Adresse',
            'debtors_postal_code' => 'PLZ',
            'debtors_city' => 'Stadt',
            'debtors_country' => 'Land',
            'rechtsform' => 'Rechtsform',
            'zahlungsverhalten' => 'Zahlungsverhalten',
            'bonität' => 'Bonität',
            'datenquelle' => 'Datenquelle'
        );
        
        return $labels[$field_name] ?? ucfirst(str_replace('_', ' ', $field_name));
    }
    
    /**
     * Get sample value for field
     */
    private function get_sample_value($field_name, $field_def) {
        // Extract default value from field definition
        if (preg_match('/DEFAULT\s+\'([^\']+)\'/', $field_def, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/DEFAULT\s+([0-9.]+)/', $field_def, $matches)) {
            return $matches[1];
        }
        
        // Field-specific sample values
        $samples = array(
            'case_id' => 'SPAM-2024-001',
            'mandant' => 'Ihre Kanzlei',
            'schuldner' => 'Max Mustermann',
            'submission_date' => '2024-01-15',
            'beweise' => 'E-Mail Screenshots',
            'dokumente' => 'Spam-E-Mail.pdf',
            'debtors_first_name' => 'Max',
            'debtors_last_name' => 'Mustermann',
            'debtors_company' => 'Mustermann GmbH',
            'debtors_email' => 'max@mustermann.com',
            'debtors_phone' => '+49 30 12345678',
            'debtors_address' => 'Musterstraße 123',
            'debtors_postal_code' => '12345',
            'debtors_city' => 'Berlin',
            'debtors_country' => 'Deutschland'
        );
        
        return $samples[$field_name] ?? '';
    }
    
    /**
     * Process CSV import
     */
    public function process_csv_import($table_name, $csv_data, $mapping = array()) {
        $results = array(
            'success' => 0,
            'errors' => 0,
            'messages' => array()
        );
        
        // Parse CSV data
        $lines = explode("\n", $csv_data);
        if (empty($lines)) {
            return array('success' => false, 'message' => 'CSV data is empty');
        }
        
        // Get header
        $header = str_getcsv($lines[0], ';');
        
        // Process each row
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }
            
            $row_data = str_getcsv($line, ';');
            
            if (count($row_data) !== count($header)) {
                $results['errors']++;
                $results['messages'][] = "Row $i: Column count mismatch";
                continue;
            }
            
            // Map CSV data to database fields
            $mapped_data = array();
            for ($j = 0; $j < count($header); $j++) {
                $csv_field = trim($header[$j]);
                $db_field = $mapping[$csv_field] ?? $this->map_csv_field_to_db($csv_field);
                
                if ($db_field) {
                    $mapped_data[$db_field] = $this->sanitize_field_value($db_field, trim($row_data[$j]));
                }
            }
            
            // Validate and insert data
            $validation_result = $this->validate_row_data($table_name, $mapped_data);
            
            if ($validation_result['valid']) {
                $insert_result = $this->schema_manager->insert_data($table_name, $mapped_data);
                
                if ($insert_result['success']) {
                    $results['success']++;
                } else {
                    $results['errors']++;
                    $results['messages'][] = "Row $i: " . $insert_result['message'];
                }
            } else {
                $results['errors']++;
                $results['messages'][] = "Row $i: " . implode(', ', $validation_result['errors']);
            }
        }
        
        return $results;
    }
    
    /**
     * Map CSV field to database field
     */
    private function map_csv_field_to_db($csv_field) {
        $mapping = array(
            'Fall-ID' => 'case_id',
            'Fall-Status' => 'case_status',
            'Priorität' => 'case_priority',
            'Mandant' => 'mandant',
            'Einreichungsdatum' => 'submission_date',
            'Notizen' => 'case_notes',
            'Brief-Status' => 'brief_status',
            'Briefe' => 'briefe',
            'Schuldner' => 'schuldner',
            'Beweise' => 'beweise',
            'Dokumente' => 'dokumente',
            'Links zu Dokumenten' => 'links_zu_dokumenten',
            'Verfahrensart' => 'verfahrensart',
            'Rechtsgrundlage' => 'rechtsgrundlage',
            'Schadenhöhe' => 'schadenhoehe',
            'Verfahrenswert' => 'verfahrenswert',
            'Erfolgsaussicht' => 'erfolgsaussicht',
            'Risiko-Bewertung' => 'risiko_bewertung',
            'Komplexität' => 'komplexitaet',
            'Kommunikationssprache' => 'kommunikation_sprache',
            'Bearbeitungsstatus' => 'bearbeitungsstatus',
            'Vorname' => 'debtors_first_name',
            'Nachname' => 'debtors_last_name',
            'Firmenname' => 'debtors_company',
            'E-Mail' => 'debtors_email',
            'Telefon' => 'debtors_phone',
            'Adresse' => 'debtors_address',
            'PLZ' => 'debtors_postal_code',
            'Stadt' => 'debtors_city',
            'Land' => 'debtors_country',
            'Rechtsform' => 'rechtsform',
            'Zahlungsverhalten' => 'zahlungsverhalten',
            'Bonität' => 'bonität',
            'Datenquelle' => 'datenquelle'
        );
        
        return $mapping[$csv_field] ?? null;
    }
    
    /**
     * Sanitize field value based on field type
     */
    private function sanitize_field_value($field_name, $value) {
        // Basic sanitization
        $value = trim($value);
        
        // Field-specific sanitization
        if (strpos($field_name, 'email') !== false) {
            return sanitize_email($value);
        }
        
        if (strpos($field_name, 'date') !== false) {
            if (empty($value)) {
                return null;
            }
            
            // Try to parse different date formats
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
            
            return null;
        }
        
        if (in_array($field_name, array('schadenhoehe', 'verfahrenswert', 'total', 'damages_loss'))) {
            return floatval(str_replace(',', '.', $value));
        }
        
        if (in_array($field_name, array('briefe', 'anzahl_verstoesse'))) {
            return intval($value);
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Validate row data
     */
    private function validate_row_data($table_name, $data) {
        $schema = $this->schema_manager->get_complete_schema_definition()[$table_name];
        $errors = array();
        
        // Check required fields
        $required_fields = array();
        if ($table_name === 'klage_cases') {
            $required_fields = array('case_id', 'mandant');
        } elseif ($table_name === 'klage_debtors') {
            $required_fields = array('debtors_name');
        }
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Required field '$field' is missing";
            }
        }
        
        // Validate email fields
        foreach ($data as $field => $value) {
            if (strpos($field, 'email') !== false && !empty($value)) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format for '$field'";
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Export table data to CSV
     */
    public function export_table_data($table_name, $format = 'csv') {
        $data = $this->schema_manager->get_table_data($table_name, 1000, 0);
        
        if (isset($data['error'])) {
            return false;
        }
        
        if ($format === 'csv') {
            return $this->export_to_csv($data['data']);
        }
        
        return $data['data'];
    }
    
    /**
     * Export data to CSV format
     */
    private function export_to_csv($data) {
        if (empty($data)) {
            return '';
        }
        
        $csv = '';
        
        // Add header
        $header = array_keys($data[0]);
        $csv .= implode(';', $header) . "\n";
        
        // Add data rows
        foreach ($data as $row) {
            $csv_row = array();
            foreach ($row as $value) {
                $csv_row[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $csv .= implode(';', $csv_row) . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Get available templates
     */
    public function get_available_templates() {
        return array(
            'klage_cases' => array(
                'forderungen_com' => array(
                    'name' => 'Forderungen.com Template',
                    'description' => '17 Felder für Forderungen.com Import',
                    'fields' => 17
                ),
                'full' => array(
                    'name' => 'Vollständiges Template',
                    'description' => 'Alle verfügbaren Felder',
                    'fields' => 'all'
                )
            ),
            'klage_debtors' => array(
                'full' => array(
                    'name' => 'Schuldner Template',
                    'description' => 'Alle Schuldner-Felder',
                    'fields' => 'all'
                )
            ),
            // Financial table removed in v1.4.7 - moved to separate plugin
            'klage_financial' => array(
                'full' => array(
                    'name' => 'Finanz Template',
                    'description' => 'Alle Finanz-Felder',
                    'fields' => 'all'
                )
            )
        );
    }
}