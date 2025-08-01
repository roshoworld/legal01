<?php
/**
 * Admin Dashboard class - Enhanced Case Management v2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Admin_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function admin_init() {
        register_setting('klage_click_settings', 'klage_click_debug_mode');
        
        // Handle template download EARLY before any output
        $this->handle_early_download();
        
        // Handle CSV export EARLY before any output
        $this->handle_early_export();
        
        // Add AJAX handlers for file downloads
        add_action('wp_ajax_klage_download_template', array($this, 'ajax_download_template'));
        add_action('wp_ajax_klage_export_calculation', array($this, 'ajax_export_calculation'));
        add_action('wp_ajax_klage_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_check_case_id_unique', array($this, 'ajax_check_case_id_unique'));
    }
    
    // Template download functionality removed in v1.9.0 - replaced with Universal Import
    private function handle_early_download() {
        // Legacy template download removed
    }
    
    private function handle_early_export() {
        // Check if this is our CSV export request
        if (isset($_GET['page']) && $_GET['page'] === 'la-cases' && 
            isset($_GET['action']) && $_GET['action'] === 'export' && 
            isset($_GET['_wpnonce'])) {
            
            // Verify nonce
            if (!wp_verify_nonce($_GET['_wpnonce'], 'export_csv')) {
                wp_die('Security check failed');
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            
            // Send the CSV export
            $this->export_cases_csv();
            exit; // Critical: Stop WordPress execution
        }
    }
    
    private function export_cases_csv() {
        global $wpdb;
        
        // Get cases data - Updated for v2.0.1 contact-centric architecture
        $cases = $wpdb->get_results("
            SELECT 
                c.case_id,
                c.case_status,
                c.case_priority,
                c.mandant,
                c.submission_date,
                c.claim_amount,
                c.legal_fees,
                c.court_fees,
                (c.claim_amount + c.legal_fees + c.court_fees) as total_amount,
                c.case_notes,
                debtor_contact.first_name as debtors_name,
                debtor_contact.email as debtors_email,
                debtor_contact.company_name as debtors_company,
                c.created_at
            FROM {$wpdb->prefix}klage_cases c
            LEFT JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.case_id AND cc.role = 'debtor' AND cc.active_status = 1
            LEFT JOIN {$wpdb->prefix}klage_contacts debtor_contact ON cc.contact_id = debtor_contact.id
            WHERE c.active_status = 'active'
            ORDER BY c.created_at DESC
        ", ARRAY_A);
        
        $filename = 'klage_cases_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Clean any output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create CSV output
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // CSV headers
        fputcsv($output, array(
            'Fall-ID',
            'Status',
            'Priorität',
            'Mandant',
            'Eingangsdatum',
            'Gesamtbetrag',
            'Notizen',
            'Schuldner Name',
            'Schuldner E-Mail',
            'Schuldner Firma',
            'Erstellt am'
        ), ';');
        
        // Write data rows
        foreach ($cases as $case) {
            fputcsv($output, array(
                $case['case_id'],
                $case['case_status'],
                $case['case_priority'],
                $case['mandant'],
                $case['submission_date'],
                $case['total_amount'],
                $case['case_notes'],
                $case['debtors_name'],
                $case['debtors_email'],
                $case['debtors_company'],
                $case['created_at']
            ), ';');
        }
        
        fclose($output);
    }
    
    public function ajax_export_csv() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'export_csv')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Generate export URL
        $export_url = wp_nonce_url(
            admin_url('admin.php?page=la-cases&action=export'),
            'export_csv'
        );
        
        wp_redirect($export_url);
        exit;
    }
    
    private function send_template_download() {
        // Check template type
        $template_type = $_GET['template_type'] ?? 'comprehensive';
        
        // Create filename based on template type
        if ($template_type === 'forderungen') {
            $filename = 'forderungen_com_import_template_' . date('Y-m-d') . '.csv';
        } else {
            $filename = 'klage_click_comprehensive_template_' . date('Y-m-d') . '.csv';
        }
        
        // Get file content
        $content = $this->get_template_content();
        
        // Clean any output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Prevent any caching
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        
        // Set download headers
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($content));
        
        // Output the content
        echo $content;
        
        // Stop all further processing
        die();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Klage.Click Hub', 'court-automation-hub'),
            __('Klage.Click Hub', 'court-automation-hub'),
            'manage_options',
            'klage-click-hub',
            array($this, 'admin_page_dashboard'),
            'dashicons-hammer',
            30
        );
        
        add_submenu_page(
            'klage-click-hub',
            __('Fälle', 'court-automation-hub'),
            __('Fälle', 'court-automation-hub'),
            'manage_options',
            'la-cases',
            array($this, 'admin_page_cases')
        );
        
        // Document Analysis Integration v1.8.7
        do_action('cah_admin_menu_integration', 'klage-click-hub');
        
        // Financial calculator removed in v1.5.1 - handled by separate plugin
        // Help page removed in v1.9.1 - will be rebuilt later
        
        add_submenu_page(
            'klage-click-hub',
            __('Einstellungen', 'court-automation-hub'),
            __('Einstellungen', 'court-automation-hub'),
            'manage_options',
            'klage-click-settings',
            array($this, 'admin_page_settings')
        );
    }
    
    public function admin_page_dashboard() {
        global $wpdb;
        
        // Get statistics - Updated for v2.0.1 contact-centric architecture
        $total_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'") ?? 0;
        $pending_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'pending' AND active_status = 'active'") ?? 0;
        $processing_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'processing' AND active_status = 'active'") ?? 0;
        $completed_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'completed' AND active_status = 'active'") ?? 0;
        
        // Fix: Use COALESCE to handle NULL values and ensure proper decimal calculation
        $total_value = $wpdb->get_var("
            SELECT SUM(
                COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0) + 
                COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0) + 
                COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)
            ) FROM {$wpdb->prefix}klage_cases 
            WHERE active_status = 'active'
        ") ?? 0;
        
        // Debug: Log the calculation for troubleshooting - Updated for v2.0.1
        if (get_option('klage_click_debug_mode')) {
            $debug_query = "SELECT 
                COUNT(*) as case_count,
                SUM(COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0)) as claim_amount_sum,
                SUM(COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0)) as legal_fees_sum,
                SUM(COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)) as court_fees_sum,
                SUM(COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0) + COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0) + COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)) as final_total
                FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'";
            $debug_result = $wpdb->get_row($debug_query);
            error_log("CAH Debug - Financial calculation: " . json_encode($debug_result));
        }
        
        // Get Document Analysis statistics v1.8.7
        $dashboard_stats = array(
            'cases' => array(
                'total' => $total_cases,
                'pending' => $pending_cases,
                'processing' => $processing_cases,
                'completed' => $completed_cases,
                'total_value' => $total_value
            )
        );
        $dashboard_stats = apply_filters('cah_dashboard_stats', $dashboard_stats);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Klage.Click Hub Dashboard', 'court-automation-hub'); ?></h1>
            
            <div style="background: #28a745; color: white; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #1e7e34;">
                <p><strong>🚀 v2.0.0 - COMPREHENSIVE LEGAL PRACTICE MANAGEMENT!</strong></p>
                <p>Major Update: Complete system transformation with contact-centric architecture, TV management, enhanced financials, and comprehensive case tracking.</p>
            </div>
            
            <div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #0073aa; font-size: 28px;"><?php echo esc_html($total_cases); ?></h3>
                    <p style="margin: 0; color: #666;">Gesamt Fälle</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #ff9800; font-size: 28px;"><?php echo esc_html($pending_cases); ?></h3>
                    <p style="margin: 0; color: #666;">Ausstehend</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2196f3; font-size: 28px;"><?php echo esc_html($processing_cases); ?></h3>
                    <p style="margin: 0; color: #666;">In Bearbeitung</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #4caf50; font-size: 28px;"><?php echo esc_html($completed_cases); ?></h3>
                    <p style="margin: 0; color: #666;">Abgeschlossen</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #0073aa; font-size: 24px;">€<?php echo esc_html(number_format($total_value, 2)); ?></h3>
                    <p style="margin: 0; color: #666;">Gesamtwert</p>
                </div>
                
                <?php 
                // Document Analysis Statistics v1.8.3
                if (isset($dashboard_stats['document_analysis'])): 
                    $doc_stats = $dashboard_stats['document_analysis'];
                ?>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #9c27b0;">
                    <h3 style="margin: 0 0 10px 0; color: #9c27b0; font-size: 28px;"><?php echo esc_html($doc_stats['total_communications']); ?></h3>
                    <p style="margin: 0; color: #666;">Kommunikationen (30T)</p>
                </div>
                <?php if ($doc_stats['unassigned_communications'] > 0): ?>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #f44336;">
                    <h3 style="margin: 0 0 10px 0; color: #f44336; font-size: 28px;"><?php echo esc_html($doc_stats['unassigned_communications']); ?></h3>
                    <p style="margin: 0; color: #666;">Unzugeordnet</p>
                    <a href="<?php echo admin_url('admin.php?page=klage-click-doc-unassigned'); ?>" class="button button-small" style="margin-top: 5px;">Überprüfen</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="postbox" style="margin-top: 30px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9;">🚀 Schnellaktionen</h2>
                <div class="inside" style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <a href="<?php echo admin_url('admin.php?page=la-cases&action=add'); ?>" class="button button-primary" style="padding: 20px; height: auto; text-decoration: none; text-align: center;">
                            <strong>📝 Neuen Fall erstellen</strong><br>
                            <small>GDPR Fall - Finanzberechnung über separates Plugin</small>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=klage-click-universal-import'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; background: #2271b1; color: white;">
                            <strong>🔄 Universal Import</strong><br>
                            <small>Forderungen.com & Multi-Client Import</small>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=klage-click-financial&action=calculator'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center;">
                            <strong>🧮 Finanzrechner</strong><br>
                            <small>Excel-ähnliche Berechnungen</small>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9;">📊 System Status</h2>
                <div class="inside" style="padding: 20px;">
                    <?php $this->display_system_status(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get form data from POST request for form persistence
     */
    private function get_form_data() {
        return $_POST ?? array();
    }
    
    private function render_add_case_form() {
        global $wpdb;
        
        // Get previously submitted data for form persistence
        $form_data = $this->get_form_data();
        
        // Get all courts for dropdown
        $all_courts = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}klage_courts 
            WHERE active_status = 1 
            ORDER BY court_name ASC
        ");
        
        ?>
        <div class="wrap">
            <h1>Neuen Fall erstellen</h1>
            
            <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;">
                <p><strong>📝 v2.1.0 - Vollständige Fallerstellung</strong></p>
                <p>Erstellen Sie einen neuen Fall mit allen erforderlichen Informationen. Nutzen Sie die Tabs für strukturierte Dateneingabe.</p>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('create_case', 'create_case_nonce'); ?>
                <input type="hidden" name="action" value="create_case">
                
                <!-- v2.1.0 Complete Tab Navigation -->
                <div class="nav-tab-wrapper">
                    <a href="#fall-daten" class="nav-tab nav-tab-active" onclick="switchCaseCreateTab(event, 'fall-daten')">📋 Fall Daten</a>
                    <a href="#beweise" class="nav-tab" onclick="switchCaseCreateTab(event, 'beweise')">🔍 Beweise</a>
                    <a href="#mandant" class="nav-tab" onclick="switchCaseCreateTab(event, 'mandant')">👤 Mandant</a>
                    <a href="#gegenseite" class="nav-tab" onclick="switchCaseCreateTab(event, 'gegenseite')">⚖️ Gegenseite</a>
                    <a href="#gericht" class="nav-tab" onclick="switchCaseCreateTab(event, 'gericht')">🏛️ Gericht</a>
                    <a href="#finanzen" class="nav-tab" onclick="switchCaseCreateTab(event, 'finanzen')">💰 Finanzen</a>
                    <a href="#dokumentenerstellung" class="nav-tab" onclick="switchCaseCreateTab(event, 'dokumentenerstellung')">📄 Dokumentenerstellung</a>
                    <a href="#crm" class="nav-tab" onclick="switchCaseCreateTab(event, 'crm')">📞 CRM</a>
                    <a href="#gerichtstermine" class="nav-tab" onclick="switchCaseCreateTab(event, 'gerichtstermine')">📅 Gerichtstermine</a>
                    <a href="#partner" class="nav-tab" onclick="switchCaseCreateTab(event, 'partner')">🤝 Partner</a>
                </div>
                
                <!-- Tab Content -->
                <div class="tab-content-wrapper" style="background: white; padding: 20px; border: 1px solid #ccd0d4; margin-top: -1px;">
                    
                    <!-- Fall Daten Tab -->
                    <div id="fall-daten" class="tab-content active">
                        <h2>📋 Fall Daten</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="case_id">Fall-ID</label></th>
                                <td>
                                    <input type="text" id="case_id" name="case_id" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['case_id'] ?? ''); ?>" 
                                           placeholder="CASE-<?php echo date('Y'); ?>-#### (auto-generiert wenn leer)">
                                    <p class="description">Eindeutige Fall-Kennung - wird automatisch generiert wenn leer gelassen</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_status">Status</label></th>
                                <td>
                                    <select id="case_status" name="case_status" class="regular-text">
                                        <option value="draft" <?php selected($form_data['case_status'] ?? 'draft', 'draft'); ?>>Entwurf</option>
                                        <option value="processing" <?php selected($form_data['case_status'] ?? 'draft', 'processing'); ?>>In Bearbeitung</option>
                                        <option value="pending" <?php selected($form_data['case_status'] ?? 'draft', 'pending'); ?>>Wartend</option>
                                        <option value="completed" <?php selected($form_data['case_status'] ?? 'draft', 'completed'); ?>>Abgeschlossen</option>
                                        <option value="archived" <?php selected($form_data['case_status'] ?? 'draft', 'archived'); ?>>Archiviert</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_priority">Priorität</label></th>
                                <td>
                                    <select id="case_priority" name="case_priority" class="regular-text">
                                        <option value="low" <?php selected($form_data['case_priority'] ?? 'medium', 'low'); ?>>Niedrig</option>
                                        <option value="medium" <?php selected($form_data['case_priority'] ?? 'medium', 'medium'); ?>>Mittel</option>
                                        <option value="high" <?php selected($form_data['case_priority'] ?? 'medium', 'high'); ?>>Hoch</option>
                                        <option value="urgent" <?php selected($form_data['case_priority'] ?? 'medium', 'urgent'); ?>>Dringend</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_complexity">Komplexität</label></th>
                                <td>
                                    <select id="case_complexity" name="case_complexity" class="regular-text">
                                        <option value="simple" <?php selected($form_data['case_complexity'] ?? 'medium', 'simple'); ?>>Einfach</option>
                                        <option value="medium" <?php selected($form_data['case_complexity'] ?? 'medium', 'medium'); ?>>Mittel</option>
                                        <option value="complex" <?php selected($form_data['case_complexity'] ?? 'medium', 'complex'); ?>>Komplex</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="legal_basis">Rechtsgrundlage</label></th>
                                <td>
                                    <input type="text" id="legal_basis" name="legal_basis" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['legal_basis'] ?? ''); ?>" placeholder="z.B. GDPR Art. 82">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="schufa_checks_completed">Schufa-Prüfung</label></th>
                                <td>
                                    <input type="checkbox" id="schufa_checks_completed" name="schufa_checks_completed" value="1" 
                                           <?php checked($form_data['schufa_checks_completed'] ?? 0, 1); ?>>
                                    <label for="schufa_checks_completed">Schufa-Prüfung abgeschlossen</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="filing_date">Einreichungsdatum</label></th>
                                <td>
                                    <input type="date" id="filing_date" name="filing_date" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['filing_date'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="response_deadline">Antwortfrist</label></th>
                                <td>
                                    <input type="date" id="response_deadline" name="response_deadline" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['response_deadline'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="next_hearing_date">Nächster Termin</label></th>
                                <td>
                                    <input type="datetime-local" id="next_hearing_date" name="next_hearing_date" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['next_hearing_date'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_notes">Fall-Notizen</label></th>
                                <td>
                                    <textarea id="case_notes" name="case_notes" rows="5" class="large-text"><?php echo esc_textarea($form_data['case_notes'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Beweise Tab -->
                    <div id="beweise" class="tab-content">
                        <h2>🔍 Beweise</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="case_documents_attachments">Dokumentenanhänge</label></th>
                                <td>
                                    <textarea id="case_documents_attachments" name="case_documents_attachments" rows="8" class="large-text" 
                                              placeholder="Liste aller relevanten Dokumente und Beweise..."><?php echo esc_textarea($form_data['case_documents_attachments'] ?? ''); ?></textarea>
                                    <p class="description">Führen Sie hier alle relevanten Dokumente und Beweise für diesen Fall auf.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="emails_sender_email">Spam-Absender E-Mail</label></th>
                                <td>
                                    <input type="email" id="emails_sender_email" name="emails_sender_email" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['emails_sender_email'] ?? ''); ?>">
                                    <p class="description">E-Mail-Adresse des Spam-Absenders</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="emails_subject">E-Mail Betreff</label></th>
                                <td>
                                    <input type="text" id="emails_subject" name="emails_subject" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['emails_subject'] ?? ''); ?>" 
                                           placeholder="Betreff der Spam-E-Mail">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="emails_content">E-Mail Inhalt</label></th>
                                <td>
                                    <textarea id="emails_content" name="emails_content" rows="6" class="large-text" 
                                              placeholder="Vollständiger Inhalt der Spam-E-Mail..."><?php echo esc_textarea($form_data['emails_content'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Mandant Tab -->
                    <div id="mandant" class="tab-content">
                        <h2>👤 Mandant</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="mandant">Mandant</label></th>
                                <td>
                                    <input type="text" id="mandant" name="mandant" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['mandant'] ?? ''); ?>">
                                    <p class="description">Mandant/Kanzlei (kann später ergänzt werden)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_type">Mandanten-Typ</label></th>
                                <td>
                                    <select id="client_type" name="client_type" class="regular-text">
                                        <option value="individual" <?php selected($form_data['client_type'] ?? 'individual', 'individual'); ?>>Privatperson</option>
                                        <option value="company" <?php selected($form_data['client_type'] ?? 'individual', 'company'); ?>>Unternehmen</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_first_name">Vorname</label></th>
                                <td>
                                    <input type="text" id="client_first_name" name="client_first_name" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['client_first_name'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_last_name">Nachname</label></th>
                                <td>
                                    <input type="text" id="client_last_name" name="client_last_name" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['client_last_name'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_company_name">Firmenname</label></th>
                                <td>
                                    <input type="text" id="client_company_name" name="client_company_name" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['client_company_name'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_email">E-Mail</label></th>
                                <td>
                                    <input type="email" id="client_email" name="client_email" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['client_email'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_phone">Telefon</label></th>
                                <td>
                                    <input type="tel" id="client_phone" name="client_phone" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['client_phone'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_address">Adresse</label></th>
                                <td>
                                    <textarea id="client_address" name="client_address" rows="3" class="large-text" 
                                              placeholder="Straße, Hausnummer, PLZ, Stadt, Land"><?php echo esc_textarea($form_data['client_address'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Gegenseite Tab -->
                    <div id="gegenseite" class="tab-content">
                        <h2>⚖️ Gegenseite</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="debtor_type">Schuldner-Typ</label></th>
                                <td>
                                    <select id="debtor_type" name="debtor_type" class="regular-text">
                                        <option value="individual" <?php selected($form_data['debtor_type'] ?? 'individual', 'individual'); ?>>Privatperson</option>
                                        <option value="company" <?php selected($form_data['debtor_type'] ?? 'individual', 'company'); ?>>Unternehmen</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_first_name">Vorname</label></th>
                                <td>
                                    <input type="text" id="debtors_first_name" name="debtors_first_name" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_first_name'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_last_name">Nachname</label></th>
                                <td>
                                    <input type="text" id="debtors_last_name" name="debtors_last_name" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_last_name'] ?? ''); ?>">
                                    <p class="description">Kann später über Kontakte ergänzt werden</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_company">Firmenname</label></th>
                                <td>
                                    <input type="text" id="debtors_company" name="debtors_company" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_company'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_email">E-Mail</label></th>
                                <td>
                                    <input type="email" id="debtors_email" name="debtors_email" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_email'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_phone">Telefon</label></th>
                                <td>
                                    <input type="tel" id="debtors_phone" name="debtors_phone" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_phone'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_address">Straße</label></th>
                                <td>
                                    <input type="text" id="debtors_address" name="debtors_address" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_address'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_postal_code">PLZ</label></th>
                                <td>
                                    <input type="text" id="debtors_postal_code" name="debtors_postal_code" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_postal_code'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_city">Stadt</label></th>
                                <td>
                                    <input type="text" id="debtors_city" name="debtors_city" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_city'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="debtors_country">Land</label></th>
                                <td>
                                    <input type="text" id="debtors_country" name="debtors_country" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['debtors_country'] ?? 'Deutschland'); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Gericht Tab -->
                    <div id="gericht" class="tab-content">
                        <h2>🏛️ Gericht</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="court_id">Zuständiges Gericht</label></th>
                                <td>
                                    <select id="court_id" name="court_id" class="regular-text">
                                        <option value="">-- Gericht auswählen --</option>
                                        <?php foreach ($all_courts as $court): ?>
                                            <option value="<?php echo esc_attr($court->id); ?>" 
                                                    <?php selected($form_data['court_id'] ?? '', $court->id); ?>>
                                                <?php echo esc_html($court->court_name . ' (' . $court->court_type . ', ' . $court->city . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Wählen Sie das zuständige Gericht für diesen Fall</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="judge_name">Richter</label></th>
                                <td>
                                    <input type="text" id="judge_name" name="judge_name" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['judge_name'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_number_court">Aktenzeichen</label></th>
                                <td>
                                    <input type="text" id="case_number_court" name="case_number_court" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['case_number_court'] ?? ''); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Finanzen Tab -->
                    <div id="finanzen" class="tab-content">
                        <h2>💰 Finanzen</h2>
                        
                        <!-- Financial amounts grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                            <div>
                                <label for="claim_amount">💰 Klagesumme (€)</label>
                                <input type="number" id="claim_amount" name="claim_amount" value="<?php echo esc_attr($form_data['claim_amount'] ?? ''); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="damage_amount">⚖️ Schadenssumme (€)</label>
                                <input type="number" id="damage_amount" name="damage_amount" value="<?php echo esc_attr($form_data['damage_amount'] ?? ''); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="art15_claim_damages">📋 Art. 15 Schäden (€)</label>
                                <input type="number" id="art15_claim_damages" name="art15_claim_damages" value="<?php echo esc_attr($form_data['art15_claim_damages'] ?? ''); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="legal_fees">💼 Anwaltskosten (€)</label>
                                <input type="number" id="legal_fees" name="legal_fees" value="<?php echo esc_attr($form_data['legal_fees'] ?? ''); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="court_fees">🏛️ Gerichtskosten (€)</label>
                                <input type="number" id="court_fees" name="court_fees" value="<?php echo esc_attr($form_data['court_fees'] ?? ''); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                        </div>
                        
                        <!-- Financial calculation display -->
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                            <h3>📊 Gesamtkalkulation</h3>
                            <table class="form-table">
                                <tr>
                                    <th>Gesamtforderung:</th>
                                    <td><strong id="total_claim_create">€ 0,00</strong></td>
                                </tr>
                                <tr>
                                    <th>Gesamtkosten:</th>
                                    <td><strong id="total_costs_create">€ 0,00</strong></td>
                                </tr>
                                <tr style="border-top: 2px solid #333;">
                                    <th>Nettobetrag:</th>
                                    <td><strong id="net_amount_create">€ 0,00</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Dokumentenerstellung Tab -->
                    <div id="dokumentenerstellung" class="tab-content">
                        <h2>📄 Dokumentenerstellung</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="document_notes">Dokument-Notizen</label></th>
                                <td>
                                    <textarea id="document_notes" name="document_notes" rows="5" class="large-text" 
                                              placeholder="Notizen für Dokumentenerstellung..."><?php echo esc_textarea($form_data['document_notes'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                            <h3>🚀 Geplante Features:</h3>
                            <ul>
                                <li>📝 Automatische Klageschrift-Generierung</li>
                                <li>📋 Vorlagen-Verwaltung</li>
                                <li>🔗 Integration mit doc-out Plugin</li>
                                <li>📑 PDF-Export mit Signatur</li>
                                <li>📧 Direkter E-Mail-Versand</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- CRM Tab -->
                    <div id="crm" class="tab-content">
                        <h2>📞 CRM</h2>
                        <?php if (is_plugin_active('klage-crm/klage-crm.php')): ?>
                            <?php 
                            // Hook for CRM plugin to add content
                            do_action('cah_crm_create_content'); 
                            ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666; background: #f8f9fa; border-radius: 5px;">
                                <h3>📞 CRM Plugin nicht aktiv</h3>
                                <p>Das CRM Plugin ist nicht installiert oder nicht aktiviert.</p>
                                <div style="text-align: left; max-width: 500px; margin: 20px auto;">
                                    <h4>🎯 CRM Features:</h4>
                                    <ul>
                                        <li>📧 Kommunikations-Management</li>
                                        <li>📅 Terminverwaltung</li>
                                        <li>🎯 Zielgruppen-Segmentierung</li>
                                        <li>📊 Marketing-Kampagnen</li>
                                        <li>📈 Kontakt-Tracking</li>
                                        <li>💬 Chat-Historie</li>
                                        <li>📱 Social Media Integration</li>
                                    </ul>
                                </div>
                                <p><small>Installieren Sie das CRM Plugin (v100+), um diese Features zu nutzen.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gerichtstermine Tab -->
                    <div id="gerichtstermine" class="tab-content">
                        <h2>📅 Gerichtstermine</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="initial_hearing_date">Erster Termin</label></th>
                                <td>
                                    <input type="datetime-local" id="initial_hearing_date" name="initial_hearing_date" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['initial_hearing_date'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="hearing_notes">Termin-Notizen</label></th>
                                <td>
                                    <textarea id="hearing_notes" name="hearing_notes" rows="4" class="large-text" 
                                              placeholder="Notizen zu Gerichtsterminen..."><?php echo esc_textarea($form_data['hearing_notes'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                            <p><strong>ℹ️ Hinweis:</strong> Weitere Gerichtstermine können nach der Fallerstellung hinzugefügt werden.</p>
                        </div>
                    </div>
                    
                    <!-- Partner Tab -->
                    <div id="partner" class="tab-content">
                        <h2>🤝 Partner</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="main_lawyer">Hauptanwalt</label></th>
                                <td>
                                    <input type="text" id="main_lawyer" name="main_lawyer" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['main_lawyer'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="law_firm">Kanzlei</label></th>
                                <td>
                                    <input type="text" id="law_firm" name="law_firm" class="regular-text" 
                                           value="<?php echo esc_attr($form_data['law_firm'] ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="partner_notes">Partner-Notizen</label></th>
                                <td>
                                    <textarea id="partner_notes" name="partner_notes" rows="4" class="large-text" 
                                              placeholder="Notizen zu beteiligten Partnern und Anwälten..."><?php echo esc_textarea($form_data['partner_notes'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-top: 20px;">
                            <p><strong>ℹ️ Hinweis:</strong> Detailliertere Partner-Verwaltung erfolgt nach der Fallerstellung über die Kontakte-Funktion.</p>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Save Button -->
                <div style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                    <button type="submit" class="button button-primary button-large">💾 Fall erstellen</button>
                    <a href="<?php echo admin_url('admin.php?page=la-cases'); ?>" class="button button-large">🔙 Zurück zur Liste</a>
                    
                    <div style="margin-top: 10px;">
                        <p><small><strong>💡 Tipp:</strong> Sie können alle Informationen über die Tabs verteilt eingeben. Beim Erstellen werden alle verfügbaren Daten gespeichert.</small></p>
                    </div>
                </div>
            </form>
        </div>
        
        <script>
        function switchCaseCreateTab(evt, tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName("nav-tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("nav-tab-active");
            }
            
            // Show selected tab and mark as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("nav-tab-active");
        }
        
        // Financial calculation functionality for create form
        document.addEventListener('DOMContentLoaded', function() {
            const claimAmount = document.getElementById('claim_amount');
            const damageAmount = document.getElementById('damage_amount');
            const art15Damages = document.getElementById('art15_claim_damages');
            const legalFees = document.getElementById('legal_fees');
            const courtFees = document.getElementById('court_fees');
            
            function updateCreateCalculations() {
                const claim = parseFloat(claimAmount.value || 0);
                const damage = parseFloat(damageAmount.value || 0);
                const art15 = parseFloat(art15Damages.value || 0);
                const legal = parseFloat(legalFees.value || 0);
                const court = parseFloat(courtFees.value || 0);
                
                const totalClaim = claim + damage + art15;
                const totalCosts = legal + court;
                const netAmount = totalClaim - totalCosts;
                
                document.getElementById('total_claim_create').textContent = '€ ' + totalClaim.toFixed(2).replace('.', ',');
                document.getElementById('total_costs_create').textContent = '€ ' + totalCosts.toFixed(2).replace('.', ',');
                document.getElementById('net_amount_create').textContent = '€ ' + netAmount.toFixed(2).replace('.', ',');
                document.getElementById('net_amount_create').style.color = netAmount >= 0 ? '#388e3c' : '#d32f2f';
            }
            
            [claimAmount, damageAmount, art15Damages, legalFees, courtFees].forEach(element => {
                if (element) element.addEventListener('input', updateCreateCalculations);
            });
            
            // Initial calculation
            updateCreateCalculations();
        });
        </script>
        
        <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .nav-tab-wrapper {
            border-bottom: 1px solid #ccd0d4;
        }
        .nav-tab {
            font-size: 14px;
            padding: 8px 12px;
        }
        .tab-content-wrapper h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .form-table th {
            width: 150px;
        }
        </style>
        
        <?php
    }
    
    private function handle_case_actions() {
        if (!isset($_POST['action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'create_case':
                if (wp_verify_nonce($_POST['create_case_nonce'], 'create_case')) {
                    $this->create_new_case();
                }
                break;
            case 'update_case':
                if (wp_verify_nonce($_POST['update_case_nonce'], 'update_case')) {
                    $this->update_case();
                }
                break;
            case 'change_status':
                if (wp_verify_nonce($_POST['change_status_nonce'], 'change_status')) {
                    $this->handle_status_change();
                }
                break;
            case 'change_priority':
                if (wp_verify_nonce($_POST['change_priority_nonce'], 'change_priority')) {
                    $this->handle_priority_change();
                }
                break;
            default:
                if (!empty($action)) {
                    echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Unbekannte Aktion: "' . esc_html($action) . '"</p></div>';
                    echo '<div class="notice notice-info"><p><strong>Debug Info:</strong><br>';
                    echo 'Verfügbare Aktionen: create_case, update_case, change_status, change_priority<br>';
                    echo 'POST data: ' . print_r($_POST, true) . '</p></div>';
                }
                break;
        }
    }
    
    public function admin_page_cases() {
        global $wpdb;
        
        // Handle case actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_case_actions();
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $case_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_add_case_form();
                break;
            case 'edit':
                $this->render_edit_case_form($case_id);
                break;
            case 'view':
                $this->render_view_case($case_id);
                break;
            case 'delete':
                $this->handle_delete_case($case_id);
                $this->render_cases_list();
                break;
            case 'change_status':
                $this->handle_get_status_change($case_id);
                $this->render_cases_list();
                break;
            case 'change_priority':
                $this->handle_get_priority_change($case_id);
                $this->render_cases_list();
                break;
            default:
                $this->render_cases_list();
                break;
        }
    }
    
    private function render_cases_list() {
        global $wpdb;
        
        // Handle bulk actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action_nonce'])) {
            if (wp_verify_nonce($_POST['bulk_action_nonce'], 'bulk_actions')) {
                $this->handle_bulk_actions();
            }
        }
        
        // Get filter and sort parameters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'case_creation_date';
        $sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
        
        // Validate sort column to prevent SQL injection
        $allowed_sort_columns = array(
            'case_id' => 'c.case_id',
            'case_status' => 'c.case_status', 
            'case_creation_date' => 'c.case_creation_date',
            'total_amount' => 'total_amount',
            'emails_sender_email' => 'debtor_contact.email'
        );
        
        $sort_column = isset($allowed_sort_columns[$sort_by]) ? $allowed_sort_columns[$sort_by] : 'c.case_creation_date';
        
        // Build query with filters
        $where_conditions = array('1=1');
        $query_params = array();
        
        if (!empty($status_filter)) {
            $where_conditions[] = 'c.case_status = %s';
            $query_params[] = $status_filter;
        }
        
        if (!empty($search)) {
            $where_conditions[] = '(c.case_id LIKE %s OR debtor_contact.email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Check if tables exist
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_cases'");
        
        if (!$tables_exist) {
            $cases = array();
        } else {
            $query = "
                SELECT 
                    c.id,
                    c.case_id,
                    c.case_creation_date,
                    c.case_status,
                    c.case_priority,
                    COALESCE(CAST(c.claim_amount AS DECIMAL(10,2)), 0) as claim_amount,
                    COALESCE(CAST(c.legal_fees AS DECIMAL(10,2)), 0) as legal_fees,
                    COALESCE(CAST(c.court_fees AS DECIMAL(10,2)), 0) as court_fees,
                    (COALESCE(CAST(c.claim_amount AS DECIMAL(10,2)), 0) + COALESCE(CAST(c.legal_fees AS DECIMAL(10,2)), 0) + COALESCE(CAST(c.court_fees AS DECIMAL(10,2)), 0)) as total_amount,
                    debtor_contact.email as emails_sender_email
                FROM {$wpdb->prefix}klage_cases c
                LEFT JOIN {$wpdb->prefix}klage_case_contacts cc ON c.id = cc.case_id AND cc.role = 'debtor' AND cc.active_status = 1
                LEFT JOIN {$wpdb->prefix}klage_contacts debtor_contact ON cc.contact_id = debtor_contact.id
                WHERE {$where_clause} AND c.active_status = 1
                ORDER BY {$sort_column} {$sort_order}
                LIMIT 50
            ";
            
            if (!empty($query_params)) {
                $cases = $wpdb->get_results($wpdb->prepare($query, $query_params));
            } else {
                $cases = $wpdb->get_results($query);
            }
        }
        
        // Get statistics - Updated for v2.0.1 contact-centric architecture
        $total_cases = $tables_exist ? ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'") ?? 0) : 0;
        $draft_cases = $tables_exist ? ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'draft' AND active_status = 'active'") ?? 0) : 0;
        $processing_cases = $tables_exist ? ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'processing' AND active_status = 'active'") ?? 0) : 0;
        $completed_cases = $tables_exist ? ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'completed' AND active_status = 'active'") ?? 0) : 0;
        
        // Fix: Use COALESCE to handle NULL values and ensure proper decimal calculation
        $total_value = $tables_exist ? ($wpdb->get_var("
            SELECT SUM(
                COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0) + 
                COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0) + 
                COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)
            ) FROM {$wpdb->prefix}klage_cases
            WHERE active_status = 'active'
        ") ?? 0) : 0;
        
        // Debug: Log the calculation for troubleshooting
        if (get_option('klage_click_debug_mode') && $tables_exist) {
            $debug_query = "SELECT 
                COUNT(*) as case_count,
                SUM(COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0)) as claim_amount_sum,
                SUM(COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0)) as legal_fees_sum,
                SUM(COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)) as court_fees_sum,
                SUM(COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0) + COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0) + COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)) as final_total
                FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'";
            $debug_result = $wpdb->get_row($debug_query);
            error_log("CAH Debug Main - Financial calculation: " . json_encode($debug_result));
            
            // Also check if demo data exists
            $demo_check = $wpdb->get_results("SELECT case_id, claim_amount, legal_fees, court_fees, active_status FROM {$wpdb->prefix}klage_cases LIMIT 5");
            error_log("CAH Debug - Sample cases: " . json_encode($demo_check));
        }
        
        // Force debug for totalizer troubleshooting - Enhanced debugging
        $debug_query = "SELECT 
            COUNT(*) as case_count,
            SUM(COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0)) as claim_amount_sum,
            SUM(COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0)) as legal_fees_sum,
            SUM(COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)) as court_fees_sum,
            SUM(COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0) + COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0) + COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)) as final_total
            FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'";
        $debug_result = $wpdb->get_row($debug_query);
        error_log("CAH FORCED Debug - Financial calculation: " . json_encode($debug_result));
        
        // Additional debugging - check data types and values
        $sample_data = $wpdb->get_results("SELECT id, case_id, claim_amount, legal_fees, court_fees, active_status FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active' LIMIT 5");
        error_log("CAH Sample Data: " . json_encode($sample_data));
        
        // Check if demo data is properly seeded
        $demo_check = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_id LIKE 'DEMO-%'");
        error_log("CAH Demo cases count: " . $demo_check);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">GDPR Spam Fälle</h1>
            <a href="<?php echo admin_url('admin.php?page=la-cases&action=add'); ?>" class="page-title-action">
                Neuen Fall hinzufügen
            </a>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>🚀 v1.1.5 - Complete Case Management!</strong></p>
                <p>Vollständige Fall-Verwaltung mit Erstellen, Bearbeiten, Filtern und Bulk-Aktionen.</p>
            </div>
            
            <!-- Statistics Dashboard -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0; color: #0073aa; font-size: 24px;"><?php echo esc_html($total_cases); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Gesamt Fälle</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0; color: #ff9800; font-size: 24px;"><?php echo esc_html($draft_cases); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Entwürfe</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0; color: #2196f3; font-size: 24px;"><?php echo esc_html($processing_cases); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">In Bearbeitung</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0; color: #4caf50; font-size: 24px;"><?php echo esc_html($completed_cases); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Abgeschlossen</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="margin: 0; color: #0073aa; font-size: 20px;">€<?php echo esc_html(number_format($total_value, 2)); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Gesamtwert</p>
                </div>
            </div>
            
            <?php if (!$tables_exist): ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ Datenbank-Tabellen fehlen!</strong> Gehen Sie zu <a href="<?php echo admin_url('admin.php?page=klage-click-settings'); ?>">Einstellungen</a> und erstellen Sie die Tabellen.</p>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="la-cases">
                    
                    <div>
                        <label for="status" style="display: block; margin-bottom: 5px; font-weight: bold;">Status:</label>
                        <select name="status" id="status">
                            <option value="">Alle Status</option>
                            <option value="draft" <?php selected($status_filter, 'draft'); ?>>📝 Entwurf</option>
                            <option value="processing" <?php selected($status_filter, 'processing'); ?>>⚡ In Bearbeitung</option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>>✅ Abgeschlossen</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Suche:</label>
                        <input type="text" name="search" id="search" value="<?php echo esc_attr($search); ?>" 
                               placeholder="Fall-ID oder E-Mail..." style="width: 200px;">
                    </div>
                    
                    <div>
                        <input type="submit" class="button" value="🔍 Filtern">
                        <a href="<?php echo admin_url('admin.php?page=la-cases'); ?>" class="button">🗑️ Zurücksetzen</a>
                    </div>
                </form>
            </div>
            
            <!-- Cases Table -->
            <form method="post" id="cases-filter">
                <?php wp_nonce_field('bulk_actions', 'bulk_action_nonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="bulk_action">
                            <option value="">Bulk-Aktionen</option>
                            <option value="status_processing">Status → In Bearbeitung</option>
                            <option value="status_completed">Status → Abgeschlossen</option>
                            <option value="delete">Löschen</option>
                        </select>
                        <input type="submit" class="button action" value="Anwenden">
                    </div>
                    
                    <div class="alignright">
                        <span style="color: #666;"><?php echo count($cases); ?> von <?php echo $total_cases; ?> Fällen</span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th class="sortable column-case-id <?php echo ($sort_by === 'case_id') ? 'sorted ' . $sort_order : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(array('sort' => 'case_id', 'order' => (($sort_by === 'case_id' && $sort_order === 'asc') ? 'desc' : 'asc')))); ?>">
                                    <span>Fall-ID</span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th class="sortable column-status <?php echo ($sort_by === 'case_status') ? 'sorted ' . $sort_order : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(array('sort' => 'case_status', 'order' => (($sort_by === 'case_status' && $sort_order === 'asc') ? 'desc' : 'asc')))); ?>">
                                    <span>Status</span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th class="sortable column-email <?php echo ($sort_by === 'emails_sender_email') ? 'sorted ' . $sort_order : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(array('sort' => 'emails_sender_email', 'order' => (($sort_by === 'emails_sender_email' && $sort_order === 'asc') ? 'desc' : 'asc')))); ?>">
                                    <span>E-Mail Absender</span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th class="sortable column-amount <?php echo ($sort_by === 'total_amount') ? 'sorted ' . $sort_order : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(array('sort' => 'total_amount', 'order' => (($sort_by === 'total_amount' && $sort_order === 'asc') ? 'desc' : 'asc')))); ?>">
                                    <span>Betrag</span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th class="sortable column-created <?php echo ($sort_by === 'case_creation_date') ? 'sorted ' . $sort_order : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(array('sort' => 'case_creation_date', 'order' => (($sort_by === 'case_creation_date' && $sort_order === 'asc') ? 'desc' : 'asc')))); ?>">
                                    <span>Erstellt</span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cases)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <?php if (!$tables_exist): ?>
                                        <p><strong>Datenbank-Tabellen müssen erst erstellt werden.</strong></p>
                                        <a href="<?php echo admin_url('admin.php?page=klage-click-settings'); ?>" class="button button-primary">
                                            🔧 Tabellen erstellen
                                        </a>
                                    <?php elseif (!empty($search) || !empty($status_filter)): ?>
                                        <p>Keine Fälle gefunden, die den Filterkriterien entsprechen.</p>
                                        <a href="<?php echo admin_url('admin.php?page=la-cases'); ?>" class="button">Filter zurücksetzen</a>
                                    <?php else: ?>
                                        <p>Keine Fälle gefunden. Erstellen Sie Ihren ersten Fall!</p>
                                        <div style="margin-top: 15px;">
                                            <a href="<?php echo admin_url('admin.php?page=la-cases&action=add'); ?>" class="button button-primary" style="margin-right: 10px;">
                                                📝 Neuen Fall erstellen
                                            </a>
                                            <a href="<?php echo admin_url('admin.php?page=klage-click-import'); ?>" class="button button-secondary">
                                                📊 CSV Import verwenden
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="case_ids[]" value="<?php echo esc_attr($case->id); ?>">
                                    </th>
                                    <td><strong><?php echo esc_html($case->case_id); ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($case->case_status); ?>">
                                            <?php 
                                            $status_icons = array(
                                                'draft' => '📝 Entwurf',
                                                'processing' => '⚡ In Bearbeitung',
                                                'completed' => '✅ Abgeschlossen'
                                            );
                                            echo $status_icons[$case->case_status] ?? esc_html($case->case_status); 
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($case->emails_sender_email ?: '-'); ?></td>
                                    <td><strong>€<?php echo esc_html(number_format($case->total_amount, 2)); ?></strong></td>
                                    <td><?php echo esc_html(date_i18n('d.m.Y', strtotime($case->case_creation_date))); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=la-cases&action=view&id=' . $case->id); ?>" 
                                           class="button button-small" title="Fall ansehen">👁️</a>
                                        <a href="<?php echo admin_url('admin.php?page=la-cases&action=edit&id=' . $case->id); ?>" 
                                           class="button button-small" title="Fall bearbeiten">✏️</a>
                                        <a href="#" onclick="confirmDelete(<?php echo $case->id; ?>, '<?php echo esc_js($case->case_id); ?>')" 
                                           class="button button-small button-link-delete" title="Fall löschen">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        
        /* Sortable Table Headers v2.1.0 */
        .wp-list-table th.sortable {
            position: relative;
        }
        
        .wp-list-table th.sortable a {
            display: block;
            color: inherit;
            text-decoration: none;
            padding: 8px;
            position: relative;
        }
        
        .wp-list-table th.sortable a:hover {
            color: #0073aa;
        }
        
        .wp-list-table th.sortable .sorting-indicator {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            opacity: 0.3;
        }
        
        .wp-list-table th.sortable .sorting-indicator:before {
            content: '↕';
            font-size: 12px;
            color: #666;
        }
        
        .wp-list-table th.sortable:hover .sorting-indicator {
            opacity: 0.8;
        }
        
        .wp-list-table th.sortable.sorted .sorting-indicator {
            opacity: 1;
        }
        
        .wp-list-table th.sortable.sorted.asc .sorting-indicator:before {
            content: '↑';
            color: #0073aa;
            font-weight: bold;
        }
        
        .wp-list-table th.sortable.sorted.desc .sorting-indicator:before {
            content: '↓';
            color: #0073aa;
            font-weight: bold;
        }
        
        .wp-list-table th.sortable.sorted {
            background-color: #f0f8ff;
        }
        
        /* Responsive table adjustments */
        @media (max-width: 768px) {
            .wp-list-table th.sortable a {
                padding: 4px;
            }
            .wp-list-table th.sortable .sorting-indicator {
                right: 2px;
            }
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('cb-select-all');
            const checkboxes = document.querySelectorAll('input[name="case_ids[]"]');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                });
            }
        });
        
        function confirmDelete(caseId, caseIdentifier) {
            if (confirm('⚠️ WARNUNG: Fall "' + caseIdentifier + '" unwiderruflich löschen?\n\nDieser Vorgang kann nicht rückgängig gemacht werden.\nAlle zugehörigen Daten werden entfernt.')) {
                const nonce = '<?php echo wp_create_nonce('delete_case_'); ?>' + caseId;
                window.location.href = '<?php echo admin_url('admin.php?page=la-cases&action=delete&id='); ?>' + caseId + '&_wpnonce=' + nonce;
            }
        }
        </script>
        <?php
    }
    
    // Financial calculator functionality removed in v1.5.1 - moved to separate plugin
    // Legacy CSV Import functionality removed in v1.9.0 - replaced with Universal Import
    
    public function admin_page_settings() {
        // Handle v2.0.0 database operations
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['v200_operation_nonce'])) {
            if (wp_verify_nonce($_POST['v200_operation_nonce'], 'v200_database_operation')) {
                if (isset($_POST['recreate_schema'])) {
                    $this->recreate_v200_schema();
                } elseif (isset($_POST['populate_demo_data'])) {
                    $this->populate_v200_demo_data();
                } elseif (isset($_POST['cleanup_old_tables'])) {
                    $this->cleanup_old_tables();
                } elseif (isset($_POST['clear_all_data'])) {
                    $this->clear_all_sample_data();
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Klage.Click Hub Einstellungen', 'court-automation-hub'); ?></h1>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>🚀 Case Creation & Totals Fix</strong></p>
                <p>Comprehensive contact-centric database architecture with TV lawyer management, financial tracking, and document integration.</p>
            </div>
            
            <!-- Database Management Section -->
            <div class="postbox" style="margin-bottom: 30px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9;">🛠️ Datenbank Management</h2>
                <div class="inside" style="padding: 20px;">
                    <div style="margin: 15px 0;">
                        <h4>Tabellen-Status:</h4>
                        <?php $this->display_v200_system_status(); ?>
                    </div>
                    
                    <form method="post" style="margin-bottom: 15px;">
                        <?php wp_nonce_field('v200_database_operation', 'v200_operation_nonce'); ?>
                        
                        <div style="margin-bottom: 15px;">
                            <input type="submit" name="recreate_schema" class="button button-primary" value="🔧 Schema neu erstellen" 
                                   onclick="return confirm('Datenbankschema neu erstellen? Bestehende Daten bleiben erhalten.');">
                            <p class="description">Erstellt alle Tabellen neu. Bestehende Daten werden nicht gelöscht.</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <input type="submit" name="populate_demo_data" class="button button-secondary" value="📝 Demo-Daten hinzufügen" 
                                   onclick="return confirm('Demo-Daten hinzufügen? (3 Fälle, 6 Kontakte, etc.)');">
                            <p class="description">Fügt realistische deutsche Rechtsfall-Daten für Tests hinzu.</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <input type="submit" name="cleanup_old_tables" class="button button-secondary" value="🧹 Alte Tabellen aufräumen" 
                                   onclick="return confirm('Alte leere Tabellen löschen? Nur sichere Bereinigung leerer Tabellen.');">
                            <p class="description">Entfernt alte, leere Tabellen aus Vorversionen (nur wenn leer).</p>
                        </div>
                        
                        <div style="margin-bottom: 15px; border: 2px solid #dc3232; padding: 10px; border-radius: 5px;">
                            <input type="submit" name="clear_all_data" class="button" value="🗑️ Alle Sample-Daten löschen" 
                                   style="background: #dc3232; color: white; border-color: #dc3232;"
                                   onclick="return confirm('ACHTUNG: Alle Fälle, Kontakte und Finanzdaten werden gelöscht! Nur für Entwicklung/Testing. Fortfahren?');">
                            <p class="description" style="color: #dc3232;"><strong>ENTWICKLUNGSPHASE:</strong> Löscht alle problematischen Sample-Daten für saubere Tests.</p>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Plugin Settings -->
            <form method="post" action="options.php">
                <?php
                settings_fields('klage_click_settings');
                do_settings_sections('klage_click_settings');
                ?>
                
                <div class="postbox">
                    <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9;">⚙️ Plugin-Einstellungen</h2>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Debug-Modus</th>
                                <td>
                                    <input type="checkbox" name="klage_click_debug_mode" value="1" <?php checked(1, get_option('klage_click_debug_mode')); ?> />
                                    <label for="klage_click_debug_mode">Debug-Informationen in Admin-Notices anzeigen</label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button('Einstellungen speichern'); ?>
                    </div>
                </div>
            </form>
            
            <!-- System Information -->
            <div class="postbox">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9;">ℹ️ System-Information</h2>
                <div class="inside" style="padding: 20px;">
                    <table class="form-table">
                        <tr>
                            <th>Plugin-Version:</th>
                            <td><strong><?php echo esc_html(CAH_PLUGIN_VERSION); ?></strong></td>
                        </tr>
                        <tr>
                            <th>WordPress-Version:</th>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <th>PHP-Version:</th>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th>Datenbank:</th>
                            <td><?php echo $GLOBALS['wpdb']->db_version(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function recreate_v200_schema() {
        echo '<div class="notice notice-info"><p><strong>🔧 Schema wird neu erstellt...</strong></p></div>';
        
        // Load v2.0.0 database class
        if (!class_exists('CAH_Database_v200')) {
            require_once CAH_PLUGIN_PATH . 'includes/class-database-v200.php';
        }
        
        $database_v200 = new CAH_Database_v200();
        $results = $database_v200->execute_schema_creation();
        
        // Handle results
        if (isset($results['creation'])) {
            echo '<div class="notice notice-success">';
            echo '<p><strong>✅ Schema erfolgreich erstellt!</strong></p>';
            echo '<p>Erstellte Tabellen: ' . count($results['creation']) . '</p>';
            echo '<ul>';
            foreach ($results['creation'] as $table => $status) {
                echo '<li>' . esc_html($table) . ': ' . esc_html($status) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            
            if (isset($results['cleanup'])) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>🧹 Alte Tabellen bereinigt:</strong></p>';
                echo '<ul>';
                foreach ($results['cleanup'] as $table => $status) {
                    echo '<li>' . esc_html($table) . ': ' . esc_html($status) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
    }
    
    private function populate_v200_demo_data() {
        echo '<div class="notice notice-info"><p><strong>📝 Demo-Daten werden hinzugefügt...</strong></p></div>';
        
        // Load v2.0.0 database class
        if (!class_exists('CAH_Database_v200')) {
            require_once CAH_PLUGIN_PATH . 'includes/class-database-v200.php';
        }
        
        $database_v200 = new CAH_Database_v200();
        $demo_results = $database_v200->populate_demo_data();
        
        echo '<div class="notice notice-success">';
        echo '<p><strong>✅ Demo-Daten erfolgreich hinzugefügt!</strong></p>';
        echo '<ul>';
        echo '<li>Kontakte: ' . intval($demo_results['contacts']) . '</li>';
        echo '<li>Gerichte: ' . intval($demo_results['courts']) . '</li>';
        echo '<li>Fälle: ' . intval($demo_results['cases']) . '</li>';
        echo '<li>Beziehungen: ' . intval($demo_results['relationships']) . '</li>';
        echo '<li>Finanzen: ' . intval($demo_results['financials']) . '</li>';
        echo '<li>TV-Aufträge: ' . intval($demo_results['tv_assignments']) . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    private function cleanup_old_tables() {
        echo '<div class="notice notice-info"><p><strong>🧹 Alte Tabellen werden bereinigt...</strong></p></div>';
        
        // Load v2.0.0 database class
        if (!class_exists('CAH_Database_v200')) {
            require_once CAH_PLUGIN_PATH . 'includes/class-database-v200.php';
        }
        
        $database_v200 = new CAH_Database_v200();
        $cleanup_results = $database_v200->cleanup_old_tables();
        
        echo '<div class="notice notice-success">';
        echo '<p><strong>✅ Bereinigung abgeschlossen!</strong></p>';
        echo '<ul>';
        foreach ($cleanup_results as $table => $status) {
            echo '<li>' . esc_html($table) . ': ' . esc_html($status) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    private function clear_all_sample_data() {
        global $wpdb;
        
        echo '<div class="notice notice-info"><p><strong>🗑️ Sample-Daten werden gelöscht...</strong></p></div>';
        
        $cleared_data = array();
        $errors = array();
        
        try {
            // Clear all cases
            $cases_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases");
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}klage_cases");
            if ($result !== false) {
                $cleared_data['cases'] = $cases_count;
            } else {
                $errors[] = 'Fehler beim Löschen der Fälle';
            }
            
            // Clear all case-contact relationships
            $relationships_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_case_contacts");
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}klage_case_contacts");
            if ($result !== false) {
                $cleared_data['case_contacts'] = $relationships_count;
            }
            
            // Clear all contacts
            $contacts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_contacts");
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}klage_contacts");
            if ($result !== false) {
                $cleared_data['contacts'] = $contacts_count;
            } else {
                $errors[] = 'Fehler beim Löschen der Kontakte';
            }
            
            // Clear all financials
            $financials_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_financials");
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}klage_financials");
            if ($result !== false) {
                $cleared_data['financials'] = $financials_count;
            }
            
            // Clear TV assignments
            $tv_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_tv_assignments");
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}klage_tv_assignments");
            if ($result !== false) {
                $cleared_data['tv_assignments'] = $tv_count;
            }
            
            // Clear audit logs (optional - keeps history clean)
            $audit_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_audit");
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}klage_audit");
            if ($result !== false) {
                $cleared_data['audit_logs'] = $audit_count;
            }
            
            // Success message
            if (empty($errors)) {
                echo '<div class="notice notice-success">';
                echo '<p><strong>✅ Sample-Daten erfolgreich gelöscht!</strong></p>';
                echo '<ul>';
                foreach ($cleared_data as $type => $count) {
                    echo '<li>' . esc_html(ucfirst($type)) . ': ' . intval($count) . ' Einträge gelöscht</li>';
                }
                echo '</ul>';
                echo '<p><strong>🔧 Nächste Schritte:</strong> Jetzt können Sie saubere Testfälle erstellen ohne Unique-Constraint-Konflikte.</p>';
                echo '</div>';
                
                error_log("CAH v214: All sample data cleared - Tables cleaned for development phase");
            } else {
                echo '<div class="notice notice-error">';
                echo '<p><strong>❌ Teilweise Fehler beim Löschen:</strong></p>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Fehler beim Löschen der Sample-Daten:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
            error_log("CAH v214: Error clearing sample data: " . $e->getMessage());
        }
    }
    
    private function display_v200_system_status() {
        global $wpdb;
        
        // Check v2.0.0 tables
        $v200_tables = array(
            'klage_contacts' => 'Kontakte (Universal)',
            'klage_cases' => 'Fälle (Erweitert)',
            'klage_case_contacts' => 'Fall-Kontakt-Beziehungen',
            'klage_tv_assignments' => 'TV-Anwalt-Aufträge',
            'klage_financials' => 'Finanztransaktionen',
            'klage_documents' => 'Dokumentenverwaltung',
            'klage_courts' => 'Gerichte-Referenz',
            'klage_audit' => 'Audit-Protokoll',
            'klage_import_configs' => 'Import-Konfigurationen',
            'klage_import_history' => 'Import-Historie'
        );
        
        echo '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">';
        echo '<table class="widefat" style="margin: 0;">';
        echo '<thead><tr><th>Tabelle</th><th>Status</th><th>Einträge</th></tr></thead>';
        echo '<tbody>';
        
        $all_exist = true;
        $total_records = 0;
        
        foreach ($v200_tables as $table => $description) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
                $total_records += intval($count);
                echo '<tr>';
                echo '<td>' . esc_html($description) . '</td>';
                echo '<td><span style="color: green;">✅ Existiert</span></td>';
                echo '<td>' . intval($count) . '</td>';
                echo '</tr>';
            } else {
                $all_exist = false;
                echo '<tr>';
                echo '<td>' . esc_html($description) . '</td>';
                echo '<td><span style="color: red;">❌ Fehlt</span></td>';
                echo '<td>-</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: ' . ($all_exist ? '#d4edda' : '#f8d7da') . '; border-radius: 3px;">';
        echo '<strong>Status: </strong>';
        if ($all_exist) {
            echo '<span style="color: #155724;">✅ Alle v2.0.0 Tabellen vorhanden</span>';
            echo '<br><strong>Gesamt-Einträge: </strong>' . $total_records;
        } else {
            echo '<span style="color: #721c24;">❌ Einige v2.0.0 Tabellen fehlen</span>';
        }
        echo '</div>';
        echo '</div>';
    }
    
    private function force_create_tables() {
        // Legacy method - redirect to v2.0.0
        echo '<div class="notice notice-warning"><p><strong>⚠️ Alte Funktion wird zu v2.0.0 umgeleitet...</strong></p></div>';
        $this->recreate_v200_schema();
    }
    
    // Remove the old column-by-column method
    private function add_missing_columns_to_existing_tables($wpdb) {
        // This method is no longer needed - dbDelta handles everything
        return;
    }
    
    public function ajax_download_template() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'download_template')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Create CSV template
        $filename = 'forderungen_import_template_' . date('Y-m-d') . '.csv';
        
        // Set headers for download
        header('Content-Type: application/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        // Add BOM for UTF-8 Excel compatibility
        echo chr(0xEF) . chr(0xBB) . chr(0xBF);
        
        // CSV Header
        $header = array(
            'Fall-ID',
            'Fall-Status', 
            'Brief-Status',
            'Mandant',
            'Einreichungsdatum',
            'Beweise',
            'Firmenname',
            'Vorname',
            'Nachname', 
            'Adresse',
            'Postleitzahl',
            'Stadt',
            'Land',
            'Email',
            'Telefon',
            'Notizen'
        );
        
        echo implode(';', $header) . "\n";
        
        // Sample data
        echo "SPAM-2024-0001;draft;pending;Ihre Firma GmbH;2024-01-15;SPAM E-Mail;;Max;Mustermann;Musterstraße 123;12345;Musterstadt;Deutschland;spam@example.com;+49123456789;Test\n";
        
        exit;
    }
    
    public function ajax_export_calculation() {
        echo "CSV Export functionality - v1.1.2";
        exit;
    }
    
    private function display_system_status() {
        global $wpdb;
        
        $required_tables = array(
            'klage_cases', 
            'klage_contacts', 
            'klage_case_contacts', 
            'klage_financials', 
            'klage_courts', 
            'klage_audit', 
            'klage_import_configs', 
            'klage_import_history',
            'klage_tv_assignments',
            'klage_documents'
        );
        
        echo '<table class="wp-list-table widefat">';
        echo '<thead><tr><th>Tabelle</th><th>Status</th><th>Einträge</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($required_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name") : 0;
            
            $status_icon = $exists ? '✅' : '❌';
            $status_text = $exists ? 'OK' : 'Fehlt';
            
            echo '<tr>';
            echo '<td>' . esc_html($table) . '</td>';
            echo '<td>' . $status_icon . ' ' . esc_html($status_text) . '</td>';
            echo '<td>' . esc_html($count) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_cases'")) {
            echo '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 5px;">';
            echo '<p><strong>⚠️ Hinweis:</strong> Haupttabellen fehlen. Gehen Sie zu Einstellungen → Datenbank reparieren.</p>';
            echo '</div>';
        }
    }
    
    private function render_edit_case_form($case_id) {
        global $wpdb;
        
        // Get case data using v2.0.0 structure
        $case = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_cases WHERE id = %d
        ", $case_id));
        
        if (!$case) {
            echo '<div class="notice notice-error"><p>Fall nicht gefunden.</p></div>';
            return;
        }
        
        // Get all contact relationships for this case
        $case_contacts = $wpdb->get_results($wpdb->prepare("
            SELECT cc.*, c.first_name, c.last_name, c.company_name, c.email, c.phone, c.contact_type,
                   c.street, c.street_number, c.postal_code, c.city, c.country, c.iban, c.bic, c.bank_name
            FROM {$wpdb->prefix}klage_case_contacts cc
            JOIN {$wpdb->prefix}klage_contacts c ON cc.contact_id = c.id
            WHERE cc.case_id = %d AND cc.active_status = 1
            ORDER BY cc.role
        ", $case_id));
        
        // Get financial data
        $financials = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_financials 
            WHERE case_id = %d
            ORDER BY transaction_date DESC
        ", $case_id));
        
        // Get court info
        $court = null;
        if ($case->court_id) {
            $court = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}klage_courts WHERE id = %d
            ", $case->court_id));
        }
        
        // Get all courts for dropdown
        $all_courts = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}klage_courts 
            WHERE active_status = 1 
            ORDER BY court_name ASC
        ");
        
        // Get TV assignments
        $tv_assignments = $wpdb->get_results($wpdb->prepare("
            SELECT tv.*, c.first_name, c.last_name, c.company_name
            FROM {$wpdb->prefix}klage_tv_assignments tv
            LEFT JOIN {$wpdb->prefix}klage_contacts c ON tv.tv_lawyer_contact_id = c.id
            WHERE tv.case_id = %d
            ORDER BY tv.court_date DESC
        ", $case_id));
        
        // Get documents
        $documents = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_documents
            WHERE case_id = %d
            ORDER BY created_at DESC
        ", $case_id));
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_case'])) {
            $this->handle_case_update_v210($case_id, $_POST);
        }
        
        // Organize contacts by role for easier access
        $contacts_by_role = array();
        foreach ($case_contacts as $contact) {
            $contacts_by_role[$contact->role] = $contact;
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Fall bearbeiten: <?php echo esc_html($case->case_id); ?></h1>
            
            <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;">
                <p><strong>📝 v2.1.0 - Vollständige Fallbearbeitung</strong></p>
                <p>Nutzen Sie die Tabs für strukturierte Fallbearbeitung. Alle Änderungen werden automatisch im Audit-Trail protokolliert.</p>
            </div>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('edit_case_action', 'edit_case_nonce'); ?>
                <input type="hidden" name="save_case" value="1">
                <input type="hidden" name="case_db_id" value="<?php echo esc_attr($case_id); ?>">
                
                <!-- v2.1.0 Complete Tab Navigation -->
                <div class="nav-tab-wrapper">
                    <a href="#fall-daten" class="nav-tab nav-tab-active" onclick="switchCaseEditTab(event, 'fall-daten')">📋 Fall Daten</a>
                    <a href="#beweise" class="nav-tab" onclick="switchCaseEditTab(event, 'beweise')">🔍 Beweise</a>
                    <a href="#mandant" class="nav-tab" onclick="switchCaseEditTab(event, 'mandant')">👤 Mandant</a>
                    <a href="#gegenseite" class="nav-tab" onclick="switchCaseEditTab(event, 'gegenseite')">⚖️ Gegenseite</a>
                    <a href="#gericht" class="nav-tab" onclick="switchCaseEditTab(event, 'gericht')">🏛️ Gericht</a>
                    <a href="#finanzen" class="nav-tab" onclick="switchCaseEditTab(event, 'finanzen')">💰 Finanzen</a>
                    <a href="#dokumentenerstellung" class="nav-tab" onclick="switchCaseEditTab(event, 'dokumentenerstellung')">📄 Dokumentenerstellung</a>
                    <a href="#crm" class="nav-tab" onclick="switchCaseEditTab(event, 'crm')">📞 CRM</a>
                    <a href="#gerichtstermine" class="nav-tab" onclick="switchCaseEditTab(event, 'gerichtstermine')">📅 Gerichtstermine</a>
                    <a href="#partner" class="nav-tab" onclick="switchCaseEditTab(event, 'partner')">🤝 Partner</a>
                </div>
                
                <!-- Tab Content -->
                <div class="tab-content-wrapper" style="background: white; padding: 20px; border: 1px solid #ccd0d4; margin-top: -1px;">
                    
                    <!-- Fall Daten Tab -->
                    <div id="fall-daten" class="tab-content active">
                        <h2>📋 Fall Daten</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="case_id">Fall-ID</label></th>
                                <td>
                                    <input type="text" id="case_id" name="case_id" value="<?php echo esc_attr($case->case_id); ?>" 
                                           class="regular-text" required>
                                    <p class="description">Eindeutige Fall-Kennung (editierbar)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_status">Status</label></th>
                                <td>
                                    <select id="case_status" name="case_status" class="regular-text">
                                        <option value="draft" <?php selected($case->case_status, 'draft'); ?>>Entwurf</option>
                                        <option value="processing" <?php selected($case->case_status, 'processing'); ?>>In Bearbeitung</option>
                                        <option value="pending" <?php selected($case->case_status, 'pending'); ?>>Wartend</option>
                                        <option value="completed" <?php selected($case->case_status, 'completed'); ?>>Abgeschlossen</option>
                                        <option value="archived" <?php selected($case->case_status, 'archived'); ?>>Archiviert</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_priority">Priorität</label></th>
                                <td>
                                    <select id="case_priority" name="case_priority" class="regular-text">
                                        <option value="low" <?php selected($case->case_priority, 'low'); ?>>Niedrig</option>
                                        <option value="medium" <?php selected($case->case_priority, 'medium'); ?>>Mittel</option>
                                        <option value="high" <?php selected($case->case_priority, 'high'); ?>>Hoch</option>
                                        <option value="urgent" <?php selected($case->case_priority, 'urgent'); ?>>Dringend</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_complexity">Komplexität</label></th>
                                <td>
                                    <select id="case_complexity" name="case_complexity" class="regular-text">
                                        <option value="simple" <?php selected($case->case_complexity, 'simple'); ?>>Einfach</option>
                                        <option value="medium" <?php selected($case->case_complexity, 'medium'); ?>>Mittel</option>
                                        <option value="complex" <?php selected($case->case_complexity, 'complex'); ?>>Komplex</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="legal_basis">Rechtsgrundlage</label></th>
                                <td>
                                    <input type="text" id="legal_basis" name="legal_basis" value="<?php echo esc_attr($case->legal_basis); ?>" 
                                           class="regular-text" placeholder="z.B. GDPR Art. 82">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="schufa_checks_completed">Schufa Go/NoGo</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="schufa_checks_completed" name="schufa_checks_completed" value="1" 
                                               <?php checked($case->schufa_checks_completed, 1); ?>>
                                        Schufa-Prüfung abgeschlossen
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="filing_date">Einreichungsdatum</label></th>
                                <td>
                                    <input type="date" id="filing_date" name="filing_date" 
                                           value="<?php echo esc_attr($case->filing_date); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="response_deadline">Antwort-Frist</label></th>
                                <td>
                                    <input type="date" id="response_deadline" name="response_deadline" 
                                           value="<?php echo esc_attr($case->response_deadline); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_notes">Notizen</label></th>
                                <td>
                                    <textarea id="case_notes" name="case_notes" rows="4" class="large-text"><?php echo esc_textarea($case->case_notes); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Beweise Tab -->
                    <div id="beweise" class="tab-content">
                        <h2>🔍 Beweise</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="case_documents_attachments">Dokumentenanhänge</label></th>
                                <td>
                                    <textarea id="case_documents_attachments" name="case_documents_attachments" rows="3" class="large-text"><?php echo esc_textarea($case->case_documents_attachments); ?></textarea>
                                    <p class="description">URLs oder Verweise auf Beweisdokumente</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Hochgeladene Dokumente</th>
                                <td>
                                    <?php if (!empty($documents)): ?>
                                        <div class="evidence-documents">
                                            <?php foreach ($documents as $doc): ?>
                                                <div class="document-item" style="border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 3px;">
                                                    <strong><?php echo esc_html($doc->title ?: $doc->original_filename); ?></strong>
                                                    <span class="document-type">(<?php echo esc_html($doc->document_type); ?>)</span>
                                                    <br>
                                                    <small>Hochgeladen: <?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($doc->created_at))); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p><em>Keine Dokumente hochgeladen</em></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Mandant Tab -->
                    <div id="mandant" class="tab-content">
                        <h2>👤 Mandant</h2>
                        <?php 
                        $plaintiff = $contacts_by_role['plaintiff'] ?? null;
                        $plaintiff_rep = $contacts_by_role['plaintiff_rep'] ?? null;
                        ?>
                        <div style="background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                            <p><strong>Mandant (Kläger):</strong> 
                            <?php if ($plaintiff): ?>
                                <?php echo esc_html($plaintiff->first_name . ' ' . $plaintiff->last_name); ?>
                                <?php if ($plaintiff->company_name): ?>
                                    (<?php echo esc_html($plaintiff->company_name); ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                <em>Noch nicht zugewiesen</em>
                            <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if ($plaintiff): ?>
                            <table class="form-table">
                                <tr><th colspan="2"><h3>Mandant Details</h3></th></tr>
                                <tr>
                                    <th>Name:</th>
                                    <td><?php echo esc_html($plaintiff->first_name . ' ' . $plaintiff->last_name); ?></td>
                                </tr>
                                <?php if ($plaintiff->company_name): ?>
                                <tr>
                                    <th>Unternehmen:</th>
                                    <td><?php echo esc_html($plaintiff->company_name); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>E-Mail:</th>
                                    <td><?php echo esc_html($plaintiff->email); ?></td>
                                </tr>
                                <tr>
                                    <th>Telefon:</th>
                                    <td><?php echo esc_html($plaintiff->phone); ?></td>
                                </tr>
                                <tr>
                                    <th>Adresse:</th>
                                    <td>
                                        <?php echo esc_html($plaintiff->street . ' ' . $plaintiff->street_number); ?><br>
                                        <?php echo esc_html($plaintiff->postal_code . ' ' . $plaintiff->city); ?>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>
                        
                        <?php if ($plaintiff_rep): ?>
                            <table class="form-table">
                                <tr><th colspan="2"><h3>Rechtsvertretung</h3></th></tr>
                                <tr>
                                    <th>Anwalt:</th>
                                    <td><?php echo esc_html($plaintiff_rep->first_name . ' ' . $plaintiff_rep->last_name); ?></td>
                                </tr>
                                <tr>
                                    <th>Kanzlei:</th>
                                    <td><?php echo esc_html($plaintiff_rep->company_name); ?></td>
                                </tr>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gegenseite Tab -->
                    <div id="gegenseite" class="tab-content">
                        <h2>⚖️ Gegenseite</h2>
                        <?php 
                        $debtor = $contacts_by_role['debtor'] ?? null;
                        $debtor_rep = $contacts_by_role['debtor_rep'] ?? null;
                        $legal_counsel = $contacts_by_role['legal_counsel'] ?? null;
                        ?>
                        
                        <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                            <p><strong>Beklagte:</strong> 
                            <?php if ($debtor): ?>
                                <?php echo esc_html($debtor->first_name . ' ' . $debtor->last_name); ?>
                                <?php if ($debtor->company_name): ?>
                                    (<?php echo esc_html($debtor->company_name); ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                <em>Noch nicht zugewiesen</em>
                            <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if ($debtor): ?>
                            <table class="form-table">
                                <tr><th colspan="2"><h3>Beklagte Details</h3></th></tr>
                                <tr>
                                    <th>Name:</th>
                                    <td><?php echo esc_html($debtor->first_name . ' ' . $debtor->last_name); ?></td>
                                </tr>
                                <?php if ($debtor->company_name): ?>
                                <tr>
                                    <th>Unternehmen:</th>
                                    <td><?php echo esc_html($debtor->company_name); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>E-Mail:</th>
                                    <td><?php echo esc_html($debtor->email); ?></td>
                                </tr>
                                <tr>
                                    <th>Adresse:</th>
                                    <td>
                                        <?php echo esc_html($debtor->street . ' ' . $debtor->street_number); ?><br>
                                        <?php echo esc_html($debtor->postal_code . ' ' . $debtor->city); ?>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>
                        
                        <?php if ($legal_counsel): ?>
                            <table class="form-table">
                                <tr><th colspan="2"><h3>Rechtsvertretung der Gegenseite</h3></th></tr>
                                <tr>
                                    <th>Anwalt:</th>
                                    <td><?php echo esc_html($legal_counsel->first_name . ' ' . $legal_counsel->last_name); ?></td>
                                </tr>
                                <tr>
                                    <th>Kanzlei:</th>
                                    <td><?php echo esc_html($legal_counsel->company_name); ?></td>
                                </tr>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gericht Tab -->
                    <div id="gericht" class="tab-content">
                        <h2>🏛️ Gericht</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="court_id">Zuständiges Gericht</label></th>
                                <td>
                                    <select id="court_id" name="court_id" class="regular-text">
                                        <option value="">-- Gericht auswählen --</option>
                                        <?php foreach ($all_courts as $court_option): ?>
                                            <option value="<?php echo $court_option->id; ?>" 
                                                    <?php selected($case->court_id, $court_option->id); ?>>
                                                <?php echo esc_html($court_option->court_name . ' - ' . $court_option->city); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php if ($court): ?>
                            <tr>
                                <th scope="row">Gerichtsdetails</th>
                                <td>
                                    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                                        <strong><?php echo esc_html($court->court_name); ?></strong><br>
                                        <?php echo esc_html($court->street . ', ' . $court->postal_code . ' ' . $court->city); ?><br>
                                        <?php if ($court->phone): ?>Tel: <?php echo esc_html($court->phone); ?><br><?php endif; ?>
                                        <?php if ($court->email): ?>E-Mail: <?php echo esc_html($court->email); ?><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <!-- Finanzen Tab -->
                    <div id="finanzen" class="tab-content">
                        <h2>💰 Finanzen</h2>
                        <div class="financial-overview" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; text-align: center;">
                                <h3>Forderungssumme</h3>
                                <div style="font-size: 24px; font-weight: bold; color: #2e7d32;">
                                    €<?php echo number_format($case->claim_amount, 2, ',', '.'); ?>
                                </div>
                            </div>
                            <div style="background: #fff3e0; padding: 15px; border-radius: 5px; text-align: center;">
                                <h3>Schadenssumme</h3>
                                <div style="font-size: 24px; font-weight: bold; color: #f57c00;">
                                    €<?php echo number_format($case->damage_amount, 2, ',', '.'); ?>
                                </div>
                            </div>
                            <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center;">
                                <h3>Art. 15 Schäden</h3>
                                <div style="font-size: 24px; font-weight: bold; color: #1976d2;">
                                    €<?php echo number_format($case->art15_claim_damages ?? 0, 2, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="claim_amount">Forderungssumme</label></th>
                                <td>
                                    <input type="number" step="0.01" id="claim_amount" name="claim_amount" 
                                           value="<?php echo esc_attr($case->claim_amount); ?>" class="regular-text">
                                    <span> €</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="damage_amount">Schadenssumme</label></th>
                                <td>
                                    <input type="number" step="0.01" id="damage_amount" name="damage_amount" 
                                           value="<?php echo esc_attr($case->damage_amount); ?>" class="regular-text">
                                    <span> €</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="art15_claim_damages">Art. 15 GDPR Schäden</label></th>
                                <td>
                                    <input type="number" step="0.01" id="art15_claim_damages" name="art15_claim_damages" 
                                           value="<?php echo esc_attr($case->art15_claim_damages); ?>" class="regular-text">
                                    <span> €</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="legal_fees">Anwaltskosten</label></th>
                                <td>
                                    <input type="number" step="0.01" id="legal_fees" name="legal_fees" 
                                           value="<?php echo esc_attr($case->legal_fees); ?>" class="regular-text">
                                    <span> €</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="court_fees">Gerichtskosten</label></th>
                                <td>
                                    <input type="number" step="0.01" id="court_fees" name="court_fees" 
                                           value="<?php echo esc_attr($case->court_fees); ?>" class="regular-text">
                                    <span> €</span>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if (!empty($financials)): ?>
                        <h3>Finanztransaktionen</h3>
                        <div class="financial-transactions">
                            <?php foreach ($financials as $transaction): ?>
                                <div style="border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 3px;">
                                    <strong><?php echo esc_html($transaction->purpose); ?></strong>
                                    <span style="float: right; font-weight: bold; color: <?php echo $transaction->transaction_type === 'payment_in' ? '#2e7d32' : '#d32f2f'; ?>">
                                        €<?php echo number_format($transaction->amount, 2, ',', '.'); ?>
                                    </span>
                                    <br>
                                    <small><?php echo esc_html($transaction->transaction_type); ?> - <?php echo esc_html($transaction->transaction_date); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dokumentenerstellung Tab -->
                    <div id="dokumentenerstellung" class="tab-content">
                        <h2>📄 Dokumentenerstellung</h2>
                        <div style="background: #f0f8ff; padding: 20px; border-radius: 5px; text-align: center;">
                            <h3>🔗 Doc-Out Plugin Integration</h3>
                            <p>Hier wird das Dokumentenerstellungs-Plugin integriert.</p>
                            <?php do_action('cah_doc_out_integration', $case_id); ?>
                            <p><em>Plugin-Integration wird hier erscheinen...</em></p>
                        </div>
                    </div>
                    
                    <!-- CRM Tab -->
                    <div id="crm" class="tab-content">
                        <h2>📞 CRM</h2>
                        <div style="background: #f0f8ff; padding: 20px; border-radius: 5px;">
                            <h3>Kommunikationsverlauf</h3>
                            <p><em>Follow-up auf In/Out-Kommunikation (manuelle Aktualisierung für jetzt)</em></p>
                            
                            <textarea placeholder="Kommunikationsnotizen hinzufügen..." rows="4" class="large-text"></textarea>
                            <br><br>
                            <button type="button" class="button">Notiz hinzufügen</button>
                            
                            <h4>Verknüpfte Dokumente im Speicher</h4>
                            <p><em>Später: Integration von E-Mail, Post, IVR, internen und externen Services</em></p>
                        </div>
                    </div>
                    
                    <!-- Gerichtstermine Tab -->
                    <div id="gerichtstermine" class="tab-content">
                        <h2>📅 Gerichtstermine</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="next_hearing_date">Nächster Termin</label></th>
                                <td>
                                    <input type="datetime-local" id="next_hearing_date" name="next_hearing_date" 
                                           value="<?php echo $case->next_hearing_date ? date('Y-m-d\TH:i', strtotime($case->next_hearing_date)) : ''; ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        </table>
                        
                        <?php if (!empty($tv_assignments)): ?>
                        <h3>TV-Anwalt Zuweisungen</h3>
                        <div class="tv-assignments">
                            <?php foreach ($tv_assignments as $tv): ?>
                                <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background: #fafafa;">
                                    <h4>Termin: <?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($tv->court_date))); ?></h4>
                                    <p><strong>TV-Anwalt:</strong> <?php echo esc_html($tv->first_name . ' ' . $tv->last_name); ?></p>
                                    <p><strong>Status:</strong> <?php echo esc_html(ucfirst($tv->status)); ?></p>
                                    <p><strong>Ort:</strong> <?php echo esc_html($tv->court_location); ?></p>
                                    <?php if ($tv->request_notes): ?>
                                        <p><strong>Notizen:</strong> <?php echo esc_html($tv->request_notes); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p><em>Keine TV-Anwalt Zuweisungen vorhanden</em></p>
                        <?php endif; ?>
                        
                        <div style="background: #f0f8ff; padding: 15px; margin-top: 20px; border-radius: 5px;">
                            <h4>🗓️ Kalender-Integration</h4>
                            <p><em>Kalenderübersicht wird hier integriert (später mit externem Kalender verbunden)</em></p>
                        </div>
                    </div>
                    
                    <!-- Partner Tab -->
                    <div id="partner" class="tab-content">
                        <h2>🤝 Partner</h2>
                        <div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
                            <h3>B2B Partner Information</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="import_source">Partner-Quelle</label></th>
                                    <td>
                                        <input type="text" id="import_source" name="import_source" 
                                               value="<?php echo esc_attr($case->import_source); ?>" class="regular-text">
                                        <p class="description">B2B Partner, der den Fall gebracht hat</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="external_id">Externe Partner-ID</label></th>
                                    <td>
                                        <input type="text" id="external_id" name="external_id" 
                                               value="<?php echo esc_attr($case->external_id); ?>" class="regular-text">
                                        <p class="description">ID des Falls beim Partner</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <h4>Partner-Historie</h4>
                            <p><strong>Eingang:</strong> <?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($case->case_creation_date))); ?></p>
                            <p><em>Später: Entwicklung der Finanzen mit Partner (Umsatzbeteiligung, Gebühren, etc.) als Teil des Financial-Plugins</em></p>
                        </div>
                    </div>
                    
                </div>
                
                <div class="submit-section" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                    <input type="submit" name="save_case" id="save_case" class="button button-primary button-large" value="💾 Fall speichern">
                    <a href="<?php echo admin_url('admin.php?page=la-cases&action=view&id=' . $case_id); ?>" 
                       class="button button-large" style="margin-left: 10px;">👁️ Zur Ansicht</a>
                    <a href="<?php echo admin_url('admin.php?page=la-cases'); ?>" 
                       class="button button-large" style="margin-left: 10px;">← Zurück zur Liste</a>
                </div>
                
            </form>
            
        </div>
        
        <script>
        function switchCaseEditTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tab links
            tablinks = document.getElementsByClassName("nav-tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("nav-tab-active");
            }
            
            // Show the selected tab content and mark the button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("nav-tab-active");
        }
        </script>
        
        <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .nav-tab-active {
            background: white !important;
            border-bottom: 1px solid white !important;
        }
        .financial-overview h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        </style>
        
        <?php
    }
    
    private function render_view_case($case_id) {
        global $wpdb;
        
        // Get case data using v2.0.0 structure
        $case = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_cases WHERE id = %d
        ", $case_id));
        
        if (!$case) {
            echo '<div class="notice notice-error"><p>Fall nicht gefunden.</p></div>';
            return;
        }
        
        // Get all contact relationships for this case
        $case_contacts = $wpdb->get_results($wpdb->prepare("
            SELECT cc.*, c.first_name, c.last_name, c.company_name, c.email, c.phone, c.contact_type,
                   c.street, c.street_number, c.postal_code, c.city, c.country, c.iban, c.bic, c.bank_name
            FROM {$wpdb->prefix}klage_case_contacts cc
            JOIN {$wpdb->prefix}klage_contacts c ON cc.contact_id = c.id
            WHERE cc.case_id = %d AND cc.active_status = 1
            ORDER BY cc.role
        ", $case_id));
        
        // Get financial data
        $financials = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_financials 
            WHERE case_id = %d
            ORDER BY transaction_date DESC
        ", $case_id));
        
        // Get court info
        $court = null;
        if ($case->court_id) {
            $court = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}klage_courts WHERE id = %d
            ", $case->court_id));
        }
        
        // Get all courts for dropdown
        $all_courts = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}klage_courts 
            WHERE active_status = 1 
            ORDER BY court_name ASC
        ");
        
        // Get TV assignments
        $tv_assignments = $wpdb->get_results($wpdb->prepare("
            SELECT tv.*, c.first_name, c.last_name, c.company_name
            FROM {$wpdb->prefix}klage_tv_assignments tv
            LEFT JOIN {$wpdb->prefix}klage_contacts c ON tv.tv_lawyer_contact_id = c.id
            WHERE tv.case_id = %d
            ORDER BY tv.court_date DESC
        ", $case_id));
        
        // Get documents
        $documents = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_documents
            WHERE case_id = %d
            ORDER BY created_at DESC
        ", $case_id));
        
        // Handle form submission (same as edit functionality)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_case'])) {
            $this->handle_case_update_v210($case_id, $_POST);
        }
        
        // Organize contacts by role for easier access
        $contacts_by_role = array();
        foreach ($case_contacts as $contact) {
            $contacts_by_role[$contact->role] = $contact;
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Fall bearbeiten: <?php echo esc_html($case->case_id); ?></h1>
            
            <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;">
                <p><strong>📝 v2.1.0 - Vollständige Fallbearbeitung</strong></p>
                <p>Nutzen Sie die Tabs für strukturierte Fallbearbeitung. Alle Änderungen werden automatisch im Audit-Trail protokolliert.</p>
            </div>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('edit_case_action', 'edit_case_nonce'); ?>
                <input type="hidden" name="save_case" value="1">
                <input type="hidden" name="case_db_id" value="<?php echo esc_attr($case_id); ?>">
                
                <!-- v2.1.0 Complete Tab Navigation -->
                <div class="nav-tab-wrapper">
                    <a href="#fall-daten" class="nav-tab nav-tab-active" onclick="switchCaseEditTab(event, 'fall-daten')">📋 Fall Daten</a>
                    <a href="#beweise" class="nav-tab" onclick="switchCaseEditTab(event, 'beweise')">🔍 Beweise</a>
                    <a href="#mandant" class="nav-tab" onclick="switchCaseEditTab(event, 'mandant')">👤 Mandant</a>
                    <a href="#gegenseite" class="nav-tab" onclick="switchCaseEditTab(event, 'gegenseite')">⚖️ Gegenseite</a>
                    <a href="#gericht" class="nav-tab" onclick="switchCaseEditTab(event, 'gericht')">🏛️ Gericht</a>
                    <a href="#finanzen" class="nav-tab" onclick="switchCaseEditTab(event, 'finanzen')">💰 Finanzen</a>
                    <a href="#dokumentenerstellung" class="nav-tab" onclick="switchCaseEditTab(event, 'dokumentenerstellung')">📄 Dokumentenerstellung</a>
                    <a href="#crm" class="nav-tab" onclick="switchCaseEditTab(event, 'crm')">📞 CRM</a>
                    <a href="#gerichtstermine" class="nav-tab" onclick="switchCaseEditTab(event, 'gerichtstermine')">📅 Gerichtstermine</a>
                    <a href="#partner" class="nav-tab" onclick="switchCaseEditTab(event, 'partner')">🤝 Partner</a>
                </div>
                
                <!-- Tab Content -->
                <div class="tab-content-wrapper" style="background: white; padding: 20px; border: 1px solid #ccd0d4; margin-top: -1px;">
                    
                    <!-- Fall Daten Tab -->
                    <div id="fall-daten" class="tab-content active">
                        <h2>📋 Fall Daten</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="case_id">Fall-ID</label></th>
                                <td>
                                    <input type="text" id="case_id" name="case_id" value="<?php echo esc_attr($case->case_id); ?>" 
                                           class="regular-text" required>
                                    <p class="description">Eindeutige Fall-Kennung (editierbar)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_status">Status</label></th>
                                <td>
                                    <select id="case_status" name="case_status" class="regular-text">
                                        <option value="draft" <?php selected($case->case_status, 'draft'); ?>>Entwurf</option>
                                        <option value="processing" <?php selected($case->case_status, 'processing'); ?>>In Bearbeitung</option>
                                        <option value="pending" <?php selected($case->case_status, 'pending'); ?>>Wartend</option>
                                        <option value="completed" <?php selected($case->case_status, 'completed'); ?>>Abgeschlossen</option>
                                        <option value="archived" <?php selected($case->case_status, 'archived'); ?>>Archiviert</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_priority">Priorität</label></th>
                                <td>
                                    <select id="case_priority" name="case_priority" class="regular-text">
                                        <option value="low" <?php selected($case->case_priority, 'low'); ?>>Niedrig</option>
                                        <option value="medium" <?php selected($case->case_priority, 'medium'); ?>>Mittel</option>
                                        <option value="high" <?php selected($case->case_priority, 'high'); ?>>Hoch</option>
                                        <option value="urgent" <?php selected($case->case_priority, 'urgent'); ?>>Dringend</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_complexity">Komplexität</label></th>
                                <td>
                                    <select id="case_complexity" name="case_complexity" class="regular-text">
                                        <option value="simple" <?php selected($case->case_complexity, 'simple'); ?>>Einfach</option>
                                        <option value="medium" <?php selected($case->case_complexity, 'medium'); ?>>Mittel</option>
                                        <option value="complex" <?php selected($case->case_complexity, 'complex'); ?>>Komplex</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="legal_basis">Rechtsgrundlage</label></th>
                                <td>
                                    <input type="text" id="legal_basis" name="legal_basis" value="<?php echo esc_attr($case->legal_basis); ?>" 
                                           class="regular-text" placeholder="z.B. GDPR Art. 82">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="schufa_checks_completed">Schufa-Prüfung</label></th>
                                <td>
                                    <input type="checkbox" id="schufa_checks_completed" name="schufa_checks_completed" value="1" 
                                           <?php checked($case->schufa_checks_completed, 1); ?>>
                                    <label for="schufa_checks_completed">Schufa-Prüfung abgeschlossen</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="filing_date">Einreichungsdatum</label></th>
                                <td>
                                    <input type="date" id="filing_date" name="filing_date" value="<?php echo esc_attr($case->filing_date); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="response_deadline">Antwortfrist</label></th>
                                <td>
                                    <input type="date" id="response_deadline" name="response_deadline" value="<?php echo esc_attr($case->response_deadline); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="next_hearing_date">Nächster Termin</label></th>
                                <td>
                                    <input type="datetime-local" id="next_hearing_date" name="next_hearing_date" value="<?php echo esc_attr($case->next_hearing_date ? date('Y-m-d\TH:i', strtotime($case->next_hearing_date)) : ''); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="case_notes">Fall-Notizen</label></th>
                                <td>
                                    <textarea id="case_notes" name="case_notes" rows="5" class="large-text"><?php echo esc_textarea($case->case_notes); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Beweise Tab -->
                    <div id="beweise" class="tab-content">
                        <h2>🔍 Beweise</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="case_documents_attachments">Dokumentenanhänge</label></th>
                                <td>
                                    <textarea id="case_documents_attachments" name="case_documents_attachments" rows="8" class="large-text" 
                                              placeholder="Liste aller relevanten Dokumente und Beweise..."><?php echo esc_textarea($case->case_documents_attachments); ?></textarea>
                                    <p class="description">Führen Sie hier alle relevanten Dokumente und Beweise für diesen Fall auf.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- Documents list if available -->
                        <?php if ($documents): ?>
                            <h3>📄 Verfügbare Dokumente</h3>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Titel</th>
                                        <th>Typ</th>
                                        <th>Erstellt</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td><?php echo esc_html($doc->document_title); ?></td>
                                            <td><?php echo esc_html($doc->document_type); ?></td>
                                            <td><?php echo esc_html(date_i18n('d.m.Y', strtotime($doc->created_at))); ?></td>
                                            <td>
                                                <a href="#" class="button button-small">📋 Anzeigen</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><em>Noch keine Dokumente für diesen Fall vorhanden.</em></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Mandant Tab -->
                    <div id="mandant" class="tab-content">
                        <h2>👤 Mandant</h2>
                        <?php 
                        $client = $contacts_by_role['client'] ?? null;
                        if ($client): 
                        ?>
                            <table class="form-table">
                                <tr>
                                    <th colspan="2"><h3>Mandanten-Information</h3></th>
                                </tr>
                                <tr>
                                    <th>Name:</th>
                                    <td><strong><?php echo esc_html($client->company_name ?: trim($client->first_name . ' ' . $client->last_name)); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Kontakttyp:</th>
                                    <td><?php echo esc_html($client->contact_type); ?></td>
                                </tr>
                                <tr>
                                    <th>E-Mail:</th>
                                    <td><?php echo esc_html($client->email ?: 'Nicht verfügbar'); ?></td>
                                </tr>
                                <tr>
                                    <th>Telefon:</th>
                                    <td><?php echo esc_html($client->phone ?: 'Nicht verfügbar'); ?></td>
                                </tr>
                                <tr>
                                    <th>Adresse:</th>
                                    <td>
                                        <?php 
                                        $address_parts = array_filter([
                                            $client->street . ($client->street_number ? ' ' . $client->street_number : ''),
                                            ($client->postal_code && $client->city) ? $client->postal_code . ' ' . $client->city : '',
                                            $client->country
                                        ]);
                                        echo esc_html($address_parts ? implode(', ', $address_parts) : 'Nicht verfügbar');
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Banking:</th>
                                    <td>
                                        <?php if ($client->iban): ?>
                                            IBAN: <?php echo esc_html($client->iban); ?><br>
                                            <?php if ($client->bic): ?>BIC: <?php echo esc_html($client->bic); ?><br><?php endif; ?>
                                            <?php if ($client->bank_name): ?>Bank: <?php echo esc_html($client->bank_name); ?><?php endif; ?>
                                        <?php else: ?>
                                            Keine Banking-Daten verfügbar
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p><em>Kein Mandant für diesen Fall zugeordnet.</em></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gegenseite Tab -->
                    <div id="gegenseite" class="tab-content">
                        <h2>⚖️ Gegenseite</h2>
                        <?php 
                        $debtors = array_filter($case_contacts, function($contact) {
                            return $contact->role === 'debtor';
                        });
                        
                        if ($debtors): 
                        ?>
                            <?php foreach ($debtors as $debtor): ?>
                                <div style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                                    <h3>👤 <?php echo esc_html($debtor->company_name ?: trim($debtor->first_name . ' ' . $debtor->last_name)); ?></h3>
                                    <table class="form-table">
                                        <tr>
                                            <th>Kontakttyp:</th>
                                            <td><?php echo esc_html($debtor->contact_type); ?></td>
                                        </tr>
                                        <tr>
                                            <th>E-Mail:</th>
                                            <td><?php echo esc_html($debtor->email ?: 'Nicht verfügbar'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Telefon:</th>
                                            <td><?php echo esc_html($debtor->phone ?: 'Nicht verfügbar'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Adresse:</th>
                                            <td>
                                                <?php 
                                                $address_parts = array_filter([
                                                    $debtor->street . ($debtor->street_number ? ' ' . $debtor->street_number : ''),
                                                    ($debtor->postal_code && $debtor->city) ? $debtor->postal_code . ' ' . $debtor->city : '',
                                                    $debtor->country
                                                ]);
                                                echo esc_html($address_parts ? implode(', ', $address_parts) : 'Nicht verfügbar');
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p><em>Keine Gegenseite für diesen Fall zugeordnet.</em></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gericht Tab -->
                    <div id="gericht" class="tab-content">
                        <h2>🏛️ Gericht</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="court_id">Zuständiges Gericht</label></th>
                                <td>
                                    <select id="court_id" name="court_id" class="regular-text">
                                        <option value="">-- Gericht auswählen --</option>
                                        <?php foreach ($all_courts as $court_option): ?>
                                            <option value="<?php echo esc_attr($court_option->id); ?>" 
                                                    <?php selected($case->court_id, $court_option->id); ?>>
                                                <?php echo esc_html($court_option->court_name . ' (' . $court_option->court_type . ', ' . $court_option->city . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($court): ?>
                            <div style="background: #f0f8ff; padding: 15px; margin-top: 20px; border-radius: 5px;">
                                <h3>Aktuelle Gericht-Information</h3>
                                <table class="form-table">
                                    <tr>
                                        <th>Gerichtsname:</th>
                                        <td><?php echo esc_html($court->court_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Typ:</th>
                                        <td><?php echo esc_html($court->court_type); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Stadt:</th>
                                        <td><?php echo esc_html($court->city); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Adresse:</th>
                                        <td><?php echo esc_html(isset($court->address) ? $court->address : ($court->street . ', ' . $court->postal_code . ' ' . $court->city)); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Telefon:</th>
                                        <td><?php echo esc_html($court->phone ?: 'Nicht verfügbar'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>E-Mail:</th>
                                        <td><?php echo esc_html($court->email ?: 'Nicht verfügbar'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Finanzen Tab -->
                    <div id="finanzen" class="tab-content">
                        <h2>💰 Finanzen</h2>
                        
                        <!-- Financial Summary Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                            <div>
                                <label for="claim_amount">💰 Klagesumme (€)</label>
                                <input type="number" id="claim_amount" name="claim_amount" value="<?php echo esc_attr($case->claim_amount); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="damage_amount">⚖️ Schadenssumme (€)</label>
                                <input type="number" id="damage_amount" name="damage_amount" value="<?php echo esc_attr($case->damage_amount); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="art15_claim_damages">📋 Art. 15 Schäden (€)</label>
                                <input type="number" id="art15_claim_damages" name="art15_claim_damages" value="<?php echo esc_attr($case->art15_claim_damages ?: ''); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="legal_fees">💼 Anwaltskosten (€)</label>
                                <input type="number" id="legal_fees" name="legal_fees" value="<?php echo esc_attr($case->legal_fees); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                            <div>
                                <label for="court_fees">🏛️ Gerichtskosten (€)</label>
                                <input type="number" id="court_fees" name="court_fees" value="<?php echo esc_attr($case->court_fees); ?>" 
                                       step="0.01" min="0" class="regular-text">
                            </div>
                        </div>
                        
                        <!-- Financial calculation display -->
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                            <h3>📊 Gesamtkalkulation</h3>
                            <table class="form-table">
                                <tr>
                                    <th>Gesamtforderung:</th>
                                    <td><strong id="total_claim">€ 0,00</strong></td>
                                </tr>
                                <tr>
                                    <th>Gesamtkosten:</th>
                                    <td><strong id="total_costs">€ 0,00</strong></td>
                                </tr>
                                <tr style="border-top: 2px solid #333;">
                                    <th>Nettobetrag:</th>
                                    <td><strong id="net_amount">€ 0,00</strong></td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Financial transactions list -->
                        <?php if ($financials): ?>
                            <h3>📋 Finanztransaktionen</h3>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Typ</th>
                                        <th>Betrag</th>
                                        <th>Status</th>
                                        <th>Beschreibung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($financials as $financial): ?>
                                        <tr>
                                            <td><?php echo esc_html(date_i18n('d.m.Y', strtotime($financial->transaction_date))); ?></td>
                                            <td><?php echo esc_html($financial->transaction_type); ?></td>
                                            <td style="font-weight: bold;">
                                                <?php 
                                                $amount_class = $financial->amount >= 0 ? 'color: #388e3c;' : 'color: #d32f2f;';
                                                echo '<span style="' . $amount_class . '">' . number_format($financial->amount, 2, ',', '.') . ' €</span>';
                                                ?>
                                            </td>
                                            <td><?php echo esc_html($financial->payment_status ?? 'Unbekannt'); ?></td>
                                            <td><?php echo esc_html($financial->description ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dokumentenerstellung Tab -->
                    <div id="dokumentenerstellung" class="tab-content">
                        <h2>📄 Dokumentenerstellung</h2>
                        <p><em>Dokumentenerstellung wird in v2.0.6+ vollständig implementiert.</em></p>
                        
                        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                            <h3>🚀 Geplante Features:</h3>
                            <ul>
                                <li>📝 Automatische Klageschrift-Generierung</li>
                                <li>📋 Vorlagen-Verwaltung</li>
                                <li>🔗 Integration mit doc-out Plugin</li>
                                <li>📑 PDF-Export mit Signatur</li>
                                <li>📧 Direkter E-Mail-Versand</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- CRM Tab -->
                    <div id="crm" class="tab-content">
                        <h2>📞 CRM</h2>
                        <?php if (is_plugin_active('klage-crm/klage-crm.php')): ?>
                            <?php 
                            // Hook for CRM plugin to add content
                            do_action('cah_crm_edit_content', $case_id, $case, $case_contacts); 
                            ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666; background: #f8f9fa; border-radius: 5px;">
                                <h3>📞 CRM Plugin nicht aktiv</h3>
                                <p>Das CRM Plugin ist nicht installiert oder nicht aktiviert.</p>
                                <div style="text-align: left; max-width: 500px; margin: 20px auto;">
                                    <h4>🎯 CRM Features:</h4>
                                    <ul>
                                        <li>📧 Kommunikations-Management</li>
                                        <li>📅 Terminverwaltung</li>
                                        <li>🎯 Zielgruppen-Segmentierung</li>
                                        <li>📊 Marketing-Kampagnen</li>
                                        <li>📈 Kontakt-Tracking</li>
                                        <li>💬 Chat-Historie</li>
                                        <li>📱 Social Media Integration</li>
                                    </ul>
                                </div>
                                <p><small>Installieren Sie das CRM Plugin (v100+), um diese Features zu nutzen.</small></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gerichtstermine Tab -->
                    <div id="gerichtstermine" class="tab-content">
                        <h2>📅 Gerichtstermine</h2>
                        
                        <?php if ($tv_assignments): ?>
                            <h3>📋 Termine-Übersicht</h3>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Uhrzeit</th>
                                        <th>Typ</th>
                                        <th>Status</th>
                                        <th>TV-Anwalt</th>
                                        <th>Notizen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tv_assignments as $assignment): ?>
                                        <tr>
                                            <td><?php echo esc_html($assignment->court_date ? date_i18n('d.m.Y', strtotime($assignment->court_date)) : '-'); ?></td>
                                            <td><?php echo esc_html($assignment->court_time ?? '-'); ?></td>
                                            <td><?php echo esc_html($assignment->assignment_type ?? 'Termin'); ?></td>
                                            <td><?php echo esc_html($assignment->assignment_status ?? 'Geplant'); ?></td>
                                            <td>
                                                <?php 
                                                if ($assignment->first_name || $assignment->last_name) {
                                                    echo esc_html(trim($assignment->first_name . ' ' . $assignment->last_name));
                                                } elseif ($assignment->company_name) {
                                                    echo esc_html($assignment->company_name);
                                                } else {
                                                    echo 'Nicht zugeordnet';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo esc_html($assignment->notes ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666; background: #f8f9fa; border-radius: 5px;">
                                <h3>📅 Keine Gerichtstermine</h3>
                                <p>Für diesen Fall sind noch keine Gerichtstermine geplant.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Partner Tab -->
                    <div id="partner" class="tab-content">
                        <h2>🤝 Partner</h2>
                        <?php 
                        $lawyers = array_filter($case_contacts, function($contact) {
                            return in_array($contact->role, ['lawyer', 'tv_lawyer', 'representative']);
                        });
                        
                        if ($lawyers): 
                        ?>
                            <h3>⚖️ Anwälte & Vertreter</h3>
                            <?php foreach ($lawyers as $lawyer): ?>
                                <div style="background: #f0f8ff; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
                                    <h4>👤 <?php echo esc_html($lawyer->company_name ?: trim($lawyer->first_name . ' ' . $lawyer->last_name)); ?></h4>
                                    <p><strong>Rolle:</strong> <?php echo esc_html($lawyer->role); ?></p>
                                    <p><strong>Typ:</strong> <?php echo esc_html($lawyer->contact_type); ?></p>
                                    <p><strong>Kontakt:</strong> <?php echo esc_html($lawyer->email ?: 'Keine E-Mail'); ?> | <?php echo esc_html($lawyer->phone ?: 'Keine Telefon'); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666; background: #f8f9fa; border-radius: 5px;">
                                <h3>🤝 Keine Partner</h3>
                                <p>Für diesen Fall sind noch keine Anwälte oder Partner zugeordnet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
                
                <!-- Save Button -->
                <div style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                    <button type="submit" class="button button-primary button-large">💾 Fall speichern</button>
                    <a href="<?php echo admin_url('admin.php?page=la-cases'); ?>" class="button button-large">🔙 Zurück zur Liste</a>
                </div>
            </form>
        </div>
        
        <script>
        function switchCaseEditTab(evt, tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName("nav-tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("nav-tab-active");
            }
            
            // Show selected tab and mark as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("nav-tab-active");
        }
        
        // Financial calculation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const claimAmount = document.getElementById('claim_amount');
            const damageAmount = document.getElementById('damage_amount');
            const art15Damages = document.getElementById('art15_claim_damages');
            const legalFees = document.getElementById('legal_fees');
            const courtFees = document.getElementById('court_fees');
            
            function updateCalculations() {
                const claim = parseFloat(claimAmount.value || 0);
                const damage = parseFloat(damageAmount.value || 0);
                const art15 = parseFloat(art15Damages.value || 0);
                const legal = parseFloat(legalFees.value || 0);
                const court = parseFloat(courtFees.value || 0);
                
                const totalClaim = claim + damage + art15;
                const totalCosts = legal + court;
                const netAmount = totalClaim - totalCosts;
                
                document.getElementById('total_claim').textContent = '€ ' + totalClaim.toFixed(2).replace('.', ',');
                document.getElementById('total_costs').textContent = '€ ' + totalCosts.toFixed(2).replace('.', ',');
                document.getElementById('net_amount').textContent = '€ ' + netAmount.toFixed(2).replace('.', ',');
                document.getElementById('net_amount').style.color = netAmount >= 0 ? '#388e3c' : '#d32f2f';
            }
            
            [claimAmount, damageAmount, art15Damages, legalFees, courtFees].forEach(element => {
                if (element) element.addEventListener('input', updateCalculations);
            });
            
            // Initial calculation
            updateCalculations();
        });
        </script>
        
        <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .nav-tab-wrapper {
            border-bottom: 1px solid #ccd0d4;
        }
        .nav-tab {
            font-size: 14px;
            padding: 8px 12px;
        }
        .tab-content-wrapper h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .form-table th {
            width: 150px;
        }
        </style>
        
        <?php
    }

    private function handle_case_update_v210($case_id, $post_data) {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($post_data['edit_case_nonce'], 'edit_case_action')) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            return;
        }
        
        // Get current case data for audit trail
        $old_case = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}klage_cases WHERE id = %d
        ", $case_id));
        
        if (!$old_case) {
            echo '<div class="notice notice-error"><p>Fall nicht gefunden.</p></div>';
            return;
        }
        
        // Prepare update data for v2.1.0
        $update_data = array(
            'case_status' => sanitize_text_field($post_data['case_status'] ?? $old_case->case_status),
            'case_priority' => sanitize_text_field($post_data['case_priority'] ?? $old_case->case_priority),
            'case_complexity' => sanitize_text_field($post_data['case_complexity'] ?? $old_case->case_complexity),
            'legal_basis' => sanitize_text_field($post_data['legal_basis'] ?? $old_case->legal_basis),
            'schufa_checks_completed' => isset($post_data['schufa_checks_completed']) ? 1 : 0,
            'filing_date' => !empty($post_data['filing_date']) ? sanitize_text_field($post_data['filing_date']) : null,
            'response_deadline' => !empty($post_data['response_deadline']) ? sanitize_text_field($post_data['response_deadline']) : null,
            'next_hearing_date' => !empty($post_data['next_hearing_date']) ? sanitize_text_field($post_data['next_hearing_date']) : null,
            'case_notes' => sanitize_textarea_field($post_data['case_notes'] ?? ''),
            'case_documents_attachments' => sanitize_textarea_field($post_data['case_documents_attachments'] ?? ''),
            'court_id' => !empty($post_data['court_id']) ? intval($post_data['court_id']) : null,
            'claim_amount' => !empty($post_data['claim_amount']) ? floatval($post_data['claim_amount']) : 0,
            'damage_amount' => !empty($post_data['damage_amount']) ? floatval($post_data['damage_amount']) : 0,
            'art15_claim_damages' => !empty($post_data['art15_claim_damages']) ? floatval($post_data['art15_claim_damages']) : null,
            'legal_fees' => !empty($post_data['legal_fees']) ? floatval($post_data['legal_fees']) : 0,
            'court_fees' => !empty($post_data['court_fees']) ? floatval($post_data['court_fees']) : 0,
            'import_source' => sanitize_text_field($post_data['import_source'] ?? $old_case->import_source),
            'external_id' => sanitize_text_field($post_data['external_id'] ?? $old_case->external_id),
            'case_updated_date' => current_time('mysql')
        );
        
        // Update the case
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            $update_data,
            array('id' => $case_id),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Create comprehensive audit trail entry
            $changes = array();
            foreach ($update_data as $field => $new_value) {
                $old_value = $old_case->$field ?? null;
                if ($old_value != $new_value) {
                    $changes[$field] = array(
                        'old' => $old_value,
                        'new' => $new_value
                    );
                }
            }
            
            if (!empty($changes)) {
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_id,
                        'action' => 'case_updated',
                        'entity_type' => 'case',
                        'entity_id' => $case_id,
                        'old_values' => json_encode($changes),
                        'new_values' => json_encode($update_data),
                        'details' => 'Fall "' . $old_case->case_id . '" wurde über v2.1.0 Tab-Interface aktualisiert',
                        'user_id' => get_current_user_id(),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ),
                    array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
                );
            }
            
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Fall "' . esc_html($old_case->case_id) . '" wurde aktualisiert.</p></div>';
            
            // Trigger action for other plugins
            do_action('cah_case_updated', $case_id, $old_case, $update_data);
            
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Fall konnte nicht aktualisiert werden: ' . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    
    private function handle_case_deletion($case_id) {
        global $wpdb;
        
        // Verify nonce for security
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_case_' . $case_id)) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            return;
        }
        
        // Get complete case information for audit trail
        $case = $wpdb->get_row($wpdb->prepare("
            SELECT case_id, case_status, claim_amount, damage_amount, case_notes 
            FROM {$wpdb->prefix}klage_cases WHERE id = %d
        ", $case_id));
        
        if (!$case) {
            echo '<div class="notice notice-error"><p>Fall nicht gefunden.</p></div>';
            return;
        }
        
        // Log the deletion BEFORE deleting (v2.1.0 enhancement)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
            $audit_details = json_encode(array(
                'deleted_case_id' => $case->case_id,
                'case_status' => $case->case_status,
                'claim_amount' => $case->claim_amount,
                'damage_amount' => $case->damage_amount,
                'notes_preview' => substr($case->case_notes, 0, 100),
                'deleted_by' => get_current_user_id(),
                'deleted_at' => current_time('mysql')
            ));
            
            $wpdb->insert(
                $wpdb->prefix . 'klage_audit',
                array(
                    'case_id' => null, // Case will be deleted
                    'action' => 'case_deleted',
                    'entity_type' => 'case',
                    'entity_id' => $case_id,
                    'details' => 'Fall "' . $case->case_id . '" wurde permanent gelöscht',
                    'new_values' => $audit_details,
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s')
            );
        }
        
        // Delete from v2.0.0 related tables first  
        $wpdb->delete($wpdb->prefix . 'klage_case_contacts', array('case_id' => $case_id), array('%d'));
        $wpdb->delete($wpdb->prefix . 'klage_financials', array('case_id' => $case_id), array('%d'));
        $wpdb->delete($wpdb->prefix . 'klage_documents', array('case_id' => $case_id), array('%d'));
        $wpdb->delete($wpdb->prefix . 'klage_tv_assignments', array('case_id' => $case_id), array('%d'));
        
        // Delete main case
        $result = $wpdb->delete($wpdb->prefix . 'klage_cases', array('id' => $case_id), array('%d'));
        
        if ($result) {
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Fall "' . esc_html($case->case_id) . '" wurde permanent gelöscht. Löschung wurde im Audit-Trail protokolliert.</p></div>';
            
            // Trigger action for other plugins
            do_action('cah_case_deleted', $case_id, $case);
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Fall konnte nicht gelöscht werden.</p></div>';
        }
    }
    
    private function handle_case_update($case_id, $post_data) {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($post_data['edit_case_nonce'], 'edit_case_action')) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            return;
        }
        
        // Get new Fall-ID and validate uniqueness
        $new_case_id = sanitize_text_field($post_data['case_id']);
        
        // Check if Fall-ID is being changed and validate uniqueness
        $current_case = $wpdb->get_row($wpdb->prepare("SELECT case_id FROM {$wpdb->prefix}klage_cases WHERE id = %d", $case_id));
        if ($current_case && $current_case->case_id !== $new_case_id) {
            // Fall-ID is being changed, check uniqueness
            $existing_case = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_id = %s AND id != %d",
                $new_case_id, $case_id
            ));
            
            if ($existing_case > 0) {
                echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Fall-ID "' . esc_html($new_case_id) . '" wird bereits verwendet.</p></div>';
                return;
            }
        }
        
        // Update case data including Fall-ID
        $case_data = array(
            'case_id' => $new_case_id,
            'case_status' => sanitize_text_field($post_data['case_status']),
            'case_priority' => sanitize_text_field($post_data['case_priority']),
            'mandant' => sanitize_text_field($post_data['mandant']),
            'submission_date' => sanitize_text_field($post_data['submission_date']),
            'case_notes' => sanitize_textarea_field($post_data['case_notes']),
            'case_updated_date' => current_time('mysql')
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            $case_data,
            array('id' => $case_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'), // Added %s for case_id
            array('%d')
        );
        
        // Update debtor if exists
        if (isset($post_data['debtors_first_name'])) {
            $case = $wpdb->get_row($wpdb->prepare("SELECT debtor_id FROM {$wpdb->prefix}klage_cases WHERE id = %d", $case_id));
            if ($case && $case->debtor_id) {
                $debtor_data = array(
                    'debtors_first_name' => sanitize_text_field($post_data['debtors_first_name']),
                    'debtors_last_name' => sanitize_text_field($post_data['debtors_last_name']),
                    'debtors_company' => sanitize_text_field($post_data['debtors_company']),
                    'debtors_email' => sanitize_email($post_data['debtors_email']),
                    'debtors_address' => sanitize_text_field($post_data['debtors_address']),
                    'debtors_postal_code' => sanitize_text_field($post_data['debtors_postal_code']),
                    'debtors_city' => sanitize_text_field($post_data['debtors_city']),
                    'letzte_aktualisierung' => current_time('mysql')
                );
                
                $wpdb->update(
                    $wpdb->prefix . 'klage_debtors',
                    $debtor_data,
                    array('id' => $case->debtor_id),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            }
        }
        
        if ($result !== false) {
            // Trigger WordPress hook for case update (for financial calculator plugin integration)
            do_action('cah_case_updated', $case_id, $case_data);
            
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Fall wurde aktualisiert.</p></div>';
            
            // Log the update
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_id,
                        'action' => 'case_updated',
                        'details' => 'Fall wurde über Admin-Interface bearbeitet',
                        'user_id' => get_current_user_id()
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Fall konnte nicht aktualisiert werden.</p></div>';
        }
    }
    
    private function create_new_case() {
        global $wpdb;
        
        // Debug logging
        error_log('Case creation attempt started. POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['create_case_nonce'], 'create_case')) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            error_log('Case creation failed: nonce verification failed');
            return;
        }
        
        try {
            // v2.1.0 - Comprehensive data collection from all tabs
            
            // Fall Daten Tab
            $case_id = sanitize_text_field($_POST['case_id']);
            $case_status = sanitize_text_field($_POST['case_status'] ?? 'draft');
            $case_priority = sanitize_text_field($_POST['case_priority'] ?? 'medium');
            $case_complexity = sanitize_text_field($_POST['case_complexity'] ?? 'medium');
            $legal_basis = sanitize_text_field($_POST['legal_basis'] ?? '');
            $schufa_checks_completed = isset($_POST['schufa_checks_completed']) ? 1 : 0;
            $filing_date = sanitize_text_field($_POST['filing_date'] ?? '');
            $response_deadline = sanitize_text_field($_POST['response_deadline'] ?? '');
            $next_hearing_date = sanitize_text_field($_POST['next_hearing_date'] ?? '');
            $case_notes = sanitize_textarea_field($_POST['case_notes'] ?? '');
            
            // Beweise Tab
            $case_documents_attachments = sanitize_textarea_field($_POST['case_documents_attachments'] ?? '');
            $emails_sender_email = sanitize_email($_POST['emails_sender_email'] ?? '');
            $emails_subject = sanitize_text_field($_POST['emails_subject'] ?? '');
            $emails_content = sanitize_textarea_field($_POST['emails_content'] ?? '');
            
            // Mandant Tab
            $mandant = sanitize_text_field($_POST['mandant'] ?? '');
            $client_type = sanitize_text_field($_POST['client_type'] ?? 'individual');
            $client_first_name = sanitize_text_field($_POST['client_first_name'] ?? '');
            $client_last_name = sanitize_text_field($_POST['client_last_name'] ?? '');
            $client_company_name = sanitize_text_field($_POST['client_company_name'] ?? '');
            $client_email = sanitize_email($_POST['client_email'] ?? '');
            $client_phone = sanitize_text_field($_POST['client_phone'] ?? '');
            $client_address = sanitize_textarea_field($_POST['client_address'] ?? '');
            
            // Gegenseite Tab (backward compatibility maintained)
            $debtor_type = sanitize_text_field($_POST['debtor_type'] ?? 'individual');
            $debtors_first_name = sanitize_text_field($_POST['debtors_first_name'] ?? '');
            $debtors_last_name = sanitize_text_field($_POST['debtors_last_name'] ?? '');
            $debtors_company = sanitize_text_field($_POST['debtors_company'] ?? '');
            $debtors_email = sanitize_email($_POST['debtors_email'] ?? '');
            $debtors_phone = sanitize_text_field($_POST['debtors_phone'] ?? '');
            $debtors_address = sanitize_text_field($_POST['debtors_address'] ?? '');
            $debtors_postal_code = sanitize_text_field($_POST['debtors_postal_code'] ?? '');
            $debtors_city = sanitize_text_field($_POST['debtors_city'] ?? '');
            $debtors_country = sanitize_text_field($_POST['debtors_country'] ?? 'Deutschland');
            
            // Gericht Tab
            $court_id = !empty($_POST['court_id']) ? intval($_POST['court_id']) : null;
            $judge_name = sanitize_text_field($_POST['judge_name'] ?? '');
            $case_number_court = sanitize_text_field($_POST['case_number_court'] ?? '');
            
            // Finanzen Tab
            $claim_amount = !empty($_POST['claim_amount']) ? floatval($_POST['claim_amount']) : 0;
            $damage_amount = !empty($_POST['damage_amount']) ? floatval($_POST['damage_amount']) : 0;
            $art15_claim_damages = !empty($_POST['art15_claim_damages']) ? floatval($_POST['art15_claim_damages']) : 0;
            $legal_fees = !empty($_POST['legal_fees']) ? floatval($_POST['legal_fees']) : 0;
            $court_fees = !empty($_POST['court_fees']) ? floatval($_POST['court_fees']) : 0;
            
            // Dokumentenerstellung Tab
            $document_notes = sanitize_textarea_field($_POST['document_notes'] ?? '');
            
            // Gerichtstermine Tab
            $initial_hearing_date = sanitize_text_field($_POST['initial_hearing_date'] ?? '');
            $hearing_notes = sanitize_textarea_field($_POST['hearing_notes'] ?? '');
            
            // Partner Tab
            $main_lawyer = sanitize_text_field($_POST['main_lawyer'] ?? '');
            $law_firm = sanitize_text_field($_POST['law_firm'] ?? '');
            $partner_notes = sanitize_textarea_field($_POST['partner_notes'] ?? '');
            
            // Maximum flexibility - no mandatory fields for creation
            // Auto-generate Fall-ID if empty
            if (empty($case_id)) {
                $case_id = 'CASE-' . date('Y') . '-' . sprintf('%04d', rand(1000, 9999));
            }
            
            $errors = array();
            
            if (!empty($errors)) {
                echo '<div class="notice notice-error"><p><strong>Fehler:</strong><br>' . implode('<br>', $errors) . '</p></div>';
                return;
            }
            
            // Check if case ID already exists and auto-generate unique one if needed
            $existing_case = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}klage_cases WHERE case_id = %s
            ", $case_id));
            
            if ($existing_case) {
                // Auto-generate a new unique case ID
                $attempts = 0;
                do {
                    $case_id = 'CASE-' . date('Y') . '-' . sprintf('%04d', rand(1000, 9999)) . strtoupper(substr(md5(time()), 0, 2));
                    $existing_case = $wpdb->get_var($wpdb->prepare("
                        SELECT id FROM {$wpdb->prefix}klage_cases WHERE case_id = %s
                    ", $case_id));
                    $attempts++;
                } while ($existing_case && $attempts < 10);
                
                if ($existing_case) {
                    echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Konnte keine eindeutige Fall-ID generieren. Bitte versuchen Sie es erneut.</p></div>';
                    error_log('Case creation failed: Could not generate unique case ID after 10 attempts');
                    return;
                }
                
                echo '<div class="notice notice-warning"><p><strong>Hinweis:</strong> Fall-ID wurde automatisch geändert zu: <strong>' . esc_html($case_id) . '</strong> (Original-ID existierte bereits)</p></div>';
            }
            
            // If no debtor name but has email evidence, use email as identifier
            if (empty($debtors_last_name) && !empty($emails_sender_email)) {
                $debtors_last_name = $emails_sender_email;
                $debtors_email = $emails_sender_email;
            }
            
            // Create debtor contact first (v2.0.0 structure)
            $debtor_contact_id = null;
            if (!empty($debtors_last_name)) {
                // Check if debtor contact already exists by email
                $existing_debtor = null;
                if (!empty($debtors_email)) {
                    $existing_debtor = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM {$wpdb->prefix}klage_contacts 
                        WHERE email = %s AND active_status = 1
                    ", $debtors_email));
                }
                
                if ($existing_debtor) {
                    $debtor_contact_id = $existing_debtor->id;
                } else {
                    $debtor_contact_result = $wpdb->insert(
                        $wpdb->prefix . 'klage_contacts',
                        array(
                            'first_name' => $debtors_first_name,
                            'last_name' => $debtors_last_name,
                            'company_name' => $debtors_company,
                            'email' => $debtors_email,
                            'phone' => $debtors_phone,
                            'street' => $debtors_address,
                            'postal_code' => $debtors_postal_code,
                            'city' => $debtors_city,
                            'country' => $debtors_country,
                            'contact_type' => $debtor_type,
                            'active_status' => 1,
                            'created_at' => current_time('mysql')
                        ),
                        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
                    );
                    
                    if ($debtor_contact_result) {
                        $debtor_contact_id = $wpdb->insert_id;
                    }
                }
            }
            
            // Create client contact if provided
            $client_contact_id = null;
            if (!empty($client_last_name) || !empty($client_company_name)) {
                // Check if contact already exists by email
                $existing_client = null;
                if (!empty($client_email)) {
                    $existing_client = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM {$wpdb->prefix}klage_contacts 
                        WHERE email = %s AND active_status = 1
                    ", $client_email));
                }
                
                if ($existing_client) {
                    $client_contact_id = $existing_client->id;
                } else {
                    $client_contact_result = $wpdb->insert(
                        $wpdb->prefix . 'klage_contacts',
                        array(
                            'first_name' => $client_first_name,
                            'last_name' => $client_last_name,
                            'company_name' => $client_company_name,
                            'email' => $client_email,
                            'phone' => $client_phone,
                            'street' => $client_address, // Full address in street field for simplicity
                            'contact_type' => $client_type,
                            'active_status' => 1,
                            'created_at' => current_time('mysql')
                        ),
                        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
                    );
                    
                    if ($client_contact_result) {
                        $client_contact_id = $wpdb->insert_id;
                    }
                }
            }
            
            // Prepare comprehensive case data for v2.1.0
            $case_data = array(
                'case_id' => $case_id,
                'case_status' => $case_status,
                'case_priority' => $case_priority,
                'case_complexity' => $case_complexity,
                'legal_basis' => $legal_basis,
                'schufa_checks_completed' => $schufa_checks_completed,
                'filing_date' => !empty($filing_date) ? $filing_date : null,
                'response_deadline' => !empty($response_deadline) ? $response_deadline : null,
                'next_hearing_date' => !empty($next_hearing_date) ? $next_hearing_date : null,
                'case_notes' => $case_notes,
                'case_documents_attachments' => $case_documents_attachments,
                'court_id' => $court_id,
                'claim_amount' => $claim_amount,
                'damage_amount' => $damage_amount,
                'art15_claim_damages' => $art15_claim_damages,
                'legal_fees' => $legal_fees,
                'court_fees' => $court_fees,
                'total_amount' => $claim_amount + $damage_amount + $art15_claim_damages,
                'document_type' => 'case',
                'active_status' => 1,
                'import_source' => 'manual_v210',
                'case_creation_date' => current_time('mysql'),
                'case_updated_date' => current_time('mysql')
            );
            
            // Remove null values to avoid database errors
            $case_data = array_filter($case_data, function($value) {
                return $value !== null && $value !== '';
            });
            
            // Prepare format array dynamically
            $format_map = array(
                'case_id' => '%s', 'case_status' => '%s', 'case_priority' => '%s', 'case_complexity' => '%s',
                'legal_basis' => '%s', 'schufa_checks_completed' => '%d', 'filing_date' => '%s',
                'response_deadline' => '%s', 'next_hearing_date' => '%s', 'case_notes' => '%s',
                'case_documents_attachments' => '%s', 'court_id' => '%d', 'claim_amount' => '%f',
                'damage_amount' => '%f', 'art15_claim_damages' => '%f', 'legal_fees' => '%f',
                'court_fees' => '%f', 'total_amount' => '%f', 'document_type' => '%s',
                'active_status' => '%d', 'import_source' => '%s', 'case_creation_date' => '%s',
                'case_updated_date' => '%s'
            );
            
            $formats = array();
            foreach ($case_data as $key => $value) {
                $formats[] = $format_map[$key] ?? '%s';
            }
            
            // Create the case
            error_log('Attempting to insert case data: ' . print_r($case_data, true));
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'klage_cases',
                $case_data,
                $formats
            );
            
            error_log('Case insert result: ' . ($result ? 'SUCCESS' : 'FAILED') . '. WP Error: ' . $wpdb->last_error . '. Query: ' . $wpdb->last_query);
            
            if ($result) {
                $case_internal_id = $wpdb->insert_id;
                
                // Create case-contact relationships
                if ($debtor_contact_id) {
                    $wpdb->insert(
                        $wpdb->prefix . 'klage_case_contacts',
                        array(
                            'case_id' => $case_internal_id,
                            'contact_id' => $debtor_contact_id,
                            'role' => 'debtor',
                            'active_status' => 1,
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%d', '%s')
                    );
                }
                
                if ($client_contact_id) {
                    $wpdb->insert(
                        $wpdb->prefix . 'klage_case_contacts',
                        array(
                            'case_id' => $case_internal_id,
                            'contact_id' => $client_contact_id,
                            'role' => 'client',
                            'active_status' => 1,
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%d', '%s')
                    );
                }
                
                // Add email evidence to case notes if provided
                if (!empty($emails_sender_email) || !empty($emails_subject) || !empty($emails_content)) {
                    $email_evidence = "\n\n--- E-Mail Evidenz ---\n";
                    if ($emails_sender_email) $email_evidence .= "Absender: " . $emails_sender_email . "\n";
                    if ($emails_subject) $email_evidence .= "Betreff: " . $emails_subject . "\n";
                    if ($emails_content) $email_evidence .= "Inhalt: " . $emails_content . "\n";
                    
                    // Update case notes with email evidence
                    $wpdb->update(
                        $wpdb->prefix . 'klage_cases',
                        array('case_notes' => $case_notes . $email_evidence),
                        array('id' => $case_internal_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                // Create comprehensive audit log entry
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_internal_id,
                        'action' => 'case_created',
                        'entity_type' => 'case',
                        'entity_id' => $case_internal_id,
                        'old_values' => null,
                        'new_values' => json_encode($case_data),
                        'details' => 'Fall "' . $case_id . '" wurde über v2.1.0 Tab-Interface erstellt',
                        'user_id' => get_current_user_id(),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
                );
                
                // Trigger WordPress hooks for integrations
                do_action('cah_case_created', $case_internal_id, $case_data);
                do_action('cah_case_created_v210', $case_internal_id, $case_data, $_POST);
                
                // Create initial financial record if amounts provided
                if ($claim_amount > 0 || $damage_amount > 0 || $art15_claim_damages > 0) {
                    $total_claim = $claim_amount + $damage_amount + $art15_claim_damages;
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'klage_financials',
                        array(
                            'case_id' => $case_internal_id,
                            'transaction_type' => 'initial_claim',
                            'amount' => $total_claim,
                            'description' => 'Anfängliche Forderung bei Fallerstellung',
                            'payment_status' => 'pending',
                            'transaction_date' => current_time('mysql'),
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%s', '%f', '%s', '%s', '%s', '%s')
                    );
                }
                
                echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Fall "' . esc_html($case_id) . '" wurde erfolgreich mit allen Daten erstellt.</p></div>';
                echo '<div style="background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;">';
                echo '<p><strong>📊 Erstellte Daten:</strong></p>';
                echo '<ul>';
                echo '<li>📋 Fall-ID: ' . esc_html($case_id) . ' (Status: ' . esc_html($case_status) . ', Priorität: ' . esc_html($case_priority) . ')</li>';
                if ($debtor_contact_id) echo '<li>⚖️ Schuldner: ' . esc_html($debtors_first_name . ' ' . $debtors_last_name) . '</li>';
                if ($client_contact_id) echo '<li>👤 Mandant: ' . esc_html($client_first_name . ' ' . $client_last_name . ' ' . $client_company_name) . '</li>';
                if ($court_id) echo '<li>🏛️ Gericht zugeordnet</li>';
                if ($claim_amount > 0) echo '<li>💰 Forderungssumme: €' . number_format($claim_amount + $damage_amount + $art15_claim_damages, 2, ',', '.') . '</li>';
                if ($case_documents_attachments) echo '<li>🔍 Beweise dokumentiert</li>';
                echo '</ul></div>';
                
                // Redirect to view the newly created case (fixed URL for unified menu)
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "' . admin_url('admin.php?page=la-cases') . '";
                    }, 4000);
                </script>';
                
            } else {
                echo '<div class="notice notice-error"><p><strong>❌ Fehler:</strong> Fall konnte nicht erstellt werden. Datenbank-Fehler: ' . esc_html($wpdb->last_error) . '</p></div>';
                echo '<div class="notice notice-info"><p><strong>Debug Info:</strong><br>';
                echo 'Letzte SQL-Query: ' . esc_html($wpdb->last_query) . '<br>';
                echo 'Tabelle existiert: ' . ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_cases'") ? 'Ja' : 'Nein') . '</p></div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p><strong>❌ Systemfehler:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    private function update_case() {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['update_case_nonce'], 'update_case')) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            return;
        }
        
        $case_db_id = intval($_POST['case_db_id']);
        if (!$case_db_id) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültige Fall-Datenbank-ID.</p></div>';
            return;
        }
        
        // Use the existing handle_case_update method
        $this->handle_case_update($case_db_id, $_POST);
    }
    
    private function handle_bulk_actions() {
        global $wpdb;
        
        if (!isset($_POST['bulk_action']) || empty($_POST['bulk_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $case_ids = isset($_POST['case_ids']) ? array_map('intval', $_POST['case_ids']) : array();
        
        if (empty($case_ids)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Keine Fälle ausgewählt.</p></div>';
            return;
        }
        
        $success_count = 0;
        $error_count = 0;
        
        switch ($action) {
            case 'delete':
                foreach ($case_ids as $case_id) {
                    // Get case for logging
                    $case = $wpdb->get_row($wpdb->prepare("
                        SELECT case_id FROM {$wpdb->prefix}klage_cases WHERE id = %d
                    ", $case_id));
                    
                    if ($case) {
                        // Delete from related tables first (excluding financial - handled by hooks)
                        $wpdb->delete($wpdb->prefix . 'klage_audit', array('case_id' => $case_id), array('%d'));
                        
                        // Delete main case
                        $result = $wpdb->delete($wpdb->prefix . 'klage_cases', array('id' => $case_id), array('%d'));
                        
                        if ($result) {
                            $success_count++;
                            
                            // Trigger WordPress hook for case deletion (for financial calculator plugin integration)
                            do_action('cah_case_deleted', $case_id);
                            
                            // Log the deletion
                            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                                $wpdb->insert(
                                    $wpdb->prefix . 'klage_audit',
                                    array(
                                        'case_id' => 0,
                                        'action' => 'case_deleted_bulk',
                                        'details' => 'Fall "' . $case->case_id . '" wurde per Bulk-Aktion gelöscht',
                                        'user_id' => get_current_user_id()
                                    ),
                                    array('%d', '%s', '%s', '%d')
                                );
                            }
                        } else {
                            $error_count++;
                        }
                    } else {
                        $error_count++;
                    }
                }
                
                if ($success_count > 0) {
                    echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> ' . $success_count . ' Fälle wurden gelöscht.</p></div>';
                }
                if ($error_count > 0) {
                    echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> ' . $error_count . ' Fälle konnten nicht gelöscht werden.</p></div>';
                }
                break;
                
            case 'change_status':
                if (!isset($_POST['new_status']) || empty($_POST['new_status'])) {
                    echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Kein neuer Status ausgewählt.</p></div>';
                    return;
                }
                
                $new_status = sanitize_text_field($_POST['new_status']);
                $valid_statuses = array('draft', 'pending', 'processing', 'completed', 'cancelled');
                
                if (!in_array($new_status, $valid_statuses)) {
                    echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültiger Status.</p></div>';
                    return;
                }
                
                foreach ($case_ids as $case_id) {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'klage_cases',
                        array(
                            'case_status' => $new_status,
                            'case_updated_date' => current_time('mysql')
                        ),
                        array('id' => $case_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $success_count++;
                        
                        // Trigger WordPress hook for case update (for financial calculator plugin integration)
                        do_action('cah_case_updated', $case_id, array('case_status' => $new_status));
                        
                        // Log the status change
                        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                            $wpdb->insert(
                                $wpdb->prefix . 'klage_audit',
                                array(
                                    'case_id' => $case_id,
                                    'action' => 'case_status_changed_bulk',
                                    'details' => 'Status zu "' . $new_status . '" geändert per Bulk-Aktion',
                                    'user_id' => get_current_user_id()
                                ),
                                array('%d', '%s', '%s', '%d')
                            );
                        }
                    } else {
                        $error_count++;
                    }
                }
                
                if ($success_count > 0) {
                    echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Status von ' . $success_count . ' Fällen wurde geändert.</p></div>';
                }
                if ($error_count > 0) {
                    echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Status von ' . $error_count . ' Fällen konnte nicht geändert werden.</p></div>';
                }
                break;
                
            case 'change_priority':
                if (!isset($_POST['new_priority']) || empty($_POST['new_priority'])) {
                    echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Keine neue Priorität ausgewählt.</p></div>';
                    return;
                }
                
                $new_priority = sanitize_text_field($_POST['new_priority']);
                $valid_priorities = array('low', 'medium', 'high', 'urgent');
                
                if (!in_array($new_priority, $valid_priorities)) {
                    echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültige Priorität.</p></div>';
                    return;
                }
                
                foreach ($case_ids as $case_id) {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'klage_cases',
                        array(
                            'case_priority' => $new_priority,
                            'case_updated_date' => current_time('mysql')
                        ),
                        array('id' => $case_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $success_count++;
                        
                        // Log the priority change
                        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                            $wpdb->insert(
                                $wpdb->prefix . 'klage_audit',
                                array(
                                    'case_id' => $case_id,
                                    'action' => 'case_priority_changed_bulk',
                                    'details' => 'Priorität zu "' . $new_priority . '" geändert per Bulk-Aktion',
                                    'user_id' => get_current_user_id()
                                ),
                                array('%d', '%s', '%s', '%d')
                            );
                        }
                    } else {
                        $error_count++;
                    }
                }
                
                if ($success_count > 0) {
                    echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Priorität von ' . $success_count . ' Fällen wurde geändert.</p></div>';
                }
                if ($error_count > 0) {
                    echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Priorität von ' . $error_count . ' Fällen konnte nicht geändert werden.</p></div>';
                }
                break;
                
            default:
                echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Unbekannte Aktion.</p></div>';
                break;
        }
    }
    
    private function handle_status_change() {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['change_status_nonce'], 'change_status')) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            return;
        }
        
        $case_id = intval($_POST['case_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        if (!$case_id || empty($new_status)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Fall-ID oder Status fehlt.</p></div>';
            return;
        }
        
        // Validate status
        $valid_statuses = array('draft', 'pending', 'processing', 'completed', 'cancelled');
        if (!in_array($new_status, $valid_statuses)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültiger Status.</p></div>';
            return;
        }
        
        // Update status
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            array(
                'case_status' => $new_status,
                'case_updated_date' => current_time('mysql')
            ),
            array('id' => $case_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Status wurde geändert.</p></div>';
            
            // Log the change
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_id,
                        'action' => 'status_changed',
                        'details' => 'Status zu "' . $new_status . '" geändert',
                        'user_id' => get_current_user_id()
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Status konnte nicht geändert werden.</p></div>';
        }
    }
    
    private function handle_priority_change() {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['change_priority_nonce'], 'change_priority')) {
            echo '<div class="notice notice-error"><p>Sicherheitsfehler.</p></div>';
            return;
        }
        
        $case_id = intval($_POST['case_id']);
        $new_priority = sanitize_text_field($_POST['new_priority']);
        
        if (!$case_id || empty($new_priority)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Fall-ID oder Priorität fehlt.</p></div>';
            return;
        }
        
        // Validate priority
        $valid_priorities = array('low', 'medium', 'high', 'urgent');
        if (!in_array($new_priority, $valid_priorities)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültige Priorität.</p></div>';
            return;
        }
        
        // Update priority
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            array(
                'case_priority' => $new_priority,
                'case_updated_date' => current_time('mysql')
            ),
            array('id' => $case_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Priorität wurde geändert.</p></div>';
            
            // Log the change
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_id,
                        'action' => 'priority_changed',
                        'details' => 'Priorität zu "' . $new_priority . '" geändert',
                        'user_id' => get_current_user_id()
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Priorität konnte nicht geändert werden.</p></div>';
        }
    }
    
    private function handle_get_status_change($case_id) {
        global $wpdb;
        
        // Get new status from URL parameter
        $new_status = isset($_GET['new_status']) ? sanitize_text_field($_GET['new_status']) : '';
        
        if (!$case_id || empty($new_status)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Fall-ID oder Status fehlt.</p></div>';
            return;
        }
        
        // Validate status
        $valid_statuses = array('draft', 'pending', 'processing', 'completed', 'cancelled');
        if (!in_array($new_status, $valid_statuses)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültiger Status.</p></div>';
            return;
        }
        
        // Update status
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            array(
                'case_status' => $new_status,
                'case_updated_date' => current_time('mysql')
            ),
            array('id' => $case_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Status wurde geändert.</p></div>';
            
            // Log the change
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_id,
                        'action' => 'status_changed',
                        'details' => 'Status zu "' . $new_status . '" geändert',
                        'user_id' => get_current_user_id()
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Status konnte nicht geändert werden.</p></div>';
        }
    }
    
    private function handle_get_priority_change($case_id) {
        global $wpdb;
        
        // Get new priority from URL parameter
        $new_priority = isset($_GET['new_priority']) ? sanitize_text_field($_GET['new_priority']) : '';
        
        if (!$case_id || empty($new_priority)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Fall-ID oder Priorität fehlt.</p></div>';
            return;
        }
        
        // Validate priority
        $valid_priorities = array('low', 'medium', 'high', 'urgent');
        if (!in_array($new_priority, $valid_priorities)) {
            echo '<div class="notice notice-error"><p><strong>Fehler:</strong> Ungültige Priorität.</p></div>';
            return;
        }
        
        // Update priority
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            array(
                'case_priority' => $new_priority,
                'case_updated_date' => current_time('mysql')
            ),
            array('id' => $case_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p><strong>✅ Erfolg!</strong> Priorität wurde geändert.</p></div>';
            
            // Log the change
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}klage_audit'")) {
                $wpdb->insert(
                    $wpdb->prefix . 'klage_audit',
                    array(
                        'case_id' => $case_id,
                        'action' => 'priority_changed',
                        'details' => 'Priorität zu "' . $new_priority . '" geändert',
                        'user_id' => get_current_user_id()
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ Fehler!</strong> Priorität konnte nicht geändert werden.</p></div>';
        }
    }
    
    /**
     * Validate case data before creation/update
     */
    private function validate_case_data($case_data) {
        $errors = array();
        
        // Required fields validation
        if (empty($case_data['case_id'])) {
            $errors[] = 'Fall-ID ist erforderlich.';
        }
        
        if (empty($case_data['mandant'])) {
            $errors[] = 'Mandant ist erforderlich.';
        }
        
        // Validate case priority
        $valid_priorities = array('low', 'medium', 'high', 'urgent');
        if (!empty($case_data['case_priority']) && !in_array($case_data['case_priority'], $valid_priorities)) {
            $errors[] = 'Ungültige Priorität.';
        }
        
        // Validate case status
        $valid_statuses = array('draft', 'pending', 'processing', 'completed', 'cancelled');
        if (!empty($case_data['case_status']) && !in_array($case_data['case_status'], $valid_statuses)) {
            $errors[] = 'Ungültiger Status.';
        }
        
        // Validate submission date format
        if (!empty($case_data['submission_date']) && !strtotime($case_data['submission_date'])) {
            $errors[] = 'Ungültiges Datum-Format.';
        }
        
        return $errors;
    }
    
    // AJAX handler for case ID uniqueness check
    public function ajax_check_case_id_unique() {
        check_ajax_referer('cah_admin_nonce', 'nonce');
        
        $case_id = sanitize_text_field($_POST['case_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'klage_cases';
        
        // Check if case_id already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE case_id = %s",
            $case_id
        ));
        
        $is_unique = ($existing == 0);
        
        wp_send_json_success(array(
            'unique' => $is_unique,
            'message' => $is_unique ? 'Fall-ID verfügbar' : 'Fall-ID bereits vergeben'
        ));
    }
}