<?php
/**
 * Template Manager for Legal Automation Finance
 * Handles CRUD operations for calculation templates
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAF_Template_Manager {
    
    private $wpdb;
    private $db_manager;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = new LAF_Database_Manager();
    }
    
    /**
     * Create default templates (Scenario 1 & 2)
     */
    public function create_default_templates() {
        $this->create_scenario_1_template();
        $this->create_scenario_2_template();
    }
    
    /**
     * Create Scenario 1 template
     */
    private function create_scenario_1_template() {
        // Check if template already exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}laf_templates WHERE template_type = %s",
            'scenario_1'
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Create template
        $template_data = array(
            'template_name' => 'Szenario 1 (Schadenersatz + Unterlassung)',
            'template_type' => 'scenario_1',
            'description' => 'Standard RVG-Berechnung für Schadenersatz und Unterlassung mit Grundschaden 350 EUR',
            'is_default' => 1,
            'is_active' => 1
        );
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'laf_templates',
            $template_data
        );
        
        if ($result === false) {
            return false;
        }
        
        $template_id = $this->wpdb->insert_id;
        
        // Create template items
        $items = array(
            array(
                'item_type' => 'base_damage',
                'item_name' => 'Grundschaden',
                'base_amount' => 350.00,
                'calculation_method' => 'fixed',
                'rvg_reference' => 'damages_loc',
                'description' => 'Grundforderung für Schadenersatz',
                'sort_order' => 1
            ),
            array(
                'item_type' => 'partner_fees',
                'item_name' => '1,3 Geschäftsgebühr (2300 VV RVG)', 
                'base_amount' => 0.00,
                'percentage' => 0.00, // Will be calculated based on dispute value
                'calculation_method' => 'formula',
                'rvg_reference' => '2300 VV RVG',
                'description' => 'Berechnung nach RVG-Tabelle × 1,3 Faktor',
                'sort_order' => 2
            ),
            array(
                'item_type' => 'communication_fees',
                'item_name' => 'Post/Telekom (7002 VV RVG)',
                'base_amount' => 0.00,
                'percentage' => 20.00,
                'calculation_method' => 'percentage',
                'rvg_reference' => '7002 VV RVG', 
                'description' => '20% der Geschäftsgebühr',
                'sort_order' => 3
            ),
            array(
                'item_type' => 'vat',
                'item_name' => 'USt 19% (7008 VV RVG)',
                'base_amount' => 0.00,
                'percentage' => 19.00,
                'calculation_method' => 'percentage',
                'rvg_reference' => '7008 VV RVG',
                'description' => '19% Umsatzsteuer auf Gebühren',
                'sort_order' => 4
            ),
            array(
                'item_type' => 'court_fees',
                'item_name' => 'Gerichtsgebühr (GKG)',
                'base_amount' => 0.00,
                'calculation_method' => 'formula',
                'rvg_reference' => 'GKG',
                'description' => 'Gerichtsgebühr nach Streitwert (GKG)',
                'sort_order' => 5,
                'is_configurable' => 0 // Optional item
            )
        );
        
        foreach ($items as $item) {
            $item['template_id'] = $template_id;
            $this->wpdb->insert(
                $this->wpdb->prefix . 'laf_template_items',
                $item
            );
        }
        
        return $template_id;
    }
    
    /**
     * Create Scenario 2 template
     */
    private function create_scenario_2_template() {
        // Check if template already exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}laf_templates WHERE template_type = %s",
            'scenario_2'
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Create template
        $template_data = array(
            'template_name' => 'Szenario 2 (+ DSGVO Auskunftsverletzung)',
            'template_type' => 'scenario_2',
            'description' => 'Erweiterte RVG-Berechnung mit zusätzlichem DSGVO-Schaden (200 EUR)',
            'is_default' => 1,
            'is_active' => 1
        );
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'laf_templates',
            $template_data
        );
        
        if ($result === false) {
            return false;
        }
        
        $template_id = $this->wpdb->insert_id;
        
        // Create template items (same as Scenario 1 + DSGVO)
        $items = array(
            array(
                'item_type' => 'base_damage',
                'item_name' => 'Grundschaden',
                'base_amount' => 350.00,
                'calculation_method' => 'fixed',
                'rvg_reference' => 'damages_loc',
                'description' => 'Grundforderung für Schadenersatz',
                'sort_order' => 1
            ),
            array(
                'item_type' => 'base_damage',
                'item_name' => 'DSGVO Auskunftsverletzung',
                'base_amount' => 200.00,
                'calculation_method' => 'fixed',
                'rvg_reference' => 'GDPR Art. 15',
                'description' => 'Zusätzlicher Schaden wegen DSGVO-Verletzung',
                'sort_order' => 2
            ),
            array(
                'item_type' => 'partner_fees',
                'item_name' => '1,3 Geschäftsgebühr (2300 VV RVG)',
                'base_amount' => 0.00,
                'calculation_method' => 'formula',
                'rvg_reference' => '2300 VV RVG',
                'description' => 'Berechnung nach RVG-Tabelle basierend auf erhöhtem Streitwert',
                'sort_order' => 3
            ),
            array(
                'item_type' => 'communication_fees',
                'item_name' => 'Post/Telekom (7002 VV RVG)',
                'base_amount' => 0.00,
                'percentage' => 20.00,
                'calculation_method' => 'percentage',
                'rvg_reference' => '7002 VV RVG',
                'description' => '20% der Geschäftsgebühr',
                'sort_order' => 4
            ),
            array(
                'item_type' => 'vat',
                'item_name' => 'USt 19% (7008 VV RVG)',
                'base_amount' => 0.00,
                'percentage' => 19.00,
                'calculation_method' => 'percentage',
                'rvg_reference' => '7008 VV RVG',
                'description' => '19% Umsatzsteuer auf Gebühren',
                'sort_order' => 5
            ),
            array(
                'item_type' => 'court_fees',
                'item_name' => 'Gerichtsgebühr (GKG)',
                'base_amount' => 0.00,
                'calculation_method' => 'formula',
                'rvg_reference' => 'GKG',
                'description' => 'Gerichtsgebühr nach Streitwert (GKG)',
                'sort_order' => 6,
                'is_configurable' => 0
            )
        );
        
        foreach ($items as $item) {
            $item['template_id'] = $template_id;
            $this->wpdb->insert(
                $this->wpdb->prefix . 'laf_template_items',
                $item
            );
        }
        
        return $template_id;
    }
    
    /**
     * Get all templates
     */
    public function get_all_templates($active_only = true) {
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}laf_templates {$where} ORDER BY is_default DESC, template_name ASC"
        );
    }
    
    /**
     * Get template by ID
     */
    public function get_template($template_id) {
        $template = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}laf_templates WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            return false;
        }
        
        // Get template items
        $items = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}laf_template_items WHERE template_id = %d ORDER BY sort_order",
            $template_id
        ));
        
        return array(
            'template' => $template,
            'items' => $items
        );
    }
    
    /**
     * Create custom template
     */
    public function create_template($template_data, $items_data = array()) {
        // Insert template
        $template_insert = array(
            'template_name' => sanitize_text_field($template_data['name']),
            'template_type' => 'custom',
            'description' => sanitize_textarea_field($template_data['description'] ?? ''),
            'is_default' => 0,
            'is_active' => 1,
            'created_by' => get_current_user_id()
        );
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'laf_templates',
            $template_insert
        );
        
        if ($result === false) {
            return false;
        }
        
        $template_id = $this->wpdb->insert_id;
        
        // Insert items
        if (!empty($items_data)) {
            foreach ($items_data as $index => $item) {
                $item_insert = array(
                    'template_id' => $template_id,
                    'item_type' => sanitize_text_field($item['type']),
                    'item_name' => sanitize_text_field($item['name']),
                    'base_amount' => (float) ($item['amount'] ?? 0),
                    'percentage' => (float) ($item['percentage'] ?? 0),
                    'calculation_method' => sanitize_text_field($item['method'] ?? 'fixed'),
                    'rvg_reference' => sanitize_text_field($item['reference'] ?? ''),
                    'description' => sanitize_textarea_field($item['description'] ?? ''),
                    'sort_order' => $index + 1,
                    'is_configurable' => 1
                );
                
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'laf_template_items',
                    $item_insert
                );
            }
        }
        
        return $template_id;
    }
    
    /**
     * Update template
     */
    public function update_template($template_id, $template_data, $items_data = array()) {
        // Update template
        $template_update = array(
            'template_name' => sanitize_text_field($template_data['name']),
            'description' => sanitize_textarea_field($template_data['description'] ?? ''),
            'is_active' => (int) ($template_data['active'] ?? 1)
        );
        
        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'laf_templates',
            $template_update,
            array('id' => $template_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            return false;
        }
        
        // Delete existing items
        $this->wpdb->delete(
            $this->wpdb->prefix . 'laf_template_items',
            array('template_id' => $template_id),
            array('%d')
        );
        
        // Insert updated items
        if (!empty($items_data)) {
            foreach ($items_data as $index => $item) {
                $item_insert = array(
                    'template_id' => $template_id,
                    'item_type' => sanitize_text_field($item['type']),
                    'item_name' => sanitize_text_field($item['name']),
                    'base_amount' => (float) ($item['amount'] ?? 0),
                    'percentage' => (float) ($item['percentage'] ?? 0),
                    'calculation_method' => sanitize_text_field($item['method'] ?? 'fixed'),
                    'rvg_reference' => sanitize_text_field($item['reference'] ?? ''),
                    'description' => sanitize_textarea_field($item['description'] ?? ''),
                    'sort_order' => $index + 1,
                    'is_configurable' => 1
                );
                
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'laf_template_items',
                    $item_insert
                );
            }
        }
        
        return true;
    }
    
    /**
     * Delete template
     */
    public function delete_template($template_id) {
        // Don't delete default templates
        $template = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT is_default FROM {$this->wpdb->prefix}laf_templates WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            return false;
        }
        
        if ($template->is_default == 1) {
            return new WP_Error('cannot_delete_default', 'Default templates cannot be deleted');
        }
        
        // Delete template items first (handled by foreign key constraint)
        // Delete template
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'laf_templates',
            array('id' => $template_id),
            array('%d')
        );
    }
    
    /**
     * Duplicate template
     */
    public function duplicate_template($template_id, $new_name) {
        $template_data = $this->get_template($template_id);
        
        if (!$template_data) {
            return false;
        }
        
        // Create new template
        $new_template_data = array(
            'name' => $new_name,
            'description' => $template_data['template']->description . ' (Kopie)'
        );
        
        // Convert items for create function
        $items_data = array();
        foreach ($template_data['items'] as $item) {
            $items_data[] = array(
                'type' => $item->item_type,
                'name' => $item->item_name,
                'amount' => $item->base_amount,
                'percentage' => $item->percentage,
                'method' => $item->calculation_method,
                'reference' => $item->rvg_reference,
                'description' => $item->description
            );
        }
        
        return $this->create_template($new_template_data, $items_data);
    }
}