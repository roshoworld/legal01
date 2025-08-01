<?php
/**
 * Case Matcher Class
 * Handles matching communications to existing cases
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Document_in_Case_Matcher {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Find cases by case number (exact match)
     * Case number format: CY-29252-MM
     */
    public function find_by_case_number($case_number) {
        if (empty($case_number)) {
            return array();
        }
        
        $cases_table = $this->wpdb->prefix . 'klage_cases';
        
        // Try exact match first
        $exact_match = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT case_id, case_number, debtor_id FROM $cases_table 
             WHERE case_number = %s 
             ORDER BY case_creation_date DESC 
             LIMIT 1",
            $case_number
        ));
        
        if ($exact_match) {
            // Get debtor name
            $debtor_name = $this->get_debtor_name($exact_match->debtor_id);
            
            return array(array(
                'case_id' => $exact_match->case_id,
                'case_number' => $exact_match->case_number,
                'debtor_name' => $debtor_name,
                'confidence' => 100,
                'match_type' => 'exact_case_number'
            ));
        }
        
        // Try pattern matching for case number format CY-XXXXX-MM
        if (preg_match('/^CY-\d{5}-[A-Z]{2}$/i', $case_number)) {
            $pattern_matches = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT case_id, case_number, debtor_id FROM $cases_table 
                 WHERE case_number LIKE %s 
                 ORDER BY case_creation_date DESC 
                 LIMIT 5",
                'CY-%'
            ));
            
            $matches = array();
            foreach ($pattern_matches as $match) {
                if (stripos($match->case_number, substr($case_number, 0, 8)) !== false) {
                    $debtor_name = $this->get_debtor_name($match->debtor_id);
                    $matches[] = array(
                        'case_id' => $match->case_id,
                        'case_number' => $match->case_number,
                        'debtor_name' => $debtor_name,
                        'confidence' => 85,
                        'match_type' => 'partial_case_number'
                    );
                }
            }
            
            return $matches;
        }
        
        return array();
    }
    
    /**
     * Find cases by debtor name (fuzzy matching)
     */
    public function find_by_debtor_name($debtor_name) {
        if (empty($debtor_name)) {
            return array();
        }
        
        $matches = array();
        $debtor_name = trim($debtor_name);
        
        // Try different matching strategies
        $strategies = array(
            'exact' => $debtor_name,
            'first_last' => $this->extract_first_last_name($debtor_name),
            'last_first' => $this->extract_last_first_name($debtor_name),
            'company' => $this->extract_company_name($debtor_name)
        );
        
        foreach ($strategies as $strategy => $search_term) {
            if (empty($search_term)) {
                continue;
            }
            
            $strategy_matches = $this->search_debtors($search_term, $strategy);
            $matches = array_merge($matches, $strategy_matches);
        }
        
        // Remove duplicates and sort by confidence
        $matches = $this->deduplicate_matches($matches);
        usort($matches, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        return array_slice($matches, 0, 5); // Return top 5 matches
    }
    
    /**
     * Search debtors table with different strategies
     */
    private function search_debtors($search_term, $strategy) {
        $debtors_table = $this->wpdb->prefix . 'klage_debtors';
        $cases_table = $this->wpdb->prefix . 'klage_cases';
        
        $matches = array();
        
        switch ($strategy) {
            case 'exact':
                $results = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT d.id as debtor_id, d.debtors_name, d.debtors_company, 
                            c.case_id, c.case_number
                     FROM $debtors_table d
                     LEFT JOIN $cases_table c ON d.id = c.debtor_id
                     WHERE d.debtors_name = %s OR d.debtors_company = %s
                     ORDER BY c.case_creation_date DESC",
                    $search_term, $search_term
                ));
                $confidence = 95;
                break;
                
            case 'first_last':
            case 'last_first':
                $results = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT d.id as debtor_id, d.debtors_name, d.debtors_company,
                            c.case_id, c.case_number
                     FROM $debtors_table d
                     LEFT JOIN $cases_table c ON d.id = c.debtor_id
                     WHERE d.debtors_name LIKE %s
                     ORDER BY c.case_creation_date DESC",
                    '%' . $search_term . '%'
                ));
                $confidence = 80;
                break;
                
            case 'company':
                $results = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT d.id as debtor_id, d.debtors_name, d.debtors_company,
                            c.case_id, c.case_number
                     FROM $debtors_table d
                     LEFT JOIN $cases_table c ON d.id = c.debtor_id
                     WHERE d.debtors_company LIKE %s
                     ORDER BY c.case_creation_date DESC",
                    '%' . $search_term . '%'
                ));
                $confidence = 70;
                break;
                
            default:
                $results = array();
                $confidence = 50;
        }
        
        foreach ($results as $result) {
            if ($result->case_id) { // Only include debtors with cases
                $similarity_score = $this->calculate_name_similarity($search_term, $result->debtors_name);
                $final_confidence = min($confidence, $similarity_score);
                
                $matches[] = array(
                    'case_id' => $result->case_id,
                    'case_number' => $result->case_number,
                    'debtor_name' => $result->debtors_name,
                    'debtor_company' => $result->debtors_company,
                    'confidence' => $final_confidence,
                    'match_type' => 'debtor_' . $strategy,
                    'similarity_score' => $similarity_score
                );
            }
        }
        
        return $matches;
    }
    
    /**
     * Extract first and last name from full name
     */
    private function extract_first_last_name($name) {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return $parts[0] . ' ' . end($parts);
        }
        return $name;
    }
    
    /**
     * Extract last name, first name format
     */
    private function extract_last_first_name($name) {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return end($parts) . ', ' . $parts[0];
        }
        return $name;
    }
    
    /**
     * Extract potential company name (look for GmbH, AG, etc.)
     */
    private function extract_company_name($name) {
        $company_indicators = array('GmbH', 'AG', 'KG', 'OHG', 'UG', 'e.V.', 'Ltd', 'Inc', 'Corp');
        
        foreach ($company_indicators as $indicator) {
            if (stripos($name, $indicator) !== false) {
                return $name;
            }
        }
        
        // If name contains multiple words and no personal titles, might be a company
        $parts = explode(' ', $name);
        $personal_titles = array('Herr', 'Frau', 'Dr.', 'Prof.', 'Mr.', 'Mrs.', 'Ms.');
        
        $has_personal_title = false;
        foreach ($personal_titles as $title) {
            if (stripos($name, $title) !== false) {
                $has_personal_title = true;
                break;
            }
        }
        
        if (!$has_personal_title && count($parts) > 2) {
            return $name;
        }
        
        return '';
    }
    
    /**
     * Calculate name similarity using Levenshtein distance
     */
    private function calculate_name_similarity($name1, $name2) {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));
        
        if ($name1 === $name2) {
            return 100;
        }
        
        $max_len = max(strlen($name1), strlen($name2));
        if ($max_len === 0) {
            return 0;
        }
        
        $distance = levenshtein($name1, $name2);
        $similarity = (($max_len - $distance) / $max_len) * 100;
        
        return max(0, round($similarity));
    }
    
    /**
     * Remove duplicate matches
     */
    private function deduplicate_matches($matches) {
        $unique_matches = array();
        $seen_cases = array();
        
        foreach ($matches as $match) {
            $key = $match['case_id'];
            if (!isset($seen_cases[$key]) || $seen_cases[$key]['confidence'] < $match['confidence']) {
                $seen_cases[$key] = $match;
            }
        }
        
        return array_values($seen_cases);
    }
    
    /**
     * Get debtor name by debtor ID
     */
    private function get_debtor_name($debtor_id) {
        if (empty($debtor_id)) {
            return '';
        }
        
        $debtors_table = $this->wpdb->prefix . 'klage_debtors';
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT debtors_name, debtors_company FROM $debtors_table WHERE id = %d",
            $debtor_id
        ));
        
        if ($result) {
            return !empty($result->debtors_company) ? $result->debtors_company : $result->debtors_name;
        }
        
        return '';
    }
    
    /**
     * Get case details by case ID
     */
    public function get_case_details($case_id) {
        $cases_table = $this->wpdb->prefix . 'klage_cases';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $cases_table WHERE case_id = %d",
            $case_id
        ));
    }
    
    /**
     * Validate case number format
     */
    public function is_valid_case_number_format($case_number) {
        return preg_match('/^CY-\d{5}-[A-Z]{2}$/i', $case_number);
    }
    
    /**
     * Get matching statistics for dashboard
     */
    public function get_matching_statistics($days = 30) {
        $communications_table = $this->wpdb->prefix . 'cah_document_in_communications';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_communications,
                SUM(CASE WHEN assignment_status = 'assigned' THEN 1 ELSE 0 END) as auto_assigned,
                SUM(CASE WHEN assignment_status = 'unassigned' THEN 1 ELSE 0 END) as unassigned,
                SUM(CASE WHEN assignment_status = 'new_case_created' THEN 1 ELSE 0 END) as new_cases,
                AVG(match_confidence) as avg_confidence
             FROM $communications_table 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}