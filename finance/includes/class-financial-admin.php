<?php
/**
 * Financial Calculator Admin Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Financial_Admin {
    
    private $db_manager;
    private $calculator;
    private $template_manager;
    
    public function __construct() {
        $this->db_manager = new CAH_Financial_DB_Manager();
        $this->calculator = new CAH_Financial_Calculator_Engine();
        $this->template_manager = new CAH_Financial_Template_Manager();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }
    
    public function add_admin_menu() {
        // Get WordPress menu structure to find the parent menu
        global $menu, $submenu;
        
        $parent_slug = null;
        
        // Look for the core plugin's menu slug
        if (isset($submenu['klage-click-hub'])) {
            $parent_slug = 'klage-click-hub';
        } else {
            // Try to find any menu containing 'klage' or create our own
            foreach ($menu as $item) {
                if (isset($item[2]) && (strpos(strtolower($item[2]), 'klage') !== false || strpos(strtolower($item[0]), 'klage') !== false)) {
                    $parent_slug = $item[2];
                    break;
                }
            }
        }
        
        // If no parent found, create our own top-level menu
        if (!$parent_slug) {
            add_menu_page(
                __('Financial Calculator', 'court-automation-hub-financial'),
                __('🧮 Finanzrechner', 'court-automation-hub-financial'),
                'manage_options',
                'cah-financial-main',
                array($this, 'calculator_page'),
                'dashicons-calculator',
                31
            );
            $parent_slug = 'cah-financial-main';
        }
        
        // Add financial calculator to the parent menu
        add_submenu_page(
            $parent_slug,
            __('Finanz-Vorlagen', 'court-automation-hub-financial'),
            __('Finanz-Vorlagen', 'court-automation-hub-financial'),
            'manage_options',
            'cah-financial-templates',
            array($this, 'templates_page')
        );
        
        add_submenu_page(
            $parent_slug,
            __('Kostenkategorien', 'court-automation-hub-financial'),
            __('Kostenkategorien', 'court-automation-hub-financial'),
            'manage_options',
            'cah-cost-categories',
            array($this, 'cost_categories_page')
        );
        
        add_submenu_page(
            $parent_slug,
            __('Kosten Items', 'court-automation-hub-financial'),
            __('Kosten Items', 'court-automation-hub-financial'),
            'manage_options',
            'cah-cost-items',
            array($this, 'cost_items_page')
        );
        
        add_submenu_page(
            $parent_slug,
            __('Unabhängige Kostenpunkte', 'court-automation-hub-financial'),
            __('Unabhängige Kostenpunkte', 'court-automation-hub-financial'),
            'manage_options',
            'cah-independent-cost-items',
            array($this, 'independent_cost_items_page')
        );
        
        add_submenu_page(
            $parent_slug,
            __('Finanzrechner', 'court-automation-hub-financial'),
            __('🧮 Finanzrechner', 'court-automation-hub-financial'),
            'manage_options',
            'cah-financial-calculator',
            array($this, 'calculator_page')
        );
    }
    
    public function handle_admin_actions() {
        if (!isset($_POST['financial_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['financial_action']);
        
        switch ($action) {
            case 'create_template':
                $this->handle_create_template();
                break;
            case 'update_template':
                $this->handle_update_template();
                break;
            case 'delete_template':
                $this->handle_delete_template();
                break;
            case 'create_cost_category':
                $this->handle_create_cost_category();
                break;
            case 'update_cost_category':
                $this->handle_update_cost_category();
                break;
            case 'delete_cost_category':
                $this->handle_delete_cost_category();
                break;
            case 'create_cost_item':
                $this->handle_create_cost_item();
                break;
            case 'update_cost_item':
                $this->handle_update_cost_item();
                break;
            case 'delete_cost_item':
                $this->handle_delete_cost_item();
                break;
            case 'assign_items_to_template':
                $this->handle_assign_items_to_template();
                break;
            case 'add_independent_items_to_template':
                $this->handle_add_independent_items_to_template();
                break;
            case 'create_independent_cost_item':
                $this->handle_create_independent_cost_item();
                break;
            case 'update_independent_cost_item':
                $this->handle_update_independent_cost_item();
                break;
            case 'delete_independent_cost_item':
                $this->handle_delete_independent_cost_item();
                break;
        }
    }
    
    public function templates_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_add_template_form();
                break;
            case 'edit':
                $this->render_edit_template_form($template_id);
                break;
            case 'view':
                $this->render_view_template($template_id);
                break;
            case 'copy':
                $this->handle_copy_template($template_id);
                break;
            default:
                $this->render_templates_list();
                break;
        }
    }
    
    public function cost_categories_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_add_cost_category_form();
                break;
            case 'edit':
                $this->render_edit_cost_category_form($category_id);
                break;
            default:
                $this->render_cost_categories_list();
                break;
        }
    }
    
    public function cost_items_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_add_cost_item_form($template_id);
                break;
            case 'edit':
                $this->render_edit_cost_item_form($item_id);
                break;
            default:
                $this->render_cost_items_list($template_id);
                break;
        }
    }
    
    public function calculator_page() {
        ?>
        <div class="wrap">
            <h1>🧮 Finanzrechner</h1>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>💰 Financial Calculator v1.6.3</strong></p>
                <p>Verwenden Sie den Finanzrechner direkt in der Fall-Bearbeitung oder verwalten Sie Ihre Vorlagen hier.</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="color: #0073aa;">📋 Vorlagen verwalten</h3>
                    <p>Erstellen und bearbeiten Sie Finanz-Vorlagen für verschiedene Fall-Typen</p>
                    <a href="<?php echo admin_url('admin.php?page=cah-financial-templates'); ?>" class="button button-primary">Vorlagen öffnen</a>
                </div>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="color: #0073aa;">💰 Kostenpunkte</h3>
                    <p>Verwalten Sie einzelne Kostenpunkte und deren Kategorien</p>
                    <a href="<?php echo admin_url('admin.php?page=cah-cost-items'); ?>" class="button button-primary">Kosten verwalten</a>
                </div>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                    <h3 style="color: #0073aa;">📝 Fall-Integration</h3>
                    <p>Nutzen Sie den Finanzrechner direkt beim Erstellen oder Bearbeiten von Fällen</p>
                    <a href="<?php echo admin_url('admin.php?page=klage-click-cases'); ?>" class="button button-primary">Fälle öffnen</a>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle">📊 Übersicht</h2>
                <div class="inside" style="padding: 20px;">
                    <?php $this->render_calculator_dashboard(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_calculator_dashboard() {
        $templates = $this->db_manager->get_templates();
        $total_templates = count($templates);
        $default_templates = count(array_filter($templates, function($t) { return $t->is_default; }));
        
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #0073aa; font-size: 24px;"><?php echo $total_templates; ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;">Gesamt Vorlagen</p>
            </div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #ff9800; font-size: 24px;"><?php echo $default_templates; ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;">Standard Vorlagen</p>
            </div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; text-align: center;">
                <h3 style="margin: 0; color: #4caf50; font-size: 24px;"><?php echo ($total_templates - $default_templates); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;">Benutzerdefiniert</p>
            </div>
        </div>
        
        <?php if (!empty($templates)): ?>
        <h4 style="margin-top: 25px;">Zuletzt verwendete Vorlagen</h4>
        <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>Vorlage</th>
                    <th>Typ</th>
                    <th>Erstellt</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($templates, 0, 5) as $template): ?>
                <tr>
                    <td><strong><?php echo esc_html($template->name); ?></strong></td>
                    <td>
                        <?php if ($template->is_default): ?>
                            <span style="background: #4caf50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Standard</span>
                        <?php else: ?>
                            <span style="background: #2196f3; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Benutzer</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date_i18n('d.m.Y', strtotime($template->created_at)); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=view&id=' . $template->id); ?>" class="button button-small">Ansehen</a>
                        <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=edit&id=' . $template->id); ?>" class="button button-small">Bearbeiten</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }
    
    private function render_templates_list() {
        $templates = $this->db_manager->get_templates();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Finanz-Vorlagen</h1>
            <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=add'); ?>" class="page-title-action">Neue Vorlage hinzufügen</a>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Typ</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <p>Keine Vorlagen gefunden.</p>
                            <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=add'); ?>" class="button button-primary">Erste Vorlage erstellen</a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><strong><?php echo esc_html($template->name); ?></strong></td>
                            <td><?php echo esc_html($template->description); ?></td>
                            <td>
                                <?php if ($template->is_default): ?>
                                    <span style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Standard</span>
                                <?php else: ?>
                                    <span style="background: #2196f3; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Benutzerdefiniert</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date_i18n('d.m.Y H:i', strtotime($template->created_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=view&id=' . $template->id); ?>" class="button button-small" title="Ansehen">👁️</a>
                                <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=edit&id=' . $template->id); ?>" class="button button-small" title="Bearbeiten">✏️</a>
                                <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=copy&id=' . $template->id); ?>" class="button button-small" title="Vorlage kopieren" onclick="return confirm('Möchten Sie diese Vorlage kopieren?')">📋</a>
                                <a href="<?php echo admin_url('admin.php?page=cah-cost-items&template_id=' . $template->id); ?>" class="button button-small" title="Kostenelemente">💰</a>
                                <?php if (!$template->is_default): ?>
                                <a href="#" onclick="confirmDelete(<?php echo $template->id; ?>)" class="button button-small button-link-delete" title="Löschen">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        function confirmDelete(templateId) {
            if (confirm('Sind Sie sicher, dass Sie diese Vorlage löschen möchten?')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="financial_action" value="delete_template">' +
                               '<input type="hidden" name="template_id" value="' + templateId + '">' +
                               '<?php wp_nonce_field('financial_action', 'financial_nonce'); ?>';
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php
    }
    
    private function render_edit_template_form($template_id) {
        $template = $this->db_manager->get_template($template_id);
        
        if (!$template) {
            echo '<div class="wrap"><h1>Vorlage nicht gefunden</h1><p>Die angeforderte Vorlage konnte nicht gefunden werden.</p></div>';
            return;
        }
        
        // Get all cost categories and independent cost items
        $categories = $this->db_manager->get_cost_categories();
        $template_items = $this->db_manager->get_cost_items_by_template($template_id);
        $independent_items = $this->db_manager->get_independent_cost_items();
        
        ?>
        <div class="wrap">
            <h1>Finanz-Vorlage bearbeiten: <?php echo esc_html($template->name); ?></h1>
            
            <!-- Template Basic Information -->
            <div class="postbox">
                <h2 class="hndle">📋 Grunddaten der Vorlage</h2>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                        <input type="hidden" name="financial_action" value="update_template">
                        <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="template_name">Name</label></th>
                                <td><input type="text" id="template_name" name="template_name" class="regular-text" value="<?php echo esc_attr($template->name); ?>" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="template_description">Beschreibung</label></th>
                                <td><textarea id="template_description" name="template_description" class="large-text" rows="4"><?php echo esc_textarea($template->description); ?></textarea></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Vorlage aktualisieren">
                            <a href="<?php echo admin_url('admin.php?page=cah-financial-templates'); ?>" class="button button-secondary">Abbrechen</a>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Template Cost Items Management -->
            <div class="postbox">
                <h2 class="hndle">💰 Kostenpunkte verwalten</h2>
                <div class="inside">
                    
                    <!-- Current Template Items -->
                    <h3>Aktuelle Kostenpunkte (<?php echo count($template_items); ?>)</h3>
                    
                    <?php if (!empty($template_items)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Kategorie</th>
                                <th>Betrag</th>
                                <th>Beschreibung</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = 0;
                            foreach ($template_items as $item): 
                                $total += $item->amount;
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($item->name); ?></strong></td>
                                <td>
                                    <span class="category-badge category-<?php echo esc_attr($item->category); ?>">
                                        <?php echo esc_html($this->calculator->get_category_names()[$item->category] ?? $item->category); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $this->calculator->format_currency($item->amount); ?></strong></td>
                                <td><?php echo esc_html($item->description ?: '-'); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cah-cost-items&action=edit&id=' . $item->id); ?>" class="button button-small">✏️</a>
                                    <a href="#" onclick="confirmRemoveFromTemplate(<?php echo $item->id; ?>)" class="button button-small button-link-delete">❌</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f0f8ff;">
                                <th colspan="2"><strong>Zwischensumme:</strong></th>
                                <th><strong><?php echo $this->calculator->format_currency($total); ?></strong></th>
                                <th colspan="2"><em>zzgl. 19% MwSt. = <?php echo $this->calculator->format_currency($total * 1.19); ?></em></th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 5px; margin: 10px 0;">
                        <p>Diese Vorlage hat noch keine Kostenpunkte.</p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add New Cost Item Section -->
                    <div style="margin-top: 30px; background: #fff3cd; padding: 20px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <h4 style="margin-top: 0;">✨ Neuen Kostenpunkt hinzufügen</h4>
                        
                        <form method="post" style="margin-bottom: 20px;">
                            <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                            <input type="hidden" name="financial_action" value="create_cost_item">
                            <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">
                            
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end;">
                                <div>
                                    <label for="new_item_name" style="display: block; margin-bottom: 5px;">Name:</label>
                                    <input type="text" id="new_item_name" name="item_name" class="regular-text" required>
                                </div>
                                
                                <div>
                                    <label for="new_item_category" style="display: block; margin-bottom: 5px;">Kategorie:</label>
                                    <select id="new_item_category" name="item_category_id" class="regular-text" required>
                                        <option value="">Wählen...</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="new_item_amount" style="display: block; margin-bottom: 5px;">Betrag (€):</label>
                                    <input type="number" id="new_item_amount" name="item_amount" class="regular-text" step="0.01" min="0" required>
                                </div>
                                
                                <div>
                                    <input type="submit" class="button button-primary" value="+ Hinzufügen">
                                </div>
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <label for="new_item_description" style="display: block; margin-bottom: 5px;">Beschreibung (optional):</label>
                                <input type="text" id="new_item_description" name="item_description" class="regular-text" style="width: 100%;">
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox" name="is_percentage" value="1"> Als Prozentsatz behandeln
                                </label>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Add Existing Items Section -->
                    <?php if (!empty($independent_items)): ?>
                    <div style="margin-top: 20px; background: #e7f3ff; padding: 20px; border-radius: 5px; border-left: 4px solid #0073aa;">
                        <h4 style="margin-top: 0;">📎 Vorhandene Kostenpunkte hinzufügen</h4>
                        
                        <form method="post">
                            <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                            <input type="hidden" name="financial_action" value="add_independent_items_to_template">
                            <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">
                            
                            <p>Wählen Sie vorhandene unabhängige Kostenpunkte aus, die zu dieser Vorlage hinzugefügt werden sollen:</p>
                            
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: white; border-radius: 4px;">
                                <?php foreach ($independent_items as $item): ?>
                                <?php
                                // Check if this item is already in the template
                                $is_already_added = false;
                                foreach ($template_items as $template_item) {
                                    if ($template_item->name === $item->name && $template_item->amount == $item->amount) {
                                        $is_already_added = true;
                                        break;
                                    }
                                }
                                ?>
                                <div style="margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center;">
                                        <input type="checkbox" name="selected_items[]" value="<?php echo esc_attr($item->id); ?>" 
                                               <?php echo $is_already_added ? 'disabled title="Bereits in Vorlage"' : ''; ?>
                                               style="margin-right: 8px;">
                                        <span style="flex: 1;">
                                            <strong><?php echo esc_html($item->name); ?></strong> 
                                            - <?php echo $this->calculator->format_currency($item->amount); ?>
                                            <small style="color: #666;">(<?php echo esc_html($this->calculator->get_category_names()[$item->category] ?? $item->category); ?>)</small>
                                            <?php if ($is_already_added): ?>
                                            <em style="color: #999;"> - Bereits hinzugefügt</em>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <p style="margin-top: 15px;">
                                <input type="submit" class="button button-secondary" value="📎 Ausgewählte Kostenpunkte hinzufügen">
                            </p>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            
        </div>
        
        <style>
        .category-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .category-basic-costs { background: #4caf50; }
        .category-court-costs { background: #ff9800; }
        .category-legal-costs { background: #2196f3; }
        .category-other-costs { background: #9c27b0; }
        </style>
        
        <script>
        function confirmRemoveFromTemplate(itemId) {
            if (confirm('Möchten Sie diesen Kostenpunkt aus der Vorlage entfernen?')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="financial_action" value="delete_cost_item">' +
                               '<input type="hidden" name="item_id" value="' + itemId + '">' +
                               '<?php wp_nonce_field('financial_action', 'financial_nonce'); ?>';
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php
    }
    
    private function render_view_template($template_id) {
        $template = $this->template_manager->get_template_with_totals($template_id);
        
        if (!$template) {
            echo '<div class="wrap"><h1>Vorlage nicht gefunden</h1><p>Die angeforderte Vorlage konnte nicht gefunden werden.</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Finanz-Vorlage: <?php echo esc_html($template->name); ?></h1>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <h3 style="margin: 0 0 5px 0;">📋 <?php echo esc_html($template->name); ?></h3>
                <p style="margin: 0;"><?php echo esc_html($template->description); ?></p>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                    Typ: <?php echo $template->is_default ? '<strong>Standard</strong>' : 'Benutzerdefiniert'; ?> | 
                    Erstellt: <?php echo date_i18n('d.m.Y H:i', strtotime($template->created_at)); ?>
                </p>
            </div>
            
            <h2>Kostenpunkte (<?php echo count($template->cost_items); ?>)</h2>
            
            <?php if (!empty($template->cost_items)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Kategorie</th>
                        <th>Betrag</th>
                        <th>Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($template->cost_items as $item): ?>
                    <tr>
                        <td><strong><?php echo esc_html($item->name); ?></strong></td>
                        <td>
                            <span class="category-badge category-<?php echo esc_attr($item->category); ?>">
                                <?php echo esc_html($this->calculator->get_category_names()[$item->category] ?? $item->category); ?>
                            </span>
                        </td>
                        <td><strong><?php echo $this->calculator->format_currency($item->amount); ?></strong></td>
                        <td><?php echo esc_html($item->description ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f0f8ff;">
                        <th colspan="2"><strong>Zwischensumme:</strong></th>
                        <th><strong><?php echo $this->calculator->format_currency($template->totals['subtotal']); ?></strong></th>
                        <th><em>zzgl. 19% MwSt. = <?php echo $this->calculator->format_currency($template->totals['total_amount']); ?></em></th>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 5px;">
                <p style="font-size: 16px; color: #666;">Diese Vorlage hat noch keine Kostenpunkte.</p>
                <a href="<?php echo admin_url('admin.php?page=cah-cost-items&action=add&template_id=' . $template_id); ?>" class="button button-primary">Ersten Kostenpunkt hinzufügen</a>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <a href="<?php echo admin_url('admin.php?page=cah-financial-templates'); ?>" class="button button-secondary">← Zurück zur Übersicht</a>
                <?php if (!$template->is_default): ?>
                <a href="<?php echo admin_url('admin.php?page=cah-financial-templates&action=edit&id=' . $template_id); ?>" class="button button-primary" style="margin-left: 10px;">Vorlage bearbeiten</a>
                <?php endif; ?>
                <a href="<?php echo admin_url('admin.php?page=cah-cost-items&template_id=' . $template_id); ?>" class="button button-primary" style="margin-left: 10px;">Kostenpunkte verwalten</a>
            </div>
        </div>
        
        <style>
        .category-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .category-basic-costs { background: #4caf50; }
        .category-court-costs { background: #ff9800; }
        .category-legal-costs { background: #2196f3; }
        .category-other-costs { background: #9c27b0; }
        </style>
        <?php
    }
    
    private function render_cost_categories_list() {
        // Get categories from database
        $categories = $this->db_manager->get_cost_categories();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Kostenkategorien</h1>
            <a href="<?php echo admin_url('admin.php?page=cah-cost-categories&action=add'); ?>" class="page-title-action">Neue Kategorie hinzufügen</a>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>💡 Kostenkategorien</strong> helfen dabei, Kostenpunkte zu organisieren und in Vorlagen übersichtlich darzustellen.</p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40px;">Farbe</th>
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Slug</th>
                        <th>Typ</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <p>Keine Kategorien gefunden.</p>
                            <a href="<?php echo admin_url('admin.php?page=cah-cost-categories&action=add'); ?>" class="button button-primary">Erste Kategorie erstellen</a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <div style="width: 30px; height: 20px; background: <?php echo esc_attr($category->color); ?>; border-radius: 4px; border: 1px solid #ddd;"></div>
                            </td>
                            <td><strong><?php echo esc_html($category->name); ?></strong></td>
                            <td><?php echo esc_html($category->description ?: '-'); ?></td>
                            <td><code><?php echo esc_html($category->slug); ?></code></td>
                            <td>
                                <?php if ($category->is_default): ?>
                                    <span style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Standard</span>
                                <?php else: ?>
                                    <span style="background: #2196f3; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Benutzerdefiniert</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$category->is_default): ?>
                                <a href="<?php echo admin_url('admin.php?page=cah-cost-categories&action=edit&id=' . $category->id); ?>" class="button button-small">✏️</a>
                                <a href="#" onclick="confirmDeleteCategory(<?php echo $category->id; ?>)" class="button button-small button-link-delete">🗑️</a>
                                <?php else: ?>
                                <span style="color: #666; font-size: 12px;">Standard-Kategorie</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px; background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 10px 0;">📋 Kategorien verwenden</h3>
                <p style="margin: 0;">Nach dem Erstellen von Kategorien können Sie diese bei <strong>Kostenpunkten</strong> zuweisen und in <strong>Finanz-Vorlagen</strong> organisiert anzeigen lassen.</p>
                <div style="margin-top: 15px;">
                    <a href="<?php echo admin_url('admin.php?page=cah-cost-items'); ?>" class="button button-primary">💰 Kostenpunkte verwalten</a>
                    <a href="<?php echo admin_url('admin.php?page=cah-financial-templates'); ?>" class="button button-secondary" style="margin-left: 10px;">📋 Vorlagen verwalten</a>
                </div>
            </div>
        </div>
        
        <script>
        function confirmDeleteCategory(categoryId) {
            if (confirm('Sind Sie sicher, dass Sie diese Kategorie löschen möchten?\\n\\nAlle zugehörigen Kostenpunkte werden der Standard-Kategorie zugeordnet.')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="financial_action" value="delete_cost_category">' +
                               '<input type="hidden" name="category_id" value="' + categoryId + '">' +
                               '<?php wp_nonce_field('financial_action', 'financial_nonce'); ?>';
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php
    }
    
    private function render_add_cost_category_form() {
        ?>
        <div class="wrap">
            <h1>Neue Kostenkategorie erstellen</h1>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="create_cost_category">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="category_name">Name</label></th>
                        <td>
                            <input type="text" id="category_name" name="category_name" class="regular-text" required>
                            <p class="description">Anzeigename der Kategorie</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_slug">Slug</label></th>
                        <td>
                            <input type="text" id="category_slug" name="category_slug" class="regular-text" pattern="[a-z0-9_-]+" required>
                            <p class="description">Eindeutige Bezeichnung (nur Kleinbuchstaben, Zahlen, _ und -)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_description">Beschreibung</label></th>
                        <td>
                            <textarea id="category_description" name="category_description" class="large-text" rows="4"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_color">Farbe</label></th>
                        <td>
                            <input type="color" id="category_color" name="category_color" value="#2196f3">
                            <p class="description">Farbe für die Darstellung in Listen</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order">Reihenfolge</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" class="regular-text" min="0" value="0">
                            <p class="description">Reihenfolge in der Anzeige (0 = erste Position)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Kategorie erstellen">
                    <a href="<?php echo admin_url('admin.php?page=cah-cost-categories'); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_edit_cost_category_form($category_id) {
        $category = $this->db_manager->get_cost_category($category_id);
        
        if (!$category) {
            echo '<div class="wrap"><h1>Kategorie nicht gefunden</h1><p>Die angeforderte Kategorie konnte nicht gefunden werden.</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Kostenkategorie bearbeiten</h1>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="update_cost_category">
                <input type="hidden" name="category_id" value="<?php echo esc_attr($category_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="category_name">Name</label></th>
                        <td>
                            <input type="text" id="category_name" name="category_name" class="regular-text" value="<?php echo esc_attr($category->name); ?>" required>
                            <p class="description">Anzeigename der Kategorie</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_slug">Slug</label></th>
                        <td>
                            <input type="text" id="category_slug" name="category_slug" class="regular-text" pattern="[a-z0-9_-]+" value="<?php echo esc_attr($category->slug); ?>" required>
                            <p class="description">Eindeutige Bezeichnung (nur Kleinbuchstaben, Zahlen, _ und -)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_description">Beschreibung</label></th>
                        <td>
                            <textarea id="category_description" name="category_description" class="large-text" rows="4"><?php echo esc_textarea($category->description); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_color">Farbe</label></th>
                        <td>
                            <input type="color" id="category_color" name="category_color" value="<?php echo esc_attr($category->color); ?>">
                            <p class="description">Farbe für die Darstellung in Listen</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order">Reihenfolge</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" class="regular-text" min="0" value="<?php echo esc_attr($category->sort_order); ?>">
                            <p class="description">Reihenfolge in der Anzeige (0 = erste Position)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Kategorie aktualisieren">
                    <a href="<?php echo admin_url('admin.php?page=cah-cost-categories'); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_add_template_form() {
        ?>
        <div class="wrap">
            <h1>Neue Finanz-Vorlage erstellen</h1>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="create_template">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="template_name">Name</label></th>
                        <td><input type="text" id="template_name" name="template_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="template_description">Beschreibung</label></th>
                        <td><textarea id="template_description" name="template_description" class="large-text" rows="4"></textarea></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Vorlage erstellen">
                    <a href="<?php echo admin_url('admin.php?page=cah-financial-templates'); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_add_cost_item_form($template_id = 0) {
        $templates = $this->db_manager->get_templates();
        $categories = $this->db_manager->get_cost_categories();
        $selected_template = null;
        
        if ($template_id) {
            $selected_template = $this->db_manager->get_template($template_id);
        }
        
        ?>
        <div class="wrap">
            <h1>Neuen Kostenpunkt hinzufügen</h1>
            
            <?php if ($selected_template): ?>
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <h3 style="margin: 0 0 5px 0;">📋 <?php echo esc_html($selected_template->name); ?></h3>
                <p style="margin: 0;">Kostenpunkt für: <?php echo esc_html($selected_template->description); ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="create_cost_item">
                <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="item_name">Name</label></th>
                        <td>
                            <input type="text" id="item_name" name="item_name" class="regular-text" required>
                            <p class="description">Name des Kostenpunkts</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_category_id">Kategorie</label></th>
                        <td>
                            <select id="item_category_id" name="item_category_id" class="regular-text" required>
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_amount">Betrag (€)</label></th>
                        <td>
                            <input type="number" id="item_amount" name="item_amount" class="regular-text" step="0.01" min="0" required>
                            <p class="description">Betrag in Euro</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_description">Beschreibung</label></th>
                        <td>
                            <textarea id="item_description" name="item_description" class="large-text" rows="4"></textarea>
                            <p class="description">Optional: Zusätzliche Beschreibung</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_percentage">Prozentual</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_percentage" name="is_percentage" value="1">
                                Betrag als Prozentsatz behandeln
                            </label>
                            <p class="description">Bei aktivierter Option wird der Betrag als Prozentsatz der Zwischensumme berechnet</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_independent">Unabhängiger Kostenpunkt</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_independent" name="is_independent" value="1" <?php echo $template_id ? '' : 'checked'; ?>>
                                Als unabhängigen Kostenpunkt erstellen (kann später Vorlagen zugewiesen werden)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order">Reihenfolge</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" class="regular-text" min="0" value="0">
                            <p class="description">Reihenfolge in der Anzeige (0 = erste Position)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Kostenpunkt erstellen">
                    <a href="<?php echo admin_url('admin.php?page=cah-cost-items' . ($template_id ? '&template_id=' . $template_id : '')); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_edit_cost_item_form($item_id) {
        // Get cost item data
        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cah_cost_items WHERE id = %d",
            $item_id
        ));
        
        if (!$item) {
            echo '<div class="wrap"><h1>Kostenpunkt nicht gefunden</h1><p>Der angeforderte Kostenpunkt konnte nicht gefunden werden.</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Kostenpunkt bearbeiten</h1>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="update_cost_item">
                <input type="hidden" name="item_id" value="<?php echo esc_attr($item_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="item_name">Name</label></th>
                        <td>
                            <input type="text" id="item_name" name="item_name" class="regular-text" value="<?php echo esc_attr($item->name); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_category">Kategorie</label></th>
                        <td>
                            <select id="item_category" name="item_category" class="regular-text" required>
                                <option value="basic_costs" <?php selected($item->category, 'basic_costs'); ?>>Grundkosten</option>
                                <option value="court_costs" <?php selected($item->category, 'court_costs'); ?>>Gerichtskosten</option>
                                <option value="legal_costs" <?php selected($item->category, 'legal_costs'); ?>>Anwaltskosten</option>
                                <option value="other_costs" <?php selected($item->category, 'other_costs'); ?>>Sonstige Kosten</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_amount">Betrag (€)</label></th>
                        <td>
                            <input type="number" id="item_amount" name="item_amount" class="regular-text" step="0.01" min="0" value="<?php echo esc_attr($item->amount); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_description">Beschreibung</label></th>
                        <td>
                            <textarea id="item_description" name="item_description" class="large-text" rows="4"><?php echo esc_textarea($item->description); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order">Reihenfolge</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" class="regular-text" min="0" value="<?php echo esc_attr($item->sort_order); ?>">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Kostenpunkt aktualisieren">
                    <a href="<?php echo admin_url('admin.php?page=cah-cost-items' . ($item->template_id ? '&template_id=' . $item->template_id : '')); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_cost_items_list($template_id = 0) {
        $templates = $this->db_manager->get_templates();
        $selected_template = null;
        $cost_items = array();
        
        if ($template_id) {
            $selected_template = $this->db_manager->get_template($template_id);
            $cost_items = $this->db_manager->get_cost_items_by_template($template_id);
        }
        
        ?>
        <div class="wrap">
            <h1>Kostenpunkte verwalten</h1>
            
            <!-- Template Selection -->
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <form method="get" style="display: flex; gap: 15px; align-items: end;">
                    <input type="hidden" name="page" value="cah-cost-items">
                    
                    <div>
                        <label for="template_id" style="display: block; margin-bottom: 5px; font-weight: bold;">Vorlage auswählen:</label>
                        <select name="template_id" id="template_id" style="width: 300px;">
                            <option value="">Bitte wählen...</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template->id; ?>" <?php selected($template_id, $template->id); ?>>
                                <?php echo esc_html($template->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <input type="submit" class="button" value="Vorlage laden">
                    </div>
                </form>
            </div>
            
            <?php if ($selected_template): ?>
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <h3 style="margin: 0 0 5px 0;">📋 <?php echo esc_html($selected_template->name); ?></h3>
                <p style="margin: 0;"><?php echo esc_html($selected_template->description); ?></p>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Kostenpunkte</h2>
                <a href="<?php echo admin_url('admin.php?page=cah-cost-items&action=add&template_id=' . $template_id); ?>" class="button button-primary">+ Neuen Kostenpunkt hinzufügen</a>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Kategorie</th>
                        <th>Betrag</th>
                        <th>Beschreibung</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cost_items)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <p>Keine Kostenpunkte für diese Vorlage gefunden.</p>
                            <a href="<?php echo admin_url('admin.php?page=cah-cost-items&action=add&template_id=' . $template_id); ?>" class="button button-primary">Ersten Kostenpunkt hinzufügen</a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $total = 0;
                        foreach ($cost_items as $item): 
                            $total += $item->amount;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($item->name); ?></strong></td>
                            <td>
                                <span class="category-badge category-<?php echo esc_attr($item->category); ?>">
                                    <?php echo esc_html($this->calculator->get_category_names()[$item->category] ?? $item->category); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $this->calculator->format_currency($item->amount); ?></strong></td>
                            <td><?php echo esc_html($item->description ?: '-'); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=cah-cost-items&action=edit&id=' . $item->id); ?>" class="button button-small">✏️</a>
                                <a href="#" onclick="confirmDeleteItem(<?php echo $item->id; ?>)" class="button button-small button-link-delete">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f0f8ff;">
                        <th colspan="2"><strong>Zwischensumme:</strong></th>
                        <th><strong><?php echo $this->calculator->format_currency($total); ?></strong></th>
                        <th colspan="2"><em>zzgl. 19% MwSt. = <?php echo $this->calculator->format_currency($total * 1.19); ?></em></th>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 60px; background: #f9f9f9; border-radius: 5px;">
                <p style="font-size: 18px; color: #666;">Wählen Sie eine Vorlage aus, um deren Kostenpunkte zu verwalten.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .category-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .category-basic-costs { background: #4caf50; }
        .category-court-costs { background: #ff9800; }
        .category-legal-costs { background: #2196f3; }
        .category-other-costs { background: #9c27b0; }
        </style>
        
        <script>
        function confirmDeleteItem(itemId) {
            if (confirm('Sind Sie sicher, dass Sie diesen Kostenpunkt löschen möchten?')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="financial_action" value="delete_cost_item">' +
                               '<input type="hidden" name="item_id" value="' + itemId + '">' +
                               '<?php wp_nonce_field('financial_action', 'financial_nonce'); ?>';
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php
    }
    
    // Handler methods - CLEAN VERSION WITHOUT DUPLICATES
    private function handle_create_template() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $name = sanitize_text_field($_POST['template_name']);
        $description = sanitize_textarea_field($_POST['template_description']);
        
        if (empty($name)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Template-Name ist erforderlich.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->create_template($name, $description, false);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Vorlage erfolgreich erstellt.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-financial-templates'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Erstellen der Vorlage.</p></div>';
            });
        }
    }
    
    private function handle_create_cost_item() {
        // Add debug logging
        error_log("DEBUG: handle_create_cost_item called with POST data: " . print_r($_POST, true));
        
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $template_id = intval($_POST['template_id']) ?: null;
        $name = sanitize_text_field($_POST['item_name']);
        $category_id = intval($_POST['item_category_id']);
        $amount = floatval($_POST['item_amount']);
        $description = sanitize_textarea_field($_POST['item_description']);
        $is_percentage = isset($_POST['is_percentage']) ? 1 : 0;
        $is_independent = isset($_POST['is_independent']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        
        error_log("DEBUG: Parsed values - template_id: $template_id, name: $name, category_id: $category_id, amount: $amount");
        
        if (empty($name) || !$category_id || $amount === '' || $amount < 0) {
            error_log("DEBUG: Validation failed - name: '$name', category_id: $category_id, amount: '$amount'");
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Name, Kategorie und gültiger Betrag (≥0) sind erforderlich.</p></div>';
            });
            return;
        }
        
        // If marked as independent or no template specified, create independent item
        if ($is_independent || !$template_id) {
            $result = $this->db_manager->create_independent_cost_item(
                $name, $category_id, $amount, $description, $is_percentage, $sort_order
            );
        } else {
            // Create template-specific item using the new enhanced method
            $result = $this->db_manager->create_cost_item(
                $template_id, null, $name, null, $amount, $description, $is_percentage, $sort_order, $category_id
            );
        }
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Kostenpunkt erfolgreich erstellt.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-cost-items' . ($template_id ? '&template_id=' . $template_id : '')));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Erstellen des Kostenpunkts.</p></div>';
            });
        }
    }
    
    private function handle_update_cost_item() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $item_id = intval($_POST['item_id']);
        $name = sanitize_text_field($_POST['item_name']);
        $category = sanitize_text_field($_POST['item_category']);
        $amount = floatval($_POST['item_amount']);
        $description = sanitize_textarea_field($_POST['item_description']);
        $sort_order = intval($_POST['sort_order']);
        
        if (empty($name) || empty($category) || $amount < 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Name, Kategorie und Betrag sind erforderlich.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->update_cost_item($item_id, array(
            'name' => $name,
            'category' => $category,
            'amount' => $amount,
            'description' => $description,
            'sort_order' => $sort_order
        ));
        
        if ($result !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Kostenpunkt erfolgreich aktualisiert.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-cost-items'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren des Kostenpunkts.</p></div>';
            });
        }
    }
    
    private function handle_delete_template() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $template_id = intval($_POST['template_id']);
        $template = $this->db_manager->get_template($template_id);
        
        if (!$template) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Vorlage nicht gefunden.</p></div>';
            });
            return;
        }
        
        if ($template->is_default) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Standard-Vorlagen können nicht gelöscht werden.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->delete_template($template_id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Vorlage erfolgreich gelöscht.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Löschen der Vorlage.</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=cah-financial-templates'));
        exit;
    }
    
    private function handle_delete_cost_item() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $item_id = intval($_POST['item_id']);
        $result = $this->db_manager->delete_cost_item($item_id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Kostenpunkt erfolgreich gelöscht.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Löschen des Kostenpunkts.</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=cah-cost-items'));
        exit;
    }
    
    private function handle_update_template() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $template_id = intval($_POST['template_id']);
        $name = sanitize_text_field($_POST['template_name']);
        $description = sanitize_textarea_field($_POST['template_description']);
        
        if (empty($name)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Template-Name ist erforderlich.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->update_template($template_id, array(
            'name' => $name,
            'description' => $description
        ));
        
        if ($result !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Vorlage erfolgreich aktualisiert.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-financial-templates'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren der Vorlage.</p></div>';
            });
        }
    }
    
    private function handle_create_cost_category() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $name = sanitize_text_field($_POST['category_name']);
        $slug = sanitize_text_field($_POST['category_slug']);
        $description = sanitize_textarea_field($_POST['category_description']);
        $color = sanitize_text_field($_POST['category_color']);
        $sort_order = intval($_POST['sort_order']);
        
        if (empty($name) || empty($slug)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Name und Slug sind erforderlich.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->create_cost_category($name, $slug, $description, $color, false, $sort_order);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Kostenkategorie erfolgreich erstellt.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-cost-categories'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Erstellen der Kategorie. Möglicherweise existiert der Slug bereits.</p></div>';
            });
        }
    }
    
    private function handle_update_cost_category() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $category_id = intval($_POST['category_id']);
        $name = sanitize_text_field($_POST['category_name']);
        $slug = sanitize_text_field($_POST['category_slug']);
        $description = sanitize_textarea_field($_POST['category_description']);
        $color = sanitize_text_field($_POST['category_color']);
        $sort_order = intval($_POST['sort_order']);
        
        if (empty($name) || empty($slug)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Name und Slug sind erforderlich.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->update_cost_category($category_id, array(
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'color' => $color,
            'sort_order' => $sort_order
        ));
        
        if ($result !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Kostenkategorie erfolgreich aktualisiert.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-cost-categories'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren der Kategorie.</p></div>';
            });
        }
    }
    
    private function handle_delete_cost_category() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $category_id = intval($_POST['category_id']);
        $category = $this->db_manager->get_cost_category($category_id);
        
        if (!$category) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Kategorie nicht gefunden.</p></div>';
            });
            return;
        }
        
        if ($category->is_default) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Standard-Kategorien können nicht gelöscht werden.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->delete_cost_category($category_id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Kostenkategorie erfolgreich gelöscht.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Löschen der Kategorie.</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=cah-cost-categories'));
        exit;
    }
    
    private function handle_assign_items_to_template() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $template_id = intval($_POST['template_id']);
        $selected_items = isset($_POST['selected_items']) ? array_map('intval', $_POST['selected_items']) : array();
        
        if (!$template_id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Ungültige Vorlagen-ID.</p></div>';
            });
            return;
        }
        
        // Get currently assigned items
        $currently_assigned = $this->db_manager->get_template_assigned_items($template_id);
        $currently_assigned_ids = array_column($currently_assigned, 'id');
        
        // Remove items that are no longer selected
        foreach ($currently_assigned_ids as $assigned_id) {
            if (!in_array($assigned_id, $selected_items)) {
                $this->db_manager->remove_cost_item_from_template($template_id, $assigned_id);
            }
        }
        
        // Add newly selected items
        foreach ($selected_items as $item_id) {
            if (!in_array($item_id, $currently_assigned_ids)) {
                $this->db_manager->assign_cost_item_to_template($template_id, $item_id);
            }
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Kostenpunkte erfolgreich der Vorlage zugeordnet.</p></div>';
        });
        
        wp_redirect(admin_url('admin.php?page=cah-financial-templates&action=edit&id=' . $template_id));
        exit;
    }
    
    private function handle_create_independent_cost_item() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $name = sanitize_text_field($_POST['item_name']);
        $category_id = intval($_POST['item_category_id']);
        $amount = floatval($_POST['item_amount']);
        $description = sanitize_textarea_field($_POST['item_description']);
        $is_percentage = isset($_POST['is_percentage']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        
        if (empty($name) || !$category_id || $amount === '' || $amount < 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Name, Kategorie und gültiger Betrag (≥0) sind erforderlich.</p></div>';
            });
            return;
        }
        
        $result = $this->db_manager->create_independent_cost_item(
            $name, $category_id, $amount, $description, $is_percentage, $sort_order
        );
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Unabhängiger Kostenpunkt erfolgreich erstellt.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-independent-cost-items'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Erstellen des unabhängigen Kostenpunkts.</p></div>';
            });
        }
    }
    
    private function handle_delete_independent_cost_item() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $item_id = intval($_POST['item_id']);
        
        // First remove from all template assignments
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'cah_template_cost_items',
            array('cost_item_id' => $item_id),
            array('%d')
        );
        
        // Then delete the independent cost item
        $result = $this->db_manager->delete_cost_item($item_id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Unabhängiger Kostenpunkt erfolgreich gelöscht.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Löschen des Kostenpunkts.</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=cah-independent-cost-items'));
        exit;
    }
    
    private function handle_update_independent_cost_item() {
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $item_id = intval($_POST['item_id']);
        $name = sanitize_text_field($_POST['item_name']);
        $category_id = intval($_POST['item_category_id']);
        $amount = floatval($_POST['item_amount']);
        $description = sanitize_textarea_field($_POST['item_description']);
        $is_percentage = isset($_POST['is_percentage']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        
        if (empty($name) || !$category_id || $amount === '' || $amount < 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Name, Kategorie und gültiger Betrag (≥0) sind erforderlich.</p></div>';
            });
            return;
        }
        
        // Update the independent cost item
        $result = $this->db_manager->update_cost_item($item_id, array(
            'name' => $name,
            'category_id' => $category_id,
            'amount' => $amount,
            'description' => $description,
            'is_percentage' => $is_percentage,
            'sort_order' => $sort_order
        ));
        
        if ($result !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Unabhängiger Kostenpunkt erfolgreich aktualisiert.</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=cah-independent-cost-items'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren des unabhängigen Kostenpunkts.</p></div>';
            });
        }
    }
    
    public function independent_cost_items_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_add_independent_cost_item_form();
                break;
            case 'edit':
                $this->render_edit_independent_cost_item_form($item_id);
                break;
            case 'assign':
                $this->render_assign_items_to_template_form();
                break;
            default:
                $this->render_independent_cost_items_list();
                break;
        }
    }
    
    private function render_independent_cost_items_list() {
        $independent_items = $this->db_manager->get_independent_cost_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Unabhängige Kostenpunkte</h1>
            <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items&action=add'); ?>" class="page-title-action">Neuen Kostenpunkt hinzufügen</a>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>💡 Unabhängige Kostenpunkte</strong> können mehreren Vorlagen zugewiesen und flexibel verwaltet werden.</p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Kategorie</th>
                        <th>Betrag</th>
                        <th>Beschreibung</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($independent_items)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <p>Keine unabhängigen Kostenpunkte gefunden.</p>
                            <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items&action=add'); ?>" class="button button-primary">Ersten Kostenpunkt erstellen</a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($independent_items as $item): ?>
                        <tr>
                            <td><strong><?php echo esc_html($item->name); ?></strong></td>
                            <td>
                                <?php if ($item->category_name): ?>
                                <span class="category-badge" style="background: <?php echo esc_attr($item->category_color); ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo esc_html($item->category_name); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #666;">Keine Kategorie</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo $this->calculator->format_currency($item->amount); ?></strong></td>
                            <td><?php echo esc_html($item->description ?: '-'); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items&action=edit&id=' . $item->id); ?>" class="button button-small">✏️</a>
                                <a href="#" onclick="confirmDeleteIndependentItem(<?php echo $item->id; ?>)" class="button button-small button-link-delete">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px; background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 10px 0;">📋 Kostenpunkte zu Vorlagen zuweisen</h3>
                <p style="margin: 0;">Sie können unabhängige Kostenpunkte verschiedenen Vorlagen zuweisen, um flexible Kostenstrukturen zu erstellen.</p>
                <div style="margin-top: 15px;">
                    <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items&action=assign'); ?>" class="button button-primary">📎 Kostenpunkte zuweisen</a>
                    <a href="<?php echo admin_url('admin.php?page=cah-financial-templates'); ?>" class="button button-secondary" style="margin-left: 10px;">📋 Vorlagen verwalten</a>
                </div>
            </div>
        </div>
        
        <script>
        function confirmDeleteIndependentItem(itemId) {
            if (confirm('Sind Sie sicher, dass Sie diesen unabhängigen Kostenpunkt löschen möchten?\\n\\nEr wird aus allen Vorlagen entfernt, denen er zugewiesen ist.')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="financial_action" value="delete_independent_cost_item">' +
                               '<input type="hidden" name="item_id" value="' + itemId + '">' +
                               '<?php wp_nonce_field('financial_action', 'financial_nonce'); ?>';
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php
    }
    
    private function render_add_independent_cost_item_form() {
        $categories = $this->db_manager->get_cost_categories();
        
        ?>
        <div class="wrap">
            <h1>Neuen unabhängigen Kostenpunkt erstellen</h1>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>💡 Unabhängiger Kostenpunkt:</strong> Dieser Kostenpunkt kann mehreren Vorlagen zugewiesen werden und flexibel verwaltet werden.</p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="create_independent_cost_item">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="item_name">Name</label></th>
                        <td>
                            <input type="text" id="item_name" name="item_name" class="regular-text" required>
                            <p class="description">Name des Kostenpunkts</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_category_id">Kategorie</label></th>
                        <td>
                            <select id="item_category_id" name="item_category_id" class="regular-text" required>
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_amount">Betrag (€)</label></th>
                        <td>
                            <input type="number" id="item_amount" name="item_amount" class="regular-text" step="0.01" min="0" required>
                            <p class="description">Betrag in Euro</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_description">Beschreibung</label></th>
                        <td>
                            <textarea id="item_description" name="item_description" class="large-text" rows="4"></textarea>
                            <p class="description">Optional: Zusätzliche Beschreibung</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_percentage">Prozentual</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_percentage" name="is_percentage" value="1">
                                Betrag als Prozentsatz behandeln
                            </label>
                            <p class="description">Bei aktivierter Option wird der Betrag als Prozentsatz der Zwischensumme berechnet</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order">Reihenfolge</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" class="regular-text" min="0" value="0">
                            <p class="description">Reihenfolge in der Anzeige (0 = erste Position)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Kostenpunkt erstellen">
                    <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items'); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_edit_independent_cost_item_form($item_id) {
        $categories = $this->db_manager->get_cost_categories();
        
        // Get the cost item data
        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cah_cost_items WHERE id = %d AND is_independent = 1",
            $item_id
        ));
        
        if (!$item) {
            echo '<div class="wrap"><h1>Kostenpunkt nicht gefunden</h1><p>Der angeforderte unabhängige Kostenpunkt konnte nicht gefunden werden.</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Unabhängigen Kostenpunkt bearbeiten</h1>
            
            <div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                <p><strong>💡 Unabhängiger Kostenpunkt:</strong> Änderungen wirken sich auf alle Vorlagen aus, denen dieser Kostenpunkt zugewiesen ist.</p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="update_independent_cost_item">
                <input type="hidden" name="item_id" value="<?php echo esc_attr($item_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="item_name">Name</label></th>
                        <td>
                            <input type="text" id="item_name" name="item_name" class="regular-text" value="<?php echo esc_attr($item->name); ?>" required>
                            <p class="description">Name des Kostenpunkts</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_category_id">Kategorie</label></th>
                        <td>
                            <select id="item_category_id" name="item_category_id" class="regular-text" required>
                                <option value="">Bitte wählen...</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($item->category_id, $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_amount">Betrag (€)</label></th>
                        <td>
                            <input type="number" id="item_amount" name="item_amount" class="regular-text" step="0.01" min="0" value="<?php echo esc_attr($item->amount); ?>" required>
                            <p class="description">Betrag in Euro</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="item_description">Beschreibung</label></th>
                        <td>
                            <textarea id="item_description" name="item_description" class="large-text" rows="4"><?php echo esc_textarea($item->description); ?></textarea>
                            <p class="description">Optional: Zusätzliche Beschreibung</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_percentage">Prozentual</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_percentage" name="is_percentage" value="1" <?php checked($item->is_percentage, 1); ?>>
                                Betrag als Prozentsatz behandeln
                            </label>
                            <p class="description">Bei aktivierter Option wird der Betrag als Prozentsatz der Zwischensumme berechnet</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order">Reihenfolge</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" class="regular-text" min="0" value="<?php echo esc_attr($item->sort_order); ?>">
                            <p class="description">Reihenfolge in der Anzeige (0 = erste Position)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Kostenpunkt aktualisieren">
                    <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items'); ?>" class="button button-secondary">Abbrechen</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_assign_items_to_template_form() {
        $templates = $this->db_manager->get_templates();
        $independent_items = $this->db_manager->get_independent_cost_items();
        $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
        $assigned_items = $selected_template_id ? $this->db_manager->get_template_assigned_items($selected_template_id) : array();
        $assigned_item_ids = array_column($assigned_items, 'id');
        
        ?>
        <div class="wrap">
            <h1>Kostenpunkte zu Vorlagen zuweisen</h1>
            
            <!-- Template Selection -->
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <form method="get" style="display: flex; gap: 15px; align-items: end;">
                    <input type="hidden" name="page" value="cah-independent-cost-items">
                    <input type="hidden" name="action" value="assign">
                    
                    <div>
                        <label for="template_id" style="display: block; margin-bottom: 5px; font-weight: bold;">Vorlage auswählen:</label>
                        <select name="template_id" id="template_id" style="width: 300px;">
                            <option value="">Bitte wählen...</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template->id; ?>" <?php selected($selected_template_id, $template->id); ?>>
                                <?php echo esc_html($template->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <input type="submit" class="button" value="Vorlage laden">
                    </div>
                </form>
            </div>
            
            <?php if ($selected_template_id && !empty($independent_items)): ?>
            <form method="post">
                <?php wp_nonce_field('financial_action', 'financial_nonce'); ?>
                <input type="hidden" name="financial_action" value="assign_items_to_template">
                <input type="hidden" name="template_id" value="<?php echo esc_attr($selected_template_id); ?>">
                
                <h3>Verfügbare Kostenpunkte</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;">Zuweisen</th>
                            <th>Name</th>
                            <th>Kategorie</th>
                            <th>Betrag</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($independent_items as $item): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_items[]" value="<?php echo $item->id; ?>" 
                                       <?php echo in_array($item->id, $assigned_item_ids) ? 'checked' : ''; ?>>
                            </td>
                            <td><strong><?php echo esc_html($item->name); ?></strong></td>
                            <td>
                                <?php if ($item->category_name): ?>
                                <span class="category-badge" style="background: <?php echo esc_attr($item->category_color); ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo esc_html($item->category_name); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $this->calculator->format_currency($item->amount); ?></td>
                            <td>
                                <?php if (in_array($item->id, $assigned_item_ids)): ?>
                                <span style="color: #4caf50; font-weight: bold;">✅ Zugewiesen</span>
                                <?php else: ?>
                                <span style="color: #999;">Nicht zugewiesen</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Zuweisung speichern">
                    <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items'); ?>" class="button button-secondary">Zurück</a>
                </p>
            </form>
            <?php elseif ($selected_template_id): ?>
            <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 5px;">
                <p>Keine unabhängigen Kostenpunkte verfügbar.</p>
                <a href="<?php echo admin_url('admin.php?page=cah-independent-cost-items&action=add'); ?>" class="button button-primary">Ersten Kostenpunkt erstellen</a>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px; background: #f9f9f9; border-radius: 5px;">
                <p style="font-size: 18px; color: #666;">Wählen Sie eine Vorlage aus, um Kostenpunkte zuzuweisen.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function admin_page() {
        // Handle migration if requested
        if (isset($_POST['run_category_migration']) && wp_verify_nonce($_POST['category_migration_nonce'], 'run_category_migration')) {
            $this->handle_category_migration();
        }
        
        ?>
        <div class="wrap">
            <h1>Financial Calculator - Kostenrechner</h1>
            
            <!-- Migration Section for v1.3.0 -->
            <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;">
                <h2 style="color: #856404; margin-top: 0;">🔄 v1.3.0 - German → English Category Migration</h2>
                <p><strong>ARCHITECTURAL IMPROVEMENT:</strong> Financial plugin now follows "English internal, German UI" standard.</p>
                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field('run_category_migration', 'category_migration_nonce'); ?>
                    <input type="submit" name="run_category_migration" class="button button-primary" 
                           value="🔧 Run Category Migration (German → English Slugs)" 
                           onclick="return confirm('Migrate category slugs from German to English? This will update existing data.');">
                </form>
                <p class="description">Migrates category slugs: grundkosten→basic_costs, gerichtskosten→court_costs, etc.</p>
            </div>
        </div>
        <?php
    }
    
    private function handle_category_migration() {
        // Run the database migration
        $migration_results = $this->db_manager->migrate_german_to_english_category_slugs();
        
        if ($migration_results['success']) {
            add_action('admin_notices', function() use ($migration_results) {
                echo '<div class="notice notice-success" style="margin: 20px 0; padding: 15px;">';
                echo '<h3 style="margin-top: 0;">✅ Category Migration Completed Successfully!</h3>';
                echo '<p><strong>' . esc_html($migration_results['message']) . '</strong></p>';
                
                if (!empty($migration_results['migrations_performed'])) {
                    echo '<details><summary><strong>Migration Details:</strong></summary>';
                    echo '<ul style="margin-top: 10px;">';
                    foreach ($migration_results['migrations_performed'] as $migration) {
                        echo '<li>' . esc_html($migration) . '</li>';
                    }
                    echo '</ul></details>';
                }
                
                echo '<p><strong>✅ Financial plugin now uses English internal naming with German UI labels!</strong></p>';
                echo '</div>';
            });
        } else {
            add_action('admin_notices', function() use ($migration_results) {
                echo '<div class="notice notice-error" style="margin: 20px 0; padding: 15px;">';
                echo '<h3 style="margin-top: 0;">❌ Category Migration Had Issues</h3>';
                echo '<p><strong>' . esc_html($migration_results['message']) . '</strong></p>';
                
                if (!empty($migration_results['migrations_performed'])) {
                    echo '<p><strong>Completed migrations:</strong></p>';
                    echo '<ul>';
                    foreach ($migration_results['migrations_performed'] as $migration) {
                        echo '<li>' . esc_html($migration) . '</li>';
                    }
                    echo '</ul>';
                }
                
                echo '</div>';
            });
        }
    }
    
    private function handle_copy_template($template_id) {
        if (!$template_id) {
            wp_die('Template ID ist erforderlich.');
        }
        
        // Get original template
        $original_template = $this->db_manager->get_template($template_id);
        if (!$original_template) {
            wp_die('Vorlage nicht gefunden.');
        }
        
        // Create copy with modified name
        $copy_name = $original_template->name . ' (Kopie)';
        $copy_count = 1;
        
        // Check for existing copies and increment name
        while ($this->template_name_exists($copy_name)) {
            $copy_count++;
            $copy_name = $original_template->name . ' (Kopie ' . $copy_count . ')';
        }
        
        // Create new template using the db_manager method directly
        $new_template_id = $this->db_manager->create_template(
            $copy_name,
            $original_template->description . ' (Kopiert am ' . date('d.m.Y') . ')',
            false // Never create default templates through copying
        );
        
        if ($new_template_id) {
            // Copy all cost items from original template
            $original_items = $this->db_manager->get_cost_items_by_template($template_id);
            
            foreach ($original_items as $item) {
                // Create new cost item with same data but new template_id
                $this->db_manager->create_cost_item(
                    $new_template_id, // new template_id
                    null, // case_id
                    $item->name, // name
                    $item->category, // category
                    $item->amount, // amount
                    $item->description, // description
                    $item->is_percentage, // is_percentage
                    $item->sort_order, // sort_order
                    $item->category_id // category_id
                );
            }
            
            // Redirect to edit the new template
            wp_redirect(admin_url('admin.php?page=cah-financial-templates&action=edit&id=' . $new_template_id . '&copied=1'));
            exit;
        } else {
            wp_die('Fehler beim Kopieren der Vorlage.');
        }
    }
    
    /**
     * Check if template name exists (helper method)
     */
    private function handle_add_independent_items_to_template() {
        // Add debug logging
        error_log("DEBUG: handle_add_independent_items_to_template called with POST data: " . print_r($_POST, true));
        
        if (!wp_verify_nonce($_POST['financial_nonce'], 'financial_action')) {
            wp_die('Security check failed');
        }
        
        $template_id = intval($_POST['template_id']);
        $selected_items = isset($_POST['selected_items']) ? array_map('intval', $_POST['selected_items']) : array();
        
        error_log("DEBUG: template_id: $template_id, selected_items: " . print_r($selected_items, true));
        
        if (!$template_id || empty($selected_items)) {
            error_log("DEBUG: Validation failed - template_id: $template_id, selected_items count: " . count($selected_items));
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Ungültige Vorlagen-ID oder keine Kostenpunkte ausgewählt.</p></div>';
            });
            return;
        }
        
        $added_count = 0;
        
        // Add each selected independent item as a new cost item in the template
        foreach ($selected_items as $item_id) {
            // Get the independent item details
            global $wpdb;
            $independent_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cah_cost_items WHERE id = %d AND is_independent = 1",
                $item_id
            ));
            
            if ($independent_item) {
                // Create a new cost item for this template based on the independent item
                $result = $this->db_manager->create_cost_item(
                    $template_id, // template_id
                    null, // case_id
                    $independent_item->name, // name
                    $independent_item->category, // category
                    $independent_item->amount, // amount
                    $independent_item->description, // description
                    $independent_item->is_percentage, // is_percentage
                    $independent_item->sort_order, // sort_order
                    $independent_item->category_id // category_id
                );
                
                if ($result) {
                    $added_count++;
                }
            }
        }
        
        if ($added_count > 0) {
            add_action('admin_notices', function() use ($added_count) {
                echo '<div class="notice notice-success"><p>' . $added_count . ' Kostenpunkte erfolgreich zur Vorlage hinzugefügt.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fehler beim Hinzufügen der Kostenpunkte.</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=cah-financial-templates&action=edit&id=' . $template_id));
        exit;
    }
    
    private function template_name_exists($name) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cah_financial_templates WHERE name = %s",
            $name
        ));
        return $count > 0;
    }
}