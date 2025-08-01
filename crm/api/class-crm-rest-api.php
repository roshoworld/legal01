<?php
/**
 * CRM REST API
 * Provides REST API endpoints for CRM functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_REST_API {
    
    private $namespace = 'la-crm/v1';
    private $communication_manager;
    private $event_manager;
    private $audience_manager;
    private $db_manager;
    
    public function __construct() {
        $this->communication_manager = new LA_CRM_Communication_Manager();
        $this->event_manager = new LA_CRM_Event_Manager();
        $this->audience_manager = new LA_CRM_Audience_Manager();
        $this->db_manager = new LA_CRM_Database();
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Communications endpoints
        register_rest_route($this->namespace, '/communications', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_communications'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_communication'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));
        
        register_rest_route($this->namespace, '/communications/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_communication'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_communication'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_communication'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));
        
        // Events endpoints
        register_rest_route($this->namespace, '/events', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_events'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_event'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));
        
        register_rest_route($this->namespace, '/events/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_event'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_event'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_event'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));
        
        // Audiences endpoints
        register_rest_route($this->namespace, '/audiences', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_audiences'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_audience'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));
        
        register_rest_route($this->namespace, '/audiences/(?P<id>\d+)/contacts', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_audience_contacts'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Templates endpoints
        register_rest_route($this->namespace, '/templates', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_templates'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/templates/(?P<id>\d+)/apply', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'apply_template'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Dashboard/Stats endpoints
        register_rest_route($this->namespace, '/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_dashboard_data'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/contacts/(?P<id>\d+)/summary', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_contact_summary'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Check permissions for API access
     */
    public function check_permissions($request) {
        return current_user_can('manage_klage_click_cases');
    }
    
    /**
     * Get communications
     */
    public function get_communications($request) {
        $contact_id = $request->get_param('contact_id');
        $case_id = $request->get_param('case_id');
        $limit = $request->get_param('limit') ?: 50;
        
        if ($contact_id) {
            $communications = $this->communication_manager->get_contact_communications($contact_id, $limit);
        } elseif ($case_id) {
            $communications = $this->communication_manager->get_case_communications($case_id, $limit);
        } else {
            return new WP_Error('missing_parameter', __('contact_id oder case_id erforderlich', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        return rest_ensure_response($communications);
    }
    
    /**
     * Get single communication
     */
    public function get_communication($request) {
        $id = $request['id'];
        $communication = $this->communication_manager->get_communication($id);
        
        if (!$communication) {
            return new WP_Error('communication_not_found', __('Kommunikation nicht gefunden', LA_CRM_TEXT_DOMAIN), array('status' => 404));
        }
        
        return rest_ensure_response($communication);
    }
    
    /**
     * Create communication
     */
    public function create_communication($request) {
        $data = array(
            'contact_id' => $request->get_param('contact_id'),
            'case_id' => $request->get_param('case_id'),
            'communication_type' => $request->get_param('communication_type'),
            'direction' => $request->get_param('direction') ?: 'outbound',
            'subject' => $request->get_param('subject'),
            'content' => $request->get_param('content'),
            'priority' => $request->get_param('priority') ?: 'normal',
            'status' => $request->get_param('status') ?: 'draft'
        );
        
        // Validate required fields
        if (empty($data['contact_id']) || empty($data['communication_type'])) {
            return new WP_Error('missing_required_fields', __('Erforderliche Felder fehlen', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        $result = $this->communication_manager->create_communication($data);
        
        if ($result) {
            $communication = $this->communication_manager->get_communication($result);
            return rest_ensure_response($communication);
        } else {
            return new WP_Error('creation_failed', __('Kommunikation konnte nicht erstellt werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Update communication
     */
    public function update_communication($request) {
        $id = $request['id'];
        $status = $request->get_param('status');
        
        if (!$status) {
            return new WP_Error('missing_status', __('Status erforderlich', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        $result = $this->communication_manager->update_status($id, $status);
        
        if ($result) {
            $communication = $this->communication_manager->get_communication($id);
            return rest_ensure_response($communication);
        } else {
            return new WP_Error('update_failed', __('Status konnte nicht aktualisiert werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Delete communication
     */
    public function delete_communication($request) {
        $id = $request['id'];
        $result = $this->communication_manager->delete_communication($id);
        
        if ($result) {
            return rest_ensure_response(array('deleted' => true));
        } else {
            return new WP_Error('deletion_failed', __('Kommunikation konnte nicht gelöscht werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Get events
     */
    public function get_events($request) {
        $contact_id = $request->get_param('contact_id');
        $case_id = $request->get_param('case_id');
        $upcoming = $request->get_param('upcoming');
        $limit = $request->get_param('limit') ?: 50;
        
        if ($upcoming) {
            $events = $this->event_manager->get_upcoming_events(30, $limit);
        } elseif ($contact_id) {
            $events = $this->event_manager->get_contact_events($contact_id, $limit);
        } elseif ($case_id) {
            $events = $this->event_manager->get_case_events($case_id, $limit);
        } else {
            return new WP_Error('missing_parameter', __('Parameter erforderlich', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        return rest_ensure_response($events);
    }
    
    /**
     * Get single event
     */
    public function get_event($request) {
        $id = $request['id'];
        $event = $this->event_manager->get_event($id);
        
        if (!$event) {
            return new WP_Error('event_not_found', __('Ereignis nicht gefunden', LA_CRM_TEXT_DOMAIN), array('status' => 404));
        }
        
        return rest_ensure_response($event);
    }
    
    /**
     * Create event
     */
    public function create_event($request) {
        $data = array(
            'contact_id' => $request->get_param('contact_id'),
            'case_id' => $request->get_param('case_id'),
            'event_type' => $request->get_param('event_type'),
            'title' => $request->get_param('title'),
            'description' => $request->get_param('description'),
            'start_datetime' => $request->get_param('start_datetime'),
            'end_datetime' => $request->get_param('end_datetime'),
            'location' => $request->get_param('location'),
            'priority' => $request->get_param('priority') ?: 'normal',
            'status' => $request->get_param('status') ?: 'scheduled'
        );
        
        // Validate required fields
        if (empty($data['contact_id']) || empty($data['title']) || empty($data['start_datetime'])) {
            return new WP_Error('missing_required_fields', __('Erforderliche Felder fehlen', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        $result = $this->event_manager->create_event($data);
        
        if ($result) {
            $event = $this->event_manager->get_event($result);
            return rest_ensure_response($event);
        } else {
            return new WP_Error('creation_failed', __('Ereignis konnte nicht erstellt werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Update event
     */
    public function update_event($request) {
        $id = $request['id'];
        $status = $request->get_param('status');
        $outcome = $request->get_param('outcome');
        
        if (!$status) {
            return new WP_Error('missing_status', __('Status erforderlich', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        $result = $this->event_manager->update_status($id, $status, $outcome);
        
        if ($result) {
            $event = $this->event_manager->get_event($id);
            return rest_ensure_response($event);
        } else {
            return new WP_Error('update_failed', __('Status konnte nicht aktualisiert werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Delete event
     */
    public function delete_event($request) {
        $id = $request['id'];
        $result = $this->event_manager->delete_event($id);
        
        if ($result) {
            return rest_ensure_response(array('deleted' => true));
        } else {
            return new WP_Error('deletion_failed', __('Ereignis konnte nicht gelöscht werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Get audiences
     */
    public function get_audiences($request) {
        $audiences = $this->audience_manager->get_audiences();
        return rest_ensure_response($audiences);
    }
    
    /**
     * Create audience
     */
    public function create_audience($request) {
        $data = array(
            'audience_name' => $request->get_param('audience_name'),
            'description' => $request->get_param('description'),
            'criteria' => $request->get_param('criteria')
        );
        
        // Validate required fields
        if (empty($data['audience_name']) || empty($data['criteria'])) {
            return new WP_Error('missing_required_fields', __('Name und Kriterien erforderlich', LA_CRM_TEXT_DOMAIN), array('status' => 400));
        }
        
        $result = $this->audience_manager->create_audience($data);
        
        if ($result) {
            $audience = $this->audience_manager->get_audience($result);
            return rest_ensure_response($audience);
        } else {
            return new WP_Error('creation_failed', __('Zielgruppe konnte nicht erstellt werden', LA_CRM_TEXT_DOMAIN), array('status' => 500));
        }
    }
    
    /**
     * Get audience contacts
     */
    public function get_audience_contacts($request) {
        $id = $request['id'];
        $contacts = $this->audience_manager->get_audience_contacts($id);
        
        return rest_ensure_response(array(
            'audience_id' => $id,
            'contacts' => $contacts,
            'count' => count($contacts)
        ));
    }
    
    /**
     * Get templates
     */
    public function get_templates($request) {
        $type = $request->get_param('type');
        $templates = $this->communication_manager->get_templates($type);
        
        return rest_ensure_response($templates);
    }
    
    /**
     * Apply template
     */
    public function apply_template($request) {
        $id = $request['id'];
        $placeholders = $request->get_param('placeholders') ?: array();
        
        $result = $this->communication_manager->apply_template($id, $placeholders);
        
        if ($result) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('template_not_found', __('Vorlage nicht gefunden', LA_CRM_TEXT_DOMAIN), array('status' => 404));
        }
    }
    
    /**
     * Get dashboard data
     */
    public function get_dashboard_data($request) {
        global $wpdb;
        
        // Get recent communications
        $recent_communications = $wpdb->get_results("
            SELECT COUNT(*) as count, DATE(created_at) as date
            FROM {$wpdb->prefix}klage_crm_communications
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND active_status = 1
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");
        
        // Get upcoming events
        $upcoming_events = $this->event_manager->get_upcoming_events(7, 10);
        
        // Get overdue events
        $overdue_events = $this->event_manager->get_overdue_events(10);
        
        // Get communication stats
        $comm_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_communications,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count
            FROM {$wpdb->prefix}klage_crm_communications
            WHERE active_status = 1
        ");
        
        // Get event stats
        $event_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as upcoming_count
            FROM {$wpdb->prefix}klage_crm_events
            WHERE active_status = 1
        ");
        
        return rest_ensure_response(array(
            'recent_communications' => $recent_communications,
            'upcoming_events' => $upcoming_events,
            'overdue_events' => $overdue_events,
            'communication_stats' => $comm_stats,
            'event_stats' => $event_stats
        ));
    }
    
    /**
     * Get contact summary
     */
    public function get_contact_summary($request) {
        $id = $request['id'];
        
        $core_integration = new LA_CRM_Core_Integration();
        $summary = $core_integration->get_contact_summary($id);
        
        return rest_ensure_response($summary);
    }
}