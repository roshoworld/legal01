<?php
/**
 * CRM Core Integration
 * Handles integration with the core Court Automation Hub plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_Core_Integration {
    
    public function __construct() {
        // Add integration hooks
        add_action('init', array($this, 'init_integration'));
    }
    
    /**
     * Initialize integration with core plugin
     */
    public function init_integration() {
        // Check if core plugin is available
        if (!class_exists('CourtAutomationHub')) {
            return;
        }
        
        // Add CRM data to case views
        add_filter('cah_case_data', array($this, 'add_crm_data_to_case'));
        
        // Add CRM fields to contact forms
        add_action('cah_contact_form_fields', array($this, 'add_crm_contact_fields'));
        
        // Hook into case creation to create CRM records
        add_action('cah_case_created', array($this, 'handle_case_created'), 10, 2);
        
        // Hook into contact updates
        add_action('cah_contact_updated', array($this, 'handle_contact_updated'), 10, 2);
    }
    
    /**
     * Add CRM data to case information
     */
    public function add_crm_data_to_case($case_data) {
        if (!isset($case_data['id'])) {
            return $case_data;
        }
        
        $case_id = $case_data['id'];
        
        // Add communication count
        $communication_manager = new LA_CRM_Communication_Manager();
        $communications = $communication_manager->get_case_communications($case_id, 5);
        $case_data['crm_recent_communications'] = $communications;
        $case_data['crm_communication_count'] = count($communications);
        
        // Add event count
        $event_manager = new LA_CRM_Event_Manager();
        $events = $event_manager->get_case_events($case_id, 5);
        $case_data['crm_recent_events'] = $events;
        $case_data['crm_event_count'] = count($events);
        
        return $case_data;
    }
    
    /**
     * Add CRM-specific fields to contact forms
     */
    public function add_crm_contact_fields($contact_id = null) {
        $communication_preferences = '';
        $event_preferences = '';
        
        if ($contact_id) {
            // Load existing CRM data for contact
            $crm_data = $this->get_contact_crm_preferences($contact_id);
            $communication_preferences = $crm_data['communication_preferences'] ?? '';
            $event_preferences = $crm_data['event_preferences'] ?? '';
        }
        
        ?>
        <div class="crm-fields-section">
            <h3><?php _e('CRM Einstellungen', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="communication_preferences"><?php _e('Kommunikationseinstellungen', LA_CRM_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <textarea 
                            name="communication_preferences" 
                            id="communication_preferences" 
                            rows="3" 
                            cols="50"
                            placeholder="<?php _e('Bevorzugte Kommunikationszeiten, -kanäle, etc.', LA_CRM_TEXT_DOMAIN); ?>"
                        ><?php echo esc_textarea($communication_preferences); ?></textarea>
                        <p class="description"><?php _e('Notizen zu Kommunikationsvorlieben des Kontakts', LA_CRM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="event_preferences"><?php _e('Termineinstellungen', LA_CRM_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <textarea 
                            name="event_preferences" 
                            id="event_preferences" 
                            rows="3" 
                            cols="50"
                            placeholder="<?php _e('Bevorzugte Terminzeiten, Reminder-Einstellungen, etc.', LA_CRM_TEXT_DOMAIN); ?>"
                        ><?php echo esc_textarea($event_preferences); ?></textarea>
                        <p class="description"><?php _e('Notizen zu Terminvorlieben des Kontakts', LA_CRM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Handle new case creation
     */
    public function handle_case_created($case_id, $case_data) {
        // Automatically create follow-up events for new cases
        if (isset($case_data['contact_id'])) {
            $event_manager = new LA_CRM_Event_Manager();
            
            // Create initial follow-up event (7 days from now)
            $follow_up_date = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            $event_data = array(
                'contact_id' => $case_data['contact_id'],
                'case_id' => $case_id,
                'event_type' => 'follow_up',
                'title' => sprintf(__('Nachverfolgung Fall: %s', LA_CRM_TEXT_DOMAIN), $case_data['case_id'] ?? ''),
                'description' => __('Automatisch erstellter Nachverfolgungstermin für neuen Fall', LA_CRM_TEXT_DOMAIN),
                'start_datetime' => $follow_up_date,
                'status' => 'scheduled',
                'priority' => 'normal'
            );
            
            $event_manager->create_event($event_data);
        }
    }
    
    /**
     * Handle contact updates
     */
    public function handle_contact_updated($contact_id, $updated_data) {
        // Save CRM-specific preferences if present
        if (isset($updated_data['communication_preferences']) || isset($updated_data['event_preferences'])) {
            $this->save_contact_crm_preferences($contact_id, $updated_data);
        }
    }
    
    /**
     * Get CRM preferences for a contact
     */
    private function get_contact_crm_preferences($contact_id) {
        $preferences = get_user_meta($contact_id, 'crm_preferences', true);
        
        if (!is_array($preferences)) {
            $preferences = array(
                'communication_preferences' => '',
                'event_preferences' => ''
            );
        }
        
        return $preferences;
    }
    
    /**
     * Save CRM preferences for a contact
     */
    private function save_contact_crm_preferences($contact_id, $preferences) {
        $existing_preferences = $this->get_contact_crm_preferences($contact_id);
        
        $updated_preferences = array_merge($existing_preferences, array_filter(array(
            'communication_preferences' => $preferences['communication_preferences'] ?? '',
            'event_preferences' => $preferences['event_preferences'] ?? ''
        )));
        
        update_user_meta($contact_id, 'crm_preferences', $updated_preferences);
        
        // Log the update
        $db_manager = new LA_CRM_Database();
        $db_manager->log_audit(
            'contact_crm_preferences_updated',
            'CRM preferences updated for contact',
            $contact_id
        );
    }
    
    /**
     * Get contact summary with CRM data
     */
    public function get_contact_summary($contact_id) {
        $communication_manager = new LA_CRM_Communication_Manager();
        $event_manager = new LA_CRM_Event_Manager();
        
        $comm_stats = $communication_manager->get_contact_stats($contact_id);
        $event_stats = $event_manager->get_contact_stats($contact_id);
        
        return array(
            'communication_stats' => $comm_stats,
            'event_stats' => $event_stats,
            'last_activity' => $this->get_last_activity($contact_id),
            'preferences' => $this->get_contact_crm_preferences($contact_id)
        );
    }
    
    /**
     * Get last activity for a contact
     */
    private function get_last_activity($contact_id) {
        global $wpdb;
        
        // Get last communication
        $last_comm = $wpdb->get_row($wpdb->prepare("
            SELECT created_at, 'communication' as type, subject as title
            FROM {$wpdb->prefix}klage_crm_communications
            WHERE contact_id = %d AND active_status = 1
            ORDER BY created_at DESC
            LIMIT 1
        ", $contact_id));
        
        // Get last event
        $last_event = $wpdb->get_row($wpdb->prepare("
            SELECT created_at, 'event' as type, title
            FROM {$wpdb->prefix}klage_crm_events
            WHERE contact_id = %d AND active_status = 1
            ORDER BY created_at DESC
            LIMIT 1
        ", $contact_id));
        
        // Return the most recent activity
        if (!$last_comm && !$last_event) {
            return null;
        }
        
        if (!$last_comm) {
            return $last_event;
        }
        
        if (!$last_event) {
            return $last_comm;
        }
        
        return (strtotime($last_comm->created_at) > strtotime($last_event->created_at)) ? $last_comm : $last_event;
    }
    
    /**
     * Export contact data including CRM information
     */
    public function export_contact_data($contact_id) {
        $db_manager = new LA_CRM_Database();
        $contact = $db_manager->get_contact_with_cases($contact_id);
        
        if (!$contact) {
            return false;
        }
        
        $communication_manager = new LA_CRM_Communication_Manager();
        $event_manager = new LA_CRM_Event_Manager();
        
        return array(
            'contact' => $contact[0] ?? null,
            'communications' => $communication_manager->get_contact_communications($contact_id, 999),
            'events' => $event_manager->get_contact_events($contact_id, 999),
            'summary' => $this->get_contact_summary($contact_id),
            'exported_at' => current_time('mysql')
        );
    }
}