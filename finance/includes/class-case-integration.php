<?php
/**
 * Case Integration for Legal Automation Finance
 * Integrates financial calculations with core case management
 */

if (!defined('ABSPATH')) {
    exit;
}

class LAF_Case_Integration {
    
    private $calculator;
    private $template_manager;
    private $db_manager;
    
    public function __construct() {
        $this->calculator = new LAF_RVG_Calculator();
        $this->template_manager = new LAF_Template_Manager();
        $this->db_manager = new LAF_Database_Manager();
        
        // Hook into core admin pages
        add_action('admin_footer', array($this, 'add_case_financial_section'));
        add_action('wp_ajax_laf_calculate_case', array($this, 'ajax_calculate_case'));
        add_action('wp_ajax_laf_save_case_calculation', array($this, 'ajax_save_case_calculation'));
        add_action('wp_ajax_laf_load_case_calculation', array($this, 'ajax_load_case_calculation'));
    }
    
    /**
     * Add financial calculation section to case admin pages
     */
    public function add_case_financial_section() {
        $screen = get_current_screen();
        
        // Only show on case-related admin pages
        if (!$screen || strpos($screen->id, 'klage-click') === false) {
            return;
        }
        
        // Check if we're on a case edit/view page
        $case_id = $_GET['case_id'] ?? $_GET['id'] ?? 0;
        if (!$case_id) {
            return;
        }
        
        $this->render_case_financial_interface($case_id);
    }
    
