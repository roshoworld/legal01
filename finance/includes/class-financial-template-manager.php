<?php
/**
 * Financial Template Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Financial_Template_Manager {
    
    private $db_manager;
    private $calculator;
    
    public function __construct() {
        $this->db_manager = new CAH_Financial_DB_Manager();
        $this->calculator = new CAH_Financial_Calculator_Engine();
    }
    
    /**
     * Create default templates on plugin activation
     */
    public function create_default_templates() {
        // Check if default templates already exist
        $wpdb = $this->db_manager->get_wpdb();
        $existing_defaults = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cah_financial_templates WHERE is_default = 1"
        );
        
        if ($existing_defaults > 0) {
            return; // Default templates already exist
        }
        
        // Create GDPR Default Template
        $template_id = $this->db_manager->create_template(
            'DSGVO Standard Template',
            'Standard-Template für DSGVO Spam-Fälle mit typischen Kostenstrukturen',
            true
        );
        
        if ($template_id) {
            $default_costs = $this->calculator->get_default_gdpr_costs();
            
            foreach ($default_costs as $cost_item) {
                $this->db_manager->create_cost_item(
                    $template_id,
                    null, // Template item, not case-specific
                    $cost_item['name'],
                    $cost_item['category'],
                    $cost_item['amount'],
                    $cost_item['description'],
                    $cost_item['is_percentage'],
                    $cost_item['sort_order']
                );
            }
        }
        
        // Create additional default templates
        $this->create_business_template();
        $this->create_minimal_template();
    }
    
    /**
     * Create business template
     */
    private function create_business_template() {
        $template_id = $this->db_manager->create_template(
            'Business DSGVO Template',
            'Template für Business-Fälle mit höheren Schadensersatzforderungen',
            false
        );
        
        if ($template_id) {
            $business_costs = array(
                array(
                    'name' => 'DSGVO Grundschaden (Business)',
                    'category' => 'grundkosten',
                    'amount' => 500.00,
                    'description' => 'Erhöhter Grundschaden für Business-Spam',
                    'is_percentage' => false,
                    'sort_order' => 1
                ),
                array(
                    'name' => 'Anwaltskosten (umfassend)',
                    'category' => 'anwaltskosten',
                    'amount' => 196.90,
                    'description' => 'Umfassende anwaltliche Betreuung',
                    'is_percentage' => false,
                    'sort_order' => 2
                ),
                array(
                    'name' => 'Dokumentationsaufwand',
                    'category' => 'sonstige',
                    'amount' => 75.00,
                    'description' => 'Aufwendige Dokumentation für Business-Fall',
                    'is_percentage' => false,
                    'sort_order' => 3
                )
            );
            
            foreach ($business_costs as $cost_item) {
                $this->db_manager->create_cost_item(
                    $template_id,
                    null,
                    $cost_item['name'],
                    $cost_item['category'],
                    $cost_item['amount'],
                    $cost_item['description'],
                    $cost_item['is_percentage'],
                    $cost_item['sort_order']
                );
            }
        }
    }
    
    /**
     * Create minimal template
     */
    private function create_minimal_template() {
        $template_id = $this->db_manager->create_template(
            'Minimal DSGVO Template',
            'Minimale Kostenstruktur für einfache Fälle',
            false
        );
        
        if ($template_id) {
            $minimal_costs = array(
                array(
                    'name' => 'DSGVO Mindestschaden',
                    'category' => 'grundkosten',
                    'amount' => 250.00,
                    'description' => 'Minimaler Schadensersatz nach DSGVO',
                    'is_percentage' => false,
                    'sort_order' => 1
                ),
                array(
                    'name' => 'Anwaltskosten (Basis)',
                    'category' => 'anwaltskosten',
                    'amount' => 76.90,
                    'description' => 'Basis-Anwaltskosten',
                    'is_percentage' => false,
                    'sort_order' => 2
                )
            );
            
            foreach ($minimal_costs as $cost_item) {
                $this->db_manager->create_cost_item(
                    $template_id,
                    null,
                    $cost_item['name'],
                    $cost_item['category'],
                    $cost_item['amount'],
                    $cost_item['description'],
                    $cost_item['is_percentage'],
                    $cost_item['sort_order']
                );
            }
        }
    }
    
    /**
     * Get template with calculated totals
     */
    public function get_template_with_totals($template_id) {
        $template = $this->db_manager->get_template($template_id);
        if (!$template) {
            return null;
        }
        
        $cost_items = $this->db_manager->get_cost_items_by_template($template_id);
        $totals = $this->calculator->calculate_totals($cost_items);
        
        $template->cost_items = $cost_items;
        $template->totals = $totals;
        
        return $template;
    }
    
    /**
     * Duplicate template with new name
     */
    public function duplicate_template($source_template_id, $new_name, $new_description = '') {
        $source_template = $this->db_manager->get_template($source_template_id);
        if (!$source_template) {
            return false;
        }
        
        // Create new template
        $new_template_id = $this->db_manager->create_template(
            $new_name,
            $new_description ?: $source_template->description . ' (Kopie)',
            false // Never create default templates through duplication
        );
        
        if (!$new_template_id) {
            return false;
        }
        
        // Copy cost items
        $source_items = $this->db_manager->get_cost_items_by_template($source_template_id);
        foreach ($source_items as $item) {
            $this->db_manager->create_cost_item(
                $new_template_id,
                null,
                $item->name,
                $item->category,
                $item->amount,
                $item->description,
                $item->is_percentage,
                $item->sort_order
            );
        }
        
        return $new_template_id;
    }
    
    /**
     * Save case configuration as new template
     */
    public function save_case_as_template($case_id, $template_name, $template_description = '') {
        // Get case cost items
        $case_items = $this->db_manager->get_cost_items_by_case($case_id);
        
        if (empty($case_items)) {
            return false;
        }
        
        // Create new template
        $template_id = $this->db_manager->create_template(
            $template_name,
            $template_description,
            false
        );
        
        if (!$template_id) {
            return false;
        }
        
        // Copy case items to template
        foreach ($case_items as $item) {
            $this->db_manager->create_cost_item(
                $template_id,
                null, // Template item, not case-specific
                $item->name,
                $item->category,
                $item->amount,
                $item->description,
                $item->is_percentage,
                $item->sort_order
            );
        }
        
        return $template_id;
    }
    
    /**
     * Get template statistics
     */
    public function get_template_stats($template_id) {
        $template = $this->get_template_with_totals($template_id);
        if (!$template) {
            return null;
        }
        
        return array(
            'total_items' => count($template->cost_items),
            'categories' => array_count_values(array_column($template->cost_items, 'category')),
            'subtotal' => $template->totals['subtotal'],
            'total_amount' => $template->totals['total_amount'],
            'last_updated' => $template->updated_at
        );
    }
    
    /**
     * Check if template name exists
     */
    public function template_name_exists($name) {
        $wpdb = $this->db_manager->get_wpdb();
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cah_financial_templates WHERE name = %s",
            $name
        ));
        return $count > 0;
    }
    
    /**
     * Get template by ID (wrapper for db_manager method)
     */
    public function get_template($id) {
        return $this->db_manager->get_template($id);
    }
    
    /**
     * Get template items
     */
    public function get_template_items($template_id) {
        return $this->db_manager->get_cost_items_by_template($template_id);
    }
    
    /**
     * Create template with array data
     */
    public function create_template($data) {
        return $this->db_manager->create_template(
            $data['name'], 
            $data['description'] ?? '', 
            $data['is_default'] ?? false
        );
    }
    
    /**
     * Add item to template
     */
    public function add_item_to_template($item_data) {
        return $this->db_manager->create_cost_item(
            $item_data['template_id'],
            null, // case_id
            '', // name - will be fetched from cost_item_id
            $item_data['category'] ?? null,
            $item_data['amount'],
            '', // description
            $item_data['is_percentage'] ?? false,
            $item_data['sort_order'] ?? 0,
            $item_data['cost_item_id'] ?? null
        );
    }
}