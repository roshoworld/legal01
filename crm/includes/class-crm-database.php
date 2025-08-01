<?php
/**
 * CRM Database Manager
 * Handles CRM-specific database tables and integrates with core v2.0.4 tables
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LA_CRM_Database {
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Create CRM-specific database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // CRM Communications table
        $communications_table = $wpdb->prefix . 'klage_crm_communications';
        
        $sql_communications = "CREATE TABLE IF NOT EXISTS $communications_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) unsigned NOT NULL,
            case_id bigint(20) unsigned DEFAULT NULL,
            communication_type enum('email','phone','meeting','sms','letter','fax') NOT NULL DEFAULT 'email',
            direction enum('inbound','outbound') NOT NULL DEFAULT 'outbound',
            subject varchar(500) DEFAULT NULL,
            content text DEFAULT NULL,
            status enum('draft','sent','delivered','read','replied','failed') DEFAULT 'sent',
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            replied_at datetime DEFAULT NULL,
            template_id bigint(20) unsigned DEFAULT NULL,
            attachments text DEFAULT NULL,
            metadata text DEFAULT NULL,
            active_status tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_id (contact_id),
            KEY case_id (case_id),
            KEY communication_type (communication_type),
            KEY direction (direction),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY active_status (active_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // CRM Events table
        $events_table = $wpdb->prefix . 'klage_crm_events';
        
        $sql_events = "CREATE TABLE IF NOT EXISTS $events_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) unsigned NOT NULL,
            case_id bigint(20) unsigned DEFAULT NULL,
            event_type enum('meeting','deadline','court_hearing','follow_up','reminder','task') NOT NULL DEFAULT 'meeting',
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime DEFAULT NULL,
            location varchar(255) DEFAULT NULL,
            status enum('scheduled','confirmed','completed','cancelled','postponed') DEFAULT 'scheduled',
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            reminder_minutes int(11) DEFAULT 15,
            attendees text DEFAULT NULL,
            notes text DEFAULT NULL,
            outcome text DEFAULT NULL,
            active_status tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_id (contact_id),
            KEY case_id (case_id),
            KEY event_type (event_type),
            KEY start_datetime (start_datetime),
            KEY status (status),
            KEY active_status (active_status)
        ) $charset_collate;";
        
        // CRM Audience Management table
        $audience_table = $wpdb->prefix . 'klage_crm_audience';
        
        $sql_audience = "CREATE TABLE IF NOT EXISTS $audience_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            audience_name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            criteria text NOT NULL,
            contact_count int(11) DEFAULT 0,
            last_updated datetime DEFAULT NULL,
            active_status tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY audience_name (audience_name),
            KEY active_status (active_status)
        ) $charset_collate;";
        
        // CRM Communication Templates table
        $templates_table = $wpdb->prefix . 'klage_crm_templates';
        
        $sql_templates = "CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            template_type enum('email','sms','letter','meeting_agenda') NOT NULL DEFAULT 'email',
            subject varchar(500) DEFAULT NULL,
            content text NOT NULL,
            placeholders text DEFAULT NULL,
            category varchar(100) DEFAULT 'general',
            usage_count int(11) DEFAULT 0,
            active_status tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_name (template_name),
            KEY template_type (template_type),
            KEY category (category),
            KEY active_status (active_status)
        ) $charset_collate;";
        
        // CRM Campaigns table
        $campaigns_table = $wpdb->prefix . 'klage_crm_campaigns';
        
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $campaigns_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            campaign_type enum('email','sms','mixed') NOT NULL DEFAULT 'email',
            audience_id bigint(20) unsigned DEFAULT NULL,
            template_id bigint(20) unsigned DEFAULT NULL,
            status enum('draft','scheduled','running','completed','paused','cancelled') DEFAULT 'draft',
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            total_recipients int(11) DEFAULT 0,
            sent_count int(11) DEFAULT 0,
            delivered_count int(11) DEFAULT 0,
            opened_count int(11) DEFAULT 0,
            clicked_count int(11) DEFAULT 0,
            replied_count int(11) DEFAULT 0,
            active_status tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_name (campaign_name),
            KEY audience_id (audience_id),
            KEY template_id (template_id),
            KEY status (status),
            KEY start_date (start_date),
            KEY active_status (active_status)
        ) $charset_collate;";
        
        // Create tables using dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array();
        $results['communications'] = dbDelta($sql_communications);
        $results['events'] = dbDelta($sql_events);
        $results['audience'] = dbDelta($sql_audience);
        $results['templates'] = dbDelta($sql_templates);
        $results['campaigns'] = dbDelta($sql_campaigns);
        
        // Create default templates
        $this->create_default_templates();
        
        // Log table creation
        error_log('LA CRM: Database tables created/updated - ' . json_encode($results));
        
        return $results;
    }
    
    /**
     * Create default communication templates
     */
    private function create_default_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'klage_crm_templates';
        
        // Check if templates already exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $templates_table");
        if ($existing_count > 0) {
            return; // Templates already exist
        }
        
        $default_templates = array(
            array(
                'template_name' => 'Initial Contact - GDPR Violation',
                'template_type' => 'email',
                'subject' => 'DSGVO-Verstoß - Schadensersatzforderung',
                'content' => 'Sehr geehrte Damen und Herren,

wir vertreten Herrn/Frau {{client_name}} in einer Angelegenheit betreffend einen Verstoß gegen die Datenschutz-Grundverordnung (DSGVO).

{{case_details}}

Wir fordern Sie hiermit zur Abgabe einer strafbewehrten Unterlassungserklärung sowie zur Zahlung von Schadensersatz in Höhe von {{damage_amount}} € auf.

Mit freundlichen Grüßen
{{law_firm_name}}',
                'placeholders' => json_encode(array('client_name', 'case_details', 'damage_amount', 'law_firm_name')),
                'category' => 'gdpr_violation'
            ),
            array(
                'template_name' => 'Follow-up Communication',
                'template_type' => 'email',
                'subject' => 'Nachfassung - {{case_reference}}',
                'content' => 'Sehr geehrte Damen und Herren,

wir nehmen Bezug auf unser Schreiben vom {{original_date}} bezüglich {{case_subject}}.

Da wir bisher keine Antwort erhalten haben, setzen wir Ihnen hiermit eine Nachfrist bis zum {{deadline_date}}.

{{additional_notes}}

Mit freundlichen Grüßen
{{law_firm_name}}',
                'placeholders' => json_encode(array('case_reference', 'original_date', 'case_subject', 'deadline_date', 'additional_notes', 'law_firm_name')),
                'category' => 'follow_up'
            ),
            array(
                'template_name' => 'Settlement Offer',
                'template_type' => 'email',
                'subject' => 'Vergleichsangebot - {{case_reference}}',
                'content' => 'Sehr geehrte Damen und Herren,

zur außergerichtlichen Beilegung der Angelegenheit {{case_reference}} unterbreiten wir Ihnen folgendes Vergleichsangebot:

{{settlement_terms}}

Dieses Angebot ist gültig bis zum {{offer_deadline}}.

Mit freundlichen Grüßen
{{law_firm_name}}',
                'placeholders' => json_encode(array('case_reference', 'settlement_terms', 'offer_deadline', 'law_firm_name')),
                'category' => 'settlement'
            ),
            array(
                'template_name' => 'Meeting Agenda',
                'template_type' => 'meeting_agenda',
                'subject' => 'Agenda - {{meeting_title}}',
                'content' => 'Meeting Agenda

Datum: {{meeting_date}}
Zeit: {{meeting_time}}
Ort: {{meeting_location}}
Teilnehmer: {{attendees}}

Agenda:
1. {{agenda_item_1}}
2. {{agenda_item_2}}
3. {{agenda_item_3}}

Notizen:
{{notes}}',
                'placeholders' => json_encode(array('meeting_title', 'meeting_date', 'meeting_time', 'meeting_location', 'attendees', 'agenda_item_1', 'agenda_item_2', 'agenda_item_3', 'notes')),
                'category' => 'meeting'
            )
        );
        
        foreach ($default_templates as $template) {
            $wpdb->insert(
                $templates_table,
                array(
                    'template_name' => $template['template_name'],
                    'template_type' => $template['template_type'],
                    'subject' => $template['subject'],
                    'content' => $template['content'],
                    'placeholders' => $template['placeholders'],
                    'category' => $template['category'],
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                )
            );
        }
        
        error_log('LA CRM: Default templates created');
    }
    
    /**
     * Get contact with case context using core v2.0.4 tables
     */
    public function get_contact_with_cases($contact_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.*,
                cc.role,
                cc.role_description,
                cases.case_id,
                cases.case_status
            FROM {$wpdb->prefix}klage_contacts c
            LEFT JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.contact_id
            LEFT JOIN {$wpdb->prefix}klage_cases cases ON cc.case_id = cases.id
            WHERE c.id = %d AND c.active_status = 1 AND (cases.active_status = 'active' OR cases.active_status IS NULL)
        ", $contact_id));
    }
    
    /**
     * Get all active contacts using core table
     */
    public function get_active_contacts() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT id, first_name, last_name, company_name, email, phone, contact_type
            FROM {$wpdb->prefix}klage_contacts
            WHERE active_status = 1
            ORDER BY last_name, first_name
        ");
    }
    
    /**
     * Get cases for a contact using core tables
     */
    public function get_contact_cases($contact_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                cases.*,
                cc.role,
                cc.role_description
            FROM {$wpdb->prefix}klage_cases cases
            JOIN {$wpdb->prefix}klage_case_contacts cc ON cases.id = cc.case_id
            WHERE cc.contact_id = %d AND cases.active_status = 'active' AND cc.active_status = 1
            ORDER BY cases.created_at DESC
        ", $contact_id));
    }
    
    /**
     * Log activity in core audit table
     */
    public function log_audit($action_type, $action_details, $contact_id = null, $case_id = null) {
        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'klage_audit';
        
        // Check if audit table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$audit_table'") != $audit_table) {
            return false;
        }
        
        return $wpdb->insert(
            $audit_table,
            array(
                'action_type' => $action_type,
                'action_details' => $action_details,
                'contact_id' => $contact_id,
                'case_id' => $case_id,
                'performed_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            )
        );
    }
}