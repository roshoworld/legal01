<?php
/**
 * Database management class
 * Creates and manages all database tables based on the data model
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Database {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // DISABLED: Run upgrade check on admin init - THIS WAS CAUSING DATA LOSS
        // add_action('admin_init', array($this, 'check_and_upgrade_schema'));
    }
    
    /**
     * Check and upgrade database schema if needed
     */
    public function check_and_upgrade_schema() {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Check if we need to upgrade
        $version_option = get_option('cah_database_version', '1.0.0');
        $current_version = '1.3.3';
        
        if (version_compare($version_option, $current_version, '<')) {
            $this->upgrade_existing_tables();
            update_option('cah_database_version', $current_version);
        }
    }
    
    /**
     * Ensure debtors table has correct schema - ULTRA SAFE VERSION
     */
    private function ensure_debtors_table_schema() {
        // DISABLED FOR SAFETY - This method was causing table recreation
        // Only add missing columns via the safe method
        return;
        
        /* COMMENTED OUT TO PREVENT DATA LOSS
        $charset_collate = $this->wpdb->get_charset_collate();
        $table_name = $this->wpdb->prefix . 'klage_debtors';
        
        // Check if table exists first
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            // Only create if doesn't exist - NEVER DROP EXISTING DATA
            // Create with correct schema
            $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            debtors_name varchar(200) NOT NULL,
            debtors_company varchar(200),
            debtors_first_name varchar(100),
            debtors_last_name varchar(100),
            debtors_email varchar(255),
            debtors_phone varchar(50),
            debtors_fax varchar(50),
            debtors_address varchar(200),
            debtors_street varchar(150),
            debtors_house_number varchar(20),
            debtors_address_addition varchar(100),
            debtors_postal_code varchar(20),
            debtors_city varchar(100),
            debtors_state varchar(100),
            debtors_country varchar(100) DEFAULT 'Deutschland',
            rechtsform varchar(50) DEFAULT 'natuerliche_person',
            handelsregister_nr varchar(50),
            ustid varchar(50),
            geschaeftsfuehrer varchar(200),
            website varchar(255),
            social_media text,
            finanzielle_situation varchar(50) DEFAULT 'unbekannt',
            zahlungsverhalten varchar(20) DEFAULT 'unbekannt',
            bonität varchar(20) DEFAULT 'unbekannt',
            insolvenz_status varchar(20) DEFAULT 'nein',
            pfändung_status varchar(20) DEFAULT 'nein',
            bevorzugte_sprache varchar(5) DEFAULT 'de',
            kommunikation_email tinyint(1) DEFAULT 1,
            kommunikation_post tinyint(1) DEFAULT 1,
            datenquelle varchar(50) DEFAULT 'manual',
            verifiziert tinyint(1) DEFAULT 0,
            letzte_aktualisierung datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY debtors_name (debtors_name),
            KEY debtors_email (debtors_email),
            KEY debtors_postal_code (debtors_postal_code)
        ) $charset_collate";
        
        $this->wpdb->query($sql);
        } // Close if (!$table_exists)
        */
    }
    
    /**
     * Upgrade existing tables to fix schema issues
     */
    private function upgrade_existing_tables() {
        $table_name = $this->wpdb->prefix . 'klage_debtors';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists) {
            // Fix debtors_country field length issue
            $column_info = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'debtors_country'");
            
            if (!empty($column_info)) {
                $column_type = $column_info[0]->Type;
                
                // If it's varchar(2), update it to varchar(100)
                if (strpos($column_type, 'varchar(2)') !== false) {
                    $alter_sql = "ALTER TABLE $table_name MODIFY COLUMN debtors_country varchar(100) DEFAULT 'Deutschland'";
                    $this->wpdb->query($alter_sql);
                    
                    // Update existing 'DE' values to 'Deutschland'
                    $update_sql = "UPDATE $table_name SET debtors_country = 'Deutschland' WHERE debtors_country = 'DE'";
                    $this->wpdb->query($update_sql);
                }
            }
            
            // Add missing columns if they don't exist
            $this->add_missing_columns_to_debtors_table($table_name);
        }
        
        // Also upgrade cases table
        $this->upgrade_cases_table();
    }
    
    /**
     * Upgrade cases table to add missing columns
     */
    private function upgrade_cases_table() {
        $table_name = $this->wpdb->prefix . 'klage_cases';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists) {
            $this->add_missing_columns_to_cases_table($table_name);
        }
    }
    
    /**
     * Add missing columns to existing cases table
     */
    private function add_missing_columns_to_cases_table($table_name) {
        // Define columns that should exist in cases table (ENGLISH STANDARD)
        $required_columns = array(
            'mandant' => "ALTER TABLE $table_name ADD COLUMN mandant varchar(100) DEFAULT NULL",
            'brief_status' => "ALTER TABLE $table_name ADD COLUMN brief_status varchar(20) DEFAULT 'pending'",
            'briefe' => "ALTER TABLE $table_name ADD COLUMN briefe int(3) DEFAULT 1",
            'schuldner' => "ALTER TABLE $table_name ADD COLUMN schuldner varchar(200) DEFAULT NULL",
            'beweise' => "ALTER TABLE $table_name ADD COLUMN beweise text DEFAULT NULL",
            'dokumente' => "ALTER TABLE $table_name ADD COLUMN dokumente text DEFAULT NULL",
            'links_zu_dokumenten' => "ALTER TABLE $table_name ADD COLUMN links_zu_dokumenten text DEFAULT NULL",
            
            // ENGLISH INTERNAL NAMING (New Standard)
            'procedure_type' => "ALTER TABLE $table_name ADD COLUMN procedure_type varchar(50) DEFAULT 'dunning_procedure'",
            'legal_basis' => "ALTER TABLE $table_name ADD COLUMN legal_basis varchar(100) DEFAULT 'GDPR Art. 82'",
            'period_start' => "ALTER TABLE $table_name ADD COLUMN period_start date DEFAULT NULL",
            'period_end' => "ALTER TABLE $table_name ADD COLUMN period_end date DEFAULT NULL",
            'violation_count' => "ALTER TABLE $table_name ADD COLUMN violation_count int(5) DEFAULT 1",
            'damage_amount' => "ALTER TABLE $table_name ADD COLUMN damage_amount decimal(10,2) DEFAULT 548.11",
            'responsible_court' => "ALTER TABLE $table_name ADD COLUMN responsible_court varchar(100) DEFAULT NULL",
            'procedure_value' => "ALTER TABLE $table_name ADD COLUMN procedure_value decimal(10,2) DEFAULT 548.11",
            'response_deadline' => "ALTER TABLE $table_name ADD COLUMN response_deadline date DEFAULT NULL",
            'payment_deadline' => "ALTER TABLE $table_name ADD COLUMN payment_deadline date DEFAULT NULL",
            'reminder_date' => "ALTER TABLE $table_name ADD COLUMN reminder_date date DEFAULT NULL",
            'lawsuit_date' => "ALTER TABLE $table_name ADD COLUMN lawsuit_date date DEFAULT NULL",
            'success_probability' => "ALTER TABLE $table_name ADD COLUMN success_probability varchar(20) DEFAULT 'high'",
            'risk_assessment' => "ALTER TABLE $table_name ADD COLUMN risk_assessment varchar(20) DEFAULT 'low'",
            'processing_complexity' => "ALTER TABLE $table_name ADD COLUMN processing_complexity varchar(20) DEFAULT 'standard'",
            'processing_risk_score' => "ALTER TABLE $table_name ADD COLUMN processing_risk_score tinyint(3) unsigned DEFAULT 3",
            'communication_language' => "ALTER TABLE $table_name ADD COLUMN communication_language varchar(5) DEFAULT 'de'",
            'preferred_contact' => "ALTER TABLE $table_name ADD COLUMN preferred_contact varchar(20) DEFAULT 'email'",
            'category' => "ALTER TABLE $table_name ADD COLUMN category varchar(50) DEFAULT 'GDPR_SPAM'",
            'subcategory' => "ALTER TABLE $table_name ADD COLUMN subcategory varchar(50) DEFAULT 'Newsletter'",
            'processing_status' => "ALTER TABLE $table_name ADD COLUMN processing_status varchar(30) DEFAULT 'new'",
            'internal_priority' => "ALTER TABLE $table_name ADD COLUMN internal_priority varchar(20) DEFAULT 'normal'",
            
            // GERMAN COLUMNS (Maintained for migration compatibility)
            'verfahrensart' => "ALTER TABLE $table_name ADD COLUMN verfahrensart varchar(50) DEFAULT 'mahnverfahren'",
            'rechtsgrundlage' => "ALTER TABLE $table_name ADD COLUMN rechtsgrundlage varchar(100) DEFAULT 'DSGVO Art. 82'",
            'zeitraum_von' => "ALTER TABLE $table_name ADD COLUMN zeitraum_von date DEFAULT NULL",
            'zeitraum_bis' => "ALTER TABLE $table_name ADD COLUMN zeitraum_bis date DEFAULT NULL",
            'anzahl_verstoesse' => "ALTER TABLE $table_name ADD COLUMN anzahl_verstoesse int(5) DEFAULT 1",
            'schadenhoehe' => "ALTER TABLE $table_name ADD COLUMN schadenhoehe decimal(10,2) DEFAULT 548.11",
            'gericht_zustaendig' => "ALTER TABLE $table_name ADD COLUMN gericht_zustaendig varchar(100) DEFAULT NULL",
            'verfahrenswert' => "ALTER TABLE $table_name ADD COLUMN verfahrenswert decimal(10,2) DEFAULT 548.11",
            'deadline_antwort' => "ALTER TABLE $table_name ADD COLUMN deadline_antwort date DEFAULT NULL",
            'deadline_zahlung' => "ALTER TABLE $table_name ADD COLUMN deadline_zahlung date DEFAULT NULL",
            'mahnung_datum' => "ALTER TABLE $table_name ADD COLUMN mahnung_datum date DEFAULT NULL",
            'klage_datum' => "ALTER TABLE $table_name ADD COLUMN klage_datum date DEFAULT NULL",
            'erfolgsaussicht' => "ALTER TABLE $table_name ADD COLUMN erfolgsaussicht varchar(20) DEFAULT 'hoch'",
            'risiko_bewertung' => "ALTER TABLE $table_name ADD COLUMN risiko_bewertung varchar(20) DEFAULT 'niedrig'",
            'kommunikation_sprache' => "ALTER TABLE $table_name ADD COLUMN kommunikation_sprache varchar(5) DEFAULT 'de'",
            'bevorzugter_kontakt' => "ALTER TABLE $table_name ADD COLUMN bevorzugter_kontakt varchar(20) DEFAULT 'email'",
            'kategorie' => "ALTER TABLE $table_name ADD COLUMN kategorie varchar(50) DEFAULT 'GDPR_SPAM'",
            'unterkategorie' => "ALTER TABLE $table_name ADD COLUMN unterkategorie varchar(50) DEFAULT 'Newsletter'",
            'bearbeitungsstatus' => "ALTER TABLE $table_name ADD COLUMN bearbeitungsstatus varchar(30) DEFAULT 'neu'",
            'prioritaet_intern' => "ALTER TABLE $table_name ADD COLUMN prioritaet_intern varchar(20) DEFAULT 'normal'",
            
            'anwaltsschreiben_status' => "ALTER TABLE $table_name ADD COLUMN anwaltsschreiben_status varchar(20) DEFAULT 'pending'",
            'mahnung_status' => "ALTER TABLE $table_name ADD COLUMN mahnung_status varchar(20) DEFAULT 'pending'",
            'klage_status' => "ALTER TABLE $table_name ADD COLUMN klage_status varchar(20) DEFAULT 'pending'",
            'vollstreckung_status' => "ALTER TABLE $table_name ADD COLUMN vollstreckung_status varchar(20) DEFAULT 'pending'",
            'egvp_aktenzeichen' => "ALTER TABLE $table_name ADD COLUMN egvp_aktenzeichen varchar(50) DEFAULT NULL",
            'xjustiz_uuid' => "ALTER TABLE $table_name ADD COLUMN xjustiz_uuid varchar(100) DEFAULT NULL",
            'import_source' => "ALTER TABLE $table_name ADD COLUMN import_source varchar(50) DEFAULT 'manual'"
        );
        
        // Get existing columns
        $existing_columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $existing_column_names = array();
        
        foreach ($existing_columns as $column) {
            $existing_column_names[] = $column->Field;
        }
        
        // Add missing columns
        foreach ($required_columns as $column_name => $alter_sql) {
            if (!in_array($column_name, $existing_column_names)) {
                $this->wpdb->query($alter_sql);
            }
        }
    }
    
    /**
     * Add missing columns to existing debtors table
     */
    private function add_missing_columns_to_debtors_table($table_name) {
        // Define columns that should exist
        $required_columns = array(
            'datenquelle' => "ALTER TABLE $table_name ADD COLUMN datenquelle varchar(50) DEFAULT 'manual'",
            'letzte_aktualisierung' => "ALTER TABLE $table_name ADD COLUMN letzte_aktualisierung datetime DEFAULT NULL",
            'website' => "ALTER TABLE $table_name ADD COLUMN website varchar(255)",
            'social_media' => "ALTER TABLE $table_name ADD COLUMN social_media text",
            'zahlungsverhalten' => "ALTER TABLE $table_name ADD COLUMN zahlungsverhalten varchar(20) DEFAULT 'unbekannt'",
            'bonität' => "ALTER TABLE $table_name ADD COLUMN bonität varchar(20) DEFAULT 'unbekannt'",
            'insolvenz_status' => "ALTER TABLE $table_name ADD COLUMN insolvenz_status varchar(20) DEFAULT 'nein'",
            'pfändung_status' => "ALTER TABLE $table_name ADD COLUMN pfändung_status varchar(20) DEFAULT 'nein'",
            'bevorzugte_sprache' => "ALTER TABLE $table_name ADD COLUMN bevorzugte_sprache varchar(5) DEFAULT 'de'",
            'kommunikation_email' => "ALTER TABLE $table_name ADD COLUMN kommunikation_email tinyint(1) DEFAULT 1",
            'kommunikation_post' => "ALTER TABLE $table_name ADD COLUMN kommunikation_post tinyint(1) DEFAULT 1",
            'verifiziert' => "ALTER TABLE $table_name ADD COLUMN verifiziert tinyint(1) DEFAULT 0"
        );
        
        // Get existing columns
        $existing_columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $existing_column_names = array();
        
        foreach ($existing_columns as $column) {
            $existing_column_names[] = $column->Field;
        }
        
        // Add missing columns
        foreach ($required_columns as $column_name => $alter_sql) {
            if (!in_array($column_name, $existing_column_names)) {
                $this->wpdb->query($alter_sql);
            }
        }
    }
    
    /**
     * Direct table creation method (bypasses dbDelta issues)
     */
    public function create_tables_direct() {
        $results = array(
            'success' => true,
            'message' => '',
            'details' => array()
        );
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // First, handle existing table updates
        $this->upgrade_existing_tables();
        
        // Ensure debtors table has correct schema
        $this->ensure_debtors_table_schema();
        
        // Fix missing columns in cases table
        $this->fix_missing_columns();
        
        // Define all tables with simpler SQL
        $tables = array(
            'klage_cases' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_cases (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id varchar(100) NOT NULL,
                case_creation_date datetime NOT NULL,
                case_updated_date datetime DEFAULT NULL,
                case_status varchar(20) DEFAULT 'draft',
                case_priority varchar(20) DEFAULT 'medium',
                case_notes text DEFAULT NULL,
                brief_status varchar(20) DEFAULT 'pending',
                submission_date date DEFAULT NULL,
                mandant varchar(100) DEFAULT NULL,
                client_id bigint(20) unsigned,
                debtor_id bigint(20) unsigned,
                total_amount decimal(10,2) DEFAULT 0.00,
                court_id bigint(20) unsigned,
                import_source varchar(50) DEFAULT NULL,
                
                -- Core Forderungen.com fields
                briefe int(3) DEFAULT 1,
                schuldner varchar(200) DEFAULT NULL,
                beweise text DEFAULT NULL,
                dokumente text DEFAULT NULL,
                links_zu_dokumenten text DEFAULT NULL,
                
                -- Legal Processing fields
                verfahrensart varchar(50) DEFAULT 'mahnverfahren',
                rechtsgrundlage varchar(100) DEFAULT 'DSGVO Art. 82',
                zeitraum_von date DEFAULT NULL,
                zeitraum_bis date DEFAULT NULL,
                anzahl_verstoesse int(5) DEFAULT 1,
                schadenhoehe decimal(10,2) DEFAULT 548.11,
                
                -- Document Management
                anwaltsschreiben_status varchar(20) DEFAULT 'pending',
                mahnung_status varchar(20) DEFAULT 'pending',
                klage_status varchar(20) DEFAULT 'pending',
                vollstreckung_status varchar(20) DEFAULT 'pending',
                
                -- Court Integration (EGVP/XJustiz)
                egvp_aktenzeichen varchar(50) DEFAULT NULL,
                xjustiz_uuid varchar(100) DEFAULT NULL,
                gericht_zustaendig varchar(100) DEFAULT NULL,
                verfahrenswert decimal(10,2) DEFAULT 548.11,
                
                -- Timeline Management
                deadline_antwort date DEFAULT NULL,
                deadline_zahlung date DEFAULT NULL,
                mahnung_datum date DEFAULT NULL,
                klage_datum date DEFAULT NULL,
                
                -- Risk Assessment
                erfolgsaussicht varchar(20) DEFAULT 'hoch',
                risiko_bewertung varchar(20) DEFAULT 'niedrig',
                processing_complexity varchar(20) DEFAULT 'standard',
                
                -- Communication
                kommunikation_sprache varchar(5) DEFAULT 'de',
                bevorzugter_kontakt varchar(20) DEFAULT 'email',
                
                -- Additional metadata
                kategorie varchar(50) DEFAULT 'GDPR_SPAM',
                unterkategorie varchar(50) DEFAULT 'Newsletter',
                bearbeitungsstatus varchar(30) DEFAULT 'neu',
                prioritaet_intern varchar(20) DEFAULT 'normal',
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY case_status (case_status),
                KEY debtor_id (debtor_id),
                KEY submission_date (submission_date)
            ) $charset_collate",
            
            'klage_clients' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_clients (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned,
                users_first_name varchar(100) NOT NULL,
                users_last_name varchar(100) NOT NULL,
                users_email varchar(255) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate",
            
            'klage_emails' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_emails (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                emails_received_date date NOT NULL,
                emails_received_time time NOT NULL,
                emails_sender_email varchar(255) NOT NULL,
                emails_user_email varchar(255) NOT NULL,
                emails_subject varchar(200),
                emails_content text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate",
            
            // Financial tables moved to separate plugin
            // 'klage_financial' => removed in v1.4.7
            
            'klage_courts' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_courts (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                court_name varchar(100) NOT NULL,
                court_address varchar(200) NOT NULL,
                court_egvp_id varchar(20),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate",
            
            'klage_audit' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_audit (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                action varchar(50) NOT NULL,
                details text,
                user_id bigint(20) unsigned NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate",
            
            'klage_debtors' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_debtors (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                
                -- Basic Information
                debtors_name varchar(200) NOT NULL,
                debtors_company varchar(200),
                debtors_first_name varchar(100),
                debtors_last_name varchar(100),
                debtors_email varchar(255),
                debtors_phone varchar(50),
                debtors_fax varchar(50),
                
                -- Address Information
                debtors_address varchar(200),
                debtors_street varchar(150),
                debtors_house_number varchar(20),
                debtors_address_addition varchar(100),
                debtors_postal_code varchar(20),
                debtors_city varchar(100),
                debtors_state varchar(100),
                debtors_country varchar(100) DEFAULT 'Deutschland',
                
                -- Legal Information
                rechtsform varchar(50) DEFAULT 'natuerliche_person',
                handelsregister_nr varchar(50),
                ustid varchar(50),
                geschaeftsfuehrer varchar(200),
                
                -- Additional Contact
                website varchar(255),
                social_media text,
                
                -- Financial Information
                zahlungsverhalten varchar(20) DEFAULT 'unbekannt',
                bonität varchar(20) DEFAULT 'unbekannt',
                
                -- Legal Status
                insolvenz_status varchar(20) DEFAULT 'nein',
                pfändung_status varchar(20) DEFAULT 'nein',
                
                -- Communication preferences
                bevorzugte_sprache varchar(5) DEFAULT 'de',
                kommunikation_email tinyint(1) DEFAULT 1,
                kommunikation_post tinyint(1) DEFAULT 1,
                
                -- Metadata
                datenquelle varchar(50) DEFAULT 'manual',
                verifiziert tinyint(1) DEFAULT 0,
                letzte_aktualisierung datetime DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY debtors_name (debtors_name),
                KEY debtors_email (debtors_email),
                KEY debtors_postal_code (debtors_postal_code),
                KEY debtors_city (debtors_city)
            ) $charset_collate",
            
            // Financial fields table moved to separate plugin
            // 'klage_financial_fields' => removed in v1.4.7
            
            'klage_import_templates' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_import_templates (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                template_name varchar(100) NOT NULL,
                template_type varchar(50) NOT NULL,
                field_mapping text NOT NULL,
                default_values text,
                validation_rules text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate",
            
            'klage_documents' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_documents (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                document_type varchar(50) NOT NULL,
                document_name varchar(200) NOT NULL,
                document_path varchar(500),
                document_url varchar(500),
                document_size bigint(20) DEFAULT 0,
                document_mime_type varchar(100),
                document_hash varchar(64),
                document_status varchar(20) DEFAULT 'active',
                created_by bigint(20) unsigned,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY document_type (document_type)
            ) $charset_collate",
            
            'klage_communications' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_communications (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                communication_type varchar(50) NOT NULL,
                direction varchar(20) NOT NULL,
                sender_name varchar(200),
                sender_email varchar(255),
                recipient_name varchar(200),
                recipient_email varchar(255),
                subject varchar(500),
                content text,
                sent_date datetime NOT NULL,
                status varchar(20) DEFAULT 'sent',
                tracking_id varchar(100),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY communication_type (communication_type),
                KEY sent_date (sent_date)
            ) $charset_collate",
            
            'klage_deadlines' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_deadlines (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                deadline_type varchar(50) NOT NULL,
                deadline_date date NOT NULL,
                deadline_time time DEFAULT '23:59:59',
                description text,
                reminder_sent tinyint(1) DEFAULT 0,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY deadline_date (deadline_date),
                KEY deadline_type (deadline_type)
            ) $charset_collate",
            
            'klage_case_history' => "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}klage_case_history (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                action_type varchar(50) NOT NULL,
                action_description text,
                old_value text,
                new_value text,
                user_id bigint(20) unsigned,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY action_type (action_type),
                KEY created_at (created_at)
            ) $charset_collate"
        );
        
        // Create each table individually
        $created_count = 0;
        $failed_count = 0;
        
        foreach ($tables as $table_name => $sql) {
            $result = $this->wpdb->query($sql);
            
            if ($result !== false) {
                $created_count++;
                $results['details'][] = "✅ $table_name: Erfolgreich erstellt";
            } else {
                $failed_count++;
                $results['details'][] = "❌ $table_name: Fehler - " . $this->wpdb->last_error;
                $results['success'] = false;
            }
        }
        
        // Insert default courts if courts table was created
        if ($created_count > 0) {
            $this->insert_default_courts();
        }
        
        if ($results['success']) {
            $results['message'] = "$created_count Tabellen erfolgreich erstellt. Dashboard aktualisieren!";
        } else {
            $results['message'] = "$failed_count Tabellen fehlgeschlagen. Debug-Modus aktivieren für Details.";
        }
        
        return $results;
    }
    
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Cases table
        $sql_cases = "CREATE TABLE {$this->wpdb->prefix}klage_cases (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            case_id varchar(100) NOT NULL UNIQUE,
            case_creation_date datetime NOT NULL,
            case_status enum('draft','pending','processing','completed','cancelled') DEFAULT 'draft',
            case_priority enum('low','medium','high','urgent') DEFAULT 'medium',
            client_id bigint(20) unsigned,
            debtor_id bigint(20) unsigned,
            case_deadline_response date,
            case_deadline_payment date,
            processing_complexity enum('simple','standard','complex') DEFAULT 'standard',
            processing_risk_score tinyint(3) unsigned DEFAULT 3,
            document_type enum('mahnbescheid','klage') DEFAULT 'mahnbescheid',
            document_language varchar(2) DEFAULT 'de',
            total_amount decimal(10,2) DEFAULT 0.00,
            court_id bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY case_id (case_id),
            KEY case_status (case_status),
            KEY client_id (client_id),
            KEY debtor_id (debtor_id),
            KEY court_id (court_id)
        ) $charset_collate;";
        
        // Debtors table
        $sql_debtors = "CREATE TABLE {$this->wpdb->prefix}klage_debtors (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            debtors_name varchar(200) NOT NULL,
            debtors_legal_form enum('einzelperson','gmbh','ag','kg','ohg','gbr','ev','andere') DEFAULT 'einzelperson',
            debtors_first_name varchar(100),
            debtors_last_name varchar(100),
            debtors_street varchar(100) NOT NULL,
            debtors_house_number varchar(10) NOT NULL,
            debtors_postal_code varchar(5) NOT NULL,
            debtors_city varchar(100) NOT NULL,
            debtors_country varchar(100) DEFAULT 'Deutschland',
            debtors_email varchar(255),
            debtors_phone varchar(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY debtors_name (debtors_name),
            KEY debtors_postal_code (debtors_postal_code),
            KEY debtors_city (debtors_city)
        ) $charset_collate;";
        
        // Clients table
        $sql_clients = "CREATE TABLE {$this->wpdb->prefix}klage_clients (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            users_first_name varchar(100) NOT NULL,
            users_last_name varchar(100) NOT NULL,
            users_email varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Emails table
        $sql_emails = "CREATE TABLE {$this->wpdb->prefix}klage_emails (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            case_id bigint(20) unsigned NOT NULL,
            emails_received_date date NOT NULL,
            emails_received_time time NOT NULL,
            emails_sender_email varchar(255) NOT NULL,
            emails_user_email varchar(255) NOT NULL,
            emails_subject varchar(200),
            emails_content text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Financial table removed in v1.4.7 - moved to separate plugin
        // Financial functionality now handled by court-automation-hub-financial-calculator plugin
        
        // Courts table
        $sql_courts = "CREATE TABLE {$this->wpdb->prefix}klage_courts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            court_name varchar(100) NOT NULL,
            court_address varchar(200) NOT NULL,
            court_egvp_id varchar(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Execute all table creation queries
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create each table individually with error checking
        $results = array();
        
        $results['cases'] = dbDelta($sql_cases);
        $results['debtors'] = dbDelta($sql_debtors);
        $results['clients'] = dbDelta($sql_clients);
        $results['emails'] = dbDelta($sql_emails);
        // Financial table removed in v1.4.7 - moved to separate plugin
        $results['courts'] = dbDelta($sql_courts);
        
        // Insert default courts
        $this->insert_default_courts();
        
        // Log results for debugging
        if (get_option('klage_click_debug_mode')) {
            error_log('Klage.Click Database Creation Results: ' . print_r($results, true));
        }
        
        return $results;
    }
    
    private function insert_default_courts() {
        // Check if courts already exist
        $court_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}klage_courts");
        
        if ($court_count == 0) {
            // Insert default German courts
            $default_courts = array(
                array(
                    'court_name' => 'Amtsgericht Frankfurt am Main',
                    'court_address' => 'Gerichtsstraße 2, 60313 Frankfurt am Main',
                    'court_egvp_id' => 'AG.FFM.001'
                ),
                array(
                    'court_name' => 'Amtsgericht München',
                    'court_address' => 'Pacellistraße 5, 80333 München',
                    'court_egvp_id' => 'AG.MUC.001'
                ),
                array(
                    'court_name' => 'Amtsgericht Berlin-Mitte',
                    'court_address' => 'Littenstraße 12-17, 10179 Berlin',
                    'court_egvp_id' => 'AG.BER.001'
                ),
                array(
                    'court_name' => 'Amtsgericht Hamburg',
                    'court_address' => 'Sievekingplatz 1, 20355 Hamburg',
                    'court_egvp_id' => 'AG.HAM.001'
                )
            );
            
            foreach ($default_courts as $court) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'klage_courts',
                    $court,
                    array('%s', '%s', '%s')
                );
            }
        }
    }
    
    public function get_table_status() {
        $tables = array('klage_cases', 'klage_debtors', 'klage_clients', 'klage_emails', 'klage_courts');
        $status = array();
        
        foreach ($tables as $table) {
            $full_table_name = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            $count = $exists ? $this->wpdb->get_var("SELECT COUNT(*) FROM $full_table_name") : 0;
            
            $status[$table] = array(
                'exists' => !empty($exists),
                'count' => $count
            );
        }
        
        return $status;
    }
    
    private function fix_missing_columns() {
        // Fix missing case_id column in klage_cases table
        $table_name = $this->wpdb->prefix . 'klage_cases';
        
        // Check if case_id column exists
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'case_id'");
        
        // Check if case_id column exists and fix its constraints
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'case_id'");
        
        if (empty($columns)) {
            // Add missing case_id column (NOT unique - should be editable)
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN case_id varchar(100) AFTER id");
            
            // Generate case_id values for existing rows
            $existing_rows = $this->wpdb->get_results("SELECT id FROM $table_name WHERE case_id IS NULL OR case_id = ''");
            foreach ($existing_rows as $row) {
                $case_id = 'SPAM-' . date('Y') . '-' . str_pad($row->id, 4, '0', STR_PAD_LEFT);
                $this->wpdb->update($table_name, array('case_id' => $case_id), array('id' => $row->id));
            }
        } else {
            // Remove UNIQUE constraint if it exists (case_id should be editable)
            $this->wpdb->query("ALTER TABLE $table_name DROP INDEX case_id");
            $this->wpdb->query("ALTER TABLE $table_name ADD INDEX case_id (case_id)");
        }
        
        // Add other missing columns as needed
        $this->add_missing_column($table_name, 'case_creation_date', 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->add_missing_column($table_name, 'case_status', "enum('draft','pending','processing','completed','cancelled') DEFAULT 'draft'");
        $this->add_missing_column($table_name, 'case_priority', "enum('low','medium','high','urgent') DEFAULT 'medium'");
        $this->add_missing_column($table_name, 'processing_complexity', "enum('simple','standard','complex') DEFAULT 'standard'");
        $this->add_missing_column($table_name, 'processing_risk_score', 'tinyint(3) unsigned DEFAULT 3');
        $this->add_missing_column($table_name, 'document_type', "enum('mahnbescheid','klage') DEFAULT 'mahnbescheid'");
        $this->add_missing_column($table_name, 'document_language', "varchar(2) DEFAULT 'de'");
        $this->add_missing_column($table_name, 'total_amount', 'decimal(10,2) DEFAULT 0.00');
    }
    
    private function add_missing_column($table_name, $column_name, $column_definition) {
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column_name'");
        if (empty($columns)) {
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN $column_name $column_definition");
        }
    }
    
    /**
     * COMPREHENSIVE SCHEMA INTEGRITY CHECK using dbDelta()
     * This method ensures ALL tables have the complete correct schema
     * Safe for existing data - only creates missing tables/columns
     */
    public function ensure_complete_schema_integrity() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array(
            'success' => true,
            'message' => '',
            'details' => array(),
            'tables_created' => 0,
            'columns_added' => 0,
            'errors' => array()
        );
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Define complete schema for all tables using dbDelta format
        $table_schemas = array(
            
            // CASES TABLE - Complete schema with ENGLISH INTERNAL NAMING + Forderungen.com fields v1.9.0
            'klage_cases' => "CREATE TABLE {$this->wpdb->prefix}klage_cases (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id varchar(100) NOT NULL,
                case_creation_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                case_updated_date datetime DEFAULT NULL,
                case_status varchar(20) DEFAULT 'draft',
                case_priority varchar(20) DEFAULT 'medium',
                case_notes text DEFAULT NULL,
                brief_status varchar(20) DEFAULT 'pending',
                submission_date date DEFAULT NULL,
                mandant varchar(100) DEFAULT NULL,
                client_id bigint(20) unsigned DEFAULT NULL,
                debtor_id bigint(20) unsigned DEFAULT NULL,
                total_amount decimal(10,2) DEFAULT 0.00,
                court_id bigint(20) unsigned DEFAULT NULL,
                import_source varchar(50) DEFAULT 'manual',
                briefe int(3) DEFAULT 1,
                schuldner varchar(200) DEFAULT NULL,
                beweise text DEFAULT NULL,
                dokumente text DEFAULT NULL,
                links_zu_dokumenten text DEFAULT NULL,
                
                -- Forderungen.com specific fields v1.9.0
                external_id varchar(100) DEFAULT NULL,
                outbound_letters_status varchar(50) DEFAULT NULL,
                outbound_letters_pdf_url text DEFAULT NULL,
                art15_claim_damages decimal(10,2) DEFAULT NULL,
                case_documents_attachments text DEFAULT NULL,
                number_of_spam_emails int(5) DEFAULT NULL,
                
                procedure_type varchar(50) DEFAULT 'dunning_procedure',
                legal_basis varchar(100) DEFAULT 'GDPR Art. 82',
                period_start date DEFAULT NULL,
                period_end date DEFAULT NULL,
                violation_count int(5) DEFAULT 1,
                damage_amount decimal(10,2) DEFAULT 548.11,
                anwaltsschreiben_status varchar(20) DEFAULT 'pending',
                mahnung_status varchar(20) DEFAULT 'pending',
                klage_status varchar(20) DEFAULT 'pending',
                vollstreckung_status varchar(20) DEFAULT 'pending',
                egvp_aktenzeichen varchar(50) DEFAULT NULL,
                xjustiz_uuid varchar(100) DEFAULT NULL,
                responsible_court varchar(100) DEFAULT NULL,
                procedure_value decimal(10,2) DEFAULT 548.11,
                response_deadline date DEFAULT NULL,
                payment_deadline date DEFAULT NULL,
                case_deadline_response date DEFAULT NULL,
                case_deadline_payment date DEFAULT NULL,
                reminder_date date DEFAULT NULL,
                lawsuit_date date DEFAULT NULL,
                success_probability varchar(20) DEFAULT 'high',
                risk_assessment varchar(20) DEFAULT 'low',
                processing_complexity varchar(20) DEFAULT 'standard',
                processing_risk_score tinyint(3) unsigned DEFAULT 3,
                document_type enum('mahnbescheid','klage') DEFAULT 'mahnbescheid',
                document_language varchar(5) DEFAULT 'de',
                communication_language varchar(5) DEFAULT 'de',
                preferred_contact varchar(20) DEFAULT 'email',
                category varchar(50) DEFAULT 'GDPR_SPAM',
                subcategory varchar(50) DEFAULT 'Newsletter',
                processing_status varchar(30) DEFAULT 'new',
                internal_priority varchar(20) DEFAULT 'normal',
                
                verfahrensart varchar(50) DEFAULT 'mahnverfahren',
                rechtsgrundlage varchar(100) DEFAULT 'DSGVO Art. 82',
                zeitraum_von date DEFAULT NULL,
                zeitraum_bis date DEFAULT NULL,
                anzahl_verstoesse int(5) DEFAULT 1,
                schadenhoehe decimal(10,2) DEFAULT 548.11,
                gericht_zustaendig varchar(100) DEFAULT NULL,
                verfahrenswert decimal(10,2) DEFAULT 548.11,
                deadline_antwort date DEFAULT NULL,
                deadline_zahlung date DEFAULT NULL,
                mahnung_datum date DEFAULT NULL,
                klage_datum date DEFAULT NULL,
                erfolgsaussicht varchar(20) DEFAULT 'hoch',
                risiko_bewertung varchar(20) DEFAULT 'niedrig',
                kommunikation_sprache varchar(5) DEFAULT 'de',
                bevorzugter_kontakt varchar(20) DEFAULT 'email',
                kategorie varchar(50) DEFAULT 'GDPR_SPAM',
                unterkategorie varchar(50) DEFAULT 'Newsletter',
                bearbeitungsstatus varchar(30) DEFAULT 'neu',
                prioritaet_intern varchar(20) DEFAULT 'normal',
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY case_status (case_status),
                KEY debtor_id (debtor_id),
                KEY submission_date (submission_date),
                KEY external_id (external_id),
                KEY import_source (import_source)
            ) $charset_collate;",
            
            // DEBTORS TABLE - Complete schema with ALL required columns
            'klage_debtors' => "CREATE TABLE {$this->wpdb->prefix}klage_debtors (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                debtors_name varchar(200) NOT NULL,
                debtors_company varchar(200) DEFAULT NULL,
                debtors_first_name varchar(100) DEFAULT NULL,
                debtors_last_name varchar(100) DEFAULT NULL,
                debtors_email varchar(255) DEFAULT NULL,
                debtors_phone varchar(50) DEFAULT NULL,
                debtors_fax varchar(50) DEFAULT NULL,
                debtors_address varchar(200) DEFAULT NULL,
                debtors_street varchar(150) DEFAULT NULL,
                debtors_house_number varchar(20) DEFAULT NULL,
                debtors_address_addition varchar(100) DEFAULT NULL,
                debtors_postal_code varchar(20) DEFAULT NULL,
                debtors_city varchar(100) DEFAULT NULL,
                debtors_state varchar(100) DEFAULT NULL,
                debtors_country varchar(100) DEFAULT 'Deutschland',
                rechtsform varchar(50) DEFAULT 'natuerliche_person',
                handelsregister_nr varchar(50) DEFAULT NULL,
                ustid varchar(50) DEFAULT NULL,
                geschaeftsfuehrer varchar(200) DEFAULT NULL,
                website varchar(255) DEFAULT NULL,
                social_media text DEFAULT NULL,
                zahlungsverhalten varchar(20) DEFAULT 'unbekannt',
                bonität varchar(20) DEFAULT 'unbekannt',
                insolvenz_status varchar(20) DEFAULT 'nein',
                pfändung_status varchar(20) DEFAULT 'nein',
                bevorzugte_sprache varchar(5) DEFAULT 'de',
                kommunikation_email tinyint(1) DEFAULT 1,
                kommunikation_post tinyint(1) DEFAULT 1,
                datenquelle varchar(50) DEFAULT 'manual',
                verifiziert tinyint(1) DEFAULT 0,
                letzte_aktualisierung datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY debtors_name (debtors_name),
                KEY debtors_email (debtors_email),
                KEY debtors_postal_code (debtors_postal_code),
                KEY debtors_city (debtors_city)
            ) $charset_collate;",
            
            // CLIENTS TABLE - Extended for Forderungen.com v1.9.0
            'klage_clients' => "CREATE TABLE {$this->wpdb->prefix}klage_clients (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned DEFAULT NULL,
                external_user_id varchar(100) DEFAULT NULL,
                users_first_name varchar(100) NOT NULL,
                users_last_name varchar(100) NOT NULL,
                users_email varchar(255) NOT NULL,
                users_street varchar(150) DEFAULT NULL,
                users_street_number varchar(20) DEFAULT NULL,
                users_postal_code varchar(20) DEFAULT NULL,
                users_city varchar(100) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY external_user_id (external_user_id),
                KEY users_email (users_email)
            ) $charset_collate;",
            
            // EMAILS TABLE - Extended for Forderungen.com v1.9.0
            'klage_emails' => "CREATE TABLE {$this->wpdb->prefix}klage_emails (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                emails_received_date date NOT NULL,
                emails_received_time time NOT NULL,
                emails_sender_email varchar(255) NOT NULL,
                emails_user_email varchar(255) NOT NULL,
                emails_subject varchar(200) DEFAULT NULL,
                emails_content text DEFAULT NULL,
                emails_tracking_pixel text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY emails_sender_email (emails_sender_email),
                KEY received_date (emails_received_date)
            ) $charset_collate;",
            
            // COURTS TABLE
            'klage_courts' => "CREATE TABLE {$this->wpdb->prefix}klage_courts (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                court_name varchar(100) NOT NULL,
                court_address varchar(200) NOT NULL,
                court_egvp_id varchar(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            
            // AUDIT TABLE
            'klage_audit' => "CREATE TABLE {$this->wpdb->prefix}klage_audit (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                action varchar(50) NOT NULL,
                details text DEFAULT NULL,
                user_id bigint(20) unsigned NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            
            // IMPORT CONFIGURATIONS TABLE - New in v1.9.0
            'klage_import_configs' => "CREATE TABLE {$this->wpdb->prefix}klage_import_configs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                config_name varchar(100) NOT NULL,
                client_type varchar(50) NOT NULL,
                field_mappings text NOT NULL,
                validation_rules text DEFAULT NULL,
                default_values text DEFAULT NULL,
                is_active tinyint(1) DEFAULT 1,
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY client_type (client_type),
                KEY is_active (is_active)
            ) $charset_collate;",
            
            // IMPORT HISTORY TABLE - New in v1.9.0
            'klage_import_history' => "CREATE TABLE {$this->wpdb->prefix}klage_import_history (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                import_type varchar(50) NOT NULL,
                client_type varchar(50) NOT NULL,
                filename varchar(255) DEFAULT NULL,
                total_rows int(10) unsigned DEFAULT 0,
                successful_imports int(10) unsigned DEFAULT 0,
                failed_imports int(10) unsigned DEFAULT 0,
                import_status varchar(20) DEFAULT 'completed',
                error_log text DEFAULT NULL,
                config_used text DEFAULT NULL,
                imported_by bigint(20) unsigned NOT NULL,
                import_duration int(10) unsigned DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY import_type (import_type),
                KEY client_type (client_type),
                KEY import_status (import_status),
                KEY created_at (created_at)
            ) $charset_collate;"
        );
        
        // Execute dbDelta for each table
        foreach ($table_schemas as $table_name => $schema_sql) {
            try {
                $result = dbDelta($schema_sql);
                
                if (!empty($result)) {
                    $results['details'][] = "✅ $table_name: " . implode(', ', $result);
                    if (strpos(implode(' ', $result), 'Created table') !== false) {
                        $results['tables_created']++;
                    }
                    if (strpos(implode(' ', $result), 'Added column') !== false) {
                        $results['columns_added']++;
                    }
                } else {
                    $results['details'][] = "✅ $table_name: Schema bereits aktuell";
                }
                
            } catch (Exception $e) {
                $results['success'] = false;
                $results['errors'][] = "$table_name: " . $e->getMessage();
                $results['details'][] = "❌ $table_name: Fehler - " . $e->getMessage();
            }
        }
        
        // Insert default data if new tables were created
        if ($results['tables_created'] > 0) {
            $this->insert_default_courts();
        }
        
        // Generate summary message
        if ($results['success']) {
            $message_parts = array();
            if ($results['tables_created'] > 0) {
                $message_parts[] = "{$results['tables_created']} Tabellen erstellt";
            }
            if ($results['columns_added'] > 0) {
                $message_parts[] = "{$results['columns_added']} Spalten hinzugefügt";  
            }
            if (empty($message_parts)) {
                $results['message'] = "Alle Tabellen sind bereits auf dem neuesten Stand";
            } else {
                $results['message'] = "Schema-Update erfolgreich: " . implode(', ', $message_parts);
            }
        } else {
            $results['message'] = "Schema-Update fehlgeschlagen: " . implode('; ', $results['errors']);
        }
        
        // Log the operation
        error_log('CAH Schema Integrity Check v1.7.3: ' . $results['message']);
        
        return $results;
    }
    
    /**
     * PRE-DEPLOYMENT VALIDATION
     * Comprehensive testing of database schema integrity
     */
    public function validate_schema_integrity() {
        $validation = array(
            'overall_status' => 'PASS',
            'confidence_score' => 100,
            'table_status' => array(),
            'column_checks' => array(),
            'issues_found' => array(),
            'recommendations' => array()
        );
        
        // Critical tables that must exist
        $required_tables = array(
            'klage_cases' => array(
                'case_id', 'case_creation_date', 'debtor_id', 'client_id',
                'debtors_company', 'debtors_postal_code' // These were the missing columns
            ),
            'klage_debtors' => array(
                'debtors_name', 'debtors_company', 'debtors_postal_code', 
                'debtors_city', 'debtors_email', 'created_at'
            ),
            'klage_clients' => array(
                'users_first_name', 'users_last_name', 'users_email'
            ),
            'klage_courts' => array(
                'court_name', 'court_address', 'court_egvp_id'
            ),
            'klage_emails' => array(
                'case_id', 'emails_sender_email', 'emails_subject'
            )
        );
        
        foreach ($required_tables as $table_name => $required_columns) {
            $full_table_name = $this->wpdb->prefix . $table_name;
            
            // Check if table exists
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if (!$table_exists) {
                $validation['overall_status'] = 'FAIL';
                $validation['confidence_score'] -= 20;
                $validation['issues_found'][] = "CRITICAL: Table $table_name does not exist";
                $validation['table_status'][$table_name] = 'MISSING';
                continue;
            }
            
            $validation['table_status'][$table_name] = 'EXISTS';
            
            // Check required columns
            $existing_columns = $this->wpdb->get_results("SHOW COLUMNS FROM $full_table_name");
            $existing_column_names = array();
            foreach ($existing_columns as $column) {
                $existing_column_names[] = $column->Field;
            }
            
            foreach ($required_columns as $required_column) {
                if (!in_array($required_column, $existing_column_names)) {
                    $validation['overall_status'] = 'FAIL';
                    $validation['confidence_score'] -= 5;
                    $validation['issues_found'][] = "Missing column: $table_name.$required_column";
                    $validation['column_checks'][$table_name][$required_column] = 'MISSING';
                } else {
                    $validation['column_checks'][$table_name][$required_column] = 'OK';
                }
            }
        }
        
        // Generate recommendations based on findings
        if ($validation['confidence_score'] < 100) {
            if ($validation['confidence_score'] >= 80) {
                $validation['recommendations'][] = "Minor schema issues detected. Run schema integrity repair.";
            } elseif ($validation['confidence_score'] >= 60) {
                $validation['recommendations'][] = "Moderate schema issues. Backup data before running repair.";
            } else {
                $validation['recommendations'][] = "CRITICAL: Major schema problems. DO NOT DEPLOY. Contact support.";
            }
        } else {
            $validation['recommendations'][] = "Schema is healthy. Safe to deploy.";
        }
        
        return $validation;
    }
    
    
    /**
     * MIGRATION: Populate English columns from German ones
     * Ensures data consistency during German → English transition
     */
    public function migrate_german_to_english_columns() {
        $migration_results = array(
            'success' => true,
            'message' => '',
            'migrations_performed' => array()
        );
        
        $cases_table = $this->wpdb->prefix . 'klage_cases';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$cases_table'");
        if (!$table_exists) {
            $migration_results['message'] = 'Cases table does not exist - nothing to migrate';
            return $migration_results;
        }
        
        // Define German → English column mappings with value translations
        $column_mappings = array(
            // Direct column mappings
            'verfahrensart' => 'procedure_type',
            'rechtsgrundlage' => 'legal_basis', 
            'zeitraum_von' => 'period_start',
            'zeitraum_bis' => 'period_end',
            'anzahl_verstoesse' => 'violation_count',
            'schadenhoehe' => 'damage_amount',
            'gericht_zustaendig' => 'responsible_court',
            'verfahrenswert' => 'procedure_value',
            'deadline_antwort' => 'response_deadline',
            'deadline_zahlung' => 'payment_deadline',
            'mahnung_datum' => 'reminder_date',
            'klage_datum' => 'lawsuit_date',
            'kommunikation_sprache' => 'communication_language',
            'kategorie' => 'category',
            'unterkategorie' => 'subcategory'
        );
        
        // Value translation mappings
        $value_mappings = array(
            'procedure_type' => array(
                'mahnverfahren' => 'dunning_procedure',
                'klage' => 'lawsuit',
                'vollstreckung' => 'enforcement'
            ),
            'success_probability' => array(
                'hoch' => 'high',
                'mittel' => 'medium', 
                'niedrig' => 'low'
            ),
            'risk_assessment' => array(
                'niedrig' => 'low',
                'mittel' => 'medium',
                'hoch' => 'high'
            ),
            'processing_complexity' => array(
                'einfach' => 'simple',
                'standard' => 'standard',
                'komplex' => 'complex'
            ),
            'preferred_contact' => array(
                'email' => 'email',
                'phone' => 'phone', 
                'post' => 'mail'
            ),
            'processing_status' => array(
                'neu' => 'new',
                'bearbeitung' => 'processing',
                'review' => 'review',
                'abgeschlossen' => 'completed'
            ),
            'internal_priority' => array(
                'normal' => 'normal',
                'niedrig' => 'low',
                'hoch' => 'high',
                'dringend' => 'urgent'
            )
        );
        
        // Perform direct column migrations
        foreach ($column_mappings as $german_col => $english_col) {
            $sql = "UPDATE $cases_table SET $english_col = $german_col WHERE ($english_col IS NULL OR $english_col = '') AND $german_col IS NOT NULL AND $german_col != ''";
            $result = $this->wpdb->query($sql);
            
            if ($result !== false) {
                $migration_results['migrations_performed'][] = "Migrated $german_col → $english_col ($result rows)";
            } else {
                $migration_results['success'] = false;
                $migration_results['migrations_performed'][] = "Failed to migrate $german_col → $english_col: " . $this->wpdb->last_error;
            }
        }
        
        // Perform value translation migrations
        $translation_mappings = array(
            'erfolgsaussicht' => array('target' => 'success_probability', 'translations' => $value_mappings['success_probability']),
            'risiko_bewertung' => array('target' => 'risk_assessment', 'translations' => $value_mappings['risk_assessment']),
            'bearbeitungsstatus' => array('target' => 'processing_status', 'translations' => $value_mappings['processing_status']),
            'prioritaet_intern' => array('target' => 'internal_priority', 'translations' => $value_mappings['internal_priority']),
            'bevorzugter_kontakt' => array('target' => 'preferred_contact', 'translations' => $value_mappings['preferred_contact'])
        );
        
        foreach ($translation_mappings as $german_col => $config) {
            $english_col = $config['target'];
            $translations = $config['translations'];
            
            foreach ($translations as $german_value => $english_value) {
                $sql = $this->wpdb->prepare(
                    "UPDATE $cases_table SET $english_col = %s WHERE $german_col = %s AND ($english_col IS NULL OR $english_col = '')",
                    $english_value,
                    $german_value
                );
                $result = $this->wpdb->query($sql);
                
                if ($result !== false && $result > 0) {
                    $migration_results['migrations_performed'][] = "Translated $german_col '$german_value' → $english_col '$english_value' ($result rows)";
                }
            }
        }
        
        if ($migration_results['success']) {
            $migration_results['message'] = 'German → English column migration completed successfully';
        } else {
            $migration_results['message'] = 'German → English column migration completed with some errors';
        }
        
        return $migration_results;
    }
    public function safe_add_missing_columns_only() {
        // LEGACY METHOD - Use ensure_complete_schema_integrity() instead
        error_log('WARNING: safe_add_missing_columns_only() is deprecated. Use ensure_complete_schema_integrity()');
        return $this->ensure_complete_schema_integrity();
    }
}