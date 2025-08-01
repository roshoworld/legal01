<?php
/**
 * Database Schema - Case Creation & Totals Fix
 * Contact-centric architecture with flexible role assignments
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Database_v200 {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create comprehensive schema
     */
    public function create_v200_schema() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $schemas = array(
            
            // UNIVERSAL CONTACTS TABLE - Industry Best Practice
            'klage_contacts' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_contacts` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                contact_type enum('person','company') NOT NULL DEFAULT 'person',
                first_name varchar(100) DEFAULT NULL,
                last_name varchar(100) DEFAULT NULL,
                company_name varchar(200) DEFAULT NULL,
                email varchar(255) DEFAULT NULL,
                phone varchar(50) DEFAULT NULL,
                
                -- Full Address Information
                street varchar(150) DEFAULT NULL,
                street_number varchar(20) DEFAULT NULL,
                postal_code varchar(20) DEFAULT NULL,
                city varchar(100) DEFAULT NULL,
                country varchar(100) DEFAULT 'Deutschland',
                
                -- Banking Information
                iban varchar(34) DEFAULT NULL,
                bic varchar(11) DEFAULT NULL,
                bank_name varchar(100) DEFAULT NULL,
                
                -- Schufa Information (Manual Entry)
                schufa_status enum('ok','nok','pending','na') DEFAULT 'na',
                schufa_score int(4) DEFAULT NULL,
                schufa_checked_date date DEFAULT NULL,
                
                -- Status & Metadata
                active_status tinyint(1) DEFAULT 1,
                notes text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY contact_type (contact_type),
                KEY active_status (active_status),
                KEY company_name (company_name),
                KEY email (email),
                UNIQUE KEY unique_contact (first_name, last_name, company_name, email)
            ) $charset_collate;",
            
            // ENHANCED CASES TABLE v2.0.0
            'klage_cases' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_cases` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id varchar(100) NOT NULL,
                case_creation_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                case_updated_date datetime DEFAULT NULL,
                case_status varchar(20) DEFAULT 'draft',
                case_priority varchar(20) DEFAULT 'medium',
                case_notes text DEFAULT NULL,
                
                -- v2.0.0 Enhancements
                active_status enum('active','inactive','archived') DEFAULT 'active',
                schufa_checks_completed tinyint(1) DEFAULT 0,
                case_complexity enum('simple','medium','complex') DEFAULT 'medium',
                
                -- Financial Information
                claim_amount decimal(10,2) DEFAULT 548.11,
                total_amount decimal(10,2) DEFAULT 0.00,
                damage_amount decimal(10,2) DEFAULT 548.11,
                art15_claim_damages decimal(10,2) DEFAULT NULL,
                legal_fees decimal(10,2) DEFAULT 0.00,
                court_fees decimal(10,2) DEFAULT 0.00,
                
                -- Court & Legal Information
                court_id bigint(20) unsigned DEFAULT NULL,
                assigned_tv_lawyer_id bigint(20) unsigned DEFAULT NULL,
                procedure_type varchar(50) DEFAULT 'dunning_procedure',
                legal_basis varchar(100) DEFAULT 'GDPR Art. 82',
                
                -- Important Dates
                filing_date date DEFAULT NULL,
                response_deadline date DEFAULT NULL,
                payment_deadline date DEFAULT NULL,
                next_hearing_date datetime DEFAULT NULL,
                
                -- Document & Communication
                document_type enum('mahnbescheid','klage') DEFAULT 'mahnbescheid',
                case_documents_attachments text DEFAULT NULL,
                
                -- Import & Integration
                external_id varchar(100) DEFAULT NULL,
                import_source varchar(50) DEFAULT 'manual',
                
                -- Legacy compatibility fields
                brief_status varchar(20) DEFAULT 'pending',
                submission_date date DEFAULT NULL,
                mandant varchar(100) DEFAULT NULL,
                client_id bigint(20) unsigned DEFAULT NULL,
                debtor_id bigint(20) unsigned DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY case_status (case_status),
                KEY active_status (active_status),
                KEY court_id (court_id),
                KEY assigned_tv_lawyer_id (assigned_tv_lawyer_id),
                KEY external_id (external_id),
                KEY import_source (import_source),
                KEY next_hearing_date (next_hearing_date),
                UNIQUE KEY unique_case_id (case_id)
            ) $charset_collate;",
            
            // CASE-CONTACT RELATIONSHIPS - Flexible Role System
            'klage_case_contacts' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_case_contacts` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                contact_id bigint(20) unsigned NOT NULL,
                
                -- Flexible Role System
                role enum('debtor','plaintiff','debtor_rep','plaintiff_rep','legal_counsel','tv_lawyer','court_contact','witness','expert') NOT NULL,
                role_details json DEFAULT NULL,
                role_description varchar(255) DEFAULT NULL,
                
                -- Relationship Status
                active_status tinyint(1) DEFAULT 1,
                assigned_date date DEFAULT NULL,
                removed_date date DEFAULT NULL,
                assignment_notes text DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY contact_id (contact_id),
                KEY role (role),
                KEY active_status (active_status),
                UNIQUE KEY unique_case_contact_role (case_id, contact_id, role)
            ) $charset_collate;",
            
            // TV (TERMINVERTRETUNG) MANAGEMENT
            'klage_tv_assignments' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_tv_assignments` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                tv_lawyer_contact_id bigint(20) unsigned NOT NULL,
                
                -- Court Date Information
                court_date datetime NOT NULL,
                court_location varchar(200) DEFAULT NULL,
                hearing_type varchar(100) DEFAULT NULL,
                estimated_duration int(3) DEFAULT 120, -- minutes
                
                -- Assignment Status
                status enum('requested','confirmed','declined','completed','cancelled') DEFAULT 'requested',
                request_sent_date datetime DEFAULT NULL,
                response_date datetime DEFAULT NULL,
                
                -- Financial Information
                consultation_fee decimal(8,2) DEFAULT NULL,
                hourly_rate decimal(8,2) DEFAULT NULL,
                estimated_cost decimal(8,2) DEFAULT NULL,
                
                -- Workflow Status
                case_info_sent tinyint(1) DEFAULT 0,
                report_received tinyint(1) DEFAULT 0,
                report_content text DEFAULT NULL,
                invoice_sent tinyint(1) DEFAULT 0,
                payment_status enum('pending','paid','overdue') DEFAULT 'pending',
                
                -- Communication
                request_notes text DEFAULT NULL,
                assignment_notes text DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY tv_lawyer_contact_id (tv_lawyer_contact_id),
                KEY court_date (court_date),
                KEY status (status),
                KEY payment_status (payment_status)
            ) $charset_collate;",
            
            // FINANCIAL TRANSACTIONS
            'klage_financials' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_financials` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                
                -- Transaction Information
                transaction_type enum('payment_in','payment_out','fee','expense','claim') NOT NULL,
                amount decimal(10,2) NOT NULL,
                currency varchar(3) DEFAULT 'EUR',
                
                -- Purpose & Description
                purpose varchar(255) NOT NULL,
                description text DEFAULT NULL,
                category varchar(100) DEFAULT NULL,
                
                -- Transaction Status
                status enum('pending','completed','failed','cancelled') DEFAULT 'pending',
                transaction_date date DEFAULT NULL,
                due_date date DEFAULT NULL,
                
                -- Invoice/Payment Details
                invoice_number varchar(100) DEFAULT NULL,
                invoice_date date DEFAULT NULL,
                payment_reference varchar(255) DEFAULT NULL,
                
                -- Related Contacts
                payer_contact_id bigint(20) unsigned DEFAULT NULL,
                payee_contact_id bigint(20) unsigned DEFAULT NULL,
                
                -- Integration Fields
                external_reference varchar(255) DEFAULT NULL,
                booking_reference varchar(255) DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY transaction_type (transaction_type),
                KEY status (status),
                KEY transaction_date (transaction_date),
                KEY invoice_number (invoice_number),
                KEY payer_contact_id (payer_contact_id),
                KEY payee_contact_id (payee_contact_id)
            ) $charset_collate;",
            
            // DOCUMENT MANAGEMENT
            'klage_documents' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_documents` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned NOT NULL,
                
                -- WordPress Media Integration
                wp_attachment_id bigint(20) unsigned DEFAULT NULL,
                original_filename varchar(255) NOT NULL,
                
                -- Document Classification
                document_type varchar(100) NOT NULL,
                document_category varchar(100) DEFAULT NULL,
                version varchar(20) DEFAULT '1.0',
                
                -- Storage Information
                file_path varchar(500) DEFAULT NULL,
                s3_encrypted_url text DEFAULT NULL,
                file_size bigint(20) DEFAULT NULL,
                mime_type varchar(100) DEFAULT NULL,
                
                -- Access Control
                access_level enum('public','internal','confidential','restricted') DEFAULT 'internal',
                uploaded_by_contact_id bigint(20) unsigned DEFAULT NULL,
                
                -- Metadata
                title varchar(255) DEFAULT NULL,
                description text DEFAULT NULL,
                tags varchar(500) DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY wp_attachment_id (wp_attachment_id),
                KEY document_type (document_type),
                KEY access_level (access_level),
                KEY uploaded_by_contact_id (uploaded_by_contact_id)
            ) $charset_collate;",
            
            // COURTS REFERENCE TABLE
            'klage_courts' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_courts` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                court_name varchar(200) NOT NULL,
                court_type varchar(100) DEFAULT NULL,
                court_code varchar(50) DEFAULT NULL,
                
                -- Address Information
                street varchar(150) DEFAULT NULL,
                postal_code varchar(20) DEFAULT NULL,
                city varchar(100) DEFAULT NULL,
                
                -- Contact Information
                phone varchar(50) DEFAULT NULL,
                email varchar(255) DEFAULT NULL,
                website varchar(255) DEFAULT NULL,
                
                -- Integration Information
                egvp_id varchar(50) DEFAULT NULL,
                xjustiz_code varchar(100) DEFAULT NULL,
                api_endpoint varchar(500) DEFAULT NULL,
                
                -- Metadata from JSON
                jurisdiction_area text DEFAULT NULL,
                specializations text DEFAULT NULL,
                operating_hours text DEFAULT NULL,
                
                active_status tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY court_type (court_type),
                KEY city (city),
                KEY active_status (active_status),
                UNIQUE KEY court_code (court_code)
            ) $charset_collate;",
            
            // AUDIT TRAIL
            'klage_audit' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_audit` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id bigint(20) unsigned DEFAULT NULL,
                contact_id bigint(20) unsigned DEFAULT NULL,
                
                -- Action Information
                action varchar(100) NOT NULL,
                entity_type varchar(50) NOT NULL,
                entity_id bigint(20) unsigned DEFAULT NULL,
                
                -- Change Details
                old_values json DEFAULT NULL,
                new_values json DEFAULT NULL,
                details text DEFAULT NULL,
                
                -- User Information
                user_id bigint(20) unsigned NOT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY case_id (case_id),
                KEY contact_id (contact_id),
                KEY action (action),
                KEY entity_type (entity_type),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) $charset_collate;",
            
            // IMPORT CONFIGURATION (Enhanced)
            'klage_import_configs' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_import_configs` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                config_name varchar(100) NOT NULL,
                client_type varchar(50) NOT NULL,
                
                -- Field Mapping Configuration
                field_mappings text NOT NULL,
                validation_rules text DEFAULT NULL,
                default_values text DEFAULT NULL,
                contact_mapping_rules text DEFAULT NULL,
                
                -- Configuration Status
                is_active tinyint(1) DEFAULT 1,
                version varchar(20) DEFAULT '1.0',
                
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY client_type (client_type),
                KEY is_active (is_active)
            ) $charset_collate;",
            
            // IMPORT HISTORY (Enhanced)
            'klage_import_history' => "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}klage_import_history` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                import_type varchar(50) NOT NULL,
                client_type varchar(50) NOT NULL,
                
                -- Import Details
                filename varchar(255) DEFAULT NULL,
                total_rows int(10) unsigned DEFAULT 0,
                successful_imports int(10) unsigned DEFAULT 0,
                failed_imports int(10) unsigned DEFAULT 0,
                
                -- Processing Information
                import_status varchar(20) DEFAULT 'completed',
                processing_time int(10) unsigned DEFAULT NULL,
                error_log text DEFAULT NULL,
                config_used text DEFAULT NULL,
                
                -- Results
                created_cases int(10) unsigned DEFAULT 0,
                created_contacts int(10) unsigned DEFAULT 0,
                linked_relationships int(10) unsigned DEFAULT 0,
                
                imported_by bigint(20) unsigned NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                
                PRIMARY KEY (id),
                KEY import_type (import_type),
                KEY client_type (client_type),
                KEY import_status (import_status),
                KEY created_at (created_at)
            ) $charset_collate;"
        );
        
        return $schemas;
    }
    
    /**
     * Drop old pre-v2.0.0 tables safely
     */
    public function cleanup_old_tables() {
        $old_tables = array(
            'klage_debtors',
            'klage_plaintiffs', 
            'klage_financial',
            'klage_lawyers',
            'klage_representatives',
            'klage_csv_imports',
            'klage_field_mappings',
            'klage_case_assignments',
            'klage_payments',
            'klage_invoices'
        );
        
        $cleanup_results = array();
        
        foreach ($old_tables as $table_name) {
            $full_table_name = $this->wpdb->prefix . $table_name;
            
            // Check if table exists first
            $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $full_table_name
            ));
            
            if ($table_exists) {
                // Check if table is empty (safety check)
                $row_count = $this->wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
                
                if ($row_count == 0) {
                    $result = $this->wpdb->query("DROP TABLE $full_table_name");
                    if ($result !== false) {
                        $cleanup_results[$table_name] = 'Dropped (empty table)';
                        error_log("CAH: Dropped empty old table $table_name");
                    } else {
                        $cleanup_results[$table_name] = 'Error dropping: ' . $this->wpdb->last_error;
                        error_log("CAH: Failed to drop table $table_name: " . $this->wpdb->last_error);
                    }
                } else {
                    $cleanup_results[$table_name] = "Skipped (contains $row_count rows)";
                    error_log("CAH: Skipped dropping $table_name - contains data");
                }
            } else {
                $cleanup_results[$table_name] = 'Not found (already removed)';
            }
        }
        
        return $cleanup_results;
    }

    /**
     * Create all tables
     */
    public function execute_schema_creation() {
        // First, clean up old tables
        $cleanup_results = $this->cleanup_old_tables();
        
        // Then create new v2.0.0 schema
        $schemas = $this->create_v200_schema();
        $results = array();
        
        foreach ($schemas as $table_name => $sql) {
            $result = $this->wpdb->query($sql);
            
            if ($result !== false) {
                $results[$table_name] = 'Created successfully';
                error_log("CAH: Created table $table_name");
            } else {
                $results[$table_name] = 'Error: ' . $this->wpdb->last_error;
                error_log("CAH: Failed to create table $table_name: " . $this->wpdb->last_error);
            }
        }
        
        // Return combined results
        return array(
            'cleanup' => $cleanup_results,
            'creation' => $results
        );
    }
    
    /**
     * Populate demo data for testing
     */
    public function populate_demo_data() {
        
        // Demo Contacts
        $demo_contacts = array(
            array(
                'contact_type' => 'company',
                'company_name' => 'SpamCorp GmbH',
                'email' => 'info@spamcorp.de',
                'phone' => '+49-40-12345',
                'street' => 'Werbestraße',
                'street_number' => '45',
                'postal_code' => '20095',
                'city' => 'Hamburg',
                'iban' => 'DE89370400440532013000',
                'bic' => 'COBADEFFXXX',
                'bank_name' => 'Commerzbank Hamburg',
                'schufa_status' => 'nok',
                'schufa_score' => 320,
                'notes' => 'Aggressive email marketing company'
            ),
            array(
                'contact_type' => 'person',
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'email' => 'max.mustermann@email.de',
                'phone' => '+49-30-98765',
                'street' => 'Musterstraße',
                'street_number' => '123',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'iban' => 'DE12500105170648489890',
                'schufa_status' => 'ok',
                'schufa_score' => 750,
                'notes' => 'GDPR violation victim - Newsletter spam'
            ),
            array(
                'contact_type' => 'person',
                'first_name' => 'Dr. Anna',
                'last_name' => 'Schmidt',
                'company_name' => 'Kanzlei Schmidt & Partner',
                'email' => 'schmidt@law.de',
                'phone' => '+49-40-99999',
                'street' => 'Rechtsallee',
                'street_number' => '77',
                'postal_code' => '20354',
                'city' => 'Hamburg',
                'iban' => 'DE89370400440532099888',
                'schufa_status' => 'ok',
                'schufa_score' => 850,
                'notes' => 'Specialized in corporate law and GDPR compliance'
            ),
            array(
                'contact_type' => 'person',
                'first_name' => 'Sarah',
                'last_name' => 'Mueller',
                'company_name' => 'TV Legal Services',
                'email' => 'mueller@tv-legal.de',
                'phone' => '+49-40-55555',
                'street' => 'Gerichtsstraße',
                'street_number' => '12',
                'postal_code' => '20355',
                'city' => 'Hamburg',
                'iban' => 'DE89370400440532088777',
                'schufa_status' => 'ok',
                'schufa_score' => 780,
                'notes' => 'Experienced TV lawyer for GDPR cases'
            ),
            array(
                'contact_type' => 'person',
                'first_name' => 'Klaus',
                'last_name' => 'Weber',
                'email' => 'k.weber@spamcorp.de',
                'phone' => '+49-40-12346',
                'street' => 'Werbestraße',
                'street_number' => '45',
                'postal_code' => '20095',
                'city' => 'Hamburg',
                'schufa_status' => 'ok',
                'schufa_score' => 680,
                'notes' => 'CEO of SpamCorp GmbH - Company representative'
            ),
            array(
                'contact_type' => 'person',
                'first_name' => 'Lisa',
                'last_name' => 'Wagner',
                'email' => 'lisa.wagner@email.de',
                'phone' => '+49-30-44444',
                'street' => 'Teststraße',
                'street_number' => '88',
                'postal_code' => '10117',
                'city' => 'Berlin',
                'iban' => 'DE12500105170648499999',
                'schufa_status' => 'ok',
                'schufa_score' => 720,
                'notes' => 'Second GDPR violation case - Fashion newsletter spam'
            )
        );
        
        // Insert demo contacts
        $contact_ids = array();
        foreach ($demo_contacts as $contact) {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'klage_contacts',
                $contact
            );
            if ($result) {
                $contact_ids[] = $this->wpdb->insert_id;
            }
        }
        
        // Demo Courts
        $demo_courts = array(
            array(
                'court_name' => 'Amtsgericht Hamburg',
                'court_type' => 'Amtsgericht',
                'court_code' => 'AG_HH',
                'street' => 'Sievekingplatz',
                'postal_code' => '20355',
                'city' => 'Hamburg',
                'phone' => '+49-40-42843-0',
                'email' => 'postfach@ag-hamburg.de',
                'egvp_id' => 'AG_HAMBURG_001'
            ),
            array(
                'court_name' => 'Landgericht Hamburg',
                'court_type' => 'Landgericht',
                'court_code' => 'LG_HH',
                'street' => 'Sievekingplatz',
                'postal_code' => '20355',
                'city' => 'Hamburg',
                'phone' => '+49-40-42843-2000',
                'email' => 'postfach@lg-hamburg.de',
                'egvp_id' => 'LG_HAMBURG_001'
            ),
            array(
                'court_name' => 'Amtsgericht Berlin-Mitte',
                'court_type' => 'Amtsgericht',
                'court_code' => 'AG_BER',
                'street' => 'Littenstraße',
                'postal_code' => '10179',
                'city' => 'Berlin',
                'phone' => '+49-30-9014-0',
                'email' => 'postfach@ag-berlin-mitte.de',
                'egvp_id' => 'AG_BERLIN_MITTE_001'
            )
        );
        
        // Insert demo courts
        $court_ids = array();
        foreach ($demo_courts as $court) {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'klage_courts',
                $court
            );
            if ($result) {
                $court_ids[] = $this->wpdb->insert_id;
            }
        }
        
        // Demo Cases
        $demo_cases = array(
            array(
                'case_id' => 'SPAM-2024-001',
                'case_status' => 'processing',
                'active_status' => 'active',
                'case_priority' => 'high',
                'case_complexity' => 'medium',
                'claim_amount' => 548.11,
                'damage_amount' => 548.11,
                'legal_fees' => 150.00,
                'court_id' => $court_ids[0] ?? 1,
                'assigned_tv_lawyer_id' => $contact_ids[3] ?? null,
                'filing_date' => '2024-01-15',
                'response_deadline' => '2024-02-15',
                'next_hearing_date' => '2024-03-01 10:00:00',
                'legal_basis' => 'GDPR Art. 82',
                'case_notes' => 'SpamCorp sent unauthorized marketing emails despite unsubscribe request. Clear GDPR violation with tracking pixel evidence.',
                'schufa_checks_completed' => 1
            ),
            array(
                'case_id' => 'SPAM-2024-002',
                'case_status' => 'draft',
                'active_status' => 'active',
                'case_priority' => 'medium',
                'case_complexity' => 'simple',
                'claim_amount' => 548.11,
                'damage_amount' => 548.11,
                'legal_fees' => 120.00,
                'court_id' => $court_ids[2] ?? 2,
                'filing_date' => '2024-02-01',
                'response_deadline' => '2024-03-01',
                'legal_basis' => 'GDPR Art. 82',
                'case_notes' => 'Fashion newsletter spam to Lisa Wagner. Multiple emails after unsubscribe.',
                'schufa_checks_completed' => 1
            ),
            array(
                'case_id' => 'SPAM-2024-003',
                'case_status' => 'processing',
                'active_status' => 'active',
                'case_priority' => 'low',
                'case_complexity' => 'complex',
                'claim_amount' => 1096.22,
                'damage_amount' => 1096.22,
                'legal_fees' => 200.00,
                'court_id' => $court_ids[0] ?? 1,
                'filing_date' => '2024-01-20',
                'response_deadline' => '2024-02-20',
                'next_hearing_date' => '2024-03-15 14:00:00',
                'legal_basis' => 'GDPR Art. 82',
                'case_notes' => 'Complex case involving multiple GDPR violations and cross-border data transfers.',
                'schufa_checks_completed' => 0
            )
        );
        
        // Insert demo cases
        $case_ids = array();
        foreach ($demo_cases as $case) {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'klage_cases',
                $case
            );
            if ($result) {
                $case_ids[] = $this->wpdb->insert_id;
            }
        }
        
        // Demo Case-Contact Relationships
        $demo_relationships = array(
            // Case 1 relationships
            array('case_id' => $case_ids[0], 'contact_id' => $contact_ids[0], 'role' => 'debtor', 'role_description' => 'Primary defendant - SpamCorp GmbH'),
            array('case_id' => $case_ids[0], 'contact_id' => isset($contact_ids[4]) ? $contact_ids[4] : $contact_ids[2], 'role' => 'debtor_rep', 'role_description' => 'CEO and legal representative'),
            array('case_id' => $case_ids[0], 'contact_id' => $contact_ids[1], 'role' => 'plaintiff', 'role_description' => 'GDPR violation victim'),
            array('case_id' => $case_ids[0], 'contact_id' => $contact_ids[2], 'role' => 'legal_counsel', 'role_description' => 'Legal counsel for debtor'),
            array('case_id' => $case_ids[0], 'contact_id' => $contact_ids[3], 'role' => 'tv_lawyer', 'role_description' => 'TV lawyer for hearing'),
            
            // Case 2 relationships
            array('case_id' => $case_ids[1], 'contact_id' => $contact_ids[0], 'role' => 'debtor', 'role_description' => 'Primary defendant - Fashion spam'),
            array('case_id' => $case_ids[1], 'contact_id' => isset($contact_ids[5]) ? $contact_ids[5] : $contact_ids[1], 'role' => 'plaintiff', 'role_description' => 'Fashion newsletter victim'),
            
            // Case 3 relationships (complex case)
            array('case_id' => $case_ids[2], 'contact_id' => $contact_ids[0], 'role' => 'debtor', 'role_description' => 'Multi-violation defendant'),
            array('case_id' => $case_ids[2], 'contact_id' => $contact_ids[1], 'role' => 'plaintiff', 'role_description' => 'Primary victim'),
            array('case_id' => $case_ids[2], 'contact_id' => isset($contact_ids[6]) ? $contact_ids[6] : $contact_ids[2], 'role' => 'plaintiff', 'role_description' => 'Secondary victim'),
            array('case_id' => $case_ids[2], 'contact_id' => $contact_ids[2], 'role' => 'legal_counsel', 'role_description' => 'Defense attorney')
        );
        
        // Insert demo relationships
        foreach ($demo_relationships as $relationship) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'klage_case_contacts',
                $relationship
            );
        }
        
        // Demo Financials
        $demo_financials = array(
            array(
                'case_id' => $case_ids[0],
                'transaction_type' => 'claim',
                'amount' => 548.11,
                'purpose' => 'GDPR Art. 82 damage claim',
                'status' => 'pending',
                'transaction_date' => '2024-01-15',
                'payer_contact_id' => $contact_ids[0],
                'payee_contact_id' => $contact_ids[1]
            ),
            array(
                'case_id' => $case_ids[0],
                'transaction_type' => 'fee',
                'amount' => 150.00,
                'purpose' => 'Legal representation fees',
                'status' => 'pending',
                'transaction_date' => '2024-01-15',
                'invoice_number' => 'INV-2024-001',
                'invoice_date' => '2024-01-15'
            ),
            array(
                'case_id' => $case_ids[0],
                'transaction_type' => 'payment_out',
                'amount' => 200.00,
                'purpose' => 'TV lawyer consultation',
                'status' => 'pending',
                'transaction_date' => '2024-03-01',
                'payee_contact_id' => $contact_ids[3],
                'invoice_number' => 'TV-2024-001',
                'invoice_date' => '2024-03-01'
            )
        );
        
        // Insert demo financials
        foreach ($demo_financials as $financial) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'klage_financials',
                $financial
            );
        }
        
        // Demo TV Assignment
        $demo_tv = array(
            'case_id' => $case_ids[0],
            'tv_lawyer_contact_id' => $contact_ids[3],
            'court_date' => '2024-03-01 10:00:00',
            'court_location' => 'Amtsgericht Hamburg, Saal 3',
            'hearing_type' => 'Hauptverhandlung',
            'estimated_duration' => 120,
            'status' => 'confirmed',
            'request_sent_date' => '2024-02-15 09:00:00',
            'response_date' => '2024-02-16 14:30:00',
            'consultation_fee' => 200.00,
            'hourly_rate' => 100.00,
            'case_info_sent' => 1,
            'request_notes' => 'GDPR violation case, straightforward hearing expected',
            'assignment_notes' => 'TV lawyer confirmed availability, case information package sent'
        );
        
        $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_tv_assignments',
            $demo_tv
        );
        
        return array(
            'contacts' => count($contact_ids),
            'courts' => count($court_ids),
            'cases' => count($case_ids),
            'relationships' => count($demo_relationships),
            'financials' => count($demo_financials),
            'tv_assignments' => 1
        );
    }
    
    // ========================================
    // CRUD OPERATIONS FOR v2.0.0 TABLES
    // ========================================
    
    /**
     * CONTACTS CRUD OPERATIONS
     */
    public function create_contact($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_contacts',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_contact($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}klage_contacts WHERE id = %d",
                $id
            )
        );
    }
    
    public function get_contacts($args = array()) {
        $defaults = array(
            'contact_type' => '',
            'active_status' => 1,
            'limit' => 50,
            'offset' => 0,
            'search' => '',
            'order_by' => 'id',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("1=1");
        
        if (!empty($args['contact_type'])) {
            $where[] = $this->wpdb->prepare("contact_type = %s", $args['contact_type']);
        }
        
        if (!empty($args['active_status'])) {
            $where[] = $this->wpdb->prepare("active_status = %d", $args['active_status']);
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(first_name LIKE %s OR last_name LIKE %s OR company_name LIKE %s OR email LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}klage_contacts 
                 WHERE {$where_clause} 
                 ORDER BY {$args['order_by']} {$args['order']} 
                 LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        return $this->wpdb->get_results($query);
    }
    
    public function update_contact($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_contacts',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_contact($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_contacts',
            array('id' => $id)
        );
    }
    
    /**
     * CASES CRUD OPERATIONS
     */
    public function create_case($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_cases',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_case($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}klage_cases WHERE id = %d",
                $id
            )
        );
    }
    
    public function get_cases($args = array()) {
        $defaults = array(
            'case_status' => '',
            'active_status' => 'active',
            'limit' => 50,
            'offset' => 0,
            'search' => '',
            'order_by' => 'id',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("1=1");
        
        if (!empty($args['case_status'])) {
            $where[] = $this->wpdb->prepare("case_status = %s", $args['case_status']);
        }
        
        if (!empty($args['active_status'])) {
            $where[] = $this->wpdb->prepare("active_status = %s", $args['active_status']);
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare(
                "(case_id LIKE %s OR case_notes LIKE %s OR legal_basis LIKE %s)",
                $search, $search, $search
            );
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}klage_cases 
                 WHERE {$where_clause} 
                 ORDER BY {$args['order_by']} {$args['order']} 
                 LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        return $this->wpdb->get_results($query);
    }
    
    public function update_case($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_cases',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_case($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_cases',
            array('id' => $id)
        );
    }
    
    /**
     * CASE-CONTACT RELATIONSHIPS CRUD OPERATIONS
     */
    public function create_case_contact_relationship($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_case_contacts',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_case_contacts($case_id, $role = '') {
        $where = array("case_id = %d");
        $params = array($case_id);
        
        if (!empty($role)) {
            $where[] = "role = %s";
            $params[] = $role;
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT cc.*, c.first_name, c.last_name, c.company_name, c.email, c.phone 
                 FROM {$this->wpdb->prefix}klage_case_contacts cc 
                 JOIN {$this->wpdb->prefix}klage_contacts c ON cc.contact_id = c.id 
                 WHERE {$where_clause} AND cc.active_status = 1",
                $params
            )
        );
    }
    
    public function update_case_contact_relationship($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_case_contacts',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_case_contact_relationship($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_case_contacts',
            array('id' => $id)
        );
    }
    
    /**
     * FINANCIALS CRUD OPERATIONS
     */
    public function create_financial($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_financials',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_case_financials($case_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT f.*, 
                        payer.first_name as payer_first_name, payer.last_name as payer_last_name, payer.company_name as payer_company,
                        payee.first_name as payee_first_name, payee.last_name as payee_last_name, payee.company_name as payee_company
                 FROM {$this->wpdb->prefix}klage_financials f
                 LEFT JOIN {$this->wpdb->prefix}klage_contacts payer ON f.payer_contact_id = payer.id
                 LEFT JOIN {$this->wpdb->prefix}klage_contacts payee ON f.payee_contact_id = payee.id
                 WHERE f.case_id = %d
                 ORDER BY f.transaction_date DESC",
                $case_id
            )
        );
    }
    
    public function update_financial($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_financials',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_financial($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_financials',
            array('id' => $id)
        );
    }
    
    /**
     * TV ASSIGNMENTS CRUD OPERATIONS
     */
    public function create_tv_assignment($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_tv_assignments',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_tv_assignments($case_id = null) {
        $where = "1=1";
        $params = array();
        
        if ($case_id !== null) {
            $where = "tv.case_id = %d";
            $params[] = $case_id;
        }
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT tv.*, 
                        c.first_name as lawyer_first_name, c.last_name as lawyer_last_name, c.company_name as lawyer_company,
                        cases.case_id as case_number
                 FROM {$this->wpdb->prefix}klage_tv_assignments tv
                 JOIN {$this->wpdb->prefix}klage_contacts c ON tv.tv_lawyer_contact_id = c.id
                 JOIN {$this->wpdb->prefix}klage_cases cases ON tv.case_id = cases.id
                 WHERE {$where}
                 ORDER BY tv.court_date DESC",
                $params
            )
        );
    }
    
    public function update_tv_assignment($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_tv_assignments',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_tv_assignment($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_tv_assignments',
            array('id' => $id)
        );
    }
    
    /**
     * DOCUMENTS CRUD OPERATIONS
     */
    public function create_document($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_documents',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_case_documents($case_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT d.*, 
                        c.first_name as uploader_first_name, c.last_name as uploader_last_name
                 FROM {$this->wpdb->prefix}klage_documents d
                 LEFT JOIN {$this->wpdb->prefix}klage_contacts c ON d.uploaded_by_contact_id = c.id
                 WHERE d.case_id = %d
                 ORDER BY d.created_at DESC",
                $case_id
            )
        );
    }
    
    public function update_document($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_documents',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_document($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_documents',
            array('id' => $id)
        );
    }
    
    /**
     * COURTS CRUD OPERATIONS
     */
    public function create_court($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'klage_courts',
            $data
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        return false;
    }
    
    public function get_courts($active_only = true) {
        $where = $active_only ? "WHERE active_status = 1" : "";
        
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}klage_courts 
             {$where}
             ORDER BY court_name ASC"
        );
    }
    
    public function get_court($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}klage_courts WHERE id = %d",
                $id
            )
        );
    }
    
    public function update_court($id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'klage_courts',
            $data,
            array('id' => $id)
        );
    }
    
    public function delete_court($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'klage_courts',
            array('id' => $id)
        );
    }
    
    /**
     * HELPER METHODS
     */
    public function get_case_summary($case_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    c.*,
                    court.court_name,
                    court.city as court_city,
                    tv_lawyer.first_name as tv_lawyer_first_name,
                    tv_lawyer.last_name as tv_lawyer_last_name
                 FROM {$this->wpdb->prefix}klage_cases c
                 LEFT JOIN {$this->wpdb->prefix}klage_courts court ON c.court_id = court.id
                 LEFT JOIN {$this->wpdb->prefix}klage_contacts tv_lawyer ON c.assigned_tv_lawyer_id = tv_lawyer.id
                 WHERE c.id = %d",
                $case_id
            )
        );
    }
    
    public function get_financial_summary($case_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    SUM(CASE WHEN transaction_type = 'claim' THEN amount ELSE 0 END) as total_claims,
                    SUM(CASE WHEN transaction_type = 'fee' THEN amount ELSE 0 END) as total_fees,
                    SUM(CASE WHEN transaction_type = 'payment_in' THEN amount ELSE 0 END) as total_payments_in,
                    SUM(CASE WHEN transaction_type = 'payment_out' THEN amount ELSE 0 END) as total_payments_out,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expenses
                 FROM {$this->wpdb->prefix}klage_financials
                 WHERE case_id = %d",
                $case_id
            )
        );
    }
}