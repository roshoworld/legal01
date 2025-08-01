<?php
/**
 * RVG Calculator Engine for Legal Automation Finance
 * Implements German RVG fee calculations according to specifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAF_RVG_Calculator {
    
    private $db_manager;
    
    public function __construct() {
        $this->db_manager = new LAF_Database_Manager();
    }
    
    /**
     * Calculate fees based on scenario and input values
     * 
     * @param string $scenario 'scenario_1' or 'scenario_2'  
     * @param array $input_data Input parameters
     * @return array Calculation results
     */
    public function calculate_scenario($scenario, $input_data = array()) {
        $defaults = array(
            'base_damage' => 350.00,
            'dsgvo_damage' => 0.00,
            'interest_start_date' => null,
            'interest_end_date' => null,
            'custom_base_rate' => null
        );
        
        $data = array_merge($defaults, $input_data);
        
        switch ($scenario) {
            case 'scenario_1':
                return $this->calculate_scenario_1($data);
            case 'scenario_2':
                return $this->calculate_scenario_2($data);
            default:
                return $this->calculate_custom($data);
        }
    }
    
    /**
     * Scenario 1: Schadenersatz + Unterlassung
     */
    private function calculate_scenario_1($data) {
        $result = array(
            'scenario' => 'scenario_1',
            'items' => array(),
            'totals' => array()
        );
        
        // 1. Grundschaden (Base Damage)
        $base_damage = (float) $data['base_damage'];
        $result['items']['base_damage'] = array(
            'name' => 'Grundschaden',
            'amount' => $base_damage,
            'type' => 'base_damage',
            'rvg_reference' => 'damages_loc'
        );
        
        // 2. 1,3 Geschäftsgebühr (Partner Fees) - 2300 VV RVG
        $partner_fee_factor = $this->db_manager->get_config('partner_fee_factor', 1.3);
        $partner_fees = $this->calculate_partner_fees($base_damage, $partner_fee_factor);
        $result['items']['partner_fees'] = array(
            'name' => '1,3 Geschäftsgebühr (2300 VV RVG)',
            'amount' => $partner_fees,
            'type' => 'partner_fees',
            'rvg_reference' => '2300 VV RVG',
            'calculation' => "Streitwert: {$base_damage} EUR × {$partner_fee_factor}"
        );
        
        // 3. Post/Telekom (Communication Fees) - 7002 VV RVG  
        $communication_percentage = $this->db_manager->get_config('communication_fee_percentage', 20.0);
        $communication_fees = ($partner_fees * $communication_percentage) / 100;
        $result['items']['communication_fees'] = array(
            'name' => 'Post/Telekom (7002 VV RVG)',
            'amount' => $communication_fees,
            'type' => 'communication_fees',
            'rvg_reference' => '7002 VV RVG',
            'calculation' => "Geschäftsgebühr: {$partner_fees} EUR × {$communication_percentage}%"
        );
        
        // Subtotal before VAT
        $subtotal = $base_damage + $partner_fees + $communication_fees;
        
        // 4. USt 19% (VAT) - 7008 VV RVG
        $vat_rate = $this->db_manager->get_config('vat_rate', 19.0);
        $vat_amount = (($partner_fees + $communication_fees) * $vat_rate) / 100; // VAT only on fees, not damages
        $result['items']['vat'] = array(
            'name' => "USt {$vat_rate}% (7008 VV RVG)",
            'amount' => $vat_amount,
            'type' => 'vat',
            'rvg_reference' => '7008 VV RVG',
            'calculation' => "Gebühren: " . ($partner_fees + $communication_fees) . " EUR × {$vat_rate}%"
        );
        
        // 5. Court Fees (if applicable)
        $court_fees = $this->calculate_court_fees($base_damage);
        if ($court_fees > 0) {
            $result['items']['court_fees'] = array(
                'name' => 'Gerichtsgebühr (GKG)',
                'amount' => $court_fees,
                'type' => 'court_fees',
                'rvg_reference' => 'GKG',
                'calculation' => "Streitwert: {$base_damage} EUR"
            );
        }
        
        // 6. Interest calculation (if dates provided)
        $interest_amount = 0;
        if (!empty($data['interest_start_date']) && !empty($data['interest_end_date'])) {
            $interest_calculation = $this->calculate_interest(
                $base_damage, 
                $data['interest_start_date'], 
                $data['interest_end_date'],
                $data['custom_base_rate']
            );
            $interest_amount = $interest_calculation['amount'];
            $result['items']['interest'] = array(
                'name' => 'Zinsen',
                'amount' => $interest_amount,
                'type' => 'interest',
                'calculation' => $interest_calculation['calculation']
            );
        }
        
        // Calculate totals
        $total_amount = $subtotal + $vat_amount + $court_fees + $interest_amount;
        
        $result['totals'] = array(
            'base_damage' => $base_damage,
            'fees_subtotal' => $partner_fees + $communication_fees,
            'vat_amount' => $vat_amount,
            'court_fees' => $court_fees,
            'interest_amount' => $interest_amount,
            'total_amount' => round($total_amount, 2)
        );
        
        return $result;
    }
    
    /**
     * Scenario 2: + DSGVO Auskunftsverletzung
     */
    private function calculate_scenario_2($data) {
        // Start with Scenario 1 calculation
        $base_calculation = $this->calculate_scenario_1($data);
        
        // Add DSGVO damage
        $dsgvo_damage = (float) $data['dsgvo_damage'];
        if ($dsgvo_damage > 0) {
            $base_calculation['items']['dsgvo_damage'] = array(
                'name' => 'DSGVO Auskunftsverletzung',
                'amount' => $dsgvo_damage,
                'type' => 'base_damage',
                'rvg_reference' => 'GDPR Art. 15'
            );
            
            // Recalculate with higher dispute value
            $total_damage = $data['base_damage'] + $dsgvo_damage;
            $data['base_damage'] = $total_damage;
            
            // Recalculate everything with new base
            $base_calculation = $this->calculate_scenario_1($data);
            $base_calculation['scenario'] = 'scenario_2';
            
            // Add DSGVO item back to items list
            $base_calculation['items']['dsgvo_damage'] = array(
                'name' => 'DSGVO Auskunftsverletzung',
                'amount' => $dsgvo_damage,
                'type' => 'base_damage',
                'rvg_reference' => 'GDPR Art. 15'
            );
            
            $base_calculation['totals']['dsgvo_damage'] = $dsgvo_damage;
            $base_calculation['totals']['total_damage'] = $total_damage;
        }
        
        return $base_calculation;
    }
    
    /**
     * Calculate partner fees based on dispute value
     * Uses RVG fee table - simplified calculation for MVP
     */
    private function calculate_partner_fees($dispute_value, $factor = 1.3) {
        // Simplified RVG calculation for MVP
        // In production, this would use the full RVG table
        
        if ($dispute_value <= 500) {
            $base_fee = 38.50;
        } elseif ($dispute_value <= 1000) {
            $base_fee = 58.50;
        } elseif ($dispute_value <= 1500) {
            $base_fee = 78.50;
        } elseif ($dispute_value <= 2000) {
            $base_fee = 98.50;
        } else {
            // For values above 2000, use percentage calculation
            $base_fee = $dispute_value * 0.051; // ~5.1% as approximate
        }
        
        return round($base_fee * $factor, 2);
    }
    
    /**
     * Calculate court fees based on dispute value (GKG)
     */
    private function calculate_court_fees($dispute_value) {
        $court_ranges = $this->db_manager->get_config('court_fee_ranges', array(
            array('min' => 0, 'max' => 500, 'fee' => 32.00),
            array('min' => 500, 'max' => 1000, 'fee' => 44.00), 
            array('min' => 1000, 'max' => 1500, 'fee' => 66.00)
        ));
        
        foreach ($court_ranges as $range) {
            if ($dispute_value >= $range['min'] && $dispute_value < $range['max']) {
                return (float) $range['fee'];
            }
        }
        
        // Default for higher values
        return 66.00;
    }
    
    /**
     * Calculate interest amount
     * Zinsbetrag = (Grundforderung × (Basiszinssatz + 5%) × Tage) / 365
     */
    private function calculate_interest($principal, $start_date, $end_date, $custom_rate = null) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $days = $start->diff($end)->days;
        
        // Use custom rate or get from config
        $annual_rate = $custom_rate ?? $this->db_manager->get_config('base_interest_rate', 5.12);
        $daily_rate = $annual_rate / 100 / 365;
        
        $interest_amount = $principal * $daily_rate * $days;
        
        return array(
            'amount' => round($interest_amount, 2),
            'days' => $days,
            'annual_rate' => $annual_rate,
            'calculation' => "({$principal} EUR × {$annual_rate}% × {$days} Tage) / 365"
        );
    }
    
    /**
     * Save calculation to database
     */
    public function save_calculation($case_id, $calculation_data, $template_id = null) {
        global $wpdb;
        
        $calculation_insert = array(
            'case_id' => $case_id,
            'template_id' => $template_id,
            'calculation_status' => 'calculated',
            'base_damage' => $calculation_data['totals']['base_damage'] ?? 0,
            'dsgvo_damage' => $calculation_data['totals']['dsgvo_damage'] ?? 0,
            'total_damage' => $calculation_data['totals']['total_damage'] ?? $calculation_data['totals']['base_damage'],
            'partner_fees' => $calculation_data['items']['partner_fees']['amount'] ?? 0,
            'communication_fees' => $calculation_data['items']['communication_fees']['amount'] ?? 0,
            'court_fees' => $calculation_data['totals']['court_fees'] ?? 0,
            'vat_amount' => $calculation_data['totals']['vat_amount'] ?? 0,
            'interest_amount' => $calculation_data['totals']['interest_amount'] ?? 0,
            'total_amount' => $calculation_data['totals']['total_amount'],
            'calculated_at' => current_time('mysql')
        );
        
        // Insert main calculation record
        $result = $wpdb->insert(
            $wpdb->prefix . 'laf_case_calculations',
            $calculation_insert
        );
        
        if ($result === false) {
            return false;
        }
        
        $calculation_id = $wpdb->insert_id;
        
        // Insert detailed items
        $sort_order = 0;
        foreach ($calculation_data['items'] as $item_key => $item_data) {
            $wpdb->insert(
                $wpdb->prefix . 'laf_case_items',
                array(
                    'calculation_id' => $calculation_id,
                    'item_type' => $item_data['type'],
                    'item_name' => $item_data['name'],
                    'amount' => $item_data['amount'],
                    'rvg_reference' => $item_data['rvg_reference'] ?? null,
                    'description' => $item_data['calculation'] ?? null,
                    'sort_order' => $sort_order++
                )
            );
        }
        
        // Update case total in core table
        $this->db_manager->update_case_total($case_id, $calculation_data['totals']['total_amount']);
        
        return $calculation_id;
    }
    
    /**
     * Get saved calculation for a case
     */
    public function get_case_calculation($case_id) {
        global $wpdb;
        
        $calculation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}laf_case_calculations WHERE case_id = %d ORDER BY created_at DESC LIMIT 1",
            $case_id
        ));
        
        if (!$calculation) {
            return false;
        }
        
        // Get calculation items
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}laf_case_items WHERE calculation_id = %d ORDER BY sort_order",
            $calculation->id
        ));
        
        return array(
            'calculation' => $calculation,
            'items' => $items
        );
    }
}