    /**
     * Render the financial calculation interface for a case
     */
    private function render_case_financial_interface($case_id) {
        $templates = $this->template_manager->get_all_templates();
        $saved_calculation = $this->calculator->get_case_calculation($case_id);
        ?>
        
        <div id="laf-case-financial" class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 class="hndle">💰 Finanzielle Berechnung</h2>
            </div>
            <div class="inside">
                
                <div class="laf-case-calculator">
                    <form id="laf-case-form" data-case-id="<?php echo $case_id; ?>">
                        <?php wp_nonce_field('laf_case_calculation', 'laf_case_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="case_scenario">Template auswählen</label>
                                </th>
                                <td>
                                    <select id="case_scenario" name="scenario" required>
                                        <option value="">-- Template wählen --</option>
                                        <option value="scenario_1"<?php echo (!$saved_calculation || $saved_calculation['calculation']->template_id == 1) ? ' selected' : ''; ?>>
                                            Szenario 1 (Schadenersatz + Unterlassung)
                                        </option>
                                        <option value="scenario_2"<?php echo ($saved_calculation && $saved_calculation['calculation']->template_id == 2) ? ' selected' : ''; ?>>
                                            Szenario 2 (+ DSGVO Auskunftsverletzung)
                                        </option>
                                        <?php foreach ($templates as $template): ?>
                                            <?php if ($template->template_type === 'custom'): ?>
                                                <option value="custom_<?php echo $template->id; ?>"
                                                    <?php echo ($saved_calculation && $saved_calculation['calculation']->template_id == $template->id) ? ' selected' : ''; ?>>
                                                    <?php echo esc_html($template->template_name); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="case_base_damage">Grundschaden (EUR)</label>
                                </th>
                                <td>
                                    <input type="number" id="case_base_damage" name="base_damage" 
                                           value="<?php echo $saved_calculation ? $saved_calculation['calculation']->base_damage : '350.00'; ?>" 
                                           step="0.01" min="0" required />
                                </td>
                            </tr>
                            <tr id="case_dsgvo_row" style="<?php echo (!$saved_calculation || !$saved_calculation['calculation']->dsgvo_damage) ? 'display:none;' : ''; ?>">
                                <th scope="row">
                                    <label for="case_dsgvo_damage">DSGVO Schaden (EUR)</label>
                                </th>
                                <td>
                                    <input type="number" id="case_dsgvo_damage" name="dsgvo_damage" 
                                           value="<?php echo $saved_calculation ? $saved_calculation['calculation']->dsgvo_damage : '200.00'; ?>" 
                                           step="0.01" min="0" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="case_interest_start">Zinsbeginn</label>
                                </th>
                                <td>
                                    <input type="date" id="case_interest_start" name="interest_start_date" 
                                           value="<?php echo $saved_calculation ? $saved_calculation['calculation']->interest_start_date : ''; ?>" />
                                    <p class="description">Wichtig: Datum muss nach dem Verstoßdatum liegen</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="case_interest_end">Zinsende</label>
                                </th>
                                <td>
                                    <input type="date" id="case_interest_end" name="interest_end_date" 
                                           value="<?php echo $saved_calculation ? $saved_calculation['calculation']->interest_end_date : ''; ?>" />
                                    <p class="description">Zahlungstermin oder Verfahrensende</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="laf-case-actions">
                            <button type="submit" class="button-primary" id="calculate-btn">Berechnen</button>
                            <button type="button" class="button" id="save-calculation-btn" style="display:none;">Berechnung speichern</button>
                            <?php if ($saved_calculation): ?>
                                <span class="description" style="margin-left: 10px;">
                                    Letzte Berechnung: <?php echo date('d.m.Y H:i', strtotime($saved_calculation['calculation']->calculated_at)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="laf-case-results" id="case-results" style="<?php echo !$saved_calculation ? 'display:none;' : ''; ?>">
                    <h3>Berechnungsergebnis</h3>
                    <div id="case-results-content">
                        <?php if ($saved_calculation): ?>
                            <?php $this->render_saved_calculation($saved_calculation); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
        #laf-case-financial .form-table th {
            width: 150px;
        }
        
        .laf-case-results table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .laf-case-results th,
        .laf-case-results td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .laf-case-results th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        
        .laf-case-results .total-row {
            background-color: #f0f8ff;
            font-weight: bold;
        }
        
        .laf-case-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentCalculation = null;
            
            // Show/hide DSGVO field based on scenario
            $('#case_scenario').change(function() {
                if ($(this).val() === 'scenario_2') {
                    $('#case_dsgvo_row').show();
                } else {
                    $('#case_dsgvo_row').hide();
                }
            });
            
            // Handle form submission for calculation
            $('#laf-case-form').submit(function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'laf_calculate_case',
                    case_id: $(this).data('case-id'),
                    scenario: $('#case_scenario').val(),
                    base_damage: $('#case_base_damage').val(),
                    dsgvo_damage: $('#case_dsgvo_damage').val(),
                    interest_start_date: $('#case_interest_start').val(),
                    interest_end_date: $('#case_interest_end').val(),
                    nonce: $('#laf_case_nonce').val()
                };
                
                $('#calculate-btn').prop('disabled', true).text('Berechne...');
                
                $.post(ajaxurl, formData, function(response) {
                    $('#calculate-btn').prop('disabled', false).text('Berechnen');
                    
                    if (response.success) {
                        currentCalculation = response.data;
                        displayCaseResults(response.data);
                        $('#case-results').show();
                        $('#save-calculation-btn').show();
                    } else {
                        alert('Fehler bei der Berechnung: ' + response.data);
                    }
                });
            });
            
            // Handle save calculation
            $('#save-calculation-btn').click(function() {
                if (!currentCalculation) {
                    alert('Keine Berechnung zum Speichern vorhanden.');
                    return;
                }
                
                var saveData = {
                    action: 'laf_save_case_calculation',
                    case_id: $('#laf-case-form').data('case-id'),
                    calculation_data: JSON.stringify(currentCalculation),
                    template_id: getTemplateIdFromScenario($('#case_scenario').val()),
                    nonce: $('#laf_case_nonce').val()
                };
                
                $(this).prop('disabled', true).text('Speichere...');
                
                $.post(ajaxurl, saveData, function(response) {
                    $('#save-calculation-btn').prop('disabled', false).text('Berechnung speichern');
                    
                    if (response.success) {
                        alert('Berechnung erfolgreich gespeichert!');
                        $('#save-calculation-btn').hide();
                        location.reload(); // Refresh to show updated status
                    } else {
                        alert('Fehler beim Speichern: ' + response.data);
                    }
                });
            });
            
