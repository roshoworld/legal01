<?php
/**
 * Unified Admin Menu Manager for Legal Automation Plugin Suite
 * Consolidates all plugin menus under one "Legal Automation" menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Legal_Automation_Unified_Menu {
    
    private static $instance = null;
    private $menu_created = false;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Priority 5 ensures this runs before individual plugin menus
        add_action('admin_menu', array($this, 'create_unified_menu'), 5);
        add_action('admin_menu', array($this, 'remove_individual_menus'), 9999);
        add_action('admin_init', array($this, 'handle_purge_data'));
    }
    
    /**
     * Create the unified Legal Automation menu
     */
    public function create_unified_menu() {
        if ($this->menu_created) {
            return;
        }
        
        // Debug logging
        error_log('Legal Automation: Creating unified menu...');
        
        // Main menu page - Dashboard (revert to manage_options - this was working)
        $main_hook = add_menu_page(
            __('Legal Automation', 'legal-automation-core'),
            __('Legal Automation', 'legal-automation-core'),
            'manage_options',
            'legal-automation',
            array($this, 'dashboard_page'),
            'dashicons-hammer',
            25
        );
        
        error_log('Legal Automation: Main menu hook: ' . $main_hook);
        
        // Dashboard submenu (same as main page)
        add_submenu_page(
            'legal-automation',
            __('Dashboard', 'legal-automation-core'),
            __('Dashboard', 'legal-automation-core'),
            'manage_options',
            'legal-automation',
            array($this, 'dashboard_page')
        );
        
        // Fälle (Cases) - restore direct access with manage_options
        $cases_hook = add_submenu_page(
            'legal-automation',
            __('Fälle', 'legal-automation-core'),
            __('Fälle', 'legal-automation-core'),
            'manage_options',
            'la-cases', 
            array($this, 'cases_page') // Back to actual cases page
        );
        
        error_log('Legal Automation: Cases menu hook: ' . $cases_hook);
        
        // Einstellungen (Settings)
        add_submenu_page(
            'legal-automation',
            __('Einstellungen', 'legal-automation-core'),
            __('Einstellungen', 'legal-automation-core'),
            'manage_options', // Keep manage_options for settings
            'legal-automation-settings',
            array($this, 'settings_page')
        );
        
        // Dokumenteneingang (Doc-in)
        if (class_exists('CourtAutomationHub_DocumentAnalysis')) {
            add_submenu_page(
                'legal-automation',
                __('Dokumenteneingang', 'legal-automation-doc-in'),
                __('Dokumenteneingang', 'legal-automation-doc-in'),
                'manage_options',
                'legal-automation-doc-in',
                array($this, 'doc_in_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Unzugeordnete Dokumente', 'legal-automation-doc-in'),
                __('⮩ Unzugeordnet', 'legal-automation-doc-in'),
                'manage_options',
                'legal-automation-doc-in-unassigned',
                array($this, 'doc_in_unassigned_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Kommunikationseinstellungen', 'legal-automation-doc-in'),
                __('⮩ Einstellungen', 'legal-automation-doc-in'),
                'manage_options',
                'legal-automation-doc-in-settings',
                array($this, 'doc_in_settings_page')
            );
        }
        
        // Dokumenten Generator (Doc-out)
        if (class_exists('KlageClickDocOut')) {
            add_submenu_page(
                'legal-automation',
                __('Dokumenten Generator', 'legal-automation-doc-out'),
                __('Dokumenten Generator', 'legal-automation-doc-out'),
                'manage_options',
                'legal-automation-doc-out',
                array($this, 'doc_out_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Template Gallery', 'legal-automation-doc-out'),
                __('⮩ Templates', 'legal-automation-doc-out'),
                'manage_options',
                'legal-automation-doc-out-templates',
                array($this, 'doc_out_templates_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Dokumente Generieren', 'legal-automation-doc-out'),
                __('⮩ Generieren', 'legal-automation-doc-out'),
                'manage_options',
                'legal-automation-doc-out-generate',
                array($this, 'doc_out_generate_page')
            );
        }
        
        // CRM
        if (class_exists('Legal_Automation_CRM')) {
            add_submenu_page(
                'legal-automation',
                __('CRM', 'legal-automation-crm'),
                __('CRM', 'legal-automation-crm'),
                'manage_options',
                'legal-automation-crm',
                array($this, 'crm_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Kontakte', 'legal-automation-crm'),
                __('⮩ Kontakte', 'legal-automation-crm'),
                'manage_options',
                'legal-automation-crm-contacts',
                array($this, 'crm_contacts_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Gerichte', 'legal-automation-crm'),
                __('⮩ Gerichte', 'legal-automation-crm'),
                'manage_options',
                'legal-automation-crm-courts',
                array($this, 'crm_courts_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Historie', 'legal-automation-crm'),
                __('⮩ Historie', 'legal-automation-crm'),
                'manage_options',
                'legal-automation-crm-history',
                array($this, 'crm_history_page')
            );
        }
        
        // Finanzen (Finance)
        if (class_exists('Legal_Automation_Finance')) {
            add_submenu_page(
                'legal-automation',
                __('Finanzen', 'legal-automation-finance'),
                __('Finanzen', 'legal-automation-finance'),
                'manage_options',
                'legal-automation-finance-calculator',
                array($this, 'finance_calculator_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Finanz-Rechner', 'legal-automation-finance'),
                __('⮩ Rechner', 'legal-automation-finance'),
                'manage_options',
                'legal-automation-finance-calculator',
                array($this, 'finance_calculator_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Finanz-Templates', 'legal-automation-finance'),
                __('⮩ Templates', 'legal-automation-finance'),
                'manage_options',
                'legal-automation-finance-templates',
                array($this, 'finance_templates_page')
            );
            
            add_submenu_page(
                'legal-automation',
                __('Finanz-Einstellungen', 'legal-automation-finance'),
                __('⮩ Einstellungen', 'legal-automation-finance'),
                'manage_options',
                'legal-automation-finance-settings',
                array($this, 'finance_settings_page')
            );
        }
        
        // Import
        if (class_exists('Legal_Automation_Import')) {
            add_submenu_page(
                'legal-automation',
                __('Import', 'legal-automation-import'),
                __('Import', 'legal-automation-import'),
                'manage_options',
                'legal-automation-import',
                array($this, 'import_page')
            );
        }
        
        $this->menu_created = true;
    }
    
    /**
     * Remove individual plugin menus to avoid duplication and conflicts
     */
    public function remove_individual_menus() {
        // Remove all possible individual plugin menus aggressively
        $menus_to_remove = array(
            'klage-click-hub',
            'legal-automation-finance', 
            'klage-doc-generator',
            'legal-automation-crm',
            'legal-automation-admin',
            'legal-automation-import',
            'cah-doc-in',
            'cah-cases',
            'court-automation-hub',
            'legal-automation-dashboard'
        );
        
        foreach ($menus_to_remove as $menu_slug) {
            remove_menu_page($menu_slug);
        }
        
        // Also remove any submenu items that might conflict
        global $submenu;
        if (isset($submenu['klage-click-hub'])) {
            unset($submenu['klage-click-hub']);
        }
        if (isset($submenu['legal-automation-admin'])) {
            unset($submenu['legal-automation-admin']);
        }
    }
    
    /**
     * Handle case management view through dashboard
     */
    private function handle_dashboard_cases_view() {
        if (class_exists('CAH_Admin_Dashboard')) {
            $core_admin = new CAH_Admin_Dashboard();
            
            // Handle GET actions (edit, view, delete)
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'delete':
                        if (isset($_GET['id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_case_' . $_GET['id'])) {
                            $this->handle_case_delete_direct();
                            // Redirect back to cases view after deletion
                            wp_redirect(admin_url('admin.php?page=la-cases&deleted=1'));
                            exit;
                        }
                        break;
                    case 'edit':
                    case 'view':
                        // Let the core admin handle edit/view within dashboard context
                        break;
                }
            }
            
            // Handle any POST actions
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'create_case':
                        if (method_exists($core_admin, 'create_new_case')) {
                            $reflection = new ReflectionClass($core_admin);
                            $method = $reflection->getMethod('create_new_case');
                            $method->setAccessible(true);
                            $method->invoke($core_admin);
                        }
                        break;
                    case 'delete_case':
                        $this->handle_case_delete_direct();
                        break;
                }
            }
            
            // Show success message if deleted
            if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>Fall erfolgreich gelöscht.</p></div>';
            }
            
            // Show the cases management page
            if (method_exists($core_admin, 'admin_page_cases')) {
                $core_admin->admin_page_cases();
                
                // Add JavaScript to fix delete/edit links to use dashboard URL
                echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Fix delete links to use la-cases page directly
                    $("a[href*=\'action=delete\']").each(function() {
                        var href = $(this).attr("href");
                        if (href.indexOf("legal-automation-cases") !== -1) {
                            href = href.replace(/page=[^&]+/, "page=la-cases");
                            $(this).attr("href", href);
                        }
                    });
                    
                    // Fix edit/view links to use la-cases page directly  
                    $("a[href*=\'action=edit\'], a[href*=\'action=view\']").each(function() {
                        var href = $(this).attr("href");
                        if (href.indexOf("legal-automation-cases") !== -1) {
                            href = href.replace(/page=[^&]+/, "page=la-cases");
                            $(this).attr("href", href);
                        }
                    });
                    
                    // Fix any form actions to use la-cases page directly
                    $("form[action*=\'legal-automation-cases\']").each(function() {
                        var action = $(this).attr("action");
                        action = action.replace(/page=[^&]+/, "page=la-cases");
                        $(this).attr("action", action);
                    });
                });
                </script>';
            }
        } else {
            echo '<div class="wrap"><h1>Fälle verwalten</h1><p>Core plugin nicht verfügbar.</p></div>';
        }
    }
    
    /**
     * Handle case creation through dashboard (workaround for cases page permission issue)
     */
    private function handle_dashboard_case_creation() {
        if (class_exists('CAH_Admin_Dashboard')) {
            $core_admin = new CAH_Admin_Dashboard();
            
            // Handle case creation if form submitted
            if (isset($_POST['action']) && $_POST['action'] === 'create_case') {
                if (method_exists($core_admin, 'create_new_case')) {
                    $reflection = new ReflectionClass($core_admin);
                    $method = $reflection->getMethod('create_new_case');
                    $method->setAccessible(true);
                    $method->invoke($core_admin);
                    // Return after creation to prevent double processing
                    return;
                }
            }
            
            // Show the case creation form ONLY if not processing POST
            if (!isset($_POST['action'])) {
                if (method_exists($core_admin, 'admin_page_cases')) {
                    // Set action to add for the form
                    $_GET['action'] = 'add';
                    $core_admin->admin_page_cases();
                }
            }
        } else {
            echo '<div class="wrap"><h1>Fall erstellen</h1><p>Core plugin nicht verfügbar.</p></div>';
        }
    }
    
    /**
     * Dashboard page - unified view
     */
    public function dashboard_page() {
        global $wpdb;
        
        // Handle case creation directly in dashboard
        if (isset($_GET['create_case']) || (isset($_POST['action']) && $_POST['action'] === 'create_case')) {
            $this->handle_dashboard_case_creation();
            return;
        }
        
        // Handle case management view
        if (isset($_GET['view']) && $_GET['view'] === 'cases') {
            $this->handle_dashboard_cases_view();
            return;
        }
        
        // Get comprehensive statistics
        $total_cases = 0;
        $pending_cases = 0;
        $processing_cases = 0;
        $completed_cases = 0;
        $total_value = 0;
        
        // Check if database tables exist
        $cases_table = $wpdb->prefix . 'klage_cases';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cases_table'") == $cases_table;
        
        if ($table_exists) {
            $total_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE active_status = 'active'") ?? 0;
            $pending_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'pending' AND active_status = 'active'") ?? 0;
            $processing_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'processing' AND active_status = 'active'") ?? 0;
            $completed_cases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_cases WHERE case_status = 'completed' AND active_status = 'active'") ?? 0;
            
            $total_value = $wpdb->get_var("
                SELECT SUM(
                    COALESCE(CAST(claim_amount AS DECIMAL(10,2)), 0) + 
                    COALESCE(CAST(legal_fees AS DECIMAL(10,2)), 0) + 
                    COALESCE(CAST(court_fees AS DECIMAL(10,2)), 0)
                ) FROM {$wpdb->prefix}klage_cases 
                WHERE active_status = 'active'
            ") ?? 0;
        }
        
        // Get document analysis stats if available
        $doc_stats = array();
        $communications_table = $wpdb->prefix . 'klage_communications';
        if ($wpdb->get_var("SHOW TABLES LIKE '$communications_table'") == $communications_table) {
            $doc_stats['total_communications'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_communications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") ?? 0;
            $doc_stats['unassigned_communications'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}klage_communications WHERE case_id IS NULL OR case_id = 0") ?? 0;
        }
        
        $this->render_unified_dashboard($total_cases, $pending_cases, $processing_cases, $completed_cases, $total_value, $doc_stats);
    }
    
    /**
     * Render unified dashboard
     */
    private function render_unified_dashboard($total_cases, $pending_cases, $processing_cases, $completed_cases, $total_value, $doc_stats) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Legal Automation - Dashboard', 'legal-automation-core'); ?></h1>
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h2 style="color: white; margin-top: 0;">🚀 Unified Legal Automation Suite</h2>
                <p style="margin-bottom: 0;">Zentrales Dashboard für alle Legal Automation Funktionen - Fallverwaltung, Dokumentenanalyse, CRM, Finanzen und mehr.</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #0073aa;">
                    <h3 style="margin: 0 0 10px 0; color: #0073aa; font-size: 32px;"><?php echo esc_html($total_cases); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Gesamt Fälle</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #ff9800;">
                    <h3 style="margin: 0 0 10px 0; color: #ff9800; font-size: 32px;"><?php echo esc_html($pending_cases); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Ausstehend</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #2196f3;">
                    <h3 style="margin: 0 0 10px 0; color: #2196f3; font-size: 32px;"><?php echo esc_html($processing_cases); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">In Bearbeitung</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #4caf50;">
                    <h3 style="margin: 0 0 10px 0; color: #4caf50; font-size: 32px;"><?php echo esc_html($completed_cases); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Abgeschlossen</p>
                </div>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #9c27b0;">
                    <h3 style="margin: 0 0 10px 0; color: #9c27b0; font-size: 28px;">€<?php echo esc_html(number_format($total_value, 2)); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Gesamtwert Portfolio</p>
                </div>
                
                <?php if (!empty($doc_stats) && $doc_stats['total_communications'] > 0): ?>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #607d8b;">
                    <h3 style="margin: 0 0 10px 0; color: #607d8b; font-size: 28px;"><?php echo esc_html($doc_stats['total_communications']); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Dokumente (30T)</p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($doc_stats) && $doc_stats['unassigned_communications'] > 0): ?>
                <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #f44336;">
                    <h3 style="margin: 0 0 10px 0; color: #f44336; font-size: 28px;"><?php echo esc_html($doc_stats['unassigned_communications']); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Unzugeordnet</p>
                    <a href="<?php echo admin_url('admin.php?page=legal-automation-doc-in-unassigned'); ?>" class="button button-small" style="margin-top: 8px;">Überprüfen</a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="postbox" style="margin-top: 30px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9; border-bottom: 1px solid #e1e1e1;">🚀 Schnellaktionen</h2>
                <div class="inside" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        
                        <a href="<?php echo admin_url('admin.php?page=legal-automation&create_case=1'); ?>" class="button button-primary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; display: block; border-radius: 6px;">
                            <strong>📝 Neuen Fall erstellen</strong><br>
                            <small style="opacity: 0.8;">Vollständige Fallerstellung mit CRUD</small>
                        </a>
                        
                        <?php if (class_exists('Legal_Automation_Finance')): ?>
                        <a href="<?php echo admin_url('admin.php?page=legal-automation-finance-calculator'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; display: block; background: #2271b1; color: white; border-radius: 6px;">
                            <strong>🧮 RVG Rechner</strong><br>
                            <small style="opacity: 0.8;">Finanzberechnungen & Templates</small>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (class_exists('CourtAutomationHub_DocumentAnalysis')): ?>
                        <a href="<?php echo admin_url('admin.php?page=legal-automation-doc-in'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; display: block; background: #9c27b0; color: white; border-radius: 6px;">
                            <strong>📄 Dokumentenanalyse</strong><br>
                            <small style="opacity: 0.8;">Eingehende Dokumente verwalten</small>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (class_exists('KlageClickDocOut')): ?>
                        <a href="<?php echo admin_url('admin.php?page=legal-automation-doc-out'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; display: block; background: #607d8b; color: white; border-radius: 6px;">
                            <strong>📋 Dokumente Generieren</strong><br>
                            <small style="opacity: 0.8;">Templates & PDF-Erstellung</small>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_CRM')): ?>
                        <a href="<?php echo admin_url('admin.php?page=legal-automation-crm-contacts'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; display: block; background: #ff5722; color: white; border-radius: 6px;">
                            <strong>👥 CRM & Kontakte</strong><br>
                            <small style="opacity: 0.8;">Mandanten, Partner, Gerichte</small>
                        </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo admin_url('admin.php?page=legal-automation-settings'); ?>" class="button button-secondary" style="padding: 20px; height: auto; text-decoration: none; text-align: center; display: block; background: #607d8b; color: white; border-radius: 6px;">
                            <strong>⚙️ Einstellungen</strong><br>
                            <small style="opacity: 0.8;">System-Konfiguration</small>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9; border-bottom: 1px solid #e1e1e1;">📊 Plugin Status</h2>
                <div class="inside" style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Core</strong><br>
                                <small>v220 Aktiv</small>
                            </div>
                        </div>
                        
                        <?php if (class_exists('CourtAutomationHub_DocumentAnalysis')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Doc-in</strong><br>
                                <small>v1.1.8 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_Finance')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Finance</strong><br>
                                <small>v2.0.1 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('KlageClickDocOut')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Doc-out</strong><br>
                                <small>v1.0.9 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_CRM')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>CRM</strong><br>
                                <small>v1.0.0 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_Import')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Import</strong><br>
                                <small>v201 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    

    /**
     * Cases page - handle directly without delegation to avoid admin plugin conflicts
     */
    public function cases_page() {
        // Debug logging
        error_log('Legal Automation: Cases page accessed by user ID: ' . get_current_user_id());
        error_log('Legal Automation: User capabilities: ' . print_r(wp_get_current_user()->allcaps, true));
        
        // Check permissions first - revert to manage_options which was working
        if (!current_user_can('manage_options')) {
            error_log('Legal Automation: Permission denied for user');
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        error_log('Legal Automation: Permission check passed');
        
        if (class_exists('CAH_Admin_Dashboard')) {
            $core_admin = new CAH_Admin_Dashboard();
            
            // Let admin dashboard handle all actions - no duplicate processing
            // All case edit/view/delete actions should go directly to la-cases page
            
            // Handle actions through admin dashboard - remove duplicate processing to prevent double case creation
            // Admin dashboard handles all POST actions via handle_case_actions() method
            
            // Show success message if deleted
            if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>Fall erfolgreich gelöscht.</p></div>';
            }
            
            $core_admin->admin_page_cases();
            
            // Add JavaScript to fix any remaining problematic links
            echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Fix delete links to use current page URL
                $("a[href*=\'action=delete\']").each(function() {
                    var href = $(this).attr("href");
                    if (href.indexOf("legal-automation-cases") !== -1) {
                        href = href.replace(/page=[^&]+/, "page=la-cases");
                        $(this).attr("href", href);
                    }
                });
                
                // Fix edit links to use current page URL
                $("a[href*=\'action=edit\'], a[href*=\'action=view\']").each(function() {
                    var href = $(this).attr("href");
                    if (href.indexOf("legal-automation-cases") !== -1) {
                        href = href.replace(/page=[^&]+/, "page=la-cases");
                        $(this).attr("href", href);
                    }
                });
                
                // Fix form actions too
                $("form[action*=\'legal-automation-cases\']").each(function() {
                    var action = $(this).attr("action");
                    action = action.replace(/page=[^&]+/, "page=la-cases");
                    $(this).attr("action", action);
                });
            });
            </script>';
        } else {
            $this->render_cases_fallback();
        }
    }
    
    /**
     * Handle case deletion directly without problematic redirects
     */
    private function handle_case_delete_direct() {
        global $wpdb;
        
        $case_id = isset($_POST['case_id']) ? intval($_POST['case_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        
        if (!$case_id) {
            return false;
        }
        
        // Soft delete - set active_status to 0
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            array('active_status' => 0, 'case_updated_date' => current_time('mysql')),
            array('id' => $case_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the deletion
            $wpdb->insert(
                $wpdb->prefix . 'klage_audit',
                array(
                    'case_id' => $case_id,
                    'action' => 'case_deleted',
                    'entity_type' => 'case',
                    'entity_id' => $case_id,
                    'details' => 'Fall wurde über Dashboard gelöscht',
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle case deletion directly 
     */
    private function handle_case_delete() {
        global $wpdb;
        
        $case_id = isset($_POST['case_id']) ? intval($_POST['case_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        
        if (!$case_id) {
            echo '<div class="notice notice-error"><p>Ungültige Fall-ID.</p></div>';
            return;
        }
        
        // Soft delete - set active_status to 0
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_cases',
            array('active_status' => 0, 'case_updated_date' => current_time('mysql')),
            array('id' => $case_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>Fall erfolgreich gelöscht.</p></div>';
            
            // Log the deletion
            $wpdb->insert(
                $wpdb->prefix . 'klage_audit',
                array(
                    'case_id' => $case_id,
                    'action' => 'case_deleted',
                    'entity_type' => 'case',
                    'entity_id' => $case_id,
                    'details' => 'Fall wurde über Unified Menu gelöscht',
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
        } else {
            echo '<div class="notice notice-error"><p>Fehler beim Löschen: ' . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    
    /**
     * Fallback cases page if core admin doesn't exist
     */
    private function render_cases_fallback() {
        global $wpdb;
        
        echo '<div class="wrap">';
        echo '<h1>Fälle</h1>';
        
        // Simple case list
        $cases = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}klage_cases 
            WHERE active_status = 1 
            ORDER BY case_creation_date DESC
        ");
        
        if (empty($cases)) {
            echo '<p>Keine Fälle vorhanden. <a href="' . admin_url('admin.php?page=legal-automation&create_case=1') . '" class="button button-primary">Neuen Fall erstellen</a></p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Fall-ID</th><th>Status</th><th>Erstellt</th><th>Aktionen</th></tr></thead>';
            echo '<tbody>';
            foreach ($cases as $case) {
                echo '<tr>';
                echo '<td>' . esc_html($case->case_id) . '</td>';
                echo '<td>' . esc_html($case->case_status ?? 'draft') . '</td>';
                echo '<td>' . esc_html($case->case_creation_date) . '</td>';
                echo '<td><a href="' . wp_nonce_url(admin_url('admin.php?page=la-cases&action=delete&id=' . $case->id), 'delete_case_' . $case->id) . '" onclick="return confirm(\'Fall wirklich löschen?\')">Löschen</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Settings page with purge functionality
     */
    public function settings_page() {
        // Check if debug mode is enabled to show purge button
        $debug_mode = get_option('klage_click_debug_mode', false);
        
        // Handle settings save
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'klage_click_settings-options')) {
            update_option('klage_click_debug_mode', isset($_POST['klage_click_debug_mode']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
            $debug_mode = get_option('klage_click_debug_mode', false);
        }
        
        ?>
        <div class="wrap">
            <h1>Legal Automation - Einstellungen</h1>
            
            <?php if (isset($_GET['purged']) && $_GET['purged'] == 'success'): ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Erfolg!</strong> 
                <?php 
                $tables_count = isset($_GET['tables']) ? intval($_GET['tables']) : 0;
                $records_count = isset($_GET['records']) ? intval($_GET['records']) : 0;
                echo "Datenbereinigung abgeschlossen: $tables_count Tabellen geleert, $records_count Datensätze gelöscht.";
                ?>
                </p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('klage_click_settings-options'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Debug Modus</th>
                        <td>
                            <label>
                                <input type="checkbox" name="klage_click_debug_mode" value="1" <?php checked($debug_mode, 1); ?> />
                                Debug-Modus aktivieren (Entwicklerfeatures)
                            </label>
                            <p class="description">Aktiviert erweiterte Protokollierung und Entwicklertools.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <?php if ($debug_mode): ?>
            <div class="postbox" style="margin-top: 30px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #fff3cd; border-bottom: 1px solid #ffeaa7;">🔧 Entwickler-Tools</h2>
                <div class="inside" style="padding: 20px; background: #fff3cd;">
                    <div style="background: #f8d7da; padding: 15px; border-radius: 4px; border-left: 4px solid #dc3545; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: #721c24;">⚠️ Vorsicht - Datenbereinigung</h3>
                        <p style="margin-bottom: 15px; color: #721c24;">Diese Aktion löscht <strong>alle</strong> Daten aus der Datenbank und kann nicht rückgängig gemacht werden!</p>
                        
                        <form method="post" onsubmit="return confirm('WARNUNG: Diese Aktion löscht ALLE Daten unwiderruflich! Sind Sie absolut sicher?');">
                            <?php wp_nonce_field('purge_all_data', 'purge_nonce'); ?>
                            <input type="hidden" name="action" value="purge_all_data">
                            <input type="submit" class="button button-secondary" value="🗑️ Alle Demo-Daten löschen" style="background: #dc3545; color: white; border-color: #dc3545;">
                        </form>
                    </div>
                    
                    <p><strong>Was wird gelöscht:</strong></p>
                    <ul>
                        <li>Alle Fälle und zugehörige Daten</li>
                        <li>Alle Kontakte und Verbindungen</li>
                        <li>Alle Kommunikationen und Dokumente</li>
                        <li>Alle Finanzberechnungen und Audits</li>
                        <li>Alle CRM-Ereignisse und Historie</li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- System Status -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle" style="padding: 15px 20px; margin: 0; background: #f9f9f9; border-bottom: 1px solid #e1e1e1;">📊 Plugin Status</h2>
                <div class="inside" style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Core</strong><br>
                                <small>v222 Aktiv</small>
                            </div>
                        </div>
                        
                        <?php if (class_exists('CourtAutomationHub_DocumentAnalysis')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Doc-in</strong><br>
                                <small>v1.1.8 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_Finance')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Finance</strong><br>
                                <small>v2.0.1 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('KlageClickDocOut')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Doc-out</strong><br>
                                <small>v1.0.9 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_CRM')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>CRM</strong><br>
                                <small>v1.0.0 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (class_exists('Legal_Automation_Import')): ?>
                        <div style="display: flex; align-items: center; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                            <span style="color: #4caf50; font-size: 20px; margin-right: 10px;">✅</span>
                            <div>
                                <strong>Import</strong><br>
                                <small>v201 Aktiv</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle data purge with comprehensive table detection
     */
    public function handle_purge_data() {
        if (isset($_POST['action']) && $_POST['action'] == 'purge_all_data') {
            if (!wp_verify_nonce($_POST['purge_nonce'], 'purge_all_data')) {
                wp_die('Security check failed');
            }
            
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            
            global $wpdb;
            
            // Get all tables that might contain demo data
            $all_tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_A);
            $existing_tables = array();
            foreach ($all_tables as $table) {
                $existing_tables[] = array_values($table)[0];
            }
            
            // Known tables to purge (including variations)
            $tables_to_purge = array(
                $wpdb->prefix . 'klage_cases',
                $wpdb->prefix . 'klage_contacts', 
                $wpdb->prefix . 'klage_case_contacts',
                $wpdb->prefix . 'klage_financials',
                $wpdb->prefix . 'klage_audit',
                $wpdb->prefix . 'klage_communications',
                $wpdb->prefix . 'klage_events',
                $wpdb->prefix . 'klage_evidence',
                $wpdb->prefix . 'klage_documents',
                $wpdb->prefix . 'klage_court_hearings',
                $wpdb->prefix . 'legal_automation_finance_calculations',
                $wpdb->prefix . 'legal_automation_crm_communications',
                $wpdb->prefix . 'legal_automation_crm_events',
                $wpdb->prefix . 'cah_cases', // Alternative naming
                $wpdb->prefix . 'cah_contacts',
                $wpdb->prefix . 'cah_communications',
                $wpdb->prefix . 'la_cases', // Legal automation naming
                $wpdb->prefix . 'la_contacts',
                $wpdb->prefix . 'court_automation_cases' // Old naming
            );
            
            $purged_tables = array();
            $records_deleted = 0;
            
            foreach ($tables_to_purge as $table) {
                if (in_array($table, $existing_tables)) {
                    // Count records before deletion
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
                    
                    if ($count > 0) {
                        $wpdb->query("TRUNCATE TABLE `$table`");
                        $purged_tables[] = str_replace($wpdb->prefix, '', $table) . " ($count records)";
                        $records_deleted += $count;
                        
                        // Reset auto-increment
                        $wpdb->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                    }
                }
            }
            
            // Log the purge action
            error_log("Legal Automation: Data purge completed. Tables: " . implode(', ', $purged_tables) . ". Total records deleted: $records_deleted");
            
            wp_redirect(admin_url('admin.php?page=legal-automation-settings&purged=success&tables=' . count($purged_tables) . '&records=' . $records_deleted));
            exit;
        }
    }
    
    // Delegate methods for other pages
    public function doc_in_page() {
        if (class_exists('CAH_Document_in_Admin')) {
            // Create instance and call dashboard page
            $admin = new CAH_Document_in_Admin();
            $admin->dashboard_page();
        }
    }
    
    public function doc_in_unassigned_page() {
        if (class_exists('CAH_Document_in_Admin')) {
            $admin = new CAH_Document_in_Admin();
            $admin->unassigned_page();
        }
    }
    
    public function doc_in_settings_page() {
        if (class_exists('CAH_Document_in_Admin')) {
            $admin = new CAH_Document_in_Admin();
            $admin->settings_page();
        }
    }
    
    public function doc_out_page() {
        if (class_exists('KCDO_Doc_Admin_Dashboard')) {
            $admin = new KCDO_Doc_Admin_Dashboard();
            $admin->render_main_page();
        }
    }
    
    public function doc_out_templates_page() {
        if (class_exists('KCDO_Doc_Admin_Dashboard')) {
            $admin = new KCDO_Doc_Admin_Dashboard();
            $admin->render_templates_page();
        }
    }
    
    public function doc_out_generate_page() {
        if (class_exists('KCDO_Doc_Admin_Dashboard')) {
            $admin = new KCDO_Doc_Admin_Dashboard();
            $admin->render_generation_page();
        }
    }
    
    public function crm_page() {
        if (class_exists('LA_CRM_Admin')) {
            $admin = new LA_CRM_Admin();
            $admin->render_dashboard_page();
        }
    }
    
    public function crm_contacts_page() {
        $enhanced_crm = new Enhanced_CRM_Contacts_Manager();
        $enhanced_crm->render_contacts_page();
    }
    
    public function crm_courts_page() {
        if (class_exists('LA_CRM_Admin')) {
            $admin = new LA_CRM_Admin();
            $admin->render_courts_page();
        }
    }
    
    public function crm_history_page() {
        if (class_exists('LA_CRM_Admin')) {
            $admin = new LA_CRM_Admin();
            $admin->render_history_page();
        }
    }
    
    public function finance_calculator_page() {
        if (class_exists('LAF_Admin')) {
            $admin = new LAF_Admin();
            $admin->main_admin_page();
        }
    }
    
    public function finance_templates_page() {
        if (class_exists('LAF_Admin')) {
            $admin = new LAF_Admin();
            $admin->templates_admin_page();
        }
    }
    
    public function finance_settings_page() {
        if (class_exists('LAF_Admin')) {
            $admin = new LAF_Admin();
            $admin->settings_admin_page();
        }
    }
    
    public function import_page() {
        if (class_exists('Legal_Automation_Import_Admin')) {
            $admin = new Legal_Automation_Import_Admin();
            $admin->render_main_page();
        }
    }
}