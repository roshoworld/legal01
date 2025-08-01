<?php
/**
 * REST API Class
 * 
 * Provides REST API endpoints for document generation
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_Doc_REST_API {
    
    private $namespace = 'klage-doc/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Templates endpoints
        register_rest_route($this->namespace, '/templates', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_templates'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'category' => array(
                        'type' => 'string',
                        'description' => __('Filter by template category', 'klage-click-doc-out'),
                    ),
                    'search' => array(
                        'type' => 'string',
                        'description' => __('Search templates', 'klage-click-doc-out'),
                    ),
                    'per_page' => array(
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ),
                    'page' => array(
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ),
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_template'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'template_name' => array(
                        'type' => 'string',
                        'required' => true,
                        'description' => __('Template name', 'klage-click-doc-out'),
                    ),
                    'template_description' => array(
                        'type' => 'string',
                        'description' => __('Template description', 'klage-click-doc-out'),
                    ),
                    'template_category' => array(
                        'type' => 'string',
                        'default' => 'general',
                        'description' => __('Template category', 'klage-click-doc-out'),
                    ),
                    'template_html' => array(
                        'type' => 'string',
                        'required' => true,
                        'description' => __('Template HTML content', 'klage-click-doc-out'),
                    ),
                )
            )
        ));
        
        // Single template endpoints
        register_rest_route($this->namespace, '/templates/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_template'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_template'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_template'),
                'permission_callback' => array($this, 'check_permissions'),
            )
        ));
        
        // Document generation endpoints
        register_rest_route($this->namespace, '/generate/draft', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'generate_draft'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'template_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => __('Template ID', 'klage-click-doc-out'),
                ),
                'case_id' => array(
                    'type' => 'integer',
                    'description' => __('Case ID for data integration', 'klage-click-doc-out'),
                ),
                'data' => array(
                    'type' => 'object',
                    'description' => __('Additional data for template', 'klage-click-doc-out'),
                ),
            )
        ));
        
        register_rest_route($this->namespace, '/generate/pdf', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'generate_pdf'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'draft_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => __('Draft ID', 'klage-click-doc-out'),
                ),
                'filename' => array(
                    'type' => 'string',
                    'description' => __('PDF filename', 'klage-click-doc-out'),
                ),
            )
        ));
        
        // Case integration endpoints
        register_rest_route($this->namespace, '/cases', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_cases'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'search' => array(
                    'type' => 'string',
                    'description' => __('Search cases', 'klage-click-doc-out'),
                ),
                'status' => array(
                    'type' => 'string',
                    'description' => __('Filter by case status', 'klage-click-doc-out'),
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ),
            )
        ));
        
        register_rest_route($this->namespace, '/cases/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_case'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Plugin status endpoint
        register_rest_route($this->namespace, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_status'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }
    
    /**
     * Check API permissions
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get templates
     */
    public function get_templates($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->template_manager) {
            return new WP_Error('service_unavailable', __('Template manager not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $args = array(
            'category' => $request->get_param('category'),
            'search' => $request->get_param('search'),
            'per_page' => $request->get_param('per_page'),
            'page' => $request->get_param('page')
        );
        
        $templates = $klage_click_doc_out->template_manager->get_templates($args);
        $total = $klage_click_doc_out->template_manager->get_templates_count($args);
        
        $response = new WP_REST_Response($templates);
        $response->header('X-Total-Count', $total);
        
        return $response;
    }
    
    /**
     * Get single template
     */
    public function get_template($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->template_manager) {
            return new WP_Error('service_unavailable', __('Template manager not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $template_id = (int) $request['id'];
        $template = $klage_click_doc_out->template_manager->get_template($template_id);
        
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'), array('status' => 404));
        }
        
        return $template;
    }
    
    /**
     * Create template
     */
    public function create_template($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->template_manager) {
            return new WP_Error('service_unavailable', __('Template manager not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $template_data = array(
            'template_name' => $request->get_param('template_name'),
            'template_description' => $request->get_param('template_description'),
            'template_category' => $request->get_param('template_category'),
            'template_html' => $request->get_param('template_html')
        );
        
        $result = $klage_click_doc_out->template_manager->create_template($template_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $template = $klage_click_doc_out->template_manager->get_template($result);
        
        return new WP_REST_Response($template, 201);
    }
    
    /**
     * Update template
     */
    public function update_template($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->template_manager) {
            return new WP_Error('service_unavailable', __('Template manager not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $template_id = (int) $request['id'];
        
        // Check if template exists
        $existing_template = $klage_click_doc_out->template_manager->get_template($template_id);
        if (!$existing_template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'), array('status' => 404));
        }
        
        $template_data = array();
        $params = array('template_name', 'template_description', 'template_category', 'template_html');
        
        foreach ($params as $param) {
            if ($request->has_param($param)) {
                $template_data[$param] = $request->get_param($param);
            }
        }
        
        $result = $klage_click_doc_out->template_manager->update_template($template_id, $template_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $template = $klage_click_doc_out->template_manager->get_template($template_id);
        
        return $template;
    }
    
    /**
     * Delete template
     */
    public function delete_template($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->template_manager) {
            return new WP_Error('service_unavailable', __('Template manager not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $template_id = (int) $request['id'];
        
        // Check if template exists
        $existing_template = $klage_click_doc_out->template_manager->get_template($template_id);
        if (!$existing_template) {
            return new WP_Error('template_not_found', __('Template not found.', 'klage-click-doc-out'), array('status' => 404));
        }
        
        $result = $klage_click_doc_out->template_manager->delete_template($template_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response(null, 204);
    }
    
    /**
     * Generate document draft
     */
    public function generate_draft($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->document_generator) {
            return new WP_Error('service_unavailable', __('Document generator not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $template_id = $request->get_param('template_id');
        $case_id = $request->get_param('case_id');
        $additional_data = $request->get_param('data') ?: array();
        
        $result = $klage_click_doc_out->document_generator->generate_document_draft($template_id, $case_id, $additional_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result, 201);
    }
    
    /**
     * Generate PDF
     */
    public function generate_pdf($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->document_generator) {
            return new WP_Error('service_unavailable', __('Document generator not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $draft_id = $request->get_param('draft_id');
        $filename = $request->get_param('filename');
        
        $options = array();
        if ($filename) {
            $options['filename'] = $filename;
        }
        
        $result = $klage_click_doc_out->document_generator->generate_final_pdf($draft_id, $options);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Return download URL
        $download_url = add_query_arg(array(
            'klage_doc_download' => '1',
            'draft_id' => $draft_id,
            'nonce' => wp_create_nonce('klage_doc_download')
        ), admin_url());
        
        return array(
            'success' => true,
            'download_url' => $download_url,
            'filename' => basename($result)
        );
    }
    
    /**
     * Get cases
     */
    public function get_cases($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->core_integration) {
            return new WP_Error('service_unavailable', __('Core integration not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $args = array(
            'search' => $request->get_param('search'),
            'status' => $request->get_param('status'),
            'per_page' => $request->get_param('per_page')
        );
        
        $cases = $klage_click_doc_out->core_integration->get_available_cases($args);
        
        return $cases;
    }
    
    /**
     * Get single case
     */
    public function get_case($request) {
        global $klage_click_doc_out;
        
        if (!$klage_click_doc_out || !$klage_click_doc_out->core_integration) {
            return new WP_Error('service_unavailable', __('Core integration not available.', 'klage-click-doc-out'), array('status' => 503));
        }
        
        $case_id = (int) $request['id'];
        $case_data = $klage_click_doc_out->core_integration->get_case_data($case_id);
        
        if (is_wp_error($case_data)) {
            return $case_data;
        }
        
        return $case_data;
    }
    
    /**
     * Get plugin status
     */
    public function get_status($request) {
        global $klage_click_doc_out;
        
        $status = array(
            'plugin_version' => KCDO_PLUGIN_VERSION,
            'core_integration' => class_exists('CourtAutomationHub'),
            'financial_integration' => class_exists('CAH_Financial_Calculator_Plugin'),
            'services' => array(
                'template_manager' => !empty($klage_click_doc_out->template_manager),
                'document_generator' => !empty($klage_click_doc_out->document_generator),
                'pdf_engine' => !empty($klage_click_doc_out->pdf_engine),
                's3_storage' => !empty($klage_click_doc_out->s3_storage),
                'core_integration' => !empty($klage_click_doc_out->core_integration)
            )
        );
        
        // PDF engine status
        if ($klage_click_doc_out && $klage_click_doc_out->pdf_engine) {
            $status['pdf_engine_status'] = $klage_click_doc_out->pdf_engine->get_engine_status();
        }
        
        // S3 storage status
        if ($klage_click_doc_out && $klage_click_doc_out->s3_storage) {
            $status['s3_storage_status'] = $klage_click_doc_out->s3_storage->get_storage_stats();
        }
        
        // Template statistics
        if ($klage_click_doc_out && $klage_click_doc_out->template_manager) {
            $status['template_stats'] = array(
                'total_templates' => $klage_click_doc_out->template_manager->get_templates_count(),
                'categories' => $klage_click_doc_out->template_manager->get_template_categories()
            );
        }
        
        return $status;
    }
}