            function displayCaseResults(calculation) {
                var html = '<table class="widefat">';
                html += '<thead><tr><th>Position</th><th>Betrag (EUR)</th><th>RVG-Referenz</th><th>Berechnung</th></tr></thead>';
                html += '<tbody>';
                
                $.each(calculation.items, function(key, item) {
                    html += '<tr>';
                    html += '<td>' + item.name + '</td>';
                    html += '<td style="text-align:right;">' + parseFloat(item.amount).toFixed(2) + '</td>';
                    html += '<td>' + (item.rvg_reference || '-') + '</td>';
                    html += '<td><small>' + (item.calculation || '-') + '</small></td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '<tfoot>';
                html += '<tr class="total-row">';
                html += '<th><strong>Gesamtbetrag</strong></th>';
                html += '<th style="text-align:right;"><strong>' + parseFloat(calculation.totals.total_amount).toFixed(2) + ' EUR</strong></th>';
                html += '<th></th><th></th>';
                html += '</tr>';
                html += '</tfoot>';
                html += '</table>';
                
                $('#case-results-content').html(html);
            }
            
            function getTemplateIdFromScenario(scenario) {
                switch(scenario) {
                    case 'scenario_1': return 1;
                    case 'scenario_2': return 2;
                    default:
                        if (scenario.startsWith('custom_')) {
                            return parseInt(scenario.replace('custom_', ''));
                        }
                        return null;
                }
            }
        });
        </script>
        
        <?php
    }
    
    /**
     * Render saved calculation display
     */
    private function render_saved_calculation($saved_data) {
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Betrag (EUR)</th>
                    <th>RVG-Referenz</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saved_data['items'] as $item): ?>
                <tr>
                    <td><?php echo esc_html($item->item_name); ?></td>
                    <td style="text-align:right;"><?php echo number_format($item->amount, 2); ?></td>
                    <td><?php echo esc_html($item->rvg_reference ?? '-'); ?></td>
                    <td><small><?php echo esc_html($item->description ?? '-'); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <th><strong>Gesamtbetrag</strong></th>
                    <th style="text-align:right;"><strong><?php echo number_format($saved_data['calculation']->total_amount, 2); ?> EUR</strong></th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        <?php
    }
    
    /**
     * AJAX: Calculate case financial
     */
    public function ajax_calculate_case() {
        check_ajax_referer('laf_case_calculation', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $case_id = (int) $_POST['case_id'];
        $scenario = sanitize_text_field($_POST['scenario']);
        
        $input_data = array(
            'base_damage' => (float) $_POST['base_damage'],
            'dsgvo_damage' => (float) ($_POST['dsgvo_damage'] ?? 0),
            'interest_start_date' => sanitize_text_field($_POST['interest_start_date']),
            'interest_end_date' => sanitize_text_field($_POST['interest_end_date'])
        );
        
        // Clean empty dates
        if (empty($input_data['interest_start_date'])) {
            $input_data['interest_start_date'] = null;
        }
        if (empty($input_data['interest_end_date'])) {
            $input_data['interest_end_date'] = null;
        }
        
        $result = $this->calculator->calculate_scenario($scenario, $input_data);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Calculation failed');
        }
    }
    
    /**
     * AJAX: Save case calculation
     */
    public function ajax_save_case_calculation() {
        check_ajax_referer('laf_case_calculation', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $case_id = (int) $_POST['case_id'];
        $template_id = (int) ($_POST['template_id'] ?? 0) ?: null;
        $calculation_data = json_decode(stripslashes($_POST['calculation_data']), true);
        
        if (!$calculation_data) {
            wp_send_json_error('Invalid calculation data');
        }
        
        $result = $this->calculator->save_calculation($case_id, $calculation_data, $template_id);
        
        if ($result) {
            wp_send_json_success('Calculation saved successfully');
        } else {
            wp_send_json_error('Failed to save calculation');
        }
    }
    
    /**
     * AJAX: Load case calculation
     */
    public function ajax_load_case_calculation() {
        check_ajax_referer('laf_case_calculation', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $case_id = (int) $_POST['case_id'];
        $result = $this->calculator->get_case_calculation($case_id);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('No calculation found');
        }
    }
}