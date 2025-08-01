<?php
/**
 * Financial Database Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Financial_DB_Manager {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get WordPress database instance
     */
    public function get_wpdb() {
        return $this->wpdb;
    }
    
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $tables = array(
            'cah_cost_categories' => "CREATE TABLE {$this->wpdb->prefix}cah_cost_categories (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(100) NOT NULL,
                description text,
                color varchar(7) DEFAULT '#2196f3',
                is_default tinyint(1) DEFAULT 0,
                sort_order int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_slug (slug),
                INDEX idx_is_default (is_default),
                INDEX idx_sort_order (sort_order),
                INDEX idx_name (name)
            ) $charset_collate",
            
            'cah_financial_templates' => "CREATE TABLE {$this->wpdb->prefix}cah_financial_templates (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                is_default tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_is_default (is_default),
                INDEX idx_name (name)
            ) $charset_collate",
            
            'cah_cost_items' => "CREATE TABLE {$this->wpdb->prefix}cah_cost_items (
                id int(11) NOT NULL AUTO_INCREMENT,
                template_id int(11) DEFAULT NULL,
                case_id int(11) DEFAULT NULL,
                category_id int(11) DEFAULT NULL,
                name varchar(255) NOT NULL,
                category varchar(50) DEFAULT NULL,
                amount decimal(10,2) NOT NULL DEFAULT 0.00,
                description text,
                is_percentage tinyint(1) DEFAULT 0,
                is_independent tinyint(1) DEFAULT 0,
                sort_order int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_template_id (template_id),
                INDEX idx_case_id (case_id),
                INDEX idx_category_id (category_id),
                INDEX idx_is_independent (is_independent),
                INDEX idx_category (category)
            ) $charset_collate",
            
            'cah_template_cost_items' => "CREATE TABLE {$this->wpdb->prefix}cah_template_cost_items (
                id int(11) NOT NULL AUTO_INCREMENT,
                template_id int(11) NOT NULL,
                cost_item_id int(11) NOT NULL,
                sort_order int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_template_item (template_id, cost_item_id),
                INDEX idx_template_id (template_id),
                INDEX idx_cost_item_id (cost_item_id)
            ) $charset_collate",
            
            'cah_case_financial' => "CREATE TABLE {$this->wpdb->prefix}cah_case_financial (
                id int(11) NOT NULL AUTO_INCREMENT,
                case_id int(11) NOT NULL,
                template_id int(11),
                subtotal decimal(10,2) DEFAULT 0.00,
                vat_rate decimal(5,2) DEFAULT 19.00,
                vat_amount decimal(10,2) DEFAULT 0.00,
                total_amount decimal(10,2) DEFAULT 0.00,
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_case (case_id)
            ) $charset_collate"
        );
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }
        
        // Update existing cost_items table if needed
        $this->update_cost_items_schema();
        
        // Create default cost categories if they don't exist
        $this->create_default_cost_categories();
    }
    
    /**
     * Update existing cost_items table to add new columns
     */
    private function update_cost_items_schema() {
        $table_name = $this->wpdb->prefix . 'cah_cost_items';
        
        // Check and add category_id column
        $column = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'category_id'");
        if (empty($column)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN category_id int(11) DEFAULT NULL AFTER case_id");
        }
        
        // Check and add is_independent column
        $column = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'is_independent'");
        if (empty($column)) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN is_independent tinyint(1) DEFAULT 0 AFTER is_percentage");
        }
        
        // Update template_id to allow NULL for independent items
        $this->wpdb->query("ALTER TABLE {$table_name} MODIFY template_id int(11) DEFAULT NULL");
    }
    
    // Template CRUD operations
    public function create_template($name, $description = '', $is_default = false) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'cah_financial_templates',
            array(
                'name' => $name,
                'description' => $description,
                'is_default' => $is_default ? 1 : 0
            ),
            array('%s', '%s', '%d')
        );
    }
    
    public function get_template($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}cah_financial_templates WHERE id = %d",
                $id
            )
        );
    }
    
    public function get_templates($include_default = true) {
        $where = $include_default ? '' : 'WHERE is_default = 0';
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}cah_financial_templates {$where} ORDER BY is_default DESC, name ASC"
        );
    }
    
    public function update_template($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'cah_financial_templates',
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    public function delete_template($id) {
        // Only delete template-item relationships, NOT the cost items themselves
        $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_template_cost_items',
            array('template_id' => $id),
            array('%d')
        );
        
        // Then delete template (cost items remain independent)
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_financial_templates',
            array('id' => $id),
            array('%d')
        );
    }
    
    // Cost Item CRUD operations
    public function create_cost_item($template_id, $case_id, $name, $category, $amount, $description = '', $is_percentage = false, $sort_order = 0, $category_id = null) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'cah_cost_items',
            array(
                'template_id' => $template_id,
                'case_id' => $case_id,
                'category_id' => $category_id,
                'name' => $name,
                'category' => $category, // Legacy field for backward compatibility
                'amount' => $amount,
                'description' => $description,
                'is_percentage' => $is_percentage ? 1 : 0,
                'is_independent' => 0, // Template/case specific items are not independent
                'sort_order' => $sort_order
            ),
            array('%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%d')
        );
    }
    
    public function get_cost_items_by_template($template_id) {
        // Use junction table for many-to-many relationship
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ci.*, cc.name as category_name, cc.color as category_color, tci.sort_order as template_sort_order
                 FROM {$this->wpdb->prefix}cah_template_cost_items tci
                 JOIN {$this->wpdb->prefix}cah_cost_items ci ON tci.cost_item_id = ci.id
                 LEFT JOIN {$this->wpdb->prefix}cah_cost_categories cc ON ci.category_id = cc.id
                 WHERE tci.template_id = %d
                 ORDER BY tci.sort_order ASC, ci.name ASC",
                $template_id
            )
        );
    }
    
    /**
     * Clear all cost items for a specific case
     */
    public function clear_case_cost_items($case_id) {
        $result = $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_cost_items',
            array('case_id' => $case_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get case financial summary table name
     */
    public function get_case_financial_table() {
        global $wpdb;
        return $wpdb->prefix . 'cah_case_financial';
    }
    
    /**
     * Get cost items by case ID
     */
    public function get_cost_items_by_case($case_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}cah_cost_items WHERE case_id = %d ORDER BY sort_order ASC, category ASC",
                $case_id
            )
        );
    }
    
    public function update_cost_item($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'cah_cost_items',
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    public function delete_cost_item($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_cost_items',
            array('id' => $id),
            array('%d')
        );
    }
    
    // Case Financial CRUD operations
    public function create_case_financial($case_id, $template_id = null) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'cah_case_financial',
            array(
                'case_id' => $case_id,
                'template_id' => $template_id
            ),
            array('%d', '%d')
        );
    }
    
    public function get_case_financial($case_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}cah_case_financial WHERE case_id = %d",
                $case_id
            )
        );
    }
    
    public function update_case_financial($case_id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'cah_case_financial',
            $data,
            array('case_id' => $case_id),
            null,
            array('%d')
        );
    }
    
    public function delete_case_financial($case_id) {
        // First delete associated cost items
        $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_cost_items',
            array('case_id' => $case_id),
            array('%d')
        );
        
        // Then delete case financial record
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_case_financial',
            array('case_id' => $case_id),
            array('%d')
        );
    }
    
    // Cost Categories CRUD operations
    private function create_default_cost_categories() {
        // Check if default categories already exist
        $existing_categories = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}cah_cost_categories WHERE is_default = 1"
        );
        
        if ($existing_categories > 0) {
            return; // Default categories already exist
        }
        
        $default_categories = array(
            array('name' => 'Grundkosten', 'slug' => 'basic_costs', 'color' => '#4caf50', 'description' => 'Grundlegende Kosten und Schäden', 'sort_order' => 1),
            array('name' => 'Gerichtskosten', 'slug' => 'court_costs', 'color' => '#ff9800', 'description' => 'Kosten für Gerichtsverfahren', 'sort_order' => 2),
            array('name' => 'Anwaltskosten', 'slug' => 'legal_costs', 'color' => '#2196f3', 'description' => 'Anwaltshonorare und Beratungskosten', 'sort_order' => 3),
            array('name' => 'Sonstige Kosten', 'slug' => 'other_costs', 'color' => '#9c27b0', 'description' => 'Weitere anfallende Kosten', 'sort_order' => 4)
        );
        
        foreach ($default_categories as $category) {
            $this->create_cost_category(
                $category['name'],
                $category['slug'],
                $category['description'],
                $category['color'],
                true,
                $category['sort_order']
            );
        }
    }
    
    public function create_cost_category($name, $slug, $description = '', $color = '#2196f3', $is_default = false, $sort_order = 0) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'cah_cost_categories',
            array(
                'name' => $name,
                'slug' => sanitize_key($slug),
                'description' => $description,
                'color' => $color,
                'is_default' => $is_default ? 1 : 0,
                'sort_order' => $sort_order
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d')
        );
    }
    
    public function get_cost_category($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}cah_cost_categories WHERE id = %d",
                $id
            )
        );
    }
    
    public function get_cost_categories($include_default = true) {
        $where = $include_default ? '' : 'WHERE is_default = 0';
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}cah_cost_categories {$where} ORDER BY sort_order ASC, name ASC"
        );
    }
    
    public function update_cost_category($id, $data) {
        if (isset($data['slug'])) {
            $data['slug'] = sanitize_key($data['slug']);
        }
        
        return $this->wpdb->update(
            $this->wpdb->prefix . 'cah_cost_categories',
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    public function delete_cost_category($id) {
        // First, update cost items to remove this category (set to null or default)
        $this->wpdb->update(
            $this->wpdb->prefix . 'cah_cost_items',
            array('category_id' => null),
            array('category_id' => $id),
            array('%s'),
            array('%d')
        );
        
        // Then delete the category
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_cost_categories',
            array('id' => $id),
            array('%d')
        );
    }
    
    // Independent Cost Items CRUD operations
    public function create_independent_cost_item($name, $category_id, $amount, $description = '', $is_percentage = false, $sort_order = 0) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'cah_cost_items',
            array(
                'template_id' => null,
                'case_id' => null,
                'category_id' => $category_id,
                'name' => $name,
                'category' => null, // Using new category_id instead of enum
                'amount' => $amount,
                'description' => $description,
                'is_percentage' => $is_percentage ? 1 : 0,
                'is_independent' => 1,
                'sort_order' => $sort_order
            ),
            array('%s', '%s', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%d')
        );
    }
    
    public function get_independent_cost_items() {
        return $this->wpdb->get_results(
            "SELECT ci.*, cc.name as category_name, cc.color as category_color 
             FROM {$this->wpdb->prefix}cah_cost_items ci 
             LEFT JOIN {$this->wpdb->prefix}cah_cost_categories cc ON ci.category_id = cc.id 
             WHERE ci.is_independent = 1 
             ORDER BY ci.sort_order ASC, ci.name ASC"
        );
    }
    
    // Template Assignment operations
    public function assign_cost_item_to_template($template_id, $cost_item_id, $sort_order = 0) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'cah_template_cost_items',
            array(
                'template_id' => $template_id,
                'cost_item_id' => $cost_item_id,
                'sort_order' => $sort_order
            ),
            array('%d', '%d', '%d')
        );
    }
    
    public function remove_cost_item_from_template($template_id, $cost_item_id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'cah_template_cost_items',
            array(
                'template_id' => $template_id,
                'cost_item_id' => $cost_item_id
            ),
            array('%d', '%d')
        );
    }
    
    public function get_template_assigned_items($template_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ci.*, cc.name as category_name, cc.color as category_color, tci.sort_order as template_sort_order
                 FROM {$this->wpdb->prefix}cah_template_cost_items tci
                 JOIN {$this->wpdb->prefix}cah_cost_items ci ON tci.cost_item_id = ci.id
                 LEFT JOIN {$this->wpdb->prefix}cah_cost_categories cc ON ci.category_id = cc.id
                 WHERE tci.template_id = %d
                 ORDER BY tci.sort_order ASC, ci.name ASC",
                $template_id
            )
        );
    }
    
    /**
     * MIGRATION: German → English Category Slug Standardization
     * Migrates existing German category slugs to English standard
     */
    public function migrate_german_to_english_category_slugs() {
        $migration_results = array(
            'success' => true,
            'message' => '',
            'migrations_performed' => array()
        );
        
        // Define German → English slug mappings
        $slug_mappings = array(
            'grundkosten' => 'basic_costs',
            'gerichtskosten' => 'court_costs', 
            'anwaltskosten' => 'legal_costs',
            'sonstige' => 'other_costs'
        );
        
        $categories_table = $this->wpdb->prefix . 'cah_cost_categories';
        $items_table = $this->wpdb->prefix . 'cah_cost_items';
        $template_items_table = $this->wpdb->prefix . 'cah_template_items';
        
        // Check if tables exist
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$categories_table'") != $categories_table) {
            $migration_results['message'] = 'Cost categories table does not exist - nothing to migrate';
            return $migration_results;
        }
        
        // Migrate cost categories slugs
        foreach ($slug_mappings as $german_slug => $english_slug) {
            $result = $this->wpdb->update(
                $categories_table,
                array('slug' => $english_slug),
                array('slug' => $german_slug),
                array('%s'),
                array('%s')
            );
            
            if ($result !== false) {
                if ($result > 0) {
                    $migration_results['migrations_performed'][] = "Updated category slug: $german_slug → $english_slug ($result rows)";
                }
            } else {
                $migration_results['success'] = false;
                $migration_results['migrations_performed'][] = "Failed to update category slug $german_slug: " . $this->wpdb->last_error;
            }
        }
        
        // Migrate template items if they use category slugs directly
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$template_items_table'") == $template_items_table) {
            foreach ($slug_mappings as $german_slug => $english_slug) {
                $result = $this->wpdb->update(
                    $template_items_table,
                    array('category' => $english_slug),
                    array('category' => $german_slug),
                    array('%s'),
                    array('%s')
                );
                
                if ($result !== false && $result > 0) {
                    $migration_results['migrations_performed'][] = "Updated template items category: $german_slug → $english_slug ($result rows)";
                } elseif ($result === false) {
                    $migration_results['success'] = false;
                    $migration_results['migrations_performed'][] = "Failed to update template items category $german_slug: " . $this->wpdb->last_error;
                }
            }
        }
        
        // Update any cost items that might store category slugs directly
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$items_table'") == $items_table) {
            // Check if category column exists and contains slugs
            $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $items_table LIKE 'category'");
            if (!empty($columns)) {
                foreach ($slug_mappings as $german_slug => $english_slug) {
                    $result = $this->wpdb->update(
                        $items_table,
                        array('category' => $english_slug),
                        array('category' => $german_slug),
                        array('%s'),
                        array('%s')
                    );
                    
                    if ($result !== false && $result > 0) {
                        $migration_results['migrations_performed'][] = "Updated cost items category: $german_slug → $english_slug ($result rows)";
                    } elseif ($result === false) {
                        $migration_results['success'] = false;
                        $migration_results['migrations_performed'][] = "Failed to update cost items category $german_slug: " . $this->wpdb->last_error;
                    }
                }
            }
        }
        
        if ($migration_results['success']) {
            if (empty($migration_results['migrations_performed'])) {
                $migration_results['message'] = 'No German category slugs found - migration not needed';
            } else {
                $migration_results['message'] = 'German → English category slug migration completed successfully';
            }
        } else {
            $migration_results['message'] = 'Category slug migration completed with some errors';
        }
        
        return $migration_results;
    }
}