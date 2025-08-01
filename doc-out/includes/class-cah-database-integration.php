<?php
/**
 * Core Plugin Database Integration Class
 * 
 * Integrates with Court Automation Hub centralized data model
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CAH_DocOut_Database_Integration {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get all debtors/contacts from core plugin
     */
    public function get_all_debtors() {
        $table_name = $this->wpdb->prefix . 'klage_debtors';
        
        $results = $this->wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY debtors_name ASC"
        );
        
        return $results;
    }
    
    /**
     * Get debtor by ID
     */
    public function get_debtor_by_id($debtor_id) {
        $table_name = $this->wpdb->prefix . 'klage_debtors';
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $debtor_id
            )
        );
        
        return $result;
    }
    
    /**
     * Get cases for a debtor
     */
    public function get_cases_by_debtor($debtor_id) {
        $table_name = $this->wpdb->prefix . 'klage_cases';
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE debtor_id = %d ORDER BY case_creation_date DESC",
                $debtor_id
            )
        );
        
        return $results;
    }
    
    /**
     * Get case details with debtor information
     */
    public function get_case_with_debtor($case_id) {
        $cases_table = $this->wpdb->prefix . 'klage_cases';
        $debtors_table = $this->wpdb->prefix . 'klage_debtors';
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT c.*, d.debtors_name, d.debtors_company, d.debtors_email, 
                        d.debtors_address, d.debtors_postal_code, d.debtors_city,
                        d.debtors_street, d.debtors_house_number, d.debtors_country
                 FROM $cases_table c
                 LEFT JOIN $debtors_table d ON c.debtor_id = d.id
                 WHERE c.id = %d",
                $case_id
            )
        );
        
        return $result;
    }
    
    /**
     * Search debtors by name or company
     */
    public function search_debtors($search_term) {
        $table_name = $this->wpdb->prefix . 'klage_debtors';
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE debtors_name LIKE %s 
                    OR debtors_company LIKE %s 
                    OR debtors_email LIKE %s
                 ORDER BY debtors_name ASC",
                '%' . $search_term . '%',
                '%' . $search_term . '%',
                '%' . $search_term . '%'
            )
        );
        
        return $results;
    }
    
    /**
     * Check if core plugin tables exist
     */
    public function check_core_tables() {
        $tables = array(
            'klage_debtors',
            'klage_cases',
            'klage_communications'
        );
        
        $existing_tables = array();
        
        foreach ($tables as $table) {
            $full_table_name = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            $existing_tables[$table] = !empty($exists);
        }
        
        return $existing_tables;
    }
    
    /**
     * Get formatted debtor address
     */
    public function get_formatted_debtor_address($debtor) {
        $address_parts = array();
        
        if (!empty($debtor->debtors_street)) {
            $street = $debtor->debtors_street;
            if (!empty($debtor->debtors_house_number)) {
                $street .= ' ' . $debtor->debtors_house_number;
            }
            $address_parts[] = $street;
        }
        
        if (!empty($debtor->debtors_postal_code) && !empty($debtor->debtors_city)) {
            $address_parts[] = $debtor->debtors_postal_code . ' ' . $debtor->debtors_city;
        }
        
        if (!empty($debtor->debtors_country)) {
            $address_parts[] = $debtor->debtors_country;
        }
        
        return implode("\n", $address_parts);
    }
    
    /**
     * Get template variables for document generation
     */
    public function get_template_variables($case_id) {
        $case_data = $this->get_case_with_debtor($case_id);
        
        if (!$case_data) {
            return array();
        }
        
        return array(
            'debtor_name' => $case_data->debtors_name,
            'debtor_company' => $case_data->debtors_company,
            'debtor_email' => $case_data->debtors_email,
            'debtor_address' => $this->get_formatted_debtor_address($case_data),
            'debtor_postal_code' => $case_data->debtors_postal_code,
            'debtor_city' => $case_data->debtors_city,
            'debtor_street' => $case_data->debtors_street,
            'debtor_house_number' => $case_data->debtors_house_number,
            'debtor_country' => $case_data->debtors_country,
            'case_id' => $case_data->case_id,
            'case_amount' => $case_data->total_amount,
            'case_date' => $case_data->case_creation_date,
            'case_status' => $case_data->case_status
        );
    }
}