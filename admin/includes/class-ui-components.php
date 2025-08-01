<?php
/**
 * UI Components for Legal Automation Admin
 * Handles reusable UI elements, JavaScript interactions, and advanced interface components
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LAA_UI_Components {
    
    /**
     * Render sortable table header
     */
    public static function render_sortable_header($column_key, $column_title, $current_sort = '', $current_order = 'asc') {
        $sort_class = '';
        $new_order = 'asc';
        
        if ($current_sort === $column_key) {
            $sort_class = 'sorted ' . $current_order;
            $new_order = ($current_order === 'asc') ? 'desc' : 'asc';
        }
        
        $sort_url = add_query_arg(array(
            'sort' => $column_key,
            'order' => $new_order
        ));
        
        return sprintf(
            '<th class="sortable column-%s %s">
                <a href="%s">
                    <span>%s</span>
                    <span class="sorting-indicator"></span>
                </a>
            </th>',
            esc_attr($column_key),
            esc_attr($sort_class),
            esc_url($sort_url),
            esc_html($column_title)
        );
    }
    
    /**
     * Render tab navigation
     */
    public static function render_tab_navigation($tabs, $active_tab = '') {
        if (empty($tabs)) {
            return '';
        }
        
        $output = '<div class="nav-tab-wrapper">';
        
        foreach ($tabs as $tab_id => $tab_data) {
            $active_class = ($active_tab === $tab_id) ? ' nav-tab-active' : '';
            $icon = isset($tab_data['icon']) ? $tab_data['icon'] . ' ' : '';
            
            $output .= sprintf(
                '<a href="#%s" class="nav-tab%s" onclick="switchCaseTab(event, \'%s\')">%s%s</a>',
                esc_attr($tab_id),
                $active_class,
                esc_attr($tab_id),
                $icon,
                esc_html($tab_data['title'])
            );
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render status badge
     */
    public static function render_status_badge($status) {
        $status_configs = array(
            'draft' => array('label' => 'Entwurf', 'class' => 'status-draft'),
            'processing' => array('label' => 'In Bearbeitung', 'class' => 'status-processing'),
            'pending' => array('label' => 'Wartend', 'class' => 'status-pending'),
            'completed' => array('label' => 'Abgeschlossen', 'class' => 'status-completed'),
            'archived' => array('label' => 'Archiviert', 'class' => 'status-archived')
        );
        
        $config = isset($status_configs[$status]) ? $status_configs[$status] : array(
            'label' => $status,
            'class' => 'status-unknown'
        );
        
        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($config['class']),
            esc_html($config['label'])
        );
    }
    
    /**
     * Render advanced search form
     */
    public static function render_advanced_search_form() {
        ob_start();
        ?>
        <div id="advanced-search-form" class="postbox" style="display: none;">
            <h3 class="hndle">🔍 Erweiterte Suche</h3>
            <div class="inside">
                <form method="get" action="">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="search_case_id">Fall-ID:</label></th>
                            <td><input type="text" id="search_case_id" name="search_case_id" class="regular-text" value="<?php echo esc_attr($_GET['search_case_id'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="search_status">Status:</label></th>
                            <td>
                                <select id="search_status" name="search_status">
                                    <option value="">Alle Status</option>
                                    <option value="draft" <?php selected($_GET['search_status'] ?? '', 'draft'); ?>>Entwurf</option>
                                    <option value="processing" <?php selected($_GET['search_status'] ?? '', 'processing'); ?>>In Bearbeitung</option>
                                    <option value="completed" <?php selected($_GET['search_status'] ?? '', 'completed'); ?>>Abgeschlossen</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="search_date_from">Von Datum:</label></th>
                            <td><input type="date" id="search_date_from" name="search_date_from" value="<?php echo esc_attr($_GET['search_date_from'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="search_date_to">Bis Datum:</label></th>
                            <td><input type="date" id="search_date_to" name="search_date_to" value="<?php echo esc_attr($_GET['search_date_to'] ?? ''); ?>"></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">🔍 Suchen</button>
                        <button type="button" class="button" onclick="clearAdvancedSearch()">🗑️ Zurücksetzen</button>
                        <button type="button" class="button" onclick="toggleAdvancedSearch()">❌ Schließen</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render bulk actions bar
     */
    public static function render_bulk_actions() {
        ob_start();
        ?>
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-top" class="screen-reader-text">Massenaktion auswählen</label>
            <select name="action" id="bulk-action-selector-top">
                <option value="-1">Massenaktion</option>
                <option value="delete">Löschen</option>
                <option value="export">Exportieren</option>
                <option value="change_status">Status ändern</option>
            </select>
            <input type="submit" class="button action" value="Anwenden">
        </div>
        
        <div class="alignright">
            <button type="button" class="button" onclick="toggleAdvancedSearch()">🔍 Erweiterte Suche</button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get default 9-tab configuration
     */
    public static function get_default_tabs() {
        return array(
            'fall-daten' => array(
                'title' => 'Fall Daten',
                'icon' => '📋'
            ),
            'beweise' => array(
                'title' => 'Beweise',
                'icon' => '🔍'
            ),
            'mandant' => array(
                'title' => 'Mandant',
                'icon' => '👤'
            ),
            'gegenseite' => array(
                'title' => 'Gegenseite',
                'icon' => '⚖️'
            ),
            'gericht' => array(
                'title' => 'Gericht',
                'icon' => '🏛️'
            ),
            'finanzen' => array(
                'title' => 'Finanzen',
                'icon' => '💰'
            ),
            'dokumentenerstellung' => array(
                'title' => 'Dokumentenerstellung',
                'icon' => '📄'
            ),
            'crm' => array(
                'title' => 'CRM',
                'icon' => '📞'
            ),
            'gerichtstermine' => array(
                'title' => 'Gerichtstermine',
                'icon' => '📅'
            ),
            'partner' => array(
                'title' => 'Partner',
                'icon' => '🤝'
            )
        );
    }
}