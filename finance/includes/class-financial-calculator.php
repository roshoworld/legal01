<?php
/**
 * Financial Calculator Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Financial_Calculator_Engine {
    
    private $vat_rate = 19.00; // Default German VAT rate
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Calculate totals for a list of cost items
     */
    public function calculate_totals($cost_items, $vat_rate = null) {
        if ($vat_rate === null) {
            $vat_rate = $this->vat_rate;
        }
        
        $subtotal = 0;
        $grouped_items = array(
            'basic_costs' => array(),
            'court_costs' => array(),
            'legal_costs' => array(),
            'other_costs' => array()
        );
        
        // First pass: Calculate base amount for non-percentage items
        $base_subtotal = 0;
        foreach ($cost_items as $item) {
            if (!$item->is_percentage) {
                $base_subtotal += floatval($item->amount);
            }
        }
        
        // Second pass: Calculate all items including percentage-based ones
        foreach ($cost_items as $item) {
            $amount = floatval($item->amount);
            
            // Handle percentage-based items using base subtotal
            if ($item->is_percentage) {
                $amount = $this->calculate_percentage_amount($amount, $base_subtotal);
            }
            
            $subtotal += $amount;
            $grouped_items[$item->category][] = array(
                'id' => $item->id,
                'name' => $item->name,
                'amount' => $amount,
                'is_percentage' => $item->is_percentage,
                'description' => $item->description
            );
        }
        
        $vat_amount = $subtotal * ($vat_rate / 100);
        $total_amount = $subtotal + $vat_amount;
        
        return array(
            'subtotal' => round($subtotal, 2),
            'vat_rate' => $vat_rate,
            'vat_amount' => round($vat_amount, 2),
            'total_amount' => round($total_amount, 2),
            'base_subtotal' => round($base_subtotal, 2),
            'grouped_items' => $grouped_items,
            'item_count' => count($cost_items)
        );
    }
    
    /**
     * Calculate percentage-based amount
     */
    private function calculate_percentage_amount($percentage, $base_amount) {
        return $base_amount * ($percentage / 100);
    }
    
    /**
     * Create cost items from template
     */
    public function copy_template_items_to_case($template_id, $case_id) {
        $db_manager = new CAH_Financial_DB_Manager();
        $template_items = $db_manager->get_cost_items_by_template($template_id);
        
        $copied_items = array();
        
        foreach ($template_items as $item) {
            $new_item_id = $db_manager->create_cost_item(
                null, // No template_id for case-specific items
                $case_id,
                $item->name,
                $item->category,
                $item->amount,
                $item->description,
                $item->is_percentage,
                $item->sort_order
            );
            
            if ($new_item_id) {
                $copied_items[] = $new_item_id;
            }
        }
        
        return $copied_items;
    }
    
    /**
     * Get default GDPR cost structure
     */
    public function get_default_gdpr_costs() {
        return array(
            array(
                'name' => 'DSGVO Grundschaden',
                'category' => 'basic_costs',
                'amount' => 350.00,
                'description' => 'Grundschaden nach DSGVO für Spam-E-Mail',
                'is_percentage' => false,
                'sort_order' => 1
            ),
            array(
                'name' => 'Anwaltskosten (Erstberatung)',
                'category' => 'legal_costs',
                'amount' => 96.90,
                'description' => 'Anwaltskosten für Erstberatung und Mahnung',
                'is_percentage' => false,
                'sort_order' => 2
            ),
            array(
                'name' => 'Kommunikationsaufwand',
                'category' => 'other_costs',
                'amount' => 25.00,
                'description' => 'Aufwand für Korrespondenz und Dokumentation',
                'is_percentage' => false,
                'sort_order' => 3
            ),
            array(
                'name' => 'Gerichtskosten (bei Klage)',
                'category' => 'court_costs',
                'amount' => 43.00,
                'description' => 'Gerichtskosten bei Einreichung der Klage',
                'is_percentage' => false,
                'sort_order' => 4
            )
        );
    }
    
    /**
     * Validate cost item data
     */
    public function validate_cost_item($data) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors[] = 'Name ist erforderlich';
        }
        
        if (!in_array($data['category'], array('basic_costs', 'court_costs', 'legal_costs', 'other_costs'))) {
            $errors[] = 'Ungültige Kategorie';
        }
        
        if (!is_numeric($data['amount']) || $data['amount'] < 0) {
            $errors[] = 'Betrag muss eine positive Zahl sein';
        }
        
        return $errors;
    }
    
    /**
     * Format currency for display
     */
    public function format_currency($amount, $include_symbol = true) {
        $formatted = number_format($amount, 2, ',', '.');
        return $include_symbol ? '€ ' . $formatted : $formatted;
    }
    
    /**
     * Get category display names
     */
    public function get_category_names() {
        return array(
            'basic_costs' => 'Grundkosten',
            'court_costs' => 'Gerichtskosten',
            'legal_costs' => 'Anwaltskosten',
            'other_costs' => 'Sonstige Kosten'
        );
    }
}