<?php
/**
 * CRM Admin Interface
 * Handles admin interface integration with core plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_Admin {
    
    private $communication_manager;
    private $event_manager;
    private $audience_manager;
    
    public function __construct() {
        $this->communication_manager = new LA_CRM_Communication_Manager();
        $this->event_manager = new LA_CRM_Event_Manager();
        $this->audience_manager = new LA_CRM_Audience_Manager();
        
        // Add AJAX handlers
        add_action('wp_ajax_la_crm_create_communication', array($this, 'ajax_create_communication'));
        add_action('wp_ajax_la_crm_create_event', array($this, 'ajax_create_event'));
        add_action('wp_ajax_la_crm_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_la_crm_load_template', array($this, 'ajax_load_template'));
        add_action('wp_ajax_la_crm_get_audience_contacts', array($this, 'ajax_get_audience_contacts'));
    }
    
    /**
     * Render CRM tab contents for case view
     */
    public function render_tab_contents() {
        global $post;
        
        // Get current case ID from URL or post
        $case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
        
        ?>
        <!-- Communications Tab -->
        <div id="communications" class="case-tab-content">
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle">📧 Kommunikations-Historie</h2>
                <div class="inside" style="padding: 20px;">
                    <?php $this->render_communications_tab($case_id); ?>
                </div>
            </div>
        </div>
        
        <!-- Events Tab -->
        <div id="events" class="case-tab-content">
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle">📅 Termine & Ereignisse</h2>
                <div class="inside" style="padding: 20px;">
                    <?php $this->render_events_tab($case_id); ?>
                </div>
            </div>
        </div>
        
        <!-- Audience Tab -->
        <div id="audience" class="case-tab-content">
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle">🎯 Zielgruppen-Verwaltung</h2>
                <div class="inside" style="padding: 20px;">
                    <?php $this->render_audience_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render communications tab
     */
    private function render_communications_tab($case_id) {
        $communications = array();
        $contacts = array();
        
        if ($case_id) {
            $communications = $this->communication_manager->get_case_communications($case_id);
            
            // Get all contacts for this case
            $db_manager = new LA_CRM_Database();
            global $wpdb;
            $contacts = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT c.id, c.first_name, c.last_name, c.company_name, c.email
                FROM {$wpdb->prefix}klage_contacts c
                JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.contact_id
                WHERE cc.case_id = %d AND c.active_status = 1
            ", $case_id));
        }
        
        $templates = $this->communication_manager->get_templates('email');
        ?>
        
        <!-- Communication Form -->
        <div class="crm-communication-form" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h3><?php _e('Neue Kommunikation erstellen', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <form id="crm-communication-form">
                <input type="hidden" name="case_id" value="<?php echo esc_attr($case_id); ?>" />
                <input type="hidden" name="action" value="la_crm_create_communication" />
                <?php wp_nonce_field('la_crm_admin_nonce', 'nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="contact_id"><?php _e('Kontakt', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="contact_id" id="contact_id" required style="width: 100%;">
                                <option value=""><?php _e('Kontakt auswählen', LA_CRM_TEXT_DOMAIN); ?></option>
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?php echo esc_attr($contact->id); ?>">
                                        <?php echo esc_html($contact->first_name . ' ' . $contact->last_name); ?>
                                        <?php if ($contact->company_name): ?>
                                            - <?php echo esc_html($contact->company_name); ?>
                                        <?php endif; ?>
                                        (<?php echo esc_html($contact->email); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="communication_type"><?php _e('Typ', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="communication_type" id="communication_type" required>
                                <option value="email"><?php _e('E-Mail', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="phone"><?php _e('Telefon', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="meeting"><?php _e('Termin', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="letter"><?php _e('Brief', LA_CRM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_id"><?php _e('Vorlage', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="template_id" id="template_id">
                                <option value=""><?php _e('Keine Vorlage', LA_CRM_TEXT_DOMAIN); ?></option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo esc_attr($template->id); ?>">
                                        <?php echo esc_html($template->template_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="load-template-btn" class="button"><?php _e('Vorlage laden', LA_CRM_TEXT_DOMAIN); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="subject"><?php _e('Betreff', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="subject" id="subject" class="regular-text" style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="content"><?php _e('Inhalt', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <textarea name="content" id="content" rows="8" style="width: 100%;"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="priority"><?php _e('Priorität', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="priority" id="priority">
                                <option value="normal"><?php _e('Normal', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="high"><?php _e('Hoch', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="urgent"><?php _e('Dringend', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="low"><?php _e('Niedrig', LA_CRM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button-primary"><?php _e('Kommunikation erstellen', LA_CRM_TEXT_DOMAIN); ?></button>
                    <button type="button" id="schedule-btn" class="button"><?php _e('Zeitplanung', LA_CRM_TEXT_DOMAIN); ?></button>
                </p>
            </form>
        </div>
        
        <!-- Communications List -->
        <div class="crm-communications-list">
            <h3><?php _e('Kommunikations-Verlauf', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <?php if (empty($communications)): ?>
                <p><?php _e('Noch keine Kommunikation vorhanden.', LA_CRM_TEXT_DOMAIN); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Datum', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Kontakt', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Typ', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Betreff', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Status', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Aktionen', LA_CRM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($communications as $comm): ?>
                            <tr>
                                <td><?php echo esc_html(date('d.m.Y H:i', strtotime($comm->created_at))); ?></td>
                                <td>
                                    <?php echo esc_html($comm->first_name . ' ' . $comm->last_name); ?>
                                    <?php if ($comm->company_name): ?>
                                        <br><small><?php echo esc_html($comm->company_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="comm-type comm-type-<?php echo esc_attr($comm->communication_type); ?>">
                                        <?php echo esc_html($this->get_communication_type_label($comm->communication_type)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($comm->subject); ?></td>
                                <td>
                                    <span class="comm-status comm-status-<?php echo esc_attr($comm->status); ?>">
                                        <?php echo esc_html($this->get_status_label($comm->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button-small view-communication" data-id="<?php echo esc_attr($comm->id); ?>">
                                        <?php _e('Anzeigen', LA_CRM_TEXT_DOMAIN); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php
    }
    
    /**
     * Render events tab
     */
    private function render_events_tab($case_id) {
        $events = array();
        $contacts = array();
        
        if ($case_id) {
            $events = $this->event_manager->get_case_events($case_id);
            
            // Get all contacts for this case
            global $wpdb;
            $contacts = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT c.id, c.first_name, c.last_name, c.company_name, c.email
                FROM {$wpdb->prefix}klage_contacts c
                JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.contact_id
                WHERE cc.case_id = %d AND c.active_status = 1
            ", $case_id));
        }
        ?>
        
        <!-- Event Form -->
        <div class="crm-event-form" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h3><?php _e('Neues Ereignis erstellen', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <form id="crm-event-form">
                <input type="hidden" name="case_id" value="<?php echo esc_attr($case_id); ?>" />
                <input type="hidden" name="action" value="la_crm_create_event" />
                <?php wp_nonce_field('la_crm_admin_nonce', 'nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="event_contact_id"><?php _e('Kontakt', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="contact_id" id="event_contact_id" required style="width: 100%;">
                                <option value=""><?php _e('Kontakt auswählen', LA_CRM_TEXT_DOMAIN); ?></option>
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?php echo esc_attr($contact->id); ?>">
                                        <?php echo esc_html($contact->first_name . ' ' . $contact->last_name); ?>
                                        <?php if ($contact->company_name): ?>
                                            - <?php echo esc_html($contact->company_name); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="event_type"><?php _e('Ereignis-Typ', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="event_type" id="event_type" required>
                                <option value="meeting"><?php _e('Termin', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="deadline"><?php _e('Frist', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="court_hearing"><?php _e('Gerichtstermin', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="follow_up"><?php _e('Nachverfolgung', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="reminder"><?php _e('Erinnerung', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="task"><?php _e('Aufgabe', LA_CRM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="event_title"><?php _e('Titel', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="title" id="event_title" class="regular-text" required style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="event_description"><?php _e('Beschreibung', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <textarea name="description" id="event_description" rows="4" style="width: 100%;"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="start_datetime"><?php _e('Startzeit', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="datetime-local" name="start_datetime" id="start_datetime" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="end_datetime"><?php _e('Endzeit', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="datetime-local" name="end_datetime" id="end_datetime" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="event_location"><?php _e('Ort', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="location" id="event_location" class="regular-text" style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="event_priority"><?php _e('Priorität', LA_CRM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select name="priority" id="event_priority">
                                <option value="normal"><?php _e('Normal', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="high"><?php _e('Hoch', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="urgent"><?php _e('Dringend', LA_CRM_TEXT_DOMAIN); ?></option>
                                <option value="low"><?php _e('Niedrig', LA_CRM_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button-primary"><?php _e('Ereignis erstellen', LA_CRM_TEXT_DOMAIN); ?></button>
                </p>
            </form>
        </div>
        
        <!-- Events List -->
        <div class="crm-events-list">
            <h3><?php _e('Ereignis-Verlauf', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <?php if (empty($events)): ?>
                <p><?php _e('Noch keine Ereignisse vorhanden.', LA_CRM_TEXT_DOMAIN); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Datum/Zeit', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Kontakt', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Typ', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Titel', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Status', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Aktionen', LA_CRM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo esc_html(date('d.m.Y H:i', strtotime($event->start_datetime))); ?></td>
                                <td>
                                    <?php echo esc_html($event->first_name . ' ' . $event->last_name); ?>
                                    <?php if ($event->company_name): ?>
                                        <br><small><?php echo esc_html($event->company_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="event-type event-type-<?php echo esc_attr($event->event_type); ?>">
                                        <?php echo esc_html($this->get_event_type_label($event->event_type)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($event->title); ?></td>
                                <td>
                                    <span class="event-status event-status-<?php echo esc_attr($event->status); ?>">
                                        <?php echo esc_html($this->get_status_label($event->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button-small view-event" data-id="<?php echo esc_attr($event->id); ?>">
                                        <?php _e('Anzeigen', LA_CRM_TEXT_DOMAIN); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render audience tab
     */
    private function render_audience_tab() {
        $audiences = $this->audience_manager->get_audiences();
        $templates = $this->audience_manager->get_audience_templates();
        ?>
        
        <!-- Quick Audience Creation -->
        <div class="crm-audience-quick" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h3><?php _e('Schnelle Zielgruppen-Erstellung', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($templates as $key => $template): ?>
                    <button type="button" class="button create-audience-template" data-template="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($template['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Audiences List -->
        <div class="crm-audiences-list">
            <h3><?php _e('Zielgruppen-Übersicht', LA_CRM_TEXT_DOMAIN); ?></h3>
            
            <?php if (empty($audiences)): ?>
                <p><?php _e('Noch keine Zielgruppen erstellt.', LA_CRM_TEXT_DOMAIN); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Beschreibung', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Anzahl Kontakte', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Letzte Aktualisierung', LA_CRM_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Aktionen', LA_CRM_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audiences as $audience): ?>
                            <tr>
                                <td><strong><?php echo esc_html($audience->audience_name); ?></strong></td>
                                <td><?php echo esc_html($audience->description); ?></td>
                                <td>
                                    <span class="audience-count"><?php echo intval($audience->contact_count); ?></span>
                                    <button type="button" class="button-small refresh-count" data-audience="<?php echo esc_attr($audience->id); ?>" title="<?php _e('Anzahl aktualisieren', LA_CRM_TEXT_DOMAIN); ?>">
                                        ↻
                                    </button>
                                </td>
                                <td>
                                    <?php if ($audience->last_updated): ?>
                                        <?php echo esc_html(date('d.m.Y H:i', strtotime($audience->last_updated))); ?>
                                    <?php else: ?>
                                        <?php _e('Nie', LA_CRM_TEXT_DOMAIN); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button-small view-audience-contacts" data-audience="<?php echo esc_attr($audience->id); ?>">
                                        <?php _e('Kontakte anzeigen', LA_CRM_TEXT_DOMAIN); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php
    }
    
    /**
     * Get communication type label
     */
    private function get_communication_type_label($type) {
        $labels = array(
            'email' => __('E-Mail', LA_CRM_TEXT_DOMAIN),
            'phone' => __('Telefon', LA_CRM_TEXT_DOMAIN),
            'meeting' => __('Termin', LA_CRM_TEXT_DOMAIN),
            'sms' => __('SMS', LA_CRM_TEXT_DOMAIN),
            'letter' => __('Brief', LA_CRM_TEXT_DOMAIN),
            'fax' => __('Fax', LA_CRM_TEXT_DOMAIN)
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }
    
    /**
     * Get event type label
     */
    private function get_event_type_label($type) {
        $labels = array(
            'meeting' => __('Termin', LA_CRM_TEXT_DOMAIN),
            'deadline' => __('Frist', LA_CRM_TEXT_DOMAIN),
            'court_hearing' => __('Gerichtstermin', LA_CRM_TEXT_DOMAIN),
            'follow_up' => __('Nachverfolgung', LA_CRM_TEXT_DOMAIN),
            'reminder' => __('Erinnerung', LA_CRM_TEXT_DOMAIN),
            'task' => __('Aufgabe', LA_CRM_TEXT_DOMAIN)
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }
    
    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'draft' => __('Entwurf', LA_CRM_TEXT_DOMAIN),
            'sent' => __('Gesendet', LA_CRM_TEXT_DOMAIN),
            'delivered' => __('Zugestellt', LA_CRM_TEXT_DOMAIN),
            'read' => __('Gelesen', LA_CRM_TEXT_DOMAIN),
            'replied' => __('Beantwortet', LA_CRM_TEXT_DOMAIN),
            'failed' => __('Fehlgeschlagen', LA_CRM_TEXT_DOMAIN),
            'scheduled' => __('Geplant', LA_CRM_TEXT_DOMAIN),
            'confirmed' => __('Bestätigt', LA_CRM_TEXT_DOMAIN),
            'completed' => __('Abgeschlossen', LA_CRM_TEXT_DOMAIN),
            'cancelled' => __('Abgesagt', LA_CRM_TEXT_DOMAIN),
            'postponed' => __('Verschoben', LA_CRM_TEXT_DOMAIN)
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * AJAX: Create communication
     */
    public function ajax_create_communication() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_admin_nonce')) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', LA_CRM_TEXT_DOMAIN));
        }
        
        $data = array(
            'contact_id' => intval($_POST['contact_id']),
            'case_id' => intval($_POST['case_id']) ?: null,
            'communication_type' => sanitize_text_field($_POST['communication_type']),
            'subject' => sanitize_text_field($_POST['subject']),
            'content' => wp_kses_post($_POST['content']),
            'priority' => sanitize_text_field($_POST['priority']),
            'direction' => 'outbound'
        );
        
        $result = $this->communication_manager->create_communication($data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Kommunikation erfolgreich erstellt', LA_CRM_TEXT_DOMAIN),
                'id' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Fehler beim Erstellen der Kommunikation', LA_CRM_TEXT_DOMAIN)
            ));
        }
    }
    
    /**
     * AJAX: Create event
     */
    public function ajax_create_event() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_admin_nonce')) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', LA_CRM_TEXT_DOMAIN));
        }
        
        $data = array(
            'contact_id' => intval($_POST['contact_id']),
            'case_id' => intval($_POST['case_id']) ?: null,
            'event_type' => sanitize_text_field($_POST['event_type']),
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description']),
            'start_datetime' => sanitize_text_field($_POST['start_datetime']),
            'end_datetime' => sanitize_text_field($_POST['end_datetime']) ?: null,
            'location' => sanitize_text_field($_POST['location']),
            'priority' => sanitize_text_field($_POST['priority'])
        );
        
        $result = $this->event_manager->create_event($data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Ereignis erfolgreich erstellt', LA_CRM_TEXT_DOMAIN),
                'id' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Fehler beim Erstellen des Ereignisses', LA_CRM_TEXT_DOMAIN)
            ));
        }
    }
    
    /**
     * AJAX: Load template
     */
    public function ajax_load_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_admin_nonce')) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', LA_CRM_TEXT_DOMAIN));
        }
        
        $template_id = intval($_POST['template_id']);
        $placeholders = $_POST['placeholders'] ?? array();
        
        $result = $this->communication_manager->apply_template($template_id, $placeholders);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array(
                'message' => __('Vorlage konnte nicht geladen werden', LA_CRM_TEXT_DOMAIN)
            ));
        }
    }
    
    /**
     * AJAX: Get audience contacts
     */
    public function ajax_get_audience_contacts() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_admin_nonce')) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', LA_CRM_TEXT_DOMAIN));
        }
        
        $audience_id = intval($_POST['audience_id']);
        $contacts = $this->audience_manager->get_audience_contacts($audience_id);
        
        wp_send_json_success(array(
            'contacts' => $contacts,
            'count' => count($contacts)
        ));
    }
    
    /**
     * AJAX: Update status
     */
    public function ajax_update_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_admin_nonce')) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen', LA_CRM_TEXT_DOMAIN));
        }
        
        $id = intval($_POST['id']);
        $type = sanitize_text_field($_POST['type']);
        $status = sanitize_text_field($_POST['status']);
        
        if ($type === 'communication') {
            $result = $this->communication_manager->update_status($id, $status);
        } elseif ($type === 'event') {
            $result = $this->event_manager->update_status($id, $status);
        } else {
            wp_send_json_error(array('message' => __('Unbekannter Typ', LA_CRM_TEXT_DOMAIN)));
        }
        
        if ($result) {
            wp_send_json_success(array('message' => __('Status erfolgreich aktualisiert', LA_CRM_TEXT_DOMAIN)));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Aktualisieren des Status', LA_CRM_TEXT_DOMAIN)));
        }
    }
}