<?php
/**
 * Plugin Name: Legal Automation - Document Analysis
 * Plugin URI: https://klage.click
 * Description: AI-powered incoming document analysis and case assignment system for Klage.Click legal automation platform
 * Version: 1.1.8
 * Author: Klage.Click
 * Text Domain: legal-automation-doc-in
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CAH_DOC_IN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CAH_DOC_IN_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CAH_DOC_IN_PLUGIN_VERSION', '1.1.7');
define('CAH_DOC_IN_TEXT_DOMAIN', 'court-automation-hub-document-analysis');

// Main plugin class
class CourtAutomationHub_DocumentAnalysis {
    
    // Declare all class properties
    public $db_manager;
    public $communications;
    public $api;
    public $case_matcher;
    public $admin;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Debug: Plugin initialization
        error_log('CAH Doc-In: Plugin initialization started');
        
        // Check if core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            error_log('CAH Doc-In: Core plugin not found - showing notice');
            add_action('admin_notices', array($this, 'core_plugin_required_notice'));
            return;
        }
        
        error_log('CAH Doc-In: Core plugin found - continuing initialization');
        
        // Load text domain
        load_plugin_textdomain(CAH_DOC_IN_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Add hooks
        $this->add_hooks();
        
        error_log('CAH Doc-In: Plugin initialization completed');
    }
    
    private function includes() {
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-db-manager.php';
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-communications.php';
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-api.php';
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-case-matcher.php';
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-admin.php';
    }
    
    private function init_components() {
        // Initialize database manager first
        $this->db_manager = new CAH_Document_in_DB_Manager();
        
        // Initialize other components
        $this->communications = new CAH_Document_in_Communications();
        $this->api = new CAH_Document_in_API();
        $this->case_matcher = new CAH_Document_in_Case_Matcher();
        $this->admin = new CAH_Document_in_Admin();
    }
    
    private function add_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('cah-doc-in-frontend', CAH_DOC_IN_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), CAH_DOC_IN_PLUGIN_VERSION, true);
        wp_enqueue_style('cah-doc-in-frontend', CAH_DOC_IN_PLUGIN_URL . 'assets/css/frontend.css', array(), CAH_DOC_IN_PLUGIN_VERSION);
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'cah-doc-in') !== false) {
            wp_enqueue_script('cah-doc-in-admin', CAH_DOC_IN_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CAH_DOC_IN_PLUGIN_VERSION, true);
            wp_enqueue_style('cah-doc-in-admin', CAH_DOC_IN_PLUGIN_URL . 'assets/css/admin.css', array(), CAH_DOC_IN_PLUGIN_VERSION);
            
            // Localize script for AJAX
            wp_localize_script('cah-doc-in-admin', 'cah_doc_in_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cah_doc_in_admin_nonce'),
                'text_domain' => CAH_DOC_IN_TEXT_DOMAIN
            ));
        }
    }
    
    public function activate() {
        // Ensure core plugin is active
        if (!class_exists('CourtAutomationHub')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Court Automation Hub - Document Analysis requires the Core Plugin to be installed and activated.', CAH_DOC_IN_TEXT_DOMAIN));
        }
        
        // Include database manager for activation
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-db-manager.php';
        
        // Create database tables
        $db_manager = new CAH_Document_in_DB_Manager();
        $db_manager->create_tables();
        
        // Create default categories
        $this->create_default_categories();
        
        // Set activation flag
        update_option('cah_doc_in_activated', true);
        update_option('cah_doc_in_version', CAH_DOC_IN_PLUGIN_VERSION);
        
        // Log activation
        $db_manager->log_audit(array(
            'action_type' => 'plugin_activation',
            'action_details' => 'Document Analysis Plugin v' . CAH_DOC_IN_PLUGIN_VERSION . ' activated'
        ));
    }
    
    public function deactivate() {
        // Clean up scheduled events if any
        wp_clear_scheduled_hook('cah_doc_in_cleanup');
        
        // Set deactivation flag
        update_option('cah_doc_in_activated', false);
        
        // Log deactivation
        require_once CAH_DOC_IN_PLUGIN_PATH . 'includes/class-doc-in-db-manager.php';
        $db_manager = new CAH_Document_in_DB_Manager();
        $db_manager->log_audit(array(
            'action_type' => 'plugin_deactivation',
            'action_details' => 'Document Analysis Plugin v' . CAH_DOC_IN_PLUGIN_VERSION . ' deactivated'
        ));
    }
    
    private function create_default_categories() {
        // Complete GDPR SPAM categories with German UI descriptions
        $default_categories = array(
            // Consent-related categories
            'consent_express_claimed' => array(
                'name' => 'Express Consent Claimed',
                'description_de' => 'Behauptung der ausdrücklichen Einwilligung: Der mutmaßliche Spammer behauptet, der Empfänger (Ihr Mandant) habe zuvor eine ausdrückliche Einwilligung erteilt (z.B. Double Opt-in, Ankreuzfeld).'
            ),
            'consent_existing_business_relationship' => array(
                'name' => 'Existing Business Relationship',
                'description_de' => 'Bestehende Geschäftsbeziehung (§ 7 Abs. 3 UWG): Der mutmaßliche Spammer behauptet, es habe eine vorherige Geschäftsbeziehung bestanden, die Direktmarketing unter spezifischen Bedingungen erlaubt (§ 7 Abs. 3 UWG).'
            ),
            'consent_withdrawn_before_sending' => array(
                'name' => 'Consent Withdrawn Before Sending',
                'description_de' => 'Einwilligung vor Versand widerrufen: Der mutmaßliche Spammer behauptet, die Einwilligung sei bereits vor dem Versand der beanstandeten E-Mail widerrufen worden.'
            ),
            'consent_checkbox_preticked' => array(
                'name' => 'Checkbox Consent Claimed',
                'description_de' => 'Der mutmaßliche Spammer behauptet, unser Mandant habe durch Ankreuzen einer Checkbox seine Einwilligung erteilt.'
            ),
            
            // Technical categories
            'technical_email_not_sent_by_them' => array(
                'name' => 'Email Not Sent By Them',
                'description_de' => 'E-Mail nicht von ihnen gesendet: Der mutmaßliche Spammer bestreitet den Versand der E-Mail und behauptet, diese sei gefälscht, ein technischer Fehler oder ohne deren Genehmigung von einem Dritten versendet worden.'
            ),
            'technical_delivery_issue' => array(
                'name' => 'Technical Delivery Issue',
                'description_de' => 'Technisches Zustellungsproblem: Der mutmaßliche Spammer behauptet, die E-Mail sei niemals tatsächlich beim Empfänger zugestellt worden (z.B. Bounce-Meldungen, Fehlermeldungen vom Mailserver).'
            ),
            'technical_proof_of_consent_requested' => array(
                'name' => 'Proof of Consent Requested',
                'description_de' => 'Nachweis der Einwilligung angefordert: Der mutmaßliche Spammer verlangt einen dokumentierten Nachweis der Einwilligung vom Empfänger.'
            ),
            'technical_not_commercial_no_marketing' => array(
                'name' => 'Not Commercial / No Marketing',
                'description_de' => 'Nicht kommerziell / Kein Marketing: Der mutmaßliche Spammer argumentiert, die E-Mail sei rein informativer, transaktionaler Natur oder stelle kein "Marketing" dar.'
            ),
            
            // Legal categories
            'legal_no_gdpr_violation_claimed' => array(
                'name' => 'No GDPR Violation Claimed',
                'description_de' => 'Keine DSGVO-Verletzung geltend gemacht: Der mutmaßliche Spammer argumentiert, es sei keine DSGVO-Verletzung erfolgt (z.B. berechtigtes Interesse nach Art. 6 Abs. 1 lit. f DSGVO, Notwendigkeit zur Vertragserfüllung).'
            ),
            'legal_no_uwg_violation_claimed' => array(
                'name' => 'No UWG Violation Claimed',
                'description_de' => 'Keine UWG-Verletzung geltend gemacht: Der mutmaßliche Spammer argumentiert, es sei keine Verletzung des deutschen Gesetzes gegen den unlauteren Wettbewerb (UWG) erfolgt.'
            ),
            'legal_legitimate_interest_claimed' => array(
                'name' => 'Legitimate Interest Claimed',
                'description_de' => 'Der mutmaßliche Spammer beruft sich auf berechtigte Interessen nach Art. 6 Abs. 1 lit. f DSGVO'
            ),
            'legal_no_damage_minimal_damage' => array(
                'name' => 'No/Minimal Damage',
                'description_de' => 'Kein/Minimaler Schaden (Art. 82 DSGVO): Der mutmaßliche Spammer argumentiert, es sei kein quantifizierbarer Schaden (materieller Schaden) entstanden, oder der immaterielle Schaden (z.B. Gefühl des Datenverlusts) sei minimal und rechtfertige keinen Schadensersatz.'
            ),
            'legal_claimed_damages_overstated' => array(
                'name' => 'Claimed Damages Overstated',
                'description_de' => 'Forderungssumme überhöht: Der mutmaßliche Spammer bestreitet die Höhe des geforderten Schadensersatzes oder der Anwaltskosten als unverhältnismäßig oder nicht durch gesetzliche Bestimmungen (z.B. RVG) gerechtfertigt.'
            ),
            'legal_statute_of_limitations_claimed' => array(
                'name' => 'Statute of Limitations Claimed',
                'description_de' => 'Verjährung geltend gemacht: Der mutmaßliche Spammer behauptet, die Forderung sei verjährt.'
            ),
            'legal_lack_of_legal_standing' => array(
                'name' => 'Lack of Legal Standing',
                'description_de' => 'Fehlende Aktivlegitimation: Der mutmaßliche Spammer stellt das Recht Ihres Mandanten, die Klage zu erheben, in Frage (z.B. nicht direkt betroffen oder nicht der tatsächliche Empfänger).'
            ),
            'legal_abuse_of_rights_warning_letter' => array(
                'name' => 'Abuse of Rights Warning Letter',
                'description_de' => 'Rechtsmissbräuchlichkeit der Abmahnung: Der mutmaßliche Spammer behauptet, die Abmahnung sei rechtsmissbräuchlich, z.B. wenn es dem Abmahnenden primär um das Generieren von Gebühren geht (§ 8c UWG).'
            ),
            
            // Process categories
            'process_formal_defect_warning_letter' => array(
                'name' => 'Formal Defect Warning Letter',
                'description_de' => 'Formeller Mangel in der Abmahnung: Der mutmaßliche Spammer behauptet, die Abmahnung selbst weise formelle Fehler auf (z.B. unzureichende Details, unklare Forderung).'
            ),
            'process_cease_and_desist_provided' => array(
                'name' => 'Cease and Desist Provided',
                'description_de' => 'Unterlassungserklärung abgegeben: Der mutmaßliche Spammer hat bereits eine (ggf. modifizierte) Unterlassungserklärung abgegeben.'
            ),
            'process_extension_requested' => array(
                'name' => 'Extension Requested',
                'description_de' => 'Nachfrist angefordert: Der mutmaßliche Spammer bittet um eine Verlängerung der Frist zur Stellungnahme oder Abgabe einer Erklärung.'
            ),
            
            // General categories
            'general_inquiry_request_for_clarification' => array(
                'name' => 'General Inquiry / Request for Clarification',
                'description_de' => 'Allgemeine Anfrage/Bitte um Klärung: Eine allgemeine Frage zur Forderung, zum Sachverhalt oder Wunsch nach weiterer Erläuterung, die keine spezifische rechtliche Verteidigung darstellt.'
            ),
            'general_willingness_to_negotiate_settle' => array(
                'name' => 'Willingness to Negotiate/Settle',
                'description_de' => 'Verhandlungs-/Vergleichsbereitschaft: Der mutmaßliche Spammer signalisiert Bereitschaft zu Verhandlungen oder schlägt einen Vergleich vor.'
            ),
            'general_contact_information_updated' => array(
                'name' => 'Contact Information Updated',
                'description_de' => 'Kontaktinformationen aktualisiert: Der mutmaßliche Spammer übermittelt neue Kontaktinformationen (Adresse, E-Mail, Telefonnummer).'
            ),
            'general_legal_representation_notified' => array(
                'name' => 'Legal Representation Notified',
                'description_de' => 'Hinweis auf Rechtsbeistand: Der mutmaßliche Spammer teilt mit, dass er bereits anwaltlich vertreten ist oder Rechtsbeistand einholen wird.'
            ),
            'general_threat_of_counter_action' => array(
                'name' => 'Threat of Counter Action',
                'description_de' => 'Drohung mit Gegenmaßnahme: Der mutmaßliche Spammer droht mit einer Gegenklage, negativer Presse oder anderen rechtlichen Schritten.'
            ),
            'general_mistaken_delivery_not_responsible' => array(
                'name' => 'Mistaken Delivery / Not Responsible',
                'description_de' => 'Irrtümliche Zustellung / Nicht zuständig: Der Empfänger der E-Mail behauptet, die E-Mail sei fälschlicherweise an ihn gesendet worden oder er sei nicht der richtige Ansprechpartner.'
            ),
            'general_uncategorized_other' => array(
                'name' => 'Uncategorized/Other',
                'description_de' => 'Unkategorisiert/Sonstiges: Für E-Mails, die in keine vordefinierte Kategorie passen und eine manuelle Überprüfung erfordern.'
            ),
            'general_reception_unterlassungserklaerung' => array(
                'name' => 'Received Cease and Desist Declaration',
                'description_de' => 'Wir haben eine Unterlassungserklärung bekommen.'
            ),
            'general_non_reception_unterlassungserklaerung' => array(
                'name' => 'No Cease and Desist Declaration Received',
                'description_de' => 'Wir haben keine Unterlassungserklärung bekommen.'
            )
        );
        
        foreach ($default_categories as $slug => $category_data) {
            // Create term with slug
            $term = wp_insert_term($category_data['name'], 'communication_category', array(
                'slug' => $slug,
                'description' => $category_data['description_de']
            ));
            
            // If term already exists, update it
            if (is_wp_error($term) && $term->get_error_code() === 'term_exists') {
                $existing_term = get_term_by('slug', $slug, 'communication_category');
                if ($existing_term) {
                    wp_update_term($existing_term->term_id, 'communication_category', array(
                        'name' => $category_data['name'],
                        'description' => $category_data['description_de']
                    ));
                }
            }
        }
        
        // Store German descriptions for API usage
        update_option('cah_doc_in_category_descriptions', $default_categories);
    }
    
    public function core_plugin_required_notice() {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . __('Court Automation Hub - Document Analysis requires the Core Plugin to be installed and activated.', CAH_DOC_IN_TEXT_DOMAIN) . '</p>';
        echo '</div>';
    }
    
    /**
     * Check if running in integrated mode
     */
    public function is_integrated_mode() {
        return class_exists('CAH_DocIn_Integration');
    }
}

// Initialize plugin
new CourtAutomationHub_DocumentAnalysis();