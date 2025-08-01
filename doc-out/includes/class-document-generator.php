<?php
/**
 * Document Generator Class
 * 
 * Handles the main document generation workflow including
 * template processing, data integration, and WYSIWYG editing
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_Document_Generator {
    
    private $template_manager;
    private $pdf_engine;
    private $core_integration;
    
    public function __construct() {
        // Initialize dependencies when available
        add_action('init', array($this, 'init_dependencies'), 20);
        
        // Add AJAX hooks
        add_action('wp_ajax_generate_document_draft', array($this, 'ajax_generate_document_draft'));
        add_action('wp_ajax_generate_final_pdf', array($this, 'ajax_generate_final_pdf'));
        add_action('wp_ajax_save_document_draft', array($this, 'ajax_save_document_draft'));
        add_action('wp_ajax_preview_document', array($this, 'ajax_preview_document'));
        
        // Add download handler
        add_action('init', array($this, 'handle_pdf_download'));
    }
    
    /**
     * Initialize dependencies
     */
    public function init_dependencies() {
        global $klage_click_doc_out;
        
        if ($klage_click_doc_out) {
            $this->template_manager = $klage_click_doc_out->template_manager;
            $this->pdf_engine = $klage_click_doc_out->pdf_engine;
            $this->core_integration = $klage_click_doc_out->core_integration;
        }
    }
    
    /**
     * Generate document draft from template and case data
     * 
     * @param int $template_id Template ID
     * @param int $case_id Case ID for data integration
     * @param array $additional_data Additional data to merge
     * @return array|WP_Error Generated draft data or error
     */
    public function generate_document_draft($template_id, $case_id = null, $additional_data = array()) {
        // Get template
        $template = $this->template_manager->get_template($template_id);
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'));
        }
        
        // Get case data if case ID provided
        $case_data = array();
        if ($case_id && $this->core_integration) {
            $case_data = $this->core_integration->get_case_data($case_id);
            if (is_wp_error($case_data)) {
                return $case_data;
            }
        }
        
        // Merge all data sources
        $data = array_merge($case_data, $additional_data);
        
        // Generate HTML draft
        $draft_html = $this->replace_template_placeholders($template->template_html, $data);
        
        // Save draft to database
        $draft_id = $this->save_document_draft(array(
            'template_id' => $template_id,
            'case_id' => $case_id,
            'draft_html' => $draft_html,
            'template_data' => $data,
            'status' => 'draft'
        ));
        
        if (is_wp_error($draft_id)) {
            return $draft_id;
        }
        
        return array(
            'draft_id' => $draft_id,
            'draft_html' => $draft_html,
            'template' => $template,
            'data' => $data,
            'placeholders' => $template->template_placeholders
        );
    }
    
    /**
     * Generate final PDF from document draft
     * 
     * @param int $draft_id Document draft ID
     * @param array $options PDF generation options
     * @return string|WP_Error PDF file path or error
     */
    public function generate_final_pdf($draft_id, $options = array()) {
        // Get draft data
        $draft = $this->get_document_draft($draft_id);
        if (!$draft) {
            return new WP_Error('draft_not_found', __('Document draft not found.', 'klage-click-doc-out'));
        }
        
        // Use the edited HTML content
        $html_content = $draft->draft_html;
        
        // Set filename if not provided
        if (empty($options['filename'])) {
            $template_slug = $draft->template_slug ?? 'document';
            $case_reference = $draft->case_id ? ('_case_' . $draft->case_id) : '';
            $options['filename'] = sanitize_file_name($template_slug . $case_reference . '_' . date('Y-m-d_H-i-s') . '.pdf');
        }
        
        // Generate PDF
        $pdf_result = $this->pdf_engine->generate_pdf($html_content, $options);
        
        if (is_wp_error($pdf_result)) {
            return $pdf_result;
        }
        
        // Update draft status
        $this->update_document_draft($draft_id, array(
            'status' => 'final',
            'pdf_generated_at' => current_time('mysql'),
            'pdf_filename' => $options['filename']
        ));
        
        // Log the generation
        do_action('klage_doc_pdf_generated', $draft_id, $pdf_result, $options);
        
        return $pdf_result;
    }
    
    /**
     * Save document draft to database
     * 
     * @param array $draft_data Draft data
     * @return int|WP_Error Draft ID or error
     */
    private function save_document_draft($draft_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'klage_document_drafts';
        
        // Prepare data for insertion
        $insert_data = array(
            'template_id' => (int) $draft_data['template_id'],
            'case_id' => isset($draft_data['case_id']) ? (int) $draft_data['case_id'] : null,
            'draft_html' => wp_kses_post($draft_data['draft_html']),
            'template_data' => json_encode($draft_data['template_data'] ?? array()),
            'status' => sanitize_text_field($draft_data['status'] ?? 'draft'),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save document draft.', 'klage-click-doc-out'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get document draft by ID
     * 
     * @param int $draft_id Draft ID
     * @return object|null Draft object or null
     */
    public function get_document_draft($draft_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'klage_document_drafts';
        
        $draft = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT d.*, t.template_name, t.template_slug 
                 FROM {$table_name} d
                 LEFT JOIN {$wpdb->prefix}klage_document_templates t ON d.template_id = t.id
                 WHERE d.id = %d",
                $draft_id
            )
        );
        
        if ($draft) {
            $draft->template_data = json_decode($draft->template_data, true);
        }
        
        return $draft;
    }
    
    /**
     * Update document draft
     * 
     * @param int $draft_id Draft ID
     * @param array $update_data Update data
     * @return bool|WP_Error Success or error
     */
    public function update_document_draft($draft_id, $update_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'klage_document_drafts';
        
        // Prepare update data
        $allowed_fields = array('draft_html', 'status', 'pdf_generated_at', 'pdf_filename');
        $filtered_data = array();
        $update_format = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($update_data[$field])) {
                switch ($field) {
                    case 'draft_html':
                        $filtered_data[$field] = wp_kses_post($update_data[$field]);
                        $update_format[] = '%s';
                        break;
                    case 'status':
                    case 'pdf_filename':
                        $filtered_data[$field] = sanitize_text_field($update_data[$field]);
                        $update_format[] = '%s';
                        break;
                    case 'pdf_generated_at':
                        $filtered_data[$field] = $update_data[$field];
                        $update_format[] = '%s';
                        break;
                }
            }
        }
        
        // Always update timestamp
        $filtered_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        $result = $wpdb->update(
            $table_name,
            $filtered_data,
            array('id' => $draft_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update document draft.', 'klage-click-doc-out'));
        }
        
        return true;
    }
    
    /**
     * Replace template placeholders with actual data
     * 
     * @param string $template_html Template HTML
     * @param array $data Data array
     * @return string Processed HTML
     */
    private function replace_template_placeholders($template_html, $data) {
        // Flatten nested arrays for easier placeholder replacement
        $flattened_data = $this->flatten_array($data);
        
        // Add some default placeholders
        $default_data = array(
            'current_date' => date_i18n(get_option('date_format')),
            'current_time' => date_i18n(get_option('time_format')),
            'current_year' => date('Y'),
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url()
        );
        
        $all_data = array_merge($default_data, $flattened_data);
        
        foreach ($all_data as $key => $value) {
            // Handle different data types
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $value = (string) $value;
                } else {
                    $value = json_encode($value);
                }
            } elseif (is_bool($value)) {
                $value = $value ? __('Yes', 'klage-click-doc-out') : __('No', 'klage-click-doc-out');
            } elseif (is_null($value)) {
                $value = '';
            }
            
            // Convert to string and apply basic formatting
            $value = (string) $value;
            
            // Replace placeholder (case-insensitive)
            $template_html = str_ireplace('{{' . $key . '}}', esc_html($value), $template_html);
        }
        
        // Clean up any remaining placeholders
        $template_html = preg_replace('/\{\{[^}]+\}\}/i', '<span style="color: red; background: yellow;">[MISSING DATA]</span>', $template_html);
        
        return $template_html;
    }
    
    /**
     * Flatten multi-dimensional array
     * 
     * @param array $array Input array
     * @param string $prefix Key prefix
     * @return array Flattened array
     */
    private function flatten_array($array, $prefix = '') {
        $result = array();
        
        if (!is_array($array)) {
            return array($prefix => $array);
        }
        
        foreach ($array as $key => $value) {
            $new_key = $prefix . $key;
            
            if (is_array($value) && !empty($value)) {
                $result = array_merge($result, $this->flatten_array($value, $new_key . '_'));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Get available placeholders for a case
     * 
     * @param int $case_id Case ID
     * @return array Available placeholders
     */
    public function get_available_placeholders($case_id = null) {
        $placeholders = array();
        
        // Default placeholders
        $placeholders['general'] = array(
            'current_date' => __('Current Date', 'klage-click-doc-out'),
            'current_time' => __('Current Time', 'klage-click-doc-out'),
            'current_year' => __('Current Year', 'klage-click-doc-out'),
            'site_name' => __('Site Name', 'klage-click-doc-out'),
            'site_url' => __('Site URL', 'klage-click-doc-out')
        );
        
        // Case-specific placeholders
        if ($case_id && $this->core_integration) {
            $case_placeholders = $this->core_integration->get_case_placeholders($case_id);
            if (!is_wp_error($case_placeholders)) {
                $placeholders['case'] = $case_placeholders;
            }
        }
        
        return apply_filters('klage_doc_available_placeholders', $placeholders, $case_id);
    }
    
    /**
     * Handle PDF download requests
     */
    public function handle_pdf_download() {
        if (!isset($_GET['klage_doc_download']) || !isset($_GET['draft_id'])) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'klage_doc_download')) {
            wp_die(__('Security check failed.', 'klage-click-doc-out'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to download this file.', 'klage-click-doc-out'));
        }
        
        $draft_id = (int) $_GET['draft_id'];
        
        // Generate PDF
        $pdf_result = $this->generate_final_pdf($draft_id);
        
        if (is_wp_error($pdf_result)) {
            wp_die($pdf_result->get_error_message());
        }
        
        // Get draft info for filename
        $draft = $this->get_document_draft($draft_id);
        $filename = $draft->pdf_filename ?? basename($pdf_result);
        
        // Serve the file
        $this->pdf_engine->serve_pdf_download($pdf_result, $filename, true);
    }
    
    /**
     * AJAX: Generate document draft
     */
    public function ajax_generate_document_draft() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $template_id = (int) $_POST['template_id'];
        $case_id = !empty($_POST['case_id']) ? (int) $_POST['case_id'] : null;
        $additional_data = !empty($_POST['additional_data']) ? $_POST['additional_data'] : array();
        
        $result = $this->generate_document_draft($template_id, $case_id, $additional_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX: Generate final PDF
     */
    public function ajax_generate_final_pdf() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $draft_id = (int) $_POST['draft_id'];
        $options = !empty($_POST['options']) ? $_POST['options'] : array();
        
        $result = $this->generate_final_pdf($draft_id, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            $download_url = add_query_arg(array(
                'klage_doc_download' => '1',
                'draft_id' => $draft_id,
                'nonce' => wp_create_nonce('klage_doc_download')
            ), admin_url());
            
            wp_send_json_success(array(
                'message' => __('PDF generated successfully.', 'klage-click-doc-out'),
                'download_url' => $download_url
            ));
        }
    }
    
    /**
     * AJAX: Save document draft
     */
    public function ajax_save_document_draft() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $draft_id = (int) $_POST['draft_id'];
        $draft_html = $_POST['draft_html'] ?? '';
        
        $result = $this->update_document_draft($draft_id, array(
            'draft_html' => $draft_html,
            'status' => 'edited'
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Draft saved successfully.', 'klage-click-doc-out')
            ));
        }
    }
    
    /**
     * AJAX: Preview document
     */
    public function ajax_preview_document() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $draft_id = (int) $_POST['draft_id'];
        $draft = $this->get_document_draft($draft_id);
        
        if (!$draft) {
            wp_send_json_error(__('Draft not found.', 'klage-click-doc-out'));
        } else {
            wp_send_json_success(array(
                'html' => $draft->draft_html
            ));
        }
    }
}