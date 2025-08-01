<?php
/**
 * Admin Interface for Legal Automation Finance
 * Handles WordPress admin integration and template management UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAF_Admin {
    
    private $template_manager;
    private $calculator;
    
    public function __construct() {
        $this->template_manager = new LAF_Template_Manager();
        $this->calculator = new LAF_RVG_Calculator();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_laf_calculate_scenario', array($this, 'ajax_calculate_scenario'));
        add_action('wp_ajax_laf_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_laf_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_laf_update_config', array($this, 'ajax_update_config'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            'Legal Automation - Finance',
            'Finance',
            'manage_options',
            'legal-automation-finance',
            array($this, 'main_admin_page'),
            'dashicons-calculator',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'legal-automation-finance',
            'Calculator',
            'Calculator',
            'manage_options',
            'legal-automation-finance',
            array($this, 'main_admin_page')
        );
        
        add_submenu_page(
            'legal-automation-finance',
            'Templates',
            'Templates',
            'manage_options',
            'laf-templates',
            array($this, 'templates_admin_page')
        );
        
        add_submenu_page(
            'legal-automation-finance',
            'Settings',
            'Settings',
            'manage_options',
            'laf-settings',
            array($this, 'settings_admin_page')
        );
    }
    
    /**
     * Main admin page - RVG Calculator
     */
    public function main_admin_page() {
        $templates = $this->template_manager->get_all_templates();
        ?>
        <div class="wrap">
            <h1>Legal Automation - Finance Calculator</h1>
            
            <div class="laf-calculator-container">
                <div class="laf-calculator-form">
                    <h2>RVG Fee Calculator</h2>
                    
                    <form id="laf-calculator-form">
                        <?php wp_nonce_field('laf_calculate', 'laf_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="scenario">Szenario</label>
                                </th>
                                <td>
                                    <select id="scenario" name="scenario" required>
                                        <option value="">-- Szenario auswählen --</option>
                                        <option value="scenario_1">Szenario 1 (Schadenersatz + Unterlassung)</option>
                                        <option value="scenario_2">Szenario 2 (+ DSGVO Auskunftsverletzung)</option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template->template_type === 'custom'): ?>
                                                <option value="custom_<?php echo $template->id; ?>">
                                                    <?php echo esc_html($template->template_name); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="base_damage">Grundschaden (EUR)</label>
                                </th>
                                <td>
                                    <input type="number" id="base_damage" name="base_damage" 
                                           value="350.00" step="0.01" min="0" required />
                                </td>
                            </tr>
                            <tr id="dsgvo_row" style="display:none;">
                                <th scope="row">
                                    <label for="dsgvo_damage">DSGVO Schaden (EUR)</label>
                                </th>
                                <td>
                                    <input type="number" id="dsgvo_damage" name="dsgvo_damage" 
                                           value="200.00" step="0.01" min="0" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="interest_start_date">Zinsbeginn</label>
                                </th>
                                <td>
                                    <input type="date" id="interest_start_date" name="interest_start_date" />
                                    <p class="description">Optional: Datum für Zinsberechnung</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="interest_end_date">Zinsende</label>
                                </th>
                                <td>
                                    <input type="date" id="interest_end_date" name="interest_end_date" />
                                    <p class="description">Optional: Enddatum für Zinsberechnung</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button-primary" value="Berechnen" />
                        </p>
                    </form>
                </div>
                
                <div class="laf-calculator-results" id="calculator-results" style="display:none;">
                    <h2>Berechnungsergebnis</h2>
                    <div id="results-content"></div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Show/hide DSGVO field based on scenario
            $('#scenario').change(function() {
                if ($(this).val() === 'scenario_2') {
                    $('#dsgvo_row').show();
                } else {
                    $('#dsgvo_row').hide();
                }
            });
            
            // Handle form submission
            $('#laf-calculator-form').submit(function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'laf_calculate_scenario',
                    scenario: $('#scenario').val(),
                    base_damage: $('#base_damage').val(),
                    dsgvo_damage: $('#dsgvo_damage').val(),
                    interest_start_date: $('#interest_start_date').val(),
                    interest_end_date: $('#interest_end_date').val(),
                    nonce: $('#laf_nonce').val()
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#calculator-results').show();
                    } else {
                        alert('Fehler bei der Berechnung: ' + response.data);
                    }
                });
            });
            
            function displayResults(calculation) {
                var html = '<div class="laf-results-table">';
                html += '<h3>Kostenaufstellung</h3>';
                html += '<table class="wp-list-table widefat striped">';
                html += '<thead><tr><th>Position</th><th>Betrag (EUR)</th><th>RVG-Referenz</th></tr></thead>';
                html += '<tbody>';
                
                $.each(calculation.items, function(key, item) {
                    html += '<tr>';
                    html += '<td>' + item.name + '</td>';
                    html += '<td>' + item.amount.toFixed(2) + '</td>';
                    html += '<td>' + (item.rvg_reference || '-') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '<tfoot>';
                html += '<tr><th><strong>Gesamtbetrag</strong></th><th><strong>' + calculation.totals.total_amount.toFixed(2) + ' EUR</strong></th><th></th></tr>';
                html += '</tfoot>';
                html += '</table></div>';
                
                $('#results-content').html(html);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Templates admin page
     */
    public function templates_admin_page() {
        $action = $_GET['action'] ?? 'list';
        $template_id = $_GET['template_id'] ?? 0;
        
        switch ($action) {
            case 'edit':
                $this->edit_template_page($template_id);
                break;
            case 'new':
                $this->new_template_page();
                break;
            default:
                $this->list_templates_page();
                break;
        }
    }
    
    /**
     * List templates page
     */
    private function list_templates_page() {
        $templates = $this->template_manager->get_all_templates(false);
        ?>
        <div class="wrap">
            <h1>Templates verwalten 
                <a href="<?php echo admin_url('admin.php?page=laf-templates&action=new'); ?>" class="page-title-action">Neu erstellen</a>
            </h1>
            
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Typ</th>
                        <th>Status</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($template->template_name); ?></strong>
                            <?php if ($template->is_default): ?>
                                <span class="badge" style="background:#007cba;color:white;padding:2px 6px;border-radius:3px;font-size:11px;">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $template->template_type)); ?></td>
                        <td>
                            <?php if ($template->is_active): ?>
                                <span style="color:green;">●</span> Aktiv
                            <?php else: ?>
                                <span style="color:red;">●</span> Inaktiv
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($template->created_at)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=laf-templates&action=edit&template_id=' . $template->id); ?>">
                                Bearbeiten
                            </a>
                            <?php if (!$template->is_default): ?>
                                | <a href="#" onclick="deleteTemplate(<?php echo $template->id; ?>); return false;" style="color:red;">
                                    Löschen
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script type="text/javascript">
        function deleteTemplate(templateId) {
            if (confirm('Template wirklich löschen?')) {
                jQuery.post(ajaxurl, {
                    action: 'laf_delete_template',
                    template_id: templateId,
                    nonce: '<?php echo wp_create_nonce('laf_delete_template'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Fehler beim Löschen: ' + response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * Settings admin page
     */
    public function settings_admin_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['laf_settings_nonce'], 'laf_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }
        
        $db_manager = new LAF_Database_Manager();
        ?>
        <div class="wrap">
            <h1>Finance Calculator Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('laf_settings', 'laf_settings_nonce'); ?>
                
                <h2>Grundeinstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="base_interest_rate">Basiszinssatz + 5% (in %)</label>
                        </th>
                        <td>
                            <input type="number" id="base_interest_rate" name="base_interest_rate" 
                                   value="<?php echo $db_manager->get_config('base_interest_rate', 5.12); ?>" 
                                   step="0.01" min="0" max="20" />
                            <p class="description">Aktueller Basiszinssatz der EZB plus 5 Prozentpunkte</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vat_rate">Umsatzsteuer (in %)</label>
                        </th>
                        <td>
                            <input type="number" id="vat_rate" name="vat_rate" 
                                   value="<?php echo $db_manager->get_config('vat_rate', 19.0); ?>" 
                                   step="0.01" min="0" max="30" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="partner_fee_factor">Geschäftsgebühr-Faktor</label>
                        </th>
                        <td>
                            <input type="number" id="partner_fee_factor" name="partner_fee_factor" 
                                   value="<?php echo $db_manager->get_config('partner_fee_factor', 1.3); ?>" 
                                   step="0.1" min="0.1" max="5" />
                            <p class="description">Faktor für RVG Geschäftsgebühr (Standard: 1,3)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Einstellungen speichern'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $db_manager = new LAF_Database_Manager();
        
        $settings = array(
            'base_interest_rate' => (float) $_POST['base_interest_rate'],
            'vat_rate' => (float) $_POST['vat_rate'],
            'partner_fee_factor' => (float) $_POST['partner_fee_factor']
        );
        
        foreach ($settings as $key => $value) {
            $db_manager->set_config($key, $value, 'decimal');
        }
    }
    
    /**
     * AJAX: Calculate scenario
     */
    public function ajax_calculate_scenario() {
        check_ajax_referer('laf_calculate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $scenario = sanitize_text_field($_POST['scenario']);
        $input_data = array(
            'base_damage' => (float) $_POST['base_damage'],
            'dsgvo_damage' => (float) ($_POST['dsgvo_damage'] ?? 0),
            'interest_start_date' => sanitize_text_field($_POST['interest_start_date']),
            'interest_end_date' => sanitize_text_field($_POST['interest_end_date'])
        );
        
        $result = $this->calculator->calculate_scenario($scenario, $input_data);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('laf_delete_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $template_id = (int) $_POST['template_id'];
        $result = $this->template_manager->delete_template($template_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } elseif ($result) {
            wp_send_json_success('Template deleted successfully');
        } else {
            wp_send_json_error('Failed to delete template');
        }
    }
    
    /**
     * New template page - simplified for MVP
     */
    private function new_template_page() {
        ?>
        <div class="wrap">
            <h1>Neues Template erstellen</h1>
            <p>Custom template creation will be available in the next version.</p>
            <p><a href="<?php echo admin_url('admin.php?page=laf-templates'); ?>" class="button">← Zurück zur Übersicht</a></p>
        </div>
        <?php
    }
    
    /**
     * Edit template page - simplified for MVP
     */
    private function edit_template_page($template_id) {
        $template_data = $this->template_manager->get_template($template_id);
        
        if (!$template_data) {
            ?>
            <div class="wrap">
                <h1>Template nicht gefunden</h1>
                <p><a href="<?php echo admin_url('admin.php?page=laf-templates'); ?>" class="button">← Zurück zur Übersicht</a></p>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Template bearbeiten: <?php echo esc_html($template_data['template']->template_name); ?></h1>
            
            <div class="laf-template-details">
                <h2>Template Details</h2>
                <table class="wp-list-table widefat">
                    <tr>
                        <th>Name:</th>
                        <td><?php echo esc_html($template_data['template']->template_name); ?></td>
                    </tr>
                    <tr>
                        <th>Typ:</th>
                        <td><?php echo ucfirst(str_replace('_', ' ', $template_data['template']->template_type)); ?></td>
                    </tr>
                    <tr>
                        <th>Beschreibung:</th>
                        <td><?php echo esc_html($template_data['template']->description); ?></td>
                    </tr>
                </table>
                
                <h3>Kostenpunkte</h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Typ</th>
                            <th>Basisbetrag</th>
                            <th>Prozentsatz</th>
                            <th>Methode</th>
                            <th>RVG-Referenz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($template_data['items'] as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->item_name); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $item->item_type)); ?></td>
                            <td><?php echo number_format($item->base_amount, 2); ?> EUR</td>
                            <td><?php echo number_format($item->percentage, 2); ?>%</td>
                            <td><?php echo ucfirst($item->calculation_method); ?></td>
                            <td><?php echo esc_html($item->rvg_reference); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p><a href="<?php echo admin_url('admin.php?page=laf-templates'); ?>" class="button">← Zurück zur Übersicht</a></p>
        </div>
        <?php
    }
}