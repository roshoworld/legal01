<?php
/**
 * Export Manager - Handles data export functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAI_Export_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->add_hooks();
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // AJAX handlers for export functionality
        add_action('wp_ajax_lai_export_cases', array($this, 'ajax_export_cases'));
        add_action('wp_ajax_lai_download_template', array($this, 'ajax_download_template'));
        
        // Handle direct export requests
        add_action('wp_loaded', array($this, 'handle_export_request'));
    }
    
    /**
     * Handle direct export requests
     */
    public function handle_export_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'lai_export_cases') {
            if (!wp_verify_nonce($_GET['nonce'], 'lai_ajax_nonce')) {
                wp_die('Security check failed');
            }
            
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            
            $this->export_cases_csv();
            exit;
        }
    }
    
    /**
     * Export cases to CSV
     */
    public function export_cases_csv($options = array()) {
        // Get export parameters
        $date_from = sanitize_text_field($_GET['export_date_from'] ?? '');
        $date_to = sanitize_text_field($_GET['export_date_to'] ?? '');
        $include_contacts = isset($_GET['include_contacts']);
        $include_financials = isset($_GET['include_financials']);
        $include_notes = isset($_GET['include_notes']);
        $format = sanitize_text_field($_GET['export_format'] ?? 'csv');
        
        // Build query
        $query = "SELECT c.*, co.email, co.first_name, co.last_name, co.company_name, co.phone";
        if ($include_financials) {
            $query .= ", f.amount as financial_amount, f.description as financial_description";
        }
        $query .= " FROM {$this->wpdb->prefix}klage_cases c";
        $query .= " LEFT JOIN {$this->wpdb->prefix}klage_case_contacts cc ON c.id = cc.case_id";
        $query .= " LEFT JOIN {$this->wpdb->prefix}klage_contacts co ON cc.contact_id = co.id";
        
        if ($include_financials) {
            $query .= " LEFT JOIN {$this->wpdb->prefix}klage_financials f ON c.id = f.case_id";
        }
        
        $where_conditions = array();
        $query_params = array();
        
        if ($date_from) {
            $where_conditions[] = "c.case_creation_date >= %s";
            $query_params[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = "c.case_creation_date <= %s";
            $query_params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($where_conditions)) {
            $query .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        $query .= " ORDER BY c.case_creation_date DESC";
        
        // Execute query
        if (!empty($query_params)) {
            $results = $this->wpdb->get_results($this->wpdb->prepare($query, $query_params), ARRAY_A);
        } else {
            $results = $this->wpdb->get_results($query, ARRAY_A);
        }
        
        // Generate CSV
        $delimiter = $format === 'csv_semicolon' ? ';' : ',';
        $filename = 'cases_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        if (!empty($results)) {
            $headers = array_keys($results[0]);
            
            // Filter headers based on options
            if (!$include_notes) {
                $headers = array_filter($headers, function($header) {
                    return !in_array($header, array('case_notes', 'case_documents_attachments'));
                });
            }
            
            fputcsv($output, $headers, $delimiter);
            
            // Write data
            foreach ($results as $row) {
                if (!$include_notes) {
                    unset($row['case_notes'], $row['case_documents_attachments']);
                }
                fputcsv($output, $row, $delimiter);
            }
        }
        
        fclose($output);
    }
    
    /**
     * AJAX handler for CSV export
     */
    public function ajax_export_cases() {
        if (!wp_verify_nonce($_POST['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Generate download URL
        $export_url = wp_nonce_url(
            admin_url('admin.php?action=lai_export_cases&' . http_build_query($_POST)),
            'lai_ajax_nonce',
            'nonce'
        );
        
        wp_send_json_success(array(
            'download_url' => $export_url,
            'message' => 'Export URL generated successfully'
        ));
    }
    
    /**
     * Download CSV templates
     */
    public function ajax_download_template() {
        if (!wp_verify_nonce($_GET['nonce'], 'lai_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $template = sanitize_text_field($_GET['template']);
        $this->generate_template($template);
        exit;
    }
    
    /**
     * Generate CSV template
     */
    private function generate_template($template_type) {
        $templates = array(
            'cases' => array(
                'headers' => array(
                    'case_id', 'case_status', 'case_type', 'claim_amount', 
                    'case_notes', 'case_creation_date'
                ),
                'sample_data' => array(
                    array('CASE-001', 'new', 'claim', '1000.00', 'Sample case notes', '2024-01-01')
                )
            ),
            'contacts' => array(
                'headers' => array(
                    'email', 'first_name', 'last_name', 'company_name', 
                    'phone', 'address', 'city', 'postal_code'
                ),
                'sample_data' => array(
                    array('john@example.com', 'John', 'Doe', 'Example Corp', 
                          '+49123456789', 'Main Street 123', 'Berlin', '10115')
                )
            ),
            'forderungen' => array(
                'headers' => array(
                    'ID', 'Lawyer Case ID', 'User_First_Name', 'User_Last_Name',
                    'User_Email', 'Debtor_Name', 'Debtor_Email', 'Status',
                    'ART15_claim_damages', 'Created Date'
                ),
                'sample_data' => array(
                    array('1', 'LAW-001', 'John', 'Doe', 'john@example.com',
                          'Jane Smith', 'jane@example.com', 'Open', '1500.00', '2024-01-01')
                )
            )
        );
        
        if (!isset($templates[$template_type])) {
            wp_die('Invalid template type');
        }
        
        $template_data = $templates[$template_type];
        $filename = $template_type . '_template.csv';
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, $template_data['headers']);
        
        // Write sample data
        foreach ($template_data['sample_data'] as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
    
    /**
     * REST API: Export CSV
     */
    public function rest_export_csv($request) {
        $options = array(
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'include_contacts' => $request->get_param('include_contacts'),
            'include_financials' => $request->get_param('include_financials'),
            'include_notes' => $request->get_param('include_notes'),
            'format' => $request->get_param('format')
        );
        
        $this->export_cases_csv($options);
        exit;
    }
    
    /**
     * REST API: Download template
     */
    public function rest_download_template($request) {
        $template = $request->get_param('template');
        $this->generate_template($template);
        exit;
    }
    
    /**
     * Get export statistics
     */
    public function get_export_stats() {
        $stats = array();
        
        // Total cases
        $stats['total_cases'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}klage_cases"
        );
        
        // Total contacts
        $stats['total_contacts'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}klage_contacts"
        );
        
        // Cases by status
        $status_counts = $this->wpdb->get_results(
            "SELECT case_status, COUNT(*) as count FROM {$this->wpdb->prefix}klage_cases GROUP BY case_status",
            ARRAY_A
        );
        
        $stats['status_breakdown'] = array();
        foreach ($status_counts as $status) {
            $stats['status_breakdown'][$status['case_status']] = $status['count'];
        }
        
        // Recent activity
        $stats['recent_cases'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}klage_cases WHERE case_creation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Export to Excel format (future implementation)
     */
    public function export_to_excel($data, $filename) {
        // Placeholder for Excel export functionality
        // Would require PHPSpreadsheet library
        throw new Exception('Excel export not yet implemented');
    }
    
    /**
     * Get available export formats
     */
    public function get_export_formats() {
        return array(
            'csv' => array(
                'name' => 'CSV (Comma Separated)',
                'extension' => 'csv',
                'mime_type' => 'text/csv'
            ),
            'csv_semicolon' => array(
                'name' => 'CSV (Semicolon Separated)',
                'extension' => 'csv',
                'mime_type' => 'text/csv'
            ),
            'excel' => array(
                'name' => 'Excel (XLSX)',
                'extension' => 'xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'available' => false // Not yet implemented
            )
        );
    }
}