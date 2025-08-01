<?php
/**
 * Template Manager Class
 * 
 * Handles full CRUD operations for document templates
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_Template_Manager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'klage_document_templates';
        
        // Add AJAX hooks
        add_action('wp_ajax_create_template', array($this, 'ajax_create_template'));
        add_action('wp_ajax_update_template', array($this, 'ajax_update_template'));
        add_action('wp_ajax_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_duplicate_template', array($this, 'ajax_duplicate_template'));
    }
    
    /**
     * Create a new template
     * 
     * @param array $template_data Template data
     * @return int|WP_Error Template ID or error
     */
    public function create_template($template_data) {
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('template_name', 'template_html');
        foreach ($required_fields as $field) {
            if (empty($template_data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Field %s is required.', 'klage-click-doc-out'), $field));
            }
        }
        
        // Generate unique slug
        $slug = $this->generate_unique_slug($template_data['template_name']);
        
        // Extract placeholders from HTML
        $placeholders = $this->extract_placeholders($template_data['template_html']);
        
        // Prepare data for insertion
        $insert_data = array(
            'template_name' => sanitize_text_field($template_data['template_name']),
            'template_slug' => $slug,
            'template_description' => sanitize_textarea_field($template_data['template_description'] ?? ''),
            'template_category' => sanitize_text_field($template_data['template_category'] ?? 'general'),
            'template_html' => wp_kses_post($template_data['template_html']),
            'template_placeholders' => json_encode($placeholders),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'is_active' => 1
        );
        
        // Insert into database
        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create template.', 'klage-click-doc-out'));
        }
        
        $template_id = $wpdb->insert_id;
        
        // Log the action
        do_action('klage_doc_template_created', $template_id, $insert_data);
        
        return $template_id;
    }
    
    /**
     * Get a template by ID
     * 
     * @param int $template_id Template ID
     * @return object|null Template object or null
     */
    public function get_template($template_id) {
        global $wpdb;
        
        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d AND is_active = 1",
                $template_id
            )
        );
        
        if ($template) {
            // Decode placeholders
            $template->template_placeholders = json_decode($template->template_placeholders, true);
        }
        
        return $template;
    }
    
    /**
     * Get template by slug
     * 
     * @param string $slug Template slug
     * @return object|null Template object or null
     */
    public function get_template_by_slug($slug) {
        global $wpdb;
        
        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE template_slug = %s AND is_active = 1",
                $slug
            )
        );
        
        if ($template) {
            // Decode placeholders
            $template->template_placeholders = json_decode($template->template_placeholders, true);
        }
        
        return $template;
    }
    
    /**
     * Get all templates with optional filtering
     * 
     * @param array $args Query arguments
     * @return array Array of template objects
     */
    public function get_templates($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'category' => '',
            'search' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'include_inactive' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!$args['include_inactive']) {
            $where_conditions[] = "is_active = %d";
            $where_values[] = 1;
        }
        
        if (!empty($args['category'])) {
            $where_conditions[] = "template_category = %s";
            $where_values[] = $args['category'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "(template_name LIKE %s OR template_description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Build ORDER BY clause
        $allowed_orderby = array('id', 'template_name', 'template_category', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build LIMIT clause
        $limit = '';
        if ($args['per_page'] > 0) {
            $offset = ($args['page'] - 1) * $args['per_page'];
            $limit = $wpdb->prepare("LIMIT %d, %d", $offset, $args['per_page']);
        }
        
        // Execute query
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} {$order} {$limit}";
        
        if (!empty($where_values)) {
            $templates = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $templates = $wpdb->get_results($query);
        }
        
        // Decode placeholders for each template
        foreach ($templates as $template) {
            $template->template_placeholders = json_decode($template->template_placeholders, true);
        }
        
        return $templates;
    }
    
    /**
     * Get templates count
     * 
     * @param array $args Query arguments
     * @return int Templates count
     */
    public function get_templates_count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'category' => '',
            'search' => '',
            'include_inactive' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!$args['include_inactive']) {
            $where_conditions[] = "is_active = %d";
            $where_values[] = 1;
        }
        
        if (!empty($args['category'])) {
            $where_conditions[] = "template_category = %s";
            $where_values[] = $args['category'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = "(template_name LIKE %s OR template_description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Execute count query
        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        
        if (!empty($where_values)) {
            return (int) $wpdb->get_var($wpdb->prepare($query, $where_values));
        } else {
            return (int) $wpdb->get_var($query);
        }
    }
    
    /**
     * Update a template
     * 
     * @param int $template_id Template ID
     * @param array $template_data Updated template data
     * @return bool|WP_Error Success or error
     */
    public function update_template($template_id, $template_data) {
        global $wpdb;
        
        // Check if template exists
        $existing_template = $this->get_template($template_id);
        if (!$existing_template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'));
        }
        
        // Extract placeholders if HTML is updated
        if (isset($template_data['template_html'])) {
            $placeholders = $this->extract_placeholders($template_data['template_html']);
            $template_data['template_placeholders'] = json_encode($placeholders);
        }
        
        // Prepare update data
        $update_data = array();
        $update_format = array();
        
        $allowed_fields = array(
            'template_name' => '%s',
            'template_description' => '%s', 
            'template_category' => '%s',
            'template_html' => '%s',
            'template_placeholders' => '%s',
            's3_url' => '%s',
            'encryption_key' => '%s',
            'is_active' => '%d'
        );
        
        foreach ($allowed_fields as $field => $format) {
            if (isset($template_data[$field])) {
                switch ($field) {
                    case 'template_name':
                    case 'template_category':
                        $update_data[$field] = sanitize_text_field($template_data[$field]);
                        break;
                    case 'template_description':
                        $update_data[$field] = sanitize_textarea_field($template_data[$field]);
                        break;
                    case 'template_html':
                        $update_data[$field] = wp_kses_post($template_data[$field]);
                        break;
                    case 'is_active':
                        $update_data[$field] = (int) $template_data[$field];
                        break;
                    default:
                        $update_data[$field] = $template_data[$field];
                }
                $update_format[] = $format;
            }
        }
        
        // Always update the timestamp
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        // Update in database
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $template_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update template.', 'klage-click-doc-out'));
        }
        
        // Log the action
        do_action('klage_doc_template_updated', $template_id, $update_data);
        
        return true;
    }
    
    /**
     * Delete a template (soft delete)
     * 
     * @param int $template_id Template ID
     * @return bool|WP_Error Success or error
     */
    public function delete_template($template_id) {
        global $wpdb;
        
        // Check if template exists
        $existing_template = $this->get_template($template_id);
        if (!$existing_template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'));
        }
        
        // Soft delete by setting is_active to 0
        $result = $wpdb->update(
            $this->table_name,
            array(
                'is_active' => 0,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $template_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete template.', 'klage-click-doc-out'));
        }
        
        // Log the action
        do_action('klage_doc_template_deleted', $template_id);
        
        return true;
    }
    
    /**
     * Duplicate a template
     * 
     * @param int $template_id Template ID to duplicate
     * @return int|WP_Error New template ID or error
     */
    public function duplicate_template($template_id) {
        $original_template = $this->get_template($template_id);
        if (!$original_template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'));
        }
        
        // Prepare data for new template
        $new_template_data = array(
            'template_name' => $original_template->template_name . ' (Copy)',
            'template_description' => $original_template->template_description,
            'template_category' => $original_template->template_category,
            'template_html' => $original_template->template_html
        );
        
        return $this->create_template($new_template_data);
    }
    
    /**
     * Get template categories
     * 
     * @return array Array of categories
     */
    public function get_template_categories() {
        global $wpdb;
        
        $categories = $wpdb->get_col(
            "SELECT DISTINCT template_category FROM {$this->table_name} WHERE is_active = 1 ORDER BY template_category"
        );
        
        // Default categories
        $default_categories = array(
            'general' => __('General', 'klage-click-doc-out'),
            'mahnung' => __('Mahnung', 'klage-click-doc-out'),
            'contract' => __('Contract', 'klage-click-doc-out'),
            'notice' => __('Notice', 'klage-click-doc-out'),
            'court' => __('Court Documents', 'klage-click-doc-out')
        );
        
        $result = array();
        foreach ($categories as $category) {
            $result[$category] = $default_categories[$category] ?? ucfirst($category);
        }
        
        return $result;
    }
    
    /**
     * Generate unique slug from template name
     * 
     * @param string $name Template name
     * @return string Unique slug
     */
    private function generate_unique_slug($name) {
        global $wpdb;
        
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;
        
        // Check if slug exists
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE template_slug = %s",
            $slug
        ))) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Extract placeholders from HTML template
     * 
     * @param string $html HTML content
     * @return array Array of placeholders
     */
    private function extract_placeholders($html) {
        preg_match_all('/\{\{([^}]+)\}\}/', $html, $matches);
        
        $placeholders = array();
        if (!empty($matches[1])) {
            foreach ($matches[1] as $placeholder) {
                $placeholder = trim($placeholder);
                if (!in_array($placeholder, $placeholders)) {
                    $placeholders[] = $placeholder;
                }
            }
        }
        
        return $placeholders;
    }
    
    /**
     * AJAX: Create template
     */
    public function ajax_create_template() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $template_data = array(
            'template_name' => $_POST['template_name'] ?? '',
            'template_description' => $_POST['template_description'] ?? '',
            'template_category' => $_POST['template_category'] ?? 'general',
            'template_html' => $_POST['template_html'] ?? ''
        );
        
        $result = $this->create_template($template_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'template_id' => $result,
                'message' => __('Template created successfully.', 'klage-click-doc-out')
            ));
        }
    }
    
    /**
     * AJAX: Update template
     */
    public function ajax_update_template() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $template_id = (int) $_POST['template_id'];
        $template_data = array(
            'template_name' => $_POST['template_name'] ?? '',
            'template_description' => $_POST['template_description'] ?? '',
            'template_category' => $_POST['template_category'] ?? 'general',
            'template_html' => $_POST['template_html'] ?? ''
        );
        
        $result = $this->update_template($template_id, $template_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Template updated successfully.', 'klage-click-doc-out')
            ));
        }
    }
    
    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $template_id = (int) $_POST['template_id'];
        $result = $this->delete_template($template_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Template deleted successfully.', 'klage-click-doc-out')
            ));
        }
    }
    
    /**
     * AJAX: Get template
     */
    public function ajax_get_template() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $template_id = (int) $_POST['template_id'];
        $template = $this->get_template($template_id);
        
        if (!$template) {
            wp_send_json_error(__('Template not found.', 'klage-click-doc-out'));
        } else {
            wp_send_json_success($template);
        }
    }
    
    /**
     * AJAX: Duplicate template
     */
    public function ajax_duplicate_template() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $template_id = (int) $_POST['template_id'];
        $result = $this->duplicate_template($template_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'new_template_id' => $result,
                'message' => __('Template duplicated successfully.', 'klage-click-doc-out')
            ));
        }
    }
}