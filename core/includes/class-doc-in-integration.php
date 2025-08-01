<?php
/**
 * Document Analysis Integration Class
 * Integrates Document Analysis functionality into Core Plugin v1.8.7
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_DocIn_Integration {
    
    private $doc_in_plugin_active;
    
    public function __construct() {
        $this->doc_in_plugin_active = $this->is_doc_in_plugin_active();
        
        // Only initialize if document analysis plugin is active
        if ($this->doc_in_plugin_active) {
            add_action('init', array($this, 'init_integration'), 20); // Run after doc-in plugin loads
            add_filter('cah_dashboard_stats', array($this, 'add_doc_stats_to_dashboard'));
            add_action('cah_admin_menu_integration', array($this, 'add_doc_menu_items'));
        }
        
        // Always add hooks for checking plugin status
        add_action('admin_notices', array($this, 'doc_in_status_notice'));
    }
    
    /**
     * Check if Document Analysis plugin is active
     */
    private function is_doc_in_plugin_active() {
        return class_exists('CourtAutomationHub_DocumentAnalysis');
    }
    
    /**
     * Initialize integration
     */
    public function init_integration() {
        // Document analysis plugin is active, proceed with integration
        if ($this->doc_in_plugin_active) {
            // Add integration hooks
            add_action('cah_after_case_created', array($this, 'link_communications_to_case'));
            add_filter('cah_case_details_tabs', array($this, 'add_communications_tab'));
            add_action('cah_case_details_communications_tab', array($this, 'render_communications_tab'));
        }
    }
    
    /**
     * Add document analysis statistics to main dashboard
     */
    public function add_doc_stats_to_dashboard($stats) {
        if (!$this->doc_in_plugin_active) {
            return $stats;
        }
        
        try {
            global $wpdb;
            
            // Get document analysis statistics
            $doc_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_communications,
                    SUM(CASE WHEN assignment_status = 'assigned' THEN 1 ELSE 0 END) as assigned_communications,
                    SUM(CASE WHEN assignment_status = 'unassigned' THEN 1 ELSE 0 END) as unassigned_communications,
                    SUM(CASE WHEN assignment_status = 'new_case_created' THEN 1 ELSE 0 END) as new_cases_from_docs,
                    AVG(match_confidence) as avg_match_confidence
                FROM {$wpdb->prefix}cah_document_in_communications 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            if ($doc_stats) {
                $stats['document_analysis'] = array(
                    'total_communications' => intval($doc_stats->total_communications),
                    'assigned_communications' => intval($doc_stats->assigned_communications),
                    'unassigned_communications' => intval($doc_stats->unassigned_communications),
                    'new_cases_from_docs' => intval($doc_stats->new_cases_from_docs),
                    'avg_match_confidence' => round(floatval($doc_stats->avg_match_confidence), 1)
                );
            }
        } catch (Exception $e) {
            // Silently handle database errors
            error_log('Document Analysis Integration Error: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Add document analysis menu items to core plugin menu
     */
    public function add_doc_menu_items($parent_slug) {
        if (!$this->doc_in_plugin_active) {
            return;
        }
        
        // Add Document Analysis submenu
        add_submenu_page(
            $parent_slug,
            __('Dokumentenanalyse', 'court-automation-hub'),
            __('Dokumentenanalyse', 'court-automation-hub'),
            'manage_options',
            'klage-click-doc-analysis',
            array($this, 'render_doc_analysis_dashboard')
        );
        
        // Add Unassigned Communications submenu
        add_submenu_page(
            $parent_slug,
            __('Unzugeordnete Kommunikation', 'court-automation-hub'),
            __('Unzugeordnet', 'court-automation-hub'),
            'manage_options',
            'klage-click-doc-unassigned',
            array($this, 'render_unassigned_communications')
        );
        
        // Add All Communications submenu
        add_submenu_page(
            $parent_slug,
            __('Alle Kommunikation', 'court-automation-hub'),
            __('Alle Kommunikation', 'court-automation-hub'),
            'manage_options',
            'klage-click-doc-all',
            array($this, 'render_all_communications')
        );
        
        // Add Categories Management
        add_submenu_page(
            $parent_slug,
            __('Kategorien verwalten', 'court-automation-hub'),
            __('Kategorien', 'court-automation-hub'),
            'manage_options',
            'klage-click-doc-categories',
            array($this, 'render_categories_management')
        );
    }
    
    /**
     * Render Document Analysis Dashboard
     */
    public function render_doc_analysis_dashboard() {
        if (class_exists('CAH_Document_in_Admin')) {
            $doc_admin = new CAH_Document_in_Admin();
            $doc_admin->dashboard_page();
        } else {
            $this->render_plugin_not_found_message();
        }
    }
    
    /**
     * Render Unassigned Communications
     */
    public function render_unassigned_communications() {
        if (class_exists('CAH_Document_in_Admin')) {
            $doc_admin = new CAH_Document_in_Admin();
            $doc_admin->unassigned_page();
        } else {
            $this->render_plugin_not_found_message();
        }
    }
    
    /**
     * Render All Communications
     */
    public function render_all_communications() {
        // Redirect to edit.php for cah_communication post type
        if ($this->doc_in_plugin_active) {
            wp_redirect(admin_url('edit.php?post_type=cah_communication'));
            exit;
        } else {
            $this->render_plugin_not_found_message();
        }
    }
    
    /**
     * Render Categories Management
     */
    public function render_categories_management() {
        if (class_exists('CAH_Document_in_Admin')) {
            $doc_admin = new CAH_Document_in_Admin();
            $doc_admin->categories_page();
        } else {
            $this->render_plugin_not_found_message();
        }
    }
    
    /**
     * Link communications to newly created case
     */
    public function link_communications_to_case($case_id) {
        if (!$this->doc_in_plugin_active) {
            return;
        }
        
        global $wpdb;
        
        // Find unassigned communications that might belong to this case
        $communications = $wpdb->get_results($wpdb->prepare("
            SELECT id, case_number, debtor_name 
            FROM {$wpdb->prefix}cah_document_in_communications 
            WHERE assignment_status = 'unassigned'
            AND (case_number IS NOT NULL OR debtor_name IS NOT NULL)
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        "));
        
        if (!empty($communications)) {
            // Get case details
            $case = $wpdb->get_row($wpdb->prepare("
                SELECT c.case_number, d.debtors_name, d.debtors_company 
                FROM {$wpdb->prefix}klage_cases c
                LEFT JOIN {$wpdb->prefix}klage_debtors d ON c.debtor_id = d.id
                WHERE c.case_id = %d
            ", $case_id));
            
            if ($case) {
                foreach ($communications as $comm) {
                    $should_link = false;
                    
                    // Check case number match
                    if (!empty($comm->case_number) && !empty($case->case_number)) {
                        if (strtolower($comm->case_number) === strtolower($case->case_number)) {
                            $should_link = true;
                        }
                    }
                    
                    // Check debtor name match
                    if (!$should_link && !empty($comm->debtor_name)) {
                        $debtor_display = !empty($case->debtors_company) ? $case->debtors_company : $case->debtors_name;
                        if (!empty($debtor_display)) {
                            $similarity = similar_text(strtolower($comm->debtor_name), strtolower($debtor_display));
                            if ($similarity > 0.8) { // 80% similarity threshold
                                $should_link = true;
                            }
                        }
                    }
                    
                    if ($should_link) {
                        // Update communication to link to this case
                        $wpdb->update(
                            $wpdb->prefix . 'cah_document_in_communications',
                            array(
                                'matched_case_id' => $case_id,
                                'assignment_status' => 'assigned',
                                'match_confidence' => 95,
                                'processed_at' => current_time('mysql')
                            ),
                            array('id' => $comm->id)
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Add communications tab to case details
     */
    public function add_communications_tab($tabs) {
        if (!$this->doc_in_plugin_active) {
            return $tabs;
        }
        
        $tabs['communications'] = __('Kommunikation', 'court-automation-hub');
        return $tabs;
    }
    
    /**
     * Render communications tab content
     */
    public function render_communications_tab($case_id) {
        if (!$this->doc_in_plugin_active) {
            return;
        }
        
        global $wpdb;
        
        // Get communications for this case
        $communications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}cah_document_in_communications 
            WHERE matched_case_id = %d 
            ORDER BY created_at DESC
        ", $case_id));
        
        if (empty($communications)) {
            echo '<p>' . __('Keine Kommunikation für diesen Fall gefunden.', 'court-automation-hub') . '</p>';
            return;
        }
        
        echo '<div class="case-communications">';
        echo '<h3>' . __('Kommunikationsverlauf', 'court-automation-hub') . '</h3>';
        
        foreach ($communications as $comm) {
            echo '<div class="communication-item" style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">';
            echo '<div class="communication-header" style="font-weight: bold; margin-bottom: 10px;">';
            echo '<span>' . esc_html($comm->email_subject) . '</span>';
            echo '<span style="float: right; font-size: 0.9em; color: #666;">' . date('d.m.Y H:i', strtotime($comm->email_received_date)) . '</span>';
            echo '</div>';
            
            echo '<div class="communication-meta" style="font-size: 0.9em; color: #666; margin-bottom: 10px;">';
            echo '<strong>' . __('Von:', 'court-automation-hub') . '</strong> ' . esc_html($comm->email_sender) . ' | ';
            echo '<strong>' . __('Kategorie:', 'court-automation-hub') . '</strong> ' . esc_html($comm->category);
            if ($comm->has_attachment) {
                echo ' | <span class="dashicons dashicons-paperclip"></span> ' . __('Anhang', 'court-automation-hub');
            }
            echo '</div>';
            
            if (!empty($comm->summary)) {
                echo '<div class="communication-summary" style="background: #f9f9f9; padding: 10px; border-radius: 3px;">';
                echo '<strong>' . __('KI-Zusammenfassung:', 'court-automation-hub') . '</strong><br>';
                echo esc_html($comm->summary);
                echo '</div>';
            }
            
            echo '<div class="communication-actions" style="margin-top: 10px;">';
            if ($comm->post_id) {
                echo '<a href="' . admin_url('post.php?post=' . $comm->post_id . '&action=edit') . '" class="button button-small">' . __('Details', 'court-automation-hub') . '</a>';
            }
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get document analysis statistics for integration
     */
    public function get_doc_analysis_stats() {
        if (!$this->doc_in_plugin_active) {
            return null;
        }
        
        if (class_exists('CAH_Document_in_Case_Matcher')) {
            $case_matcher = new CAH_Document_in_Case_Matcher();
            return $case_matcher->get_matching_statistics(30);
        }
        
        return null;
    }
    
    /**
     * Admin notice for document analysis plugin status
     */
    public function doc_in_status_notice() {
        // Only show on our admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'klage-click') === false) {
            return;
        }
        
        if (!$this->doc_in_plugin_active) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('Document Analysis Plugin:', 'court-automation-hub') . '</strong> ';
            echo __('Das Dokumentenanalyse-Plugin ist nicht aktiv. Installieren Sie es für vollständige Funktionalität.', 'court-automation-hub');
            echo '</p></div>';
        }
    }
    
    /**
     * Render plugin not found message
     */
    private function render_plugin_not_found_message() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Dokumentenanalyse nicht verfügbar', 'court-automation-hub') . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>' . __('Das Dokumentenanalyse-Plugin ist nicht installiert oder aktiviert. Bitte installieren Sie das "Court Automation Hub - Document Analysis" Plugin, um diese Funktionalität zu nutzen.', 'court-automation-hub') . '</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Check if integration is ready
     */
    public function is_integration_ready() {
        return $this->doc_in_plugin_active;
    }
    
    /**
     * Get unassigned communications count for dashboard
     */
    public function get_unassigned_count() {
        if (!$this->doc_in_plugin_active) {
            return 0;
        }
        
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}cah_document_in_communications 
            WHERE assignment_status = 'unassigned'
        ");
        
        return intval($count);
    }
}