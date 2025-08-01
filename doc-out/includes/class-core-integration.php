<?php
/**
 * Core Integration Class
 * 
 * Handles integration with the Court Automation Hub core plugin
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_Core_Integration {
    
    private $core_plugin;
    private $core_plugin_active;
    private $db_integration;
    
    public function __construct() {
        $this->core_plugin_active = $this->is_core_plugin_active();
        
        if ($this->core_plugin_active) {
            $this->db_integration = new CAH_DocOut_Database_Integration();
        }
        
        // Hook into core plugin initialization
        add_action('init', array($this, 'initialize_integration'), 25);
        
        // Add document generation to case management
        add_action('klage_case_meta_boxes', array($this, 'add_document_meta_box'));
        add_action('klage_case_actions', array($this, 'add_document_actions'));
        
        // Add AJAX hooks for core integration
        add_action('wp_ajax_get_case_data', array($this, 'ajax_get_case_data'));
        add_action('wp_ajax_get_available_cases', array($this, 'ajax_get_available_cases'));
        
        // Show dependency notice if core plugin is not active
        $this->show_dependency_notice();
    }
    
    /**
     * Check if core plugin is active
     */
    public function is_core_plugin_active() {
        return class_exists('CourtAutomationHub');
    }
    
    /**
     * Show admin notice if core plugin is not active
     */
    public function show_dependency_notice() {
        if (!$this->core_plugin_active) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error">';
                echo '<p><strong>Document Generator Plugin:</strong> ';
                echo 'Requires Court Automation Hub Core Plugin to be active for debtor data access.';
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Get debtor data for document generation
     */
    public function get_debtor_for_document($debtor_id) {
        if (!$this->core_plugin_active || !$this->db_integration) {
            return null;
        }
        
        return $this->db_integration->get_debtor_by_id($debtor_id);
    }
    
    /**
     * Get case data for document generation
     */
    public function get_case_for_document($case_id) {
        if (!$this->core_plugin_active || !$this->db_integration) {
            return null;
        }
        
        return $this->db_integration->get_case_with_debtor($case_id);
    }
    
    /**
     * Get template variables for document generation
     */
    public function get_template_variables($case_id) {
        if (!$this->core_plugin_active || !$this->db_integration) {
            return array();
        }
        
        return $this->db_integration->get_template_variables($case_id);
    }
    
    /**
     * Initialize integration with core plugin
     */
    public function initialize_integration() {
        // Check if core plugin is active and get instance
        if (class_exists('CourtAutomationHub')) {
            global $court_automation_hub;
            $this->core_plugin = $court_automation_hub;
        }
    }
    
    /**
     * Get case data by ID
     * 
     * @param int $case_id Case ID
     * @return array|WP_Error Case data or error
     */
    public function get_case_data($case_id) {
        global $wpdb;
        
        if (!$this->is_core_plugin_available()) {
            return new WP_Error('core_not_available', __('Core plugin is not available.', 'klage-click-doc-out'));
        }
        
        // Get main case data
        $case_table = $wpdb->prefix . 'klage_cases';
        $case = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$case_table} WHERE case_id = %d",
                $case_id
            ),
            ARRAY_A
        );
        
        if (!$case) {
            return new WP_Error('case_not_found', __('Case not found.', 'klage-click-doc-out'));
        }
        
        // Get related data
        $case_data = array(
            'case' => $case,
            'debtor' => $this->get_debtor_data($case['debtor_id']),
            'client' => $this->get_client_data($case['client_id']),
            'court' => $this->get_court_data($case['court_id'] ?? null),
            'emails' => $this->get_case_emails($case_id),
            'financial' => $this->get_case_financial_data($case_id)
        );
        
        return apply_filters('klage_doc_case_data', $case_data, $case_id);
    }
    
    /**
     * Get debtor data by ID
     * 
     * @param int $debtor_id Debtor ID
     * @return array|null Debtor data or null
     */
    private function get_debtor_data($debtor_id) {
        global $wpdb;
        
        if (empty($debtor_id)) {
            return null;
        }
        
        $debtor_table = $wpdb->prefix . 'klage_debtors';
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$debtor_table} WHERE id = %d",
                $debtor_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get client data by ID
     * 
     * @param int $client_id Client ID
     * @return array|null Client data or null
     */
    private function get_client_data($client_id) {
        global $wpdb;
        
        if (empty($client_id)) {
            return null;
        }
        
        $client_table = $wpdb->prefix . 'klage_clients';
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$client_table} WHERE id = %d",
                $client_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get court data by ID
     * 
     * @param int $court_id Court ID
     * @return array|null Court data or null
     */
    private function get_court_data($court_id) {
        global $wpdb;
        
        if (empty($court_id)) {
            return null;
        }
        
        $court_table = $wpdb->prefix . 'klage_courts';
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$court_table} WHERE id = %d",
                $court_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get case emails
     * 
     * @param int $case_id Case ID
     * @return array Email data
     */
    private function get_case_emails($case_id) {
        global $wpdb;
        
        $email_table = $wpdb->prefix . 'klage_emails';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$email_table} WHERE case_id = %d ORDER BY sent_at DESC",
                $case_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get case financial data
     * 
     * @param int $case_id Case ID
     * @return array|null Financial data or null
     */
    private function get_case_financial_data($case_id) {
        // Check if financial plugin is active
        if (!class_exists('CAH_Financial_Calculator_Plugin')) {
            return null;
        }
        
        global $wpdb;
        
        // Get financial calculations for this case
        $financial_table = $wpdb->prefix . 'cah_case_financials';
        $financial_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$financial_table} WHERE case_id = %d ORDER BY created_at DESC LIMIT 1",
                $case_id
            ),
            ARRAY_A
        );
        
        if ($financial_data) {
            // Decode JSON fields if present
            if (!empty($financial_data['cost_breakdown'])) {
                $financial_data['cost_breakdown'] = json_decode($financial_data['cost_breakdown'], true);
            }
            
            if (!empty($financial_data['template_data'])) {
                $financial_data['template_data'] = json_decode($financial_data['template_data'], true);
            }
        }
        
        return $financial_data;
    }
    
    /**
     * Get all available cases for selection
     * 
     * @param array $args Query arguments
     * @return array Cases data
     */
    public function get_available_cases($args = array()) {
        global $wpdb;
        
        if (!$this->is_core_plugin_available()) {
            error_log("Doc Generator: Core plugin not available");
            return array();
        }
        
        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'search' => '',
            'status' => '',
            'orderby' => 'case_creation_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $case_table = $wpdb->prefix . 'klage_cases';
        $debtor_table = $wpdb->prefix . 'klage_debtors';
        
        // Check if tables exist
        $case_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$case_table'") == $case_table;
        $debtor_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$debtor_table'") == $debtor_table;
        
        if (!$case_table_exists || !$debtor_table_exists) {
            error_log("Doc Generator: Missing tables - Cases: " . ($case_table_exists ? 'exists' : 'missing') . 
                     ", Debtors: " . ($debtor_table_exists ? 'exists' : 'missing'));
            return array();
        }
        
        // Debug: Check table structure
        $case_columns = $wpdb->get_results("DESCRIBE $case_table");
        $debtor_columns = $wpdb->get_results("DESCRIBE $debtor_table");
        
        error_log("Doc Generator: Case table columns: " . json_encode(array_column($case_columns, 'Field')));
        error_log("Doc Generator: Debtor table columns: " . json_encode(array_column($debtor_columns, 'Field')));
        
        $where_conditions = array();
        $where_values = array();
        
        // Search functionality
        if (!empty($args['search'])) {
            $where_conditions[] = "(c.case_id LIKE %s OR d.debtors_name LIKE %s OR d.debtors_company LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Status filter
        if (!empty($args['status'])) {
            $where_conditions[] = "c.case_status = %s";
            $where_values[] = $args['status'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Order by
        $allowed_orderby = array('case_id', 'case_creation_date', 'debtors_name', 'total_amount');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'case_creation_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        if ($orderby === 'debtors_name') {
            $orderby = 'd.debtors_name';
        } else {
            $orderby = 'c.' . $orderby;
        }
        
        // Pagination
        $limit = '';
        if ($args['per_page'] > 0) {
            $offset = ($args['page'] - 1) * $args['per_page'];
            $limit = $wpdb->prepare("LIMIT %d, %d", $offset, $args['per_page']);
        }
        
        // Execute query
        $query = "
            SELECT c.case_id, c.case_creation_date, c.case_status, c.total_amount,
                   d.debtors_name, d.debtors_company, d.debtors_email
            FROM {$case_table} c
            LEFT JOIN {$debtor_table} d ON c.debtor_id = d.id
            {$where_clause}
            ORDER BY {$orderby} {$order}
            {$limit}
        ";
        
        error_log("Doc Generator: Executing query: " . $query);
        
        if (!empty($where_values)) {
            $cases = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
        } else {
            $cases = $wpdb->get_results($query, ARRAY_A);
        }
        
        // Debug query result
        if ($wpdb->last_error) {
            error_log("Doc Generator: SQL Error: " . $wpdb->last_error);
        }
        
        error_log("Doc Generator: Query returned " . count($cases) . " cases");
        if (!empty($cases)) {
            error_log("Doc Generator: First case: " . json_encode($cases[0]));
        }
        
        return $cases;
    }
    
    /**
     * Get available placeholders for case data
     * 
     * @param int $case_id Case ID
     * @return array Available placeholders
     */
    public function get_case_placeholders($case_id) {
        $case_data = $this->get_case_data($case_id);
        
        if (is_wp_error($case_data)) {
            return array();
        }
        
        $placeholders = array();
        
        // Case placeholders
        if ($case_data['case']) {
            foreach ($case_data['case'] as $key => $value) {
                $placeholders['case_' . $key] = sprintf(__('Case: %s', 'klage-click-doc-out'), ucfirst(str_replace('_', ' ', $key)));
            }
        }
        
        // Debtor placeholders
        if ($case_data['debtor']) {
            foreach ($case_data['debtor'] as $key => $value) {
                $placeholders['debtor_' . $key] = sprintf(__('Debtor: %s', 'klage-click-doc-out'), ucfirst(str_replace('_', ' ', $key)));
            }
        }
        
        // Client placeholders
        if ($case_data['client']) {
            foreach ($case_data['client'] as $key => $value) {
                $placeholders['client_' . $key] = sprintf(__('Client: %s', 'klage-click-doc-out'), ucfirst(str_replace('_', ' ', $key)));
            }
        }
        
        // Court placeholders
        if ($case_data['court']) {
            foreach ($case_data['court'] as $key => $value) {
                $placeholders['court_' . $key] = sprintf(__('Court: %s', 'klage-click-doc-out'), ucfirst(str_replace('_', ' ', $key)));
            }
        }
        
        // Financial placeholders
        if ($case_data['financial']) {
            foreach ($case_data['financial'] as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $placeholders['financial_' . $key] = sprintf(__('Financial: %s', 'klage-click-doc-out'), ucfirst(str_replace('_', ' ', $key)));
                }
            }
        }
        
        return $placeholders;
    }
    
    /**
     * Add document meta box to case edit screen
     * 
     * @param int $case_id Case ID
     */
    public function add_document_meta_box($case_id) {
        if (!$this->is_core_plugin_available()) {
            return;
        }
        
        echo '<div class="postbox">';
        echo '<h3 class="hndle">' . __('Document Generation', 'klage-click-doc-out') . '</h3>';
        echo '<div class="inside">';
        
        // Get available templates
        global $klage_click_doc_out;
        if ($klage_click_doc_out && $klage_click_doc_out->template_manager) {
            $templates = $klage_click_doc_out->template_manager->get_templates();
            
            if (!empty($templates)) {
                echo '<p>' . __('Generate documents from templates using case data:', 'klage-click-doc-out') . '</p>';
                echo '<select id="doc-template-select" name="template_id">';
                echo '<option value="">' . __('Select template...', 'klage-click-doc-out') . '</option>';
                
                foreach ($templates as $template) {
                    echo '<option value="' . esc_attr($template->id) . '">';
                    echo esc_html($template->template_name) . ' (' . esc_html($template->template_category) . ')';
                    echo '</option>';
                }
                
                echo '</select>';
                
                echo '<div style="margin-top: 10px;">';
                echo '<button type="button" id="generate-document" class="button button-primary">';
                echo __('Generate Document', 'klage-click-doc-out');
                echo '</button>';
                echo '</div>';
                
                // Add JavaScript
                echo '<script>
                jQuery(document).ready(function($) {
                    $("#generate-document").on("click", function() {
                        var templateId = $("#doc-template-select").val();
                        if (!templateId) {
                            alert("' . __('Please select a template.', 'klage-click-doc-out') . '");
                            return;
                        }
                        
                        var data = {
                            action: "generate_document_draft",
                            template_id: templateId,
                            case_id: ' . (int) $case_id . ',
                            nonce: klageDocAjax.nonce
                        };
                        
                        $.post(ajaxurl, data, function(response) {
                            if (response.success) {
                                var editUrl = "' . admin_url('admin.php?page=klage-doc-generate&draft_id=') . '" + response.data.draft_id;
                                window.open(editUrl, "_blank");
                            } else {
                                alert("Error: " + response.data);
                            }
                        });
                    });
                });
                </script>';
            } else {
                echo '<p>' . __('No templates available. Please create templates first.', 'klage-click-doc-out') . '</p>';
                echo '<a href="' . admin_url('admin.php?page=klage-doc-templates') . '" class="button">';
                echo __('Manage Templates', 'klage-click-doc-out');
                echo '</a>';
            }
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Add document actions to case actions area
     * 
     * @param int $case_id Case ID
     */
    public function add_document_actions($case_id) {
        if (!$this->is_core_plugin_available()) {
            return;
        }
        
        echo '<div class="klage-doc-actions">';
        echo '<h4>' . __('Document Actions', 'klage-click-doc-out') . '</h4>';
        
        echo '<a href="' . admin_url('admin.php?page=klage-doc-generate&case_id=' . (int) $case_id) . '" class="button">';
        echo __('Generate New Document', 'klage-click-doc-out');
        echo '</a>';
        
        // Show recent documents for this case
        $recent_drafts = $this->get_case_recent_documents($case_id);
        if (!empty($recent_drafts)) {
            echo '<h5>' . __('Recent Documents', 'klage-click-doc-out') . '</h5>';
            echo '<ul>';
            foreach ($recent_drafts as $draft) {
                echo '<li>';
                echo '<a href="' . admin_url('admin.php?page=klage-doc-generate&draft_id=' . $draft->id) . '">';
                echo esc_html($draft->template_name) . ' (' . $draft->status . ')';
                echo '</a>';
                echo ' - ' . date_i18n(get_option('date_format'), strtotime($draft->created_at));
                echo '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get recent documents for a case
     * 
     * @param int $case_id Case ID
     * @return array Recent document drafts
     */
    private function get_case_recent_documents($case_id) {
        global $wpdb;
        
        $drafts_table = $wpdb->prefix . 'klage_document_drafts';
        $templates_table = $wpdb->prefix . 'klage_document_templates';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.*, t.template_name
                 FROM {$drafts_table} d
                 LEFT JOIN {$templates_table} t ON d.template_id = t.id
                 WHERE d.case_id = %d
                 ORDER BY d.created_at DESC
                 LIMIT 5",
                $case_id
            )
        );
    }
    
    /**
     * Check if core plugin is available
     * 
     * @return bool Core plugin availability
     */
    private function is_core_plugin_available() {
        return class_exists('CourtAutomationHub') && $this->core_plugin;
    }
    
    /**
     * AJAX: Get case data
     */
    public function ajax_get_case_data() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $case_id = (int) $_POST['case_id'];
        $case_data = $this->get_case_data($case_id);
        
        if (is_wp_error($case_data)) {
            wp_send_json_error($case_data->get_error_message());
        } else {
            wp_send_json_success($case_data);
        }
    }
    
    /**
     * AJAX: Get available cases
     */
    public function ajax_get_available_cases() {
        check_ajax_referer('klage_doc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'klage-click-doc-out'));
        }
        
        $args = array(
            'search' => $_POST['search'] ?? '',
            'status' => $_POST['status'] ?? '',
            'per_page' => (int) ($_POST['per_page'] ?? 20),
            'page' => (int) ($_POST['page'] ?? 1)
        );
        
        $cases = $this->get_available_cases($args);
        
        wp_send_json_success($cases);
    }
}