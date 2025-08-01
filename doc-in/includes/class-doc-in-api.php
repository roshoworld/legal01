<?php
/**
 * REST API Endpoints for Pipedream Integration
 * Handles all API endpoints for document analysis workflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Document_in_API {
    
    private $db_manager;
    private $case_matcher;
    private $communications;
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        
        // Initialize dependencies
        $this->db_manager = new CAH_Document_in_DB_Manager();
        $this->case_matcher = new CAH_Document_in_Case_Matcher();
        $this->communications = new CAH_Document_in_Communications();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'cah-doc-in/v1';
        
        // GET /wp-json/cah-doc-in/v1/categories
        register_rest_route($namespace, '/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_categories'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
        
        // GET /wp-json/cah-doc-in/v1/case-lookup
        register_rest_route($namespace, '/case-lookup', array(
            'methods' => 'GET',
            'callback' => array($this, 'case_lookup'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'caseNumber' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ),
                'debtorName' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                )
            )
        ));
        
        // POST /wp-json/cah-doc-in/v1/communications
        register_rest_route($namespace, '/communications', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_communication'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'caseNumber' => array('required' => false),
                'debtorName' => array('required' => false),
                'emailMetadata' => array('required' => true),
                'analysis' => array('required' => true),
                'pipedream_execution_id' => array('required' => false)
            )
        ));
        
        // POST /wp-json/cah-doc-in/v1/attach-media
        register_rest_route($namespace, '/attach-media', array(
            'methods' => 'POST',
            'callback' => array($this, 'attach_media'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
        
        // GET /wp-json/cah-doc-in/v1/communication/(?P<id>\d+)
        register_rest_route($namespace, '/communication/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_communication'),
            'permission_callback' => array($this, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }
    
    /**
     * Check API permissions - using Application Passwords or custom auth
     */
    public function check_api_permissions($request) {
        // For now, allow any authenticated user
        // In production, implement specific API key validation
        return current_user_can('manage_options');
    }
    
    /**
     * GET /wp-json/cah-doc-in/v1/categories
     * Returns active communication categories for Pipedream with German descriptions
     */
    public function get_categories($request) {
        $start_time = microtime(true);
        
        try {
            // Get categories from taxonomy
            $terms = get_terms(array(
                'taxonomy' => 'communication_category',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ));
            
            if (is_wp_error($terms)) {
                throw new Exception('Failed to retrieve categories: ' . $terms->get_error_message());
            }
            
            $categories = array();
            $categories_detailed = array();
            
            // Get stored German descriptions
            $category_descriptions = get_option('cah_doc_in_category_descriptions', array());
            
            foreach ($terms as $term) {
                // For backwards compatibility - simple array of names
                $categories[] = $term->name;
                
                // Detailed format with German descriptions for enhanced Pipedream integration
                $category_detail = array(
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'description_en' => $term->name,
                    'description_de' => !empty($term->description) ? $term->description : $term->name,
                    'count' => $term->count
                );
                
                // Add stored description if available
                if (isset($category_descriptions[$term->slug])) {
                    $category_detail['description_de'] = $category_descriptions[$term->slug]['description_de'];
                }
                
                $categories_detailed[] = $category_detail;
            }
            
            // If no categories found, return GDPR spam categories as defaults
            if (empty($categories)) {
                $default_categories = array(
                    'general_inquiry_request_for_clarification',
                    'consent_express_claimed',
                    'legal_no_gdpr_violation_claimed',
                    'technical_email_not_sent_by_them',
                    'process_cease_and_desist_provided',
                    'general_uncategorized_other'
                );
                
                $categories = $default_categories;
                
                foreach ($default_categories as $cat) {
                    $categories_detailed[] = array(
                        'slug' => $cat,
                        'name' => ucwords(str_replace('_', ' ', $cat)),
                        'description_en' => ucwords(str_replace('_', ' ', $cat)),
                        'description_de' => ucwords(str_replace('_', ' ', $cat)),
                        'count' => 0
                    );
                }
            }
            
            $response = array(
                'success' => true,
                'categories' => $categories, // Simple format for backwards compatibility
                'categories_detailed' => $categories_detailed, // Enhanced format with German descriptions
                'total_count' => count($categories),
                'timestamp' => current_time('c'),
                'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
                'api_version' => '1.1.0' // Indicate enhanced API
            );
            
            // Log audit
            $this->db_manager->log_audit(array(
                'action_type' => 'api_categories_fetch',
                'action_details' => 'Enhanced categories fetched for Pipedream: ' . count($categories) . ' categories with German descriptions',
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 200
            ));
            
            return new WP_REST_Response($response, 200);
            
        } catch (Exception $e) {
            // Log error
            $this->db_manager->log_audit(array(
                'action_type' => 'api_categories_fetch',
                'action_details' => 'Categories fetch failed',
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 500,
                'error_message' => $e->getMessage()
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('c')
            ), 500);
        }
    }
    
    /**
     * GET /wp-json/cah-doc-in/v1/case-lookup
     * Looks up existing cases by case number OR debtor name
     */
    public function case_lookup($request) {
        $start_time = microtime(true);
        $case_number = $request->get_param('caseNumber');
        $debtor_name = $request->get_param('debtorName');
        
        try {
            if (empty($case_number) && empty($debtor_name)) {
                throw new Exception('Either caseNumber or debtorName parameter is required');
            }
            
            $search_type = !empty($case_number) ? 'case_number' : 'debtor_name';
            $search_value = !empty($case_number) ? $case_number : $debtor_name;
            
            // Use case matcher to find matches
            if ($search_type === 'case_number') {
                $matches = $this->case_matcher->find_by_case_number($case_number);
            } else {
                $matches = $this->case_matcher->find_by_debtor_name($debtor_name);
            }
            
            $response = array(
                'success' => true,
                'search_type' => $search_type,
                'search_value' => $search_value,
                'timestamp' => current_time('c'),
                'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
            );
            
            if (!empty($matches)) {
                $best_match = $matches[0]; // Highest confidence match
                $response['match_found'] = true;
                $response['case_id'] = $best_match['case_id'];
                $response['confidence'] = $best_match['confidence'];
                $response['case_number'] = $best_match['case_number'];
                $response['debtor_name'] = $best_match['debtor_name'];
            } else {
                $response['match_found'] = false;
                $response['case_id'] = null;
                $response['confidence'] = 0;
            }
            
            // Log audit
            $this->db_manager->log_audit(array(
                'action_type' => 'api_case_lookup',
                'action_details' => "Case lookup: {$search_type}={$search_value}, match_found=" . ($response['match_found'] ? 'true' : 'false'),
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 200
            ));
            
            return new WP_REST_Response($response, 200);
            
        } catch (Exception $e) {
            // Log error
            $this->db_manager->log_audit(array(
                'action_type' => 'api_case_lookup',
                'action_details' => 'Case lookup failed',
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 500,
                'error_message' => $e->getMessage()
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('c')
            ), 500);
        }
    }
    
    /**
     * POST /wp-json/cah-doc-in/v1/communications
     * Creates new communication record from Pipedream data
     */
    public function create_communication($request) {
        $start_time = microtime(true);
        
        try {
            $data = $request->get_json_params();
            
            // Validate required data
            if (empty($data['emailMetadata']) || empty($data['analysis'])) {
                throw new Exception('Missing required fields: emailMetadata and analysis are required');
            }
            
            // Create WordPress post
            $post_id = $this->communications->create_communication_from_pipedream($data);
            
            if (!$post_id) {
                throw new Exception('Failed to create communication post');
            }
            
            // Prepare database record data
            $db_data = array(
                'post_id' => $post_id,
                'case_number' => $data['caseNumber'] ?? null,
                'debtor_name' => $data['debtorName'] ?? null,
                'email_subject' => $data['emailMetadata']['subject'] ?? '',
                'email_sender' => $data['emailMetadata']['sender'] ?? '',
                'email_received_date' => $data['emailMetadata']['receivedDate'] ?? current_time('mysql'),
                'has_attachment' => $data['emailMetadata']['hasAttachment'] ?? false,
                'message_id' => $data['emailMetadata']['messageId'] ?? '',
                'summary' => $data['analysis']['summary'] ?? '',
                'category' => $data['analysis']['category'] ?? '',
                'is_new_case' => $data['analysis']['isNewCase'] ?? '',
                'confidence_score' => $data['analysis']['confidenceScore'] ?? null,
                'extracted_entities' => is_array($data['analysis']['extractedEntities'] ?? []) ? 
                    implode(', ', $data['analysis']['extractedEntities']) : 
                    ($data['analysis']['extractedEntities'] ?? ''),
                'pipedream_execution_id' => $data['pipedream_execution_id'] ?? '',
                'assignment_status' => 'unassigned'
            );
            
            // Check if case lookup result was included
            if (isset($data['case_lookup_result'])) {
                if ($data['case_lookup_result']['match_found']) {
                    $db_data['matched_case_id'] = $data['case_lookup_result']['case_id'];
                    $db_data['match_confidence'] = $data['case_lookup_result']['confidence'];
                    $db_data['assignment_status'] = 'assigned';
                    
                    // Update post meta as well
                    update_post_meta($post_id, '_matched_case_id', $data['case_lookup_result']['case_id']);
                    update_post_meta($post_id, '_match_confidence', $data['case_lookup_result']['confidence']);
                    update_post_meta($post_id, '_assignment_status', 'assigned');
                }
            }
            
            // Insert database record
            $communication_id = $this->db_manager->insert_communication($db_data);
            
            if (!$communication_id) {
                throw new Exception('Failed to create database record');
            }
            
            $response = array(
                'success' => true,
                'communication_id' => $communication_id,
                'post_id' => $post_id,
                'assignment_status' => $db_data['assignment_status'],
                'wordpress_admin_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'timestamp' => current_time('c'),
                'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
            );
            
            // Log audit
            $this->db_manager->log_audit(array(
                'communication_id' => $communication_id,
                'action_type' => 'api_communication_created',
                'action_details' => "Communication created from Pipedream: {$data['emailMetadata']['sender']} - {$data['analysis']['category']}",
                'pipedream_execution_id' => $data['pipedream_execution_id'] ?? '',
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 201
            ));
            
            return new WP_REST_Response($response, 201);
            
        } catch (Exception $e) {
            // Log error
            $this->db_manager->log_audit(array(
                'action_type' => 'api_communication_create',
                'action_details' => 'Communication creation failed',
                'pipedream_execution_id' => $data['pipedream_execution_id'] ?? '',
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 500,
                'error_message' => $e->getMessage()
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('c')
            ), 500);
        }
    }
    
    /**
     * POST /wp-json/cah-doc-in/v1/attach-media
     * Handles file upload from Pipedream
     */
    public function attach_media($request) {
        $start_time = microtime(true);
        
        try {
            $communication_id = $request->get_param('communication_id');
            
            if (empty($communication_id)) {
                throw new Exception('communication_id parameter is required');
            }
            
            // Check if communication exists
            $communication = $this->db_manager->get_communication($communication_id);
            if (!$communication) {
                throw new Exception('Communication not found');
            }
            
            // Handle file upload
            if (empty($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }
            
            $uploaded_file = $_FILES['file'];
            
            // WordPress media upload
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            $attachment_id = media_handle_upload('file', $communication->post_id);
            
            if (is_wp_error($attachment_id)) {
                throw new Exception('File upload failed: ' . $attachment_id->get_error_message());
            }
            
            // Get attachment details
            $attachment_url = wp_get_attachment_url($attachment_id);
            $attachment_meta = wp_get_attachment_metadata($attachment_id);
            $file_size = filesize(get_attached_file($attachment_id));
            
            // Insert attachment record
            $attachment_data = array(
                'communication_id' => $communication_id,
                'wp_attachment_id' => $attachment_id,
                'original_filename' => $uploaded_file['name'],
                'file_size' => $file_size,
                'file_type' => $uploaded_file['type'],
                'attachment_url' => $attachment_url,
                'upload_status' => 'completed'
            );
            
            $attachment_record_id = $this->db_manager->insert_attachment($attachment_data);
            
            // Update communication post meta
            update_post_meta($communication->post_id, '_attachment_link', $attachment_url);
            update_post_meta($communication->post_id, '_has_attachment', 1);
            
            $response = array(
                'success' => true,
                'attachment_id' => $attachment_id,
                'attachment_url' => $attachment_url,
                'file_size' => $file_size,
                'file_type' => $uploaded_file['type'],
                'timestamp' => current_time('c'),
                'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
            );
            
            // Log audit
            $this->db_manager->log_audit(array(
                'communication_id' => $communication_id,
                'action_type' => 'api_attachment_upload',
                'action_details' => "File uploaded: {$uploaded_file['name']} ({$file_size} bytes)",
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 201
            ));
            
            return new WP_REST_Response($response, 201);
            
        } catch (Exception $e) {
            // Log error
            $this->db_manager->log_audit(array(
                'communication_id' => $communication_id ?? null,
                'action_type' => 'api_attachment_upload',
                'action_details' => 'Attachment upload failed',
                'execution_time' => microtime(true) - $start_time,
                'api_response_code' => 500,
                'error_message' => $e->getMessage()
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('c')
            ), 500);
        }
    }
    
    /**
     * GET /wp-json/cah-doc-in/v1/communication/{id}
     * Get communication details
     */
    public function get_communication($request) {
        $start_time = microtime(true);
        $communication_id = $request->get_param('id');
        
        try {
            $communication = $this->db_manager->get_communication($communication_id);
            
            if (!$communication) {
                throw new Exception('Communication not found');
            }
            
            // Get attachments
            $attachments = $this->db_manager->get_communication_attachments($communication_id);
            
            $response = array(
                'success' => true,
                'communication' => $communication,
                'attachments' => $attachments,
                'wordpress_admin_url' => admin_url('post.php?post=' . $communication->post_id . '&action=edit'),
                'timestamp' => current_time('c'),
                'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
            );
            
            return new WP_REST_Response($response, 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('c')
            ), 404);
        }
    }
}