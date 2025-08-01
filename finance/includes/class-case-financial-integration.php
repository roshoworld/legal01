<?php
/**
 * Case Financial Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Case_Financial_Integration {
    
    private $db_manager;
    private $calculator;
    private $template_manager;
    
    public function __construct() {
        $this->db_manager = new CAH_Financial_DB_Manager();
        $this->calculator = new CAH_Financial_Calculator_Engine();
        $this->template_manager = new CAH_Financial_Template_Manager();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Hook into core case creation/update actions
        add_action('cah_case_created', array($this, 'handle_case_created'));
        add_action('cah_case_updated', array($this, 'handle_case_updated'));
        add_action('cah_case_deleted', array($this, 'handle_case_deleted'));
        
        // AJAX handlers for financial tab
        add_action('wp_ajax_load_financial_templates', array($this, 'ajax_load_templates'));
        add_action('wp_ajax_load_template_items', array($this, 'ajax_load_template_items'));
        add_action('wp_ajax_calculate_financial_totals', array($this, 'ajax_calculate_totals'));
        add_action('wp_ajax_save_case_financial', array($this, 'ajax_save_case_financial'));
        add_action('wp_ajax_save_case_financial_spreadsheet', array($this, 'ajax_save_case_financial_spreadsheet'));
        
        // Case-level cost item CRUD handlers
        add_action('wp_ajax_load_case_cost_items', array($this, 'ajax_load_case_cost_items'));
        add_action('wp_ajax_load_case_financial_data', array($this, 'ajax_load_case_financial_data'));
        add_action('wp_ajax_create_case_cost_item', array($this, 'ajax_create_case_cost_item'));
        add_action('wp_ajax_update_case_cost_item', array($this, 'ajax_update_case_cost_item'));
        add_action('wp_ajax_delete_case_cost_item', array($this, 'ajax_delete_case_cost_item'));
        
        // v1.5.0 - Enhanced CRUD operations
        add_action('wp_ajax_create_financial_template', array($this, 'ajax_create_template'));
        add_action('wp_ajax_update_financial_template', array($this, 'ajax_update_template'));
        add_action('wp_ajax_delete_financial_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_duplicate_financial_template', array($this, 'ajax_duplicate_template'));
        add_action('wp_ajax_create_cost_item', array($this, 'ajax_create_cost_item'));
        add_action('wp_ajax_update_cost_item', array($this, 'ajax_update_cost_item'));
        add_action('wp_ajax_delete_cost_item', array($this, 'ajax_delete_cost_item'));
        add_action('wp_ajax_bulk_cost_items_action', array($this, 'ajax_bulk_cost_items_action'));
        // v1.5.1 - Independent Cost Item Management
        add_action('wp_ajax_get_all_cost_items', array($this, 'ajax_get_all_cost_items'));
        add_action('wp_ajax_create_independent_cost_item', array($this, 'ajax_create_independent_cost_item'));
        add_action('wp_ajax_assign_item_to_template', array($this, 'ajax_assign_item_to_template'));
        add_action('wp_ajax_remove_item_from_template', array($this, 'ajax_remove_item_from_template'));
        
        // Enqueue scripts for financial integration
        add_action('admin_enqueue_scripts', array($this, 'enqueue_integration_scripts'));
        
        // Add financial content to case tabs
        add_action('admin_footer', array($this, 'render_financial_tab_content'));
    }
    
    public function enqueue_integration_scripts() {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        // Only enqueue on case pages, not template management pages
        $is_case_page = (strpos($screen->id, 'la-cases') !== false && 
                        strpos($screen->id, 'cah-financial') === false && 
                        strpos($screen->id, 'cah-cost') === false);
        
        if ($is_case_page) {
            wp_enqueue_script('cah-case-financial', CAH_FC_PLUGIN_URL . 'assets/js/case-financial.js', array('jquery'), CAH_FC_PLUGIN_VERSION, true);
            wp_enqueue_style('cah-case-financial', CAH_FC_PLUGIN_URL . 'assets/css/case-financial.css', array(), CAH_FC_PLUGIN_VERSION);
            
            wp_localize_script('cah-case-financial', 'cah_case_financial', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cah_financial_nonce'),
                'currency_symbol' => '€',
                'vat_rate' => '19.00'
            ));
        }
    }
    
    public function render_financial_tab_content() {
        $screen = get_current_screen();
        
        // Only show in case editing context, NOT in template management
        if (!$screen) {
            return;
        }
        
        // Be very specific about which pages to show on
        $allowed_pages = array(
            'la-cases',
            'admin_page_la-cases',
            'toplevel_page_la-cases'
        );
        
        $show_content = false;
        foreach ($allowed_pages as $page) {
            if (strpos($screen->id, $page) !== false) {
                $show_content = true;
                break;
            }
        }
        
        // Also check if we're specifically on a case edit page
        if (!$show_content && isset($_GET['page']) && $_GET['page'] === 'la-cases' && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $show_content = true;
        }
        
        // Do not show on financial template or cost management pages
        if (strpos($screen->id, 'cah-financial') !== false || strpos($screen->id, 'cah-cost') !== false) {
            $show_content = false;
        }
        
        if (!$show_content) {
            return;
        }
        
        // Check if we're in case editing mode with a valid case ID
        $case_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        // Don't show financial tab during case creation (no ID present)
        if (!$case_id) {
            return; // Simply don't render anything during case creation
        }
        
        // Enqueue scripts and localize
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'cah_case_financial', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cah_financial_nonce'),
            'case_id' => $case_id
        ));
        ?>
        
        <div id="case-financial-content" style="max-width: 1200px; margin: 0 auto;">
            <!-- Simple Clean Interface -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">
                <h3 style="margin: 0 0 20px 0; color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                    💰 Finanzberechnung (Fall #<?php echo $case_id; ?>)
                </h3>
                
                <!-- Template Selection -->
                <div style="margin-bottom: 25px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #0073aa;">
                    <h4 style="margin: 0 0 15px 0;">Vorlage wählen:</h4>
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <select id="template-select" style="min-width: 250px; padding: 8px;">
                            <option value="">Vorlagen werden geladen...</option>
                        </select>
                        <button type="button" id="load-btn" class="button button-primary" disabled>
                            📥 Vorlage laden
                        </button>
                        <span id="template-status" style="color: #666; font-style: italic;"></span>
                    </div>
                </div>
                
                <!-- Items Display -->
                <div id="items-section" style="margin-bottom: 25px;">
                    <h4 style="margin: 0 0 15px 0;">📋 Kostenpunkte:</h4>
                    <div id="items-container" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; min-height: 120px; padding: 20px;">
                        <!-- Cost items will be rendered here dynamically -->
                    </div>
                </div>
                
                <!-- Totals Display -->
                <div id="totals-section" style="background: linear-gradient(135deg, #0073aa 0%, #00a0d2 100%); color: white; padding: 20px; border-radius: 8px; margin: 25px auto; max-width: 800px;">
                    <h4 style="margin: 0 0 15px 0; color: white; text-align: center;">💶 Kostenübersicht:</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; text-align: center;">
                        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px;">
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Nettobetrag</div>
                            <div id="display-subtotal" style="font-size: 20px; font-weight: bold;">€ 0,00</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px;">
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">MwSt. (19%)</div>
                            <div id="display-vat" style="font-size: 20px; font-weight: bold;">€ 0,00</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px; border: 2px solid rgba(255,255,255,0.3);">
                            <div style="font-size: 16px; opacity: 0.9; margin-bottom: 5px;">Gesamtbetrag</div>
                            <div id="display-total" style="font-size: 28px; font-weight: bold; color: #ffeb3b;">€ 0,00</div>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Spreadsheet-like Cost Items Management -->
                <div id="case-items-section" style="margin: 25px 0; padding: 20px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #00a0d2;">
                    <h4 style="margin: 0 0 15px 0; color: #00a0d2;">🧮 Fall-spezifische Kostenberechnung (Tabellenkalkulation):</h4>
                    
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
                        <button type="button" id="add-case-item-btn" class="button button-primary">
                            ➕ Neue Zeile hinzufügen
                        </button>
                        <button type="button" id="duplicate-selected-btn" class="button button-secondary" disabled>
                            📋 Zeile duplizieren
                        </button>
                        <button type="button" id="delete-selected-btn" class="button button-link-delete" disabled>
                            🗑️ Ausgewählte löschen
                        </button>
                        <button type="button" id="recalculate-btn" class="button">
                            🔄 Neu berechnen
                        </button>
                        <span id="case-items-status" style="color: #666; font-style: italic; margin-left: auto;"></span>
                    </div>
                    
                    <!-- Spreadsheet-like Table -->
                    <div id="cost-spreadsheet" style="background: white; border: 2px solid #ddd; border-radius: 5px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <table id="cost-items-table" style="width: 100%; border-collapse: collapse; min-width: 800px;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #00a0d2 0%, #0073aa 100%); color: white;">
                                    <th style="width: 40px; padding: 12px; text-align: center;">
                                        <input type="checkbox" id="select-all-items" title="Alle auswählen">
                                    </th>
                                    <th style="width: 200px; padding: 12px; text-align: left; font-weight: 600;">Kostenpunkt</th>
                                    <th style="width: 120px; padding: 12px; text-align: left; font-weight: 600;">Kategorie</th>
                                    <th style="width: 100px; padding: 12px; text-align: right; font-weight: 600;">Betrag (€)</th>
                                    <th style="width: 60px; padding: 12px; text-align: center; font-weight: 600;">%</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600;">Beschreibung</th>
                                    <th style="width: 80px; padding: 12px; text-align: center; font-weight: 600;">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="cost-items-tbody">
                                <tr id="no-items-row">
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #666; font-style: italic;">
                                        Keine Kostenpunkte vorhanden. Klicken Sie "Neue Zeile hinzufügen" oder laden Sie eine Vorlage.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; border-top: 2px solid #00a0d2;">
                                    <td colspan="3" style="padding: 15px; font-weight: bold; color: #0073aa;">Zwischensumme (Netto):</td>
                                    <td id="subtotal-cell" style="padding: 15px; text-align: right; font-weight: bold; color: #0073aa; font-size: 16px;">€ 0,00</td>
                                    <td colspan="3"></td>
                                </tr>
                                <tr style="background: #f0f8ff;">
                                    <td colspan="3" style="padding: 12px; font-weight: bold;">MwSt (19%):</td>
                                    <td id="vat-cell" style="padding: 12px; text-align: right; font-weight: bold; font-size: 14px;">€ 0,00</td>
                                    <td colspan="3"></td>
                                </tr>
                                <tr style="background: linear-gradient(135deg, #0073aa 0%, #00a0d2 100%); color: white;">
                                    <td colspan="3" style="padding: 15px; font-weight: bold; font-size: 16px;">GESAMTBETRAG:</td>
                                    <td id="total-cell" style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #ffeb3b;">€ 0,00</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div style="display: flex; gap: 20px; margin-top: 15px; font-size: 14px; color: #666;">
                        <span>📊 Kostenpunkte: <strong id="items-count">0</strong></span>
                        <span>📋 Kategorien: <strong id="categories-count">0</strong></span>
                        <span>💾 Status: <strong id="save-status-indicator">Nicht gespeichert</strong></span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="text-align: center; border-top: 1px solid #ddd; padding-top: 20px; margin-top: 25px;">
                    <button type="button" id="save-financial-btn" class="button button-primary button-large" disabled>
                        💾 Finanzberechnung für Fall speichern
                    </button>
                    <button type="button" id="calculate-totals-btn" class="button button-secondary button-large" style="margin-left: 15px;">
                        🧮 Summen neu berechnen
                    </button>
                    <div id="save-status" style="margin-top: 10px; font-style: italic; color: #666;"></div>
                </div>
                
                <!-- Messages -->
                <div id="financial-messages" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <!-- Case Cost Item Editor Modal -->
        <div id="case-item-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 600px; width: 90%;">
                <h4 style="margin: 0 0 20px 0; color: #00a0d2; border-bottom: 2px solid #00a0d2; padding-bottom: 10px;">
                    💰 Kostenpunkt bearbeiten
                </h4>
                
                <form id="case-item-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="case-item-name">Name:</label></th>
                            <td><input type="text" id="case-item-name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="case-item-category">Kategorie:</label></th>
                            <td>
                                <select id="case-item-category" class="regular-text">
                                    <option value="basic_costs">Grundkosten</option>
                                    <option value="court_costs">Gerichtskosten</option>
                                    <option value="legal_costs">Anwaltskosten</option>
                                    <option value="other_costs">Sonstige Kosten</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="case-item-amount">Betrag (€):</label></th>
                            <td><input type="number" id="case-item-amount" class="regular-text" step="0.01" min="0" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="case-item-description">Beschreibung:</label></th>
                            <td><textarea id="case-item-description" class="large-text" rows="3"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="case-item-percentage">Prozentual:</label></th>
                            <td>
                                <label><input type="checkbox" id="case-item-percentage"> Als Prozentsatz berechnen</label>
                                <p class="description">Bei aktivierter Option wird der Betrag als Prozentsatz der Zwischensumme berechnet</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="text-align: right; margin-top: 25px; border-top: 1px solid #ddd; padding-top: 20px;">
                        <button type="button" id="cancel-case-item-btn" class="button">Abbrechen</button>
                        <button type="submit" class="button button-primary" style="margin-left: 10px;">Speichern</button>
                        <button type="button" id="delete-case-item-btn" class="button button-link-delete" style="margin-left: 10px; display: none;">Löschen</button>
                    </div>
                    
                    <input type="hidden" id="case-item-id" value="">
                    <input type="hidden" id="case-item-action" value="create">
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentItems = [];
            var caseItems = [];
            var currentCaseId = <?php echo $case_id; ?>;
            var editingRowId = null;
            var selectedRows = [];
            
            console.log('Financial calculator initialized for case:', currentCaseId);
            
            // Initialize
            loadTemplates();
            loadCaseItems();
            
            // Add debounce function for calculations
            var calculateDebounce = null;
            
            // Template Events
            $('#template-select').change(function() {
                var templateId = $(this).val();
                if (templateId) {
                    $('#load-btn').prop('disabled', false);
                    $('#template-status').text('Bereit zum Laden');
                } else {
                    $('#load-btn').prop('disabled', true);
                    $('#template-status').text('');
                }
            });
            
            $('#load-btn').click(function() {
                var templateId = $('#template-select').val();
                if (templateId) {
                    loadTemplateItems(templateId);
                }
            });
            
            // Spreadsheet Events
            $('#add-case-item-btn').click(function() {
                addNewRow();
            });
            
            $('#duplicate-selected-btn').click(function() {
                duplicateSelectedRows();
            });
            
            $('#delete-selected-btn').click(function() {
                if (confirm('Möchten Sie die ausgewählten Kostenpunkte wirklich löschen?')) {
                    deleteSelectedRows();
                }
            });
            
            $('#recalculate-btn').click(function() {
                recalculateAllTotals();
            });
            
            $('#select-all-items').change(function() {
                var checked = $(this).is(':checked');
                $('.item-checkbox').prop('checked', checked).trigger('change');
            });
            
            // Dynamic row selection handling
            $(document).on('change', '.item-checkbox', function() {
                updateSelectionState();
            });
            
            // Inline editing events
            $(document).on('blur', '.editable-field', function() {
                saveRowChanges($(this).closest('tr'));
            });
            
            $(document).on('keypress', '.editable-field', function(e) {
                if (e.which === 13) { // Enter key
                    $(this).blur();
                }
            });
            
            $(document).on('change', 'select.editable-field', function() {
                saveRowChanges($(this).closest('tr'));
            });
            
            $(document).on('change', 'input[type="checkbox"].editable-field', function() {
                saveRowChanges($(this).closest('tr'));
            });
            
            // Add input event for immediate feedback on amount changes
            $(document).on('input', 'input[data-field="amount"]', function() {
                var row = $(this).closest('tr');
                // Debounce the save for amount changes
                clearTimeout($(this).data('save-timeout'));
                var timeout = setTimeout(function() {
                    saveRowChanges(row);
                }, 300);
                $(this).data('save-timeout', timeout);
            });
            
            // Row action events
            $(document).on('click', '.delete-row-btn', function() {
                var row = $(this).closest('tr');
                if (confirm('Möchten Sie diesen Kostenpunkt löschen?')) {
                    deleteRow(row);
                }
            });
            
            $(document).on('click', '.duplicate-row-btn', function() {
                var row = $(this).closest('tr');
                duplicateRow(row);
            });
            
            // Save Events
            $('#save-financial-btn').click(function() {
                saveFinancialData();
            });
            
            $('#calculate-totals-btn').click(function() {
                console.log('Manual recalculation triggered');
                recalculateAllTotals();
                showMessage('💡 Summen wurden neu berechnet', 'info');
            });
            
            function loadTemplates() {
                $('#template-status').text('Lade Vorlagen...');
                
                $.ajax({
                    url: cah_case_financial.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'load_financial_templates',
                        nonce: cah_case_financial.nonce
                    },
                    success: function(response) {
                        console.log('Templates loaded:', response);
                        
                        var select = $('#template-select');
                        select.empty().append('<option value="">-- Vorlage auswählen --</option>');
                        
                        if (response.success && response.data && response.data.length > 0) {
                            $.each(response.data, function(i, template) {
                                select.append('<option value="' + template.id + '">' + template.name + '</option>');
                            });
                            $('#template-status').text(response.data.length + ' Vorlagen verfügbar');
                        } else {
                            $('#template-status').text('Keine Vorlagen gefunden');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Template loading failed:', error);
                        $('#template-status').text('Fehler beim Laden');
                    }
                });
            }
            
            function loadCaseItems() {
                $('#case-items-status').text('Lade gespeicherte Kostenpunkte...');
                
                $.ajax({
                    url: cah_case_financial.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'load_case_financial_data',
                        case_id: currentCaseId,
                        nonce: cah_case_financial.nonce
                    },
                    success: function(response) {
                        console.log('Case financial data loaded:', response);
                        
                        if (response.success && response.data) {
                            // Clear existing items first to prevent duplication
                            caseItems = [];
                            
                            if (response.data.items && Array.isArray(response.data.items)) {
                                caseItems = response.data.items.slice(); // Create a copy
                            }
                            
                            // Load actual saved case totals, not template defaults
                            if (response.data.totals) {
                                updateTotalsDisplay(response.data.totals);
                                $('#save-status-indicator').text('Gespeichert').css('color', 'green');
                            } else {
                                // No saved totals, calculate from items
                                setTimeout(function() {
                                    recalculateAllTotals();
                                }, 50);
                            }
                            
                            renderSpreadsheet();
                            $('#case-items-status').text('✅ ' + caseItems.length + ' Kostenpunkte geladen');
                            
                            // Force immediate calculation regardless of saved totals
                            setTimeout(function() {
                                recalculateAllTotals();
                            }, 50);
                            
                            // Additional calculation after rendering
                            setTimeout(function() {
                                recalculateAllTotals();
                            }, 200);
                        } else {
                            // No saved data, start fresh
                            caseItems = [];
                            renderSpreadsheet();
                            $('#case-items-status').text('Bereit für neue Kostenpunkte');
                            $('#save-status-indicator').text('Nicht gespeichert').css('color', 'orange');
                        }
                        
                        // Always force calculation for any loaded items, even if empty
                        setTimeout(function() {
                            recalculateAllTotals();
                        }, 100);
                        
                        setTimeout(function() {
                            recalculateAllTotals();
                        }, 300);
                    },
                    error: function(xhr, status, error) {
                        console.error('Case items loading failed:', error);
                        caseItems = []; // Ensure clean state on error
                        renderSpreadsheet();
                        $('#case-items-status').text('❌ Fehler beim Laden');
                    }
                });
            }
            
            function renderSpreadsheet() {
                var tbody = $('#cost-items-tbody');
                tbody.empty();
                
                if (caseItems.length === 0) {
                    tbody.append('<tr id="no-items-row"><td colspan="7" style="text-align: center; padding: 40px; color: #666; font-style: italic;">Keine Kostenpunkte vorhanden. Klicken Sie "Neue Zeile hinzufügen" oder laden Sie eine Vorlage.</td></tr>');
                    updateStats();
                    return;
                }
                
                caseItems.forEach(function(item, index) {
                    var row = createSpreadsheetRow(item, index);
                    tbody.append(row);
                });
                
                updateStats();
                recalculateAllTotals();
            }
            
            function createSpreadsheetRow(item, index) {
                // Ensure consistent ID generation
                if (!item.id && !item._tempId) {
                    item._tempId = Date.now() + '_' + index + '_' + Math.random().toString(36).substr(2, 9);
                }
                var itemId = item.id || 'new_' + item._tempId;
                var isPercentage = item.is_percentage == 1;
                
                var html = '<tr data-item-id="' + itemId + '" style="' + (index % 2 === 0 ? 'background: #f8f9fa;' : 'background: white;') + '">';
                
                // Checkbox
                html += '<td style="padding: 8px; text-align: center;">';
                html += '<input type="checkbox" class="item-checkbox" data-item-id="' + itemId + '">';
                html += '</td>';
                
                // Name (editable)
                html += '<td style="padding: 8px;">';
                html += '<input type="text" class="editable-field" data-field="name" value="' + escapeHtml(item.name || '') + '" style="width: 100%; border: 1px solid #ddd; padding: 4px;" placeholder="Kostenpunkt...">';
                html += '</td>';
                
                // Category (editable dropdown)
                html += '<td style="padding: 8px;">';
                html += '<select class="editable-field" data-field="category" style="width: 100%; border: 1px solid #ddd; padding: 4px;">';
                html += '<option value="basic_costs"' + (item.category === 'basic_costs' ? ' selected' : '') + '>Grundkosten</option>';
                html += '<option value="court_costs"' + (item.category === 'court_costs' ? ' selected' : '') + '>Gerichtskosten</option>';
                html += '<option value="legal_costs"' + (item.category === 'legal_costs' ? ' selected' : '') + '>Anwaltskosten</option>';
                html += '<option value="other_costs"' + (item.category === 'other_costs' ? ' selected' : '') + '>Sonstige</option>';
                html += '</select>';
                html += '</td>';
                
                // Amount (editable)
                html += '<td style="padding: 8px;">';
                html += '<input type="number" class="editable-field" data-field="amount" value="' + (item.amount || 0) + '" step="0.01" min="0" style="width: 100%; border: 1px solid #ddd; padding: 4px; text-align: right;">';
                html += '</td>';
                
                // Percentage checkbox
                html += '<td style="padding: 8px; text-align: center;">';
                html += '<input type="checkbox" class="editable-field" data-field="is_percentage"' + (isPercentage ? ' checked' : '') + ' title="Als Prozentsatz berechnen">';
                html += '</td>';
                
                // Description (editable)
                html += '<td style="padding: 8px;">';
                html += '<input type="text" class="editable-field" data-field="description" value="' + escapeHtml(item.description || '') + '" style="width: 100%; border: 1px solid #ddd; padding: 4px;" placeholder="Beschreibung...">';
                html += '</td>';
                
                // Actions
                html += '<td style="padding: 8px; text-align: center;">';
                html += '<button type="button" class="button button-small duplicate-row-btn" title="Duplizieren">📋</button> ';
                html += '<button type="button" class="button button-small button-link-delete delete-row-btn" title="Löschen">🗑️</button>';
                html += '</td>';
                
                html += '</tr>';
                return html;
            }
            
            function addNewRow() {
                var timestamp = Date.now();
                var newItem = {
                    id: null,
                    _tempId: timestamp + '_' + Math.random().toString(36).substr(2, 9),
                    name: '',
                    category: 'basic_costs',
                    amount: 0,
                    description: '',
                    is_percentage: false
                };
                
                caseItems.push(newItem);
                renderSpreadsheet();
                
                // Focus on the new row's name field after a brief delay to ensure rendering
                setTimeout(function() {
                    var newRow = $('#cost-items-tbody tr:last-child');
                    if (newRow.length > 0) {
                        newRow.find('[data-field="name"]').focus();
                    }
                }, 50);
                
                $('#save-status-indicator').text('Nicht gespeichert').css('color', 'orange');
            }
            
            function duplicateRow(row) {
                var itemId = row.data('item-id');
                var item = findItemById(itemId);
                
                if (item) {
                    var newItem = {
                        id: null,
                        name: item.name + ' (Kopie)',
                        category: item.category,
                        amount: item.amount,
                        description: item.description,
                        is_percentage: item.is_percentage
                    };
                    
                    caseItems.push(newItem);
                    renderSpreadsheet();
                    $('#save-status-indicator').text('Nicht gespeichert').css('color', 'orange');
                }
            }
            
            function duplicateSelectedRows() {
                var selected = $('.item-checkbox:checked');
                if (selected.length === 0) {
                    alert('Bitte wählen Sie mindestens eine Zeile zum Duplizieren aus.');
                    return;
                }
                
                selected.each(function() {
                    var row = $(this).closest('tr');
                    duplicateRow(row);
                });
            }
            
            function deleteRow(row) {
                var itemId = row.data('item-id');
                var item = findItemById(itemId);
                
                if (item && item.id) {
                    // Item has a database ID, need to delete from server
                    $.ajax({
                        url: cah_case_financial.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'delete_case_cost_item',
                            item_id: item.id,
                            nonce: cah_case_financial.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Remove from local array only after successful server deletion
                                caseItems = caseItems.filter(function(caseItem) {
                                    return caseItem !== item;
                                });
                                renderSpreadsheet();
                                $('#save-status-indicator').text('Gelöscht - speichern empfohlen').css('color', 'orange');
                                console.log('Item deleted from database:', item.id);
                            } else {
                                alert('Fehler beim Löschen: ' + (response.data || 'Unbekannter Fehler'));
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('AJAX Fehler beim Löschen: ' + error);
                        }
                    });
                } else {
                    // Item has no database ID, just remove from local array
                    caseItems = caseItems.filter(function(caseItem) {
                        return caseItem !== item;
                    });
                    renderSpreadsheet();
                    $('#save-status-indicator').text('Nicht gespeichert').css('color', 'orange');
                }
            }
            
            function deleteSelectedRows() {
                var selected = $('.item-checkbox:checked');
                if (selected.length === 0) {
                    alert('Bitte wählen Sie mindestens eine Zeile zum Löschen aus.');
                    return;
                }
                
                selected.each(function() {
                    var row = $(this).closest('tr');
                    deleteRow(row);
                });
            }
            
            function saveRowChanges(row) {
                var itemId = row.data('item-id');
                var item = findItemById(itemId);
                
                if (!item) {
                    // Create new item if it doesn't exist - this should rarely happen
                    console.warn('Creating new item for missing ID:', itemId);
                    item = { 
                        id: null,
                        name: '',
                        category: 'basic_costs',
                        amount: 0,
                        description: '',
                        is_percentage: false
                    };
                    caseItems.push(item);
                }
                
                // Update item with form values, ensuring data integrity
                row.find('.editable-field').each(function() {
                    var field = $(this).data('field');
                    var value = $(this).val();
                    
                    if (field === 'is_percentage') {
                        item[field] = $(this).is(':checked');
                    } else if (field === 'amount') {
                        var numValue = parseFloat(value);
                        item[field] = isNaN(numValue) ? 0 : numValue;
                    } else if (field === 'name') {
                        item[field] = value.trim();
                    } else if (field === 'category') {
                        // Ensure valid category
                        var validCategories = ['basic_costs', 'court_costs', 'legal_costs', 'other_costs'];
                        item[field] = validCategories.includes(value) ? value : 'basic_costs';
                    } else {
                        item[field] = value;
                    }
                });
                
                // Force immediate recalculation
                recalculateAllTotals();
                
                // Also force a backup calculation
                setTimeout(function() {
                    recalculateAllTotals();
                }, 100);
                
                $('#save-status-indicator').text('Nicht gespeichert').css('color', 'orange');
                
                console.log('Row changes saved and calculations triggered for item:', item);
            }
            
            function updateSelectionState() {
                var selectedCount = $('.item-checkbox:checked').length;
                var totalCount = $('.item-checkbox').length;
                
                $('#duplicate-selected-btn').prop('disabled', selectedCount === 0);
                $('#delete-selected-btn').prop('disabled', selectedCount === 0);
                
                $('#select-all-items').prop('indeterminate', selectedCount > 0 && selectedCount < totalCount);
                $('#select-all-items').prop('checked', selectedCount === totalCount && totalCount > 0);
            }
            
            function findItemById(itemId) {
                if (!itemId) return null;
                
                return caseItems.find(function(item) {
                    // Handle both database IDs and temporary IDs
                    if (item.id && itemId == item.id) {
                        return true;
                    }
                    var tempId = item._tempId ? 'new_' + item._tempId : null;
                    return tempId && itemId === tempId;
                });
            }
            
            function recalculateAllTotals() {
                // Clear any pending calculation
                if (calculateDebounce) {
                    clearTimeout(calculateDebounce);
                }
                
                // Debounce the calculation to prevent excessive calls
                calculateDebounce = setTimeout(function() {
                    performCalculation();
                }, 100);
            }
            
            function performCalculation() {
                var subtotal = 0;
                var categoryCount = {};
                
                // First pass: Calculate base amount for non-percentage items
                var baseSubtotal = 0;
                caseItems.forEach(function(item) {
                    if (!item.is_percentage) {
                        baseSubtotal += parseFloat(item.amount) || 0;
                    }
                });
                
                // Second pass: Calculate all items including percentage-based ones
                caseItems.forEach(function(item) {
                    var amount = parseFloat(item.amount) || 0;
                    
                    if (item.is_percentage) {
                        // Handle percentage-based items using base subtotal
                        amount = baseSubtotal * (amount / 100);
                    }
                    
                    subtotal += amount;
                    
                    // Count categories
                    var category = item.category || 'basic_costs';
                    categoryCount[category] = (categoryCount[category] || 0) + 1;
                });
                
                var vatAmount = subtotal * 0.19;
                var totalAmount = subtotal + vatAmount;
                
                // Update totals display
                updateTotalsDisplay({
                    subtotal: subtotal,
                    vat_amount: vatAmount,
                    total_amount: totalAmount
                });
                
                // Update stats
                $('#categories-count').text(Object.keys(categoryCount).length);
                
                // Enable save button if we have items
                $('#save-financial-btn').prop('disabled', caseItems.length === 0);
            }
            
            function updateTotalsDisplay(totals) {
                $('#subtotal-cell').text('€ ' + formatNumber(totals.subtotal || 0));
                $('#vat-cell').text('€ ' + formatNumber(totals.vat_amount || 0));
                $('#total-cell').text('€ ' + formatNumber(totals.total_amount || 0));
                
                // Also update the old display for compatibility
                $('#display-subtotal').text('€ ' + formatNumber(totals.subtotal || 0));
                $('#display-vat').text('€ ' + formatNumber(totals.vat_amount || 0));
                $('#display-total').text('€ ' + formatNumber(totals.total_amount || 0));
            }
            
            function updateStats() {
                $('#items-count').text(caseItems.length);
                
                var categoryCount = {};
                caseItems.forEach(function(item) {
                    var category = item.category || 'basic_costs';
                    categoryCount[category] = (categoryCount[category] || 0) + 1;
                });
                $('#categories-count').text(Object.keys(categoryCount).length);
            }
            
            function openCaseItemModal(action, itemData) {
                $('#case-item-action').val(action);
                
                if (action === 'create') {
                    $('#case-item-modal h4').text('💰 Neuen Kostenpunkt hinzufügen');
                    $('#case-item-form')[0].reset();
                    $('#case-item-id').val('');
                    $('#delete-case-item-btn').hide();
                } else if (action === 'edit' && itemData) {
                    $('#case-item-modal h4').text('💰 Kostenpunkt bearbeiten');
                    $('#case-item-name').val(itemData.name);
                    $('#case-item-category').val(itemData.category);
                    $('#case-item-amount').val(itemData.amount);
                    $('#case-item-description').val(itemData.description || '');
                    $('#case-item-percentage').prop('checked', itemData.is_percentage == 1);
                    $('#case-item-id').val(itemData.id);
                    $('#delete-case-item-btn').show();
                }
                
                $('#case-item-modal').show();
            }
            
            function closeCaseItemModal() {
                $('#case-item-modal').hide();
            }
            
            function editCaseItem(itemId) {
                var item = caseItems.find(function(i) { return i.id == itemId; });
                if (item) {
                    openCaseItemModal('edit', item);
                }
            }
            
            function saveCaseItem() {
                var action = $('#case-item-action').val();
                var ajaxAction = action === 'create' ? 'create_case_cost_item' : 'update_case_cost_item';
                
                var data = {
                    action: ajaxAction,
                    case_id: currentCaseId,
                    name: $('#case-item-name').val(),
                    category: $('#case-item-category').val(),
                    amount: parseFloat($('#case-item-amount').val()) || 0,
                    description: $('#case-item-description').val(),
                    is_percentage: $('#case-item-percentage').is(':checked') ? 1 : 0,
                    nonce: cah_case_financial.nonce
                };
                
                if (action === 'edit') {
                    data.item_id = $('#case-item-id').val();
                }
                
                $.ajax({
                    url: cah_case_financial.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            closeCaseItemModal();
                            loadCaseItems(); // Reload items
                            showMessage('✅ Kostenpunkt erfolgreich gespeichert', 'success');
                        } else {
                            showMessage('❌ ' + (response.data || 'Fehler beim Speichern'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showMessage('❌ AJAX Fehler: ' + error, 'error');
                    }
                });
            }
            
            function deleteCaseItemById(itemId) {
                $.ajax({
                    url: cah_case_financial.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'delete_case_cost_item',
                        item_id: itemId,
                        nonce: cah_case_financial.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            loadCaseItems(); // Reload items
                            showMessage('✅ Kostenpunkt erfolgreich gelöscht', 'success');
                        } else {
                            showMessage('❌ ' + (response.data || 'Fehler beim Löschen'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showMessage('❌ AJAX Fehler: ' + error, 'error');
                    }
                });
            }
            
            function deleteCaseItem() {
                var itemId = $('#case-item-id').val();
                if (itemId) {
                    deleteCaseItemById(itemId);
                    closeCaseItemModal();
                }
            }
            
            function calculateAllTotals() {
                var allItems = [];
                
                // Add template items
                if (currentItems && currentItems.length > 0) {
                    allItems = allItems.concat(currentItems);
                }
                
                // Add case-specific items
                if (caseItems && caseItems.length > 0) {
                    allItems = allItems.concat(caseItems);
                }
                
                // First pass: Calculate base amount for non-percentage items
                var baseSubtotal = 0;
                $.each(allItems, function(i, item) {
                    if (!item.is_percentage) {
                        baseSubtotal += parseFloat(item.amount || 0);
                    }
                });
                
                // Second pass: Calculate all items including percentage-based ones
                var subtotal = 0;
                $.each(allItems, function(i, item) {
                    var amount = parseFloat(item.amount || 0);
                    if (item.is_percentage) {
                        amount = baseSubtotal * (amount / 100);
                    }
                    subtotal += amount;
                });
                
                var vatAmount = subtotal * 0.19;
                var totalAmount = subtotal + vatAmount;
                
                updateTotals({
                    subtotal: subtotal,
                    vat_amount: vatAmount,
                    total_amount: totalAmount
                });
                
                // Enable save button if we have items
                if (allItems.length > 0) {
                    $('#save-financial-btn').prop('disabled', false);
                } else {
                    $('#save-financial-btn').prop('disabled', true);
                }
            }
            
            function loadTemplateItems(templateId) {
                $('#template-status').text('Lade Kostenpunkte...');
                $('#load-btn').prop('disabled', true).text('Lädt...');
                
                $.ajax({
                    url: cah_case_financial.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'load_template_items',
                        template_id: templateId,
                        nonce: cah_case_financial.nonce
                    },
                    success: function(response) {
                        console.log('Template items loaded:', response);
                        
                        if (response.success && response.data && response.data.items) {
                            var templateItems = response.data.items || [];
                            
                            // For new cases or when no items exist, just load the template
                            // For existing cases with items, replace by default (no confirmation needed)
                            var action = 'replace';
                            
                            // Clear existing items completely
                            caseItems = [];
                            
                            // Create clean copies of template items for case use
                            templateItems.forEach(function(item) {
                                var newItem = {
                                    id: null, // No ID so it gets saved as case-specific
                                    name: item.name || '',
                                    category: item.category || 'basic_costs',
                                    amount: parseFloat(item.amount) || 0,
                                    description: item.description || '',
                                    is_percentage: item.is_percentage == 1
                                };
                                caseItems.push(newItem);
                            });
                            
                            // Re-render the spreadsheet with new data
                            renderSpreadsheet();
                            
                            // Force immediate and repeated calculations for reliability
                            recalculateAllTotals();
                            
                            setTimeout(function() {
                                recalculateAllTotals();
                            }, 100);
                            
                            setTimeout(function() {
                                recalculateAllTotals();
                            }, 300);
                            
                            $('#template-status').text('✅ Vorlage geladen (' + templateItems.length + ' Kostenpunkte)');
                            $('#save-status-indicator').text('Nicht gespeichert').css('color', 'orange');
                        } else {
                            $('#template-status').text('❌ Keine Kostenpunkte in der Vorlage gefunden');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Template items loading failed:', error);
                        $('#template-status').text('❌ AJAX Fehler beim Laden');
                    },
                    complete: function() {
                        $('#load-btn').prop('disabled', false).text('📥 Vorlage laden');
                    }
                });
            }
            
            function displayItems(items) {
                var container = $('#items-container');
                
                if (!items || items.length === 0) {
                    container.html('<div style="text-align: center; color: #666; padding: 30px;"><em>Keine Kostenpunkte in dieser Vorlage</em></div>');
                    return;
                }
                
                var html = '<table style="width: 100%; border-collapse: collapse; background: white; border-radius: 5px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                html += '<thead><tr style="background: #0073aa; color: white;">';
                html += '<th style="padding: 12px; text-align: left; font-weight: 600;">Kostenpunkt</th>';
                html += '<th style="padding: 12px; text-align: left; font-weight: 600;">Kategorie</th>';
                html += '<th style="padding: 12px; text-align: right; font-weight: 600;">Betrag</th>';
                html += '<th style="padding: 12px; text-align: left; font-weight: 600;">Beschreibung</th>';
                html += '</tr></thead><tbody>';
                
                $.each(items, function(i, item) {
                    var rowClass = i % 2 === 0 ? 'background: #f8f9fa;' : 'background: white;';
                    html += '<tr style="' + rowClass + '">';
                    html += '<td style="padding: 12px; font-weight: 500;">' + escapeHtml(item.name) + '</td>';
                    html += '<td style="padding: 12px;"><span style="background: #e9ecef; padding: 4px 8px; border-radius: 3px; font-size: 12px;">' + getCategoryName(item.category) + '</span></td>';
                    html += '<td style="padding: 12px; text-align: right; font-weight: 600; color: #0073aa;">€ ' + formatNumber(item.amount) + '</td>';
                    html += '<td style="padding: 12px; color: #666;">' + escapeHtml(item.description || '-') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                container.html(html);
            }
            
            function updateTotals(totals) {
                $('#display-subtotal').text('€ ' + formatNumber(totals.subtotal || 0));
                $('#display-vat').text('€ ' + formatNumber(totals.vat_amount || 0));
                $('#display-total').text('€ ' + formatNumber(totals.total_amount || 0));
            }
            
            function saveFinancialData() {
                if (caseItems.length === 0) {
                    showMessage('⚠️ Keine Kostenpunkte zum Speichern', 'warning');
                    return;
                }
                
                $('#save-financial-btn').prop('disabled', true).text('Speichert...');
                $('#save-status').text('Finanzberechnung wird gespeichert...');
                
                // Calculate totals from current items with proper percentage handling
                var baseSubtotal = 0;
                var subtotal = 0;
                
                // First pass: Calculate base subtotal for non-percentage items
                caseItems.forEach(function(item) {
                    if (!item.is_percentage) {
                        baseSubtotal += parseFloat(item.amount) || 0;
                    }
                });
                
                // Second pass: Calculate all items including percentage-based ones
                caseItems.forEach(function(item) {
                    var amount = parseFloat(item.amount) || 0;
                    if (item.is_percentage) {
                        amount = baseSubtotal * (amount / 100);
                    }
                    subtotal += amount;
                });
                
                var vatAmount = subtotal * 0.19;
                var totalAmount = subtotal + vatAmount;
                
                $.ajax({
                    url: cah_case_financial.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'save_case_financial_spreadsheet',
                        case_id: currentCaseId,
                        template_id: $('#template-select').val() || null,
                        subtotal: subtotal.toFixed(2),
                        vat_amount: vatAmount.toFixed(2),
                        total_amount: totalAmount.toFixed(2),
                        items_data: JSON.stringify(caseItems),
                        nonce: cah_case_financial.nonce
                    },
                    success: function(response) {
                        console.log('Save response:', response);
                        
                        if (response.success) {
                            $('#save-status').text('✅ Erfolgreich gespeichert!');
                            $('#save-status-indicator').text('Gespeichert').css('color', 'green');
                            showMessage('✅ Finanzberechnung erfolgreich gespeichert', 'success');
                            
                            // Update IDs of newly created items
                            if (response.data && response.data.item_ids) {
                                response.data.item_ids.forEach(function(mapping) {
                                    var item = caseItems.find(function(i) { return i.temp_id === mapping.temp_id; });
                                    if (item) {
                                        item.id = mapping.id;
                                        delete item.temp_id;
                                    }
                                });
                            }
                        } else {
                            $('#save-status').text('❌ Speichern fehlgeschlagen');
                            $('#save-status-indicator').text('Fehler').css('color', 'red');
                            showMessage('❌ ' + (response.data || 'Fehler beim Speichern'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Save failed:', error);
                        $('#save-status').text('❌ AJAX Fehler');
                        $('#save-status-indicator').text('AJAX Fehler').css('color', 'red');
                        showMessage('❌ AJAX Fehler beim Speichern: ' + error, 'error');
                    },
                    complete: function() {
                        $('#save-financial-btn').prop('disabled', false).text('💾 Finanzberechnung für Fall speichern');
                        setTimeout(function() {
                            $('#save-status').text('');
                        }, 3000);
                    }
                });
            }
            
            // Utility functions
            function formatNumber(num) {
                return parseFloat(num || 0).toFixed(2).replace('.', ',');
            }
            
            function getCategoryName(category) {
                var names = {
                    'basic_costs': 'Grundkosten',
                    'court_costs': 'Gerichtskosten',
                    'legal_costs': 'Anwaltskosten',
                    'other_costs': 'Sonstige'
                };
                return names[category] || category || 'Unbekannt';
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                return $('<div>').text(text).html();
            }
            
            function showMessage(msg, type) {
                var className = 'notice-' + (type || 'info');
                var bgColor = type === 'success' ? '#d4edda' : type === 'warning' ? '#fff3cd' : '#f8d7da';
                var borderColor = type === 'success' ? '#c3e6cb' : type === 'warning' ? '#ffeaa7' : '#f5c6cb';
                
                var html = '<div class="notice ' + className + '" style="background: ' + bgColor + '; border-left: 4px solid ' + borderColor + '; padding: 12px; margin: 10px 0; border-radius: 3px;"><p style="margin: 0;">' + msg + '</p></div>';
                
                $('#financial-messages').html(html);
                
                setTimeout(function() {
                    $('#financial-messages').empty();
                }, 5000);
            }
        });
        </script>
        <?php
    }
    
    // AJAX Handlers
    public function ajax_load_templates() {
        // More flexible nonce checking for debugging
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cah_financial_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }
        
        try {
            $templates = $this->db_manager->get_templates();
            
            if (empty($templates)) {
                // If no templates exist, create a default one
                $this->template_manager->create_default_templates();
                $templates = $this->db_manager->get_templates();
            }
            
            wp_send_json_success($templates);
        } catch (Exception $e) {
            wp_send_json_error('Error loading templates: ' . $e->getMessage());
        }
    }
    
    public function ajax_load_template_items() {
        // More flexible nonce checking
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cah_financial_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }
        
        $template_id = intval($_POST['template_id']);
        if (!$template_id) {
            wp_send_json_error('Invalid template ID');
            return;
        }
        
        try {
            $items = $this->db_manager->get_cost_items_by_template($template_id);
            
            if (empty($items)) {
                // Return empty items with zero totals
                $response_data = array(
                    'items' => array(),
                    'totals' => array(
                        'subtotal' => 0.00,
                        'vat_rate' => 19.00,
                        'vat_amount' => 0.00,
                        'total_amount' => 0.00,
                        'item_count' => 0
                    )
                );
                wp_send_json_success($response_data);
                return;
            }
            
            // Include category information for better display
            foreach ($items as $item) {
                if (!isset($item->category_name) && isset($item->category)) {
                    $item->category_name = ucfirst($item->category);
                }
            }
            
            // Calculate totals using the calculator engine
            $totals = $this->calculator->calculate_totals($items);
            
            // Prepare response with both items and totals
            $response_data = array(
                'items' => $items,
                'totals' => $totals
            );
            
            wp_send_json_success($response_data);
        } catch (Exception $e) {
            wp_send_json_error('Error loading template items: ' . $e->getMessage());
        }
    }
    
    public function ajax_calculate_totals() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $items_data = json_decode(stripslashes($_POST['items']), true);
        if (!$items_data) {
            wp_send_json_error('Invalid items data');
        }
        
        // Convert array data to objects for calculator
        $items = array();
        foreach ($items_data as $item_data) {
            $item = (object) $item_data;
            $items[] = $item;
        }
        
        $totals = $this->calculator->calculate_totals($items);
        wp_send_json_success($totals);
    }
    
    /**
     * Save case financial data from spreadsheet interface
     */
    public function ajax_save_case_financial_spreadsheet() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $case_id = intval($_POST['case_id']);
        $template_id = intval($_POST['template_id']) ?: null;
        $subtotal = floatval($_POST['subtotal']);
        $vat_amount = floatval($_POST['vat_amount']);
        $total_amount = floatval($_POST['total_amount']);
        $items_data = json_decode(stripslashes($_POST['items_data']), true);
        
        if (!$case_id || !is_array($items_data)) {
            wp_send_json_error('Invalid case ID or items data');
            return;
        }
        
        global $wpdb;
        
        try {
            $wpdb->query('START TRANSACTION');
            
            // 1. Clear existing case items
            $this->db_manager->clear_case_cost_items($case_id);
            
            // 2. Save new items and collect IDs
            $item_ids = array();
            foreach ($items_data as $index => $item_data) {
                $item_id = $this->db_manager->create_cost_item(
                    $template_id,
                    $case_id,
                    sanitize_text_field($item_data['name'] ?? ''),
                    sanitize_text_field($item_data['category'] ?? 'basic_costs'),
                    floatval($item_data['amount'] ?? 0),
                    sanitize_text_field($item_data['description'] ?? ''),
                    (bool)($item_data['is_percentage'] ?? false),
                    $index
                );
                
                if ($item_id) {
                    $item_ids[] = array(
                        'temp_id' => $item_data['temp_id'] ?? null,
                        'id' => $item_id
                    );
                }
            }
            
            // 3. Save financial summary
            $this->save_case_financial_summary($case_id, $template_id, $subtotal, $vat_amount, $total_amount);
            
            // 4. Update case total in main cases table
            $this->update_case_total_amount($case_id);
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success(array(
                'message' => 'Financial data saved successfully',
                'item_ids' => $item_ids
            ));
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Error saving financial data: ' . $e->getMessage());
        }
    }
    
    /**
     * Save case financial summary
     */
    private function save_case_financial_summary($case_id, $template_id, $subtotal, $vat_amount, $total_amount) {
        global $wpdb;
        
        $table_name = $this->db_manager->get_case_financial_table();
        
        // Delete existing summary
        $wpdb->delete($table_name, array('case_id' => $case_id), array('%d'));
        
        // Insert new summary
        $result = $wpdb->insert(
            $table_name,
            array(
                'case_id' => $case_id,
                'template_id' => $template_id,
                'subtotal' => $subtotal,
                'vat_amount' => $vat_amount,
                'total_amount' => $total_amount,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%f', '%f', '%s', '%s')
        );
        
        if ($result === false) {
            throw new Exception('Failed to save financial summary');
        }
    }
    
    /**
     * Load complete case financial data (items + totals)
     */
    public function ajax_load_case_financial_data() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cah_financial_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }
        
        $case_id = intval($_POST['case_id']);
        if (!$case_id) {
            wp_send_json_error('Invalid case ID');
            return;
        }
        
        try {
            // Load case items
            $items = $this->db_manager->get_cost_items_by_case($case_id);
            
            // Load case financial summary if exists
            $totals = $this->get_case_financial_totals($case_id);
            
            $response_data = array(
                'items' => $items,
                'totals' => $totals
            );
            
            wp_send_json_success($response_data);
        } catch (Exception $e) {
            wp_send_json_error('Error loading case financial data: ' . $e->getMessage());
        }
    }
    
    /**
     * Get saved financial totals for a case
     */
    private function get_case_financial_totals($case_id) {
        global $wpdb;
        
        $table_name = $this->db_manager->get_case_financial_table();
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT subtotal, vat_amount, total_amount FROM {$table_name} WHERE case_id = %d ORDER BY created_at DESC LIMIT 1",
            $case_id
        ), ARRAY_A);
        
        if ($result) {
            return array(
                'subtotal' => floatval($result['subtotal']),
                'vat_amount' => floatval($result['vat_amount']),
                'total_amount' => floatval($result['total_amount'])
            );
        }
        
        return null;
    }
    public function ajax_load_case_cost_items() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cah_financial_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }
        
        $case_id = intval($_POST['case_id']);
        if (!$case_id) {
            wp_send_json_error('Invalid case ID');
            return;
        }
        
        try {
            $items = $this->db_manager->get_cost_items_by_case($case_id);
            wp_send_json_success($items);
        } catch (Exception $e) {
            wp_send_json_error('Error loading case items: ' . $e->getMessage());
        }
    }
    
    /**
     * Create case-specific cost item
     */
    public function ajax_create_case_cost_item() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $case_id = intval($_POST['case_id']);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'basic_costs');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        $is_percentage = isset($_POST['is_percentage']) ? (bool)$_POST['is_percentage'] : false;
        
        if (!$case_id || empty($name)) {
            wp_send_json_error('Case ID and name are required');
            return;
        }
        
        try {
            $item_id = $this->db_manager->create_cost_item(
                null, // template_id
                $case_id,
                $name,
                $category,
                $amount,
                $description,
                $is_percentage,
                0 // sort_order
            );
            
            if ($item_id) {
                wp_send_json_success(array(
                    'item_id' => $item_id,
                    'message' => 'Cost item created successfully'
                ));
            } else {
                wp_send_json_error('Failed to create cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error creating cost item: ' . $e->getMessage());
        }
    }
    
    /**
     * Update case-specific cost item
     */
    public function ajax_update_case_cost_item() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'basic_costs');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        $is_percentage = isset($_POST['is_percentage']) ? (bool)$_POST['is_percentage'] : false;
        
        if (!$item_id || empty($name)) {
            wp_send_json_error('Item ID and name are required');
            return;
        }
        
        try {
            $result = $this->db_manager->update_cost_item($item_id, array(
                'name' => $name,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'is_percentage' => $is_percentage ? 1 : 0
            ));
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Cost item updated successfully'
                ));
            } else {
                wp_send_json_error('Failed to update cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error updating cost item: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete case-specific cost item
     */
    public function ajax_delete_case_cost_item() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $item_id = intval($_POST['item_id'] ?? 0);
        
        if (!$item_id) {
            wp_send_json_error('Item ID is required');
            return;
        }
        
        try {
            $result = $this->db_manager->delete_cost_item($item_id);
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Cost item deleted successfully'
                ));
            } else {
                wp_send_json_error('Failed to delete cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error deleting cost item: ' . $e->getMessage());
        }
    }
    
    // Case event handlers
    public function handle_case_created($case_id) {
        // Handle case creation if needed
    }
    
    public function handle_case_updated($case_id) {
        // Update the main case table with the latest financial total
        $this->update_case_total_amount($case_id);
    }
    
    /**
     * Update the total_amount in klage_cases table with actual financial data
     */
    private function update_case_total_amount($case_id) {
        global $wpdb;
        
        // Get the latest financial total for this case
        $financial_table = $this->db_manager->get_case_financial_table();
        $financial_data = $wpdb->get_row($wpdb->prepare(
            "SELECT total_amount FROM {$financial_table} WHERE case_id = %d ORDER BY created_at DESC LIMIT 1",
            $case_id
        ));
        
        if ($financial_data) {
            // Update the case table with actual financial total
            $result = $wpdb->update(
                $wpdb->prefix . 'klage_cases',
                array('total_amount' => $financial_data->total_amount),
                array('id' => $case_id),
                array('%f'),
                array('%d')
            );
            
            if ($result !== false) {
                error_log("Updated case {$case_id} total to {$financial_data->total_amount}");
            }
        } else {
            // No financial data exists, set to 0
            $wpdb->update(
                $wpdb->prefix . 'klage_cases',
                array('total_amount' => 0.00),
                array('id' => $case_id),
                array('%f'),
                array('%d')
            );
        }
    }
    
    /**
     * Recalculate all case totals (useful for fixing legacy data)
     */
    public function recalculate_all_case_totals() {
        global $wpdb;
        
        // Get all cases
        $cases = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}klage_cases");
        
        foreach ($cases as $case) {
            $this->update_case_total_amount($case->id);
        }
        
        return count($cases);
    }
    
    public function handle_case_deleted($case_id) {
        // Clean up financial data when case is deleted
        $this->db_manager->delete_case_financial($case_id);
    }
    
    // v1.5.0 - Enhanced CRUD AJAX Handlers
    
    public function ajax_create_template() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_text_field($_POST['description'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error('Template name is required');
            return;
        }
        
        try {
            $template_id = $this->db_manager->create_template($name, $description);
            if ($template_id) {
                $template = $this->db_manager->get_template($template_id);
                wp_send_json_success(array(
                    'template' => $template,
                    'message' => 'Template created successfully'
                ));
            } else {
                wp_send_json_error('Failed to create template');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error creating template: ' . $e->getMessage());
        }
    }
    
    public function ajax_update_template() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_text_field($_POST['description'] ?? '');
        
        if (!$template_id || empty($name)) {
            wp_send_json_error('Template ID and name are required');
            return;
        }
        
        try {
            $result = $this->db_manager->update_template($template_id, array(
                'name' => $name,
                'description' => $description
            ));
            
            if ($result !== false) {
                $template = $this->db_manager->get_template($template_id);
                wp_send_json_success(array(
                    'template' => $template,
                    'message' => 'Template updated successfully'
                ));
            } else {
                wp_send_json_error('Failed to update template');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error updating template: ' . $e->getMessage());
        }
    }
    
    public function ajax_delete_template() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if (!$template_id) {
            wp_send_json_error('Template ID is required');
            return;
        }
        
        try {
            $result = $this->db_manager->delete_template($template_id);
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Template deleted successfully'
                ));
            } else {
                wp_send_json_error('Failed to delete template');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error deleting template: ' . $e->getMessage());
        }
    }
    
    public function ajax_duplicate_template() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if (!$template_id) {
            wp_send_json_error('Template ID is required');
            return;
        }
        
        try {
            $original_template = $this->db_manager->get_template($template_id);
            if (!$original_template) {
                wp_send_json_error('Original template not found');
                return;
            }
            
            // Create duplicate with new name
            $new_name = $original_template->name . ' (Kopie)';
            $new_template_id = $this->db_manager->create_template($new_name, $original_template->description);
            
            if ($new_template_id) {
                // Copy cost items
                $original_items = $this->db_manager->get_cost_items_by_template($template_id);
                foreach ($original_items as $item) {
                    $this->db_manager->create_cost_item(
                        $new_template_id,
                        null,
                        $item->name,
                        $item->category,
                        $item->amount,
                        $item->description,
                        $item->is_percentage,
                        $item->sort_order
                    );
                }
                
                $new_template = $this->db_manager->get_template($new_template_id);
                wp_send_json_success(array(
                    'template' => $new_template,
                    'message' => 'Template duplicated successfully'
                ));
            } else {
                wp_send_json_error('Failed to duplicate template');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error duplicating template: ' . $e->getMessage());
        }
    }
    
    public function ajax_create_cost_item() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0) ?: null;
        $case_id = intval($_POST['case_id'] ?? 0) ?: null;
        $name = sanitize_text_field($_POST['name'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'basic_costs');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        $is_percentage = isset($_POST['is_percentage']) ? (bool)$_POST['is_percentage'] : false;
        
        if (empty($name)) {
            wp_send_json_error('Item name is required');
            return;
        }
        
        try {
            $item_id = $this->db_manager->create_cost_item(
                $template_id,
                $case_id,
                $name,
                $category,
                $amount,
                $description,
                $is_percentage,
                0
            );
            
            if ($item_id) {
                wp_send_json_success(array(
                    'item_id' => $item_id,
                    'message' => 'Cost item created successfully'
                ));
            } else {
                wp_send_json_error('Failed to create cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error creating cost item: ' . $e->getMessage());
        }
    }
    
    public function ajax_update_cost_item() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'basic_costs');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        $is_percentage = isset($_POST['is_percentage']) ? (bool)$_POST['is_percentage'] : false;
        
        if (!$item_id || empty($name)) {
            wp_send_json_error('Item ID and name are required');
            return;
        }
        
        try {
            $result = $this->db_manager->update_cost_item($item_id, array(
                'name' => $name,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'is_percentage' => $is_percentage ? 1 : 0
            ));
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Cost item updated successfully'
                ));
            } else {
                wp_send_json_error('Failed to update cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error updating cost item: ' . $e->getMessage());
        }
    }
    
    public function ajax_delete_cost_item() {
        global $wpdb;
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $item_id = intval($_POST['item_id'] ?? 0);
        
        if (!$item_id) {
            wp_send_json_error('Item ID is required');
            return;
        }
        
        try {
            // Get case_id before deletion
            $cost_item = $wpdb->get_row($wpdb->prepare(
                "SELECT case_id FROM {$wpdb->prefix}cah_cost_items WHERE id = %d",
                $item_id
            ));
            
            $result = $this->db_manager->delete_cost_item($item_id);
            if ($result !== false) {
                // Update case total after deletion
                if ($cost_item && $cost_item->case_id) {
                    $this->update_case_total_amount($cost_item->case_id);
                }
                
                wp_send_json_success(array(
                    'message' => 'Cost item deleted successfully'
                ));
            } else {
                wp_send_json_error('Failed to delete cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error deleting cost item: ' . $e->getMessage());
        }
    }
    
    public function ajax_bulk_cost_items_action() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $item_ids = array_map('intval', $_POST['item_ids'] ?? array());
        
        if (empty($action) || empty($item_ids)) {
            wp_send_json_error('Action and item IDs are required');
            return;
        }
        
        try {
            $results = array();
            
            switch ($action) {
                case 'delete':
                    foreach ($item_ids as $item_id) {
                        $result = $this->db_manager->delete_cost_item($item_id);
                        $results[] = $result !== false;
                    }
                    break;
                    
                case 'category':
                    $new_category = sanitize_text_field($_POST['new_category'] ?? 'basic_costs');
                    foreach ($item_ids as $item_id) {
                        $result = $this->db_manager->update_cost_item($item_id, array('category' => $new_category));
                        $results[] = $result !== false;
                    }
                    break;
                    
                default:
                    wp_send_json_error('Invalid bulk action');
                    return;
            }
            
            $success_count = count(array_filter($results));
            wp_send_json_success(array(
                'message' => "Bulk action completed: {$success_count} items processed"
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error performing bulk action: ' . $e->getMessage());
        }
    }
    
    public function ajax_export_financial_csv() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $case_id = intval($_POST['case_id'] ?? 0);
        $template_id = intval($_POST['template_id'] ?? 0);
        
        try {
            $items = array();
            if ($case_id) {
                $items = $this->db_manager->get_cost_items_by_case($case_id);
            } elseif ($template_id) {
                $items = $this->db_manager->get_cost_items_by_template($template_id);
            }
            
            $csv_data = array();
            $csv_data[] = array('Name', 'Kategorie', 'Betrag', 'Prozentual', 'Beschreibung');
            
            foreach ($items as $item) {
                $csv_data[] = array(
                    $item->name,
                    ucfirst($item->category),
                    $item->amount,
                    $item->is_percentage ? 'Ja' : 'Nein',
                    $item->description
                );
            }
            
            wp_send_json_success(array(
                'csv_data' => $csv_data,
                'filename' => 'financial_calculation_' . date('Y-m-d_H-i-s') . '.csv'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error exporting CSV: ' . $e->getMessage());
        }
    }
    
    // v1.5.1 - Independent Cost Item Management
    
    public function ajax_get_all_cost_items() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        try {
            $items = $this->db_manager->get_independent_cost_items();
            
            // Add template usage information
            foreach ($items as $item) {
                $templates = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT ft.name 
                     FROM {$this->wpdb->prefix}cah_template_cost_items tci
                     JOIN {$this->wpdb->prefix}cah_financial_templates ft ON tci.template_id = ft.id
                     WHERE tci.cost_item_id = %d",
                    $item->id
                ));
                $item->used_in_templates = array_column($templates, 'name');
            }
            
            wp_send_json_success($items);
        } catch (Exception $e) {
            wp_send_json_error('Error loading cost items: ' . $e->getMessage());
        }
    }
    
    public function ajax_create_independent_cost_item() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'basic_costs');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        $is_percentage = isset($_POST['is_percentage']) ? (bool)$_POST['is_percentage'] : false;
        
        if (empty($name)) {
            wp_send_json_error('Item name is required');
            return;
        }
        
        // Get category ID
        $category_row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}cah_cost_categories WHERE slug = %s",
            $category
        ));
        $category_id = $category_row ? $category_row->id : null;
        
        try {
            $item_id = $this->db_manager->create_independent_cost_item(
                $name,
                $category_id,
                $amount,
                $description,
                $is_percentage,
                0
            );
            
            if ($item_id) {
                wp_send_json_success(array(
                    'item_id' => $item_id,
                    'message' => 'Independent cost item created successfully'
                ));
            } else {
                wp_send_json_error('Failed to create independent cost item');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error creating independent cost item: ' . $e->getMessage());
        }
    }
    
    public function ajax_assign_item_to_template() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if (!$template_id || !$item_id) {
            wp_send_json_error('Template ID and Item ID are required');
            return;
        }
        
        try {
            $result = $this->db_manager->assign_cost_item_to_template($template_id, $item_id, $sort_order);
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Cost item assigned to template successfully'
                ));
            } else {
                wp_send_json_error('Failed to assign cost item to template');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error assigning cost item: ' . $e->getMessage());
        }
    }
    
    public function ajax_remove_item_from_template() {
        check_ajax_referer('cah_financial_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        
        if (!$template_id || !$item_id) {
            wp_send_json_error('Template ID and Item ID are required');
            return;
        }
        
        try {
            $result = $this->db_manager->remove_cost_item_from_template($template_id, $item_id);
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Cost item removed from template successfully'
                ));
            } else {
                wp_send_json_error('Failed to remove cost item from template');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error removing cost item: ' . $e->getMessage());
        }
    }
}