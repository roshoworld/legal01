<?php
/**
 * Enhanced CRM Contacts Management with Category Tabs
 * Provides full CRUD operations for contacts with categorization
 */

if (!defined('ABSPATH')) {
    exit;
}

class Enhanced_CRM_Contacts_Manager {
    
    public function __construct() {
        add_action('wp_ajax_la_crm_create_contact', array($this, 'ajax_create_contact'));
        add_action('wp_ajax_la_crm_update_contact', array($this, 'ajax_update_contact'));
        add_action('wp_ajax_la_crm_delete_contact', array($this, 'ajax_delete_contact'));
        add_action('wp_ajax_la_crm_get_contact', array($this, 'ajax_get_contact'));
    }
    
    /**
     * Render enhanced contacts page with tabs
     */
    public function render_contacts_page() {
        global $wpdb;
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'mandanten';
        
        // Get contact statistics
        $stats = $this->get_contact_statistics();
        
        ?>
        <div class="wrap">
            <h1>CRM - Kontakte</h1>
            
            <!-- Statistics Dashboard -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #0073aa;">
                    <h3 style="margin: 0; color: #0073aa; font-size: 24px;"><?php echo esc_html($stats['mandanten']); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Mandanten</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #28a745;">
                    <h3 style="margin: 0; color: #28a745; font-size: 24px;"><?php echo esc_html($stats['partner']); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Partner</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #dc3545;">
                    <h3 style="margin: 0; color: #dc3545; font-size: 24px;"><?php echo esc_html($stats['rechtsanwaelte']); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Rechtsanwälte</p>
                </div>
                <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #6c757d;">
                    <h3 style="margin: 0; color: #6c757d; font-size: 24px;"><?php echo esc_html($stats['other']); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Sonstige</p>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <div class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=legal-automation-crm-contacts&tab=mandanten'); ?>" 
                   class="nav-tab <?php echo $active_tab === 'mandanten' ? 'nav-tab-active' : ''; ?>">
                    👤 Mandanten (<?php echo $stats['mandanten']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=legal-automation-crm-contacts&tab=partner'); ?>" 
                   class="nav-tab <?php echo $active_tab === 'partner' ? 'nav-tab-active' : ''; ?>">
                    🤝 Partner (<?php echo $stats['partner']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=legal-automation-crm-contacts&tab=rechtsanwaelte'); ?>" 
                   class="nav-tab <?php echo $active_tab === 'rechtsanwaelte' ? 'nav-tab-active' : ''; ?>">
                    ⚖️ Rechtsanwälte (<?php echo $stats['rechtsanwaelte']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=legal-automation-crm-contacts&tab=all'); ?>" 
                   class="nav-tab <?php echo $active_tab === 'all' ? 'nav-tab-active' : ''; ?>">
                    📋 Alle Kontakte
                </a>
            </div>
            
            <div class="tab-content" style="background: white; padding: 20px; border: 1px solid #ccd0d4; margin-top: -1px;">
                
                <!-- Quick Add Contact Form -->
                <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 25px;">
                    <h3 style="margin-top: 0;">✨ Schnell-Erstellung</h3>
                    <form id="quick-contact-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <?php wp_nonce_field('la_crm_contact', 'contact_nonce'); ?>
                        
                        <div>
                            <label><strong>Kategorie:</strong></label>
                            <select name="category" id="quick-category" required>
                                <option value="mandanten" <?php selected($active_tab, 'mandanten'); ?>>👤 Mandant</option>
                                <option value="partner" <?php selected($active_tab, 'partner'); ?>>🤝 Partner</option>
                                <option value="rechtsanwaelte" <?php selected($active_tab, 'rechtsanwaelte'); ?>>⚖️ Rechtsanwalt</option>
                                <option value="other">📋 Sonstige</option>
                            </select>
                        </div>
                        
                        <div>
                            <label><strong>Vorname:</strong></label>
                            <input type="text" name="first_name" id="quick-first-name">
                        </div>
                        
                        <div>
                            <label><strong>Nachname:</strong></label>
                            <input type="text" name="last_name" id="quick-last-name" required>
                        </div>
                        
                        <div>
                            <label><strong>E-Mail:</strong></label>
                            <input type="email" name="email" id="quick-email">
                        </div>
                        
                        <div>
                            <label><strong>Firma:</strong></label>
                            <input type="text" name="company_name" id="quick-company">
                        </div>
                        
                        <div>
                            <button type="submit" class="button button-primary">➕ Hinzufügen</button>
                        </div>
                    </form>
                </div>
                
                <?php
                // Render the appropriate tab content
                switch ($active_tab) {
                    case 'mandanten':
                        $this->render_contact_list('mandanten', 'Mandanten');
                        break;
                    case 'partner':
                        $this->render_contact_list('partner', 'Partner');
                        break;
                    case 'rechtsanwaelte':
                        $this->render_contact_list('rechtsanwaelte', 'Rechtsanwälte');
                        break;
                    case 'all':
                    default:
                        $this->render_contact_list('all', 'Alle Kontakte');
                        break;
                }
                ?>
            </div>
        </div>
        
        <!-- Contact Edit Modal -->
        <div id="contact-edit-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; border-radius: 8px; width: 90%; max-width: 600px;">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
                    <h2 id="modal-title">Kontakt bearbeiten</h2>
                    <span onclick="closeContactModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                </div>
                
                <form id="contact-edit-form">
                    <input type="hidden" id="edit-contact-id" name="contact_id">
                    <?php wp_nonce_field('la_crm_contact', 'edit_contact_nonce'); ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label><strong>Kategorie:</strong></label>
                            <select name="category" id="edit-category" required>
                                <option value="mandanten">👤 Mandant</option>
                                <option value="partner">🤝 Partner</option>
                                <option value="rechtsanwaelte">⚖️ Rechtsanwalt</option>
                                <option value="other">📋 Sonstige</option>
                            </select>
                        </div>
                        
                        <div>
                            <label><strong>Typ:</strong></label>
                            <select name="contact_type" id="edit-contact-type">
                                <option value="individual">Privatperson</option>
                                <option value="company">Unternehmen</option>
                            </select>
                        </div>
                        
                        <div>
                            <label><strong>Vorname:</strong></label>
                            <input type="text" name="first_name" id="edit-first-name">
                        </div>
                        
                        <div>
                            <label><strong>Nachname:</strong></label>
                            <input type="text" name="last_name" id="edit-last-name" required>
                        </div>
                        
                        <div>
                            <label><strong>Firma:</strong></label>
                            <input type="text" name="company_name" id="edit-company">
                        </div>
                        
                        <div>
                            <label><strong>E-Mail:</strong></label>
                            <input type="email" name="email" id="edit-email">
                        </div>
                        
                        <div>
                            <label><strong>Telefon:</strong></label>
                            <input type="tel" name="phone" id="edit-phone">
                        </div>
                        
                        <div>
                            <label><strong>PLZ:</strong></label>
                            <input type="text" name="postal_code" id="edit-postal-code">
                        </div>
                        
                        <div style="grid-column: 1 / -1;">
                            <label><strong>Adresse:</strong></label>
                            <input type="text" name="street" id="edit-street" style="width: 100%;">
                        </div>
                        
                        <div>
                            <label><strong>Stadt:</strong></label>
                            <input type="text" name="city" id="edit-city">
                        </div>
                        
                        <div>
                            <label><strong>Land:</strong></label>
                            <input type="text" name="country" id="edit-country" value="Deutschland">
                        </div>
                        
                        <div style="grid-column: 1 / -1;">
                            <label><strong>Notizen:</strong></label>
                            <textarea name="notes" id="edit-notes" rows="3" style="width: 100%;"></textarea>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="button" onclick="closeContactModal()" class="button">Abbrechen</button>
                        <button type="submit" class="button button-primary">💾 Speichern</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Quick contact form submission
            $('#quick-contact-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'la_crm_create_contact',
                    category: $('#quick-category').val(),
                    first_name: $('#quick-first-name').val(),
                    last_name: $('#quick-last-name').val(),
                    email: $('#quick-email').val(),
                    company_name: $('#quick-company').val(),
                    contact_type: 'individual',
                    nonce: $('[name="contact_nonce"]').val()
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('✅ Kontakt erfolgreich erstellt!');
                        location.reload();
                    } else {
                        alert('❌ Fehler: ' + response.data.message);
                    }
                });
            });
            
            // Contact edit form submission
            $('#contact-edit-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize() + '&action=la_crm_update_contact';
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('✅ Kontakt erfolgreich aktualisiert!');
                        closeContactModal();
                        location.reload();
                    } else {
                        alert('❌ Fehler: ' + response.data.message);
                    }
                });
            });
        });
        
        function editContact(contactId) {
            // Load contact data via AJAX
            jQuery.post(ajaxurl, {
                action: 'la_crm_get_contact',
                contact_id: contactId,
                nonce: '<?php echo wp_create_nonce('la_crm_contact'); ?>'
            }, function(response) {
                if (response.success) {
                    var contact = response.data;
                    
                    // Populate form fields
                    jQuery('#edit-contact-id').val(contact.id);
                    jQuery('#edit-category').val(contact.category || 'other');
                    jQuery('#edit-contact-type').val(contact.contact_type || 'individual');
                    jQuery('#edit-first-name').val(contact.first_name || '');
                    jQuery('#edit-last-name').val(contact.last_name || '');
                    jQuery('#edit-company').val(contact.company_name || '');
                    jQuery('#edit-email').val(contact.email || '');
                    jQuery('#edit-phone').val(contact.phone || '');
                    jQuery('#edit-postal-code').val(contact.postal_code || '');
                    jQuery('#edit-street').val(contact.street || '');
                    jQuery('#edit-city').val(contact.city || '');
                    jQuery('#edit-country').val(contact.country || 'Deutschland');
                    jQuery('#edit-notes').val(contact.notes || '');
                    
                    // Show modal
                    jQuery('#contact-edit-modal').show();
                } else {
                    alert('❌ Fehler beim Laden der Kontaktdaten: ' + response.data.message);
                }
            });
        }
        
        function deleteContact(contactId, contactName) {
            if (confirm('⚠️ Kontakt "' + contactName + '" wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden.')) {
                jQuery.post(ajaxurl, {
                    action: 'la_crm_delete_contact',
                    contact_id: contactId,
                    nonce: '<?php echo wp_create_nonce('la_crm_contact'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('✅ Kontakt erfolgreich gelöscht!');
                        location.reload();
                    } else {
                        alert('❌ Fehler beim Löschen: ' + response.data.message);
                    }
                });
            }
        }
        
        function closeContactModal() {
            jQuery('#contact-edit-modal').hide();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('contact-edit-modal');
            if (event.target == modal) {
                closeContactModal();
            }
        }
        </script>
        
        <style>
        .contacts-table th, .contacts-table td {
            padding: 12px 8px;
            text-align: left;
        }
        .contact-category-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        .category-mandanten { background: #e3f2fd; color: #1565c0; }
        .category-partner { background: #e8f5e9; color: #2e7d32; }
        .category-rechtsanwaelte { background: #ffebee; color: #c62828; }
        .category-other { background: #f3e5f5; color: #7b1fa2; }
        </style>
        <?php
    }
    
    /**
     * Render contact list for specific category
     */
    private function render_contact_list($category, $title) {
        global $wpdb;
        
        // Build query based on category
        $where_clause = "WHERE active_status = 1";
        $params = array();
        
        if ($category !== 'all') {
            $where_clause .= " AND (contact_category = %s OR category = %s)";
            $params[] = $category;
            $params[] = $category;
        }
        
        $query = "
            SELECT *, 
                   COALESCE(contact_category, category, 'other') as display_category
            FROM {$wpdb->prefix}klage_contacts 
            $where_clause 
            ORDER BY last_name ASC, first_name ASC
        ";
        
        if (!empty($params)) {
            $contacts = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $contacts = $wpdb->get_results($query);
        }
        
        ?>
        <div class="contacts-list-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><?php echo esc_html($title); ?> (<?php echo count($contacts); ?>)</h3>
                
                <div>
                    <input type="text" id="contact-search" placeholder="🔍 Kontakte durchsuchen..." style="width: 250px;">
                </div>
            </div>
            
            <?php if (empty($contacts)): ?>
                <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 8px;">
                    <p style="font-size: 16px; color: #666;">Noch keine Kontakte in dieser Kategorie.</p>
                    <p>Verwenden Sie das Schnell-Formular oben, um einen neuen Kontakt hinzuzufügen.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped contacts-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Kategorie</th>
                            <th>Name</th>
                            <th>Firma</th>
                            <th>E-Mail</th>
                            <th>Telefon</th>
                            <th style="width: 120px;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr class="contact-row" data-name="<?php echo esc_attr(strtolower($contact->first_name . ' ' . $contact->last_name . ' ' . $contact->company_name)); ?>">
                                <td>
                                    <span class="contact-category-badge category-<?php echo esc_attr($contact->display_category); ?>">
                                        <?php
                                        $category_icons = array(
                                            'mandanten' => '👤',
                                            'partner' => '🤝',
                                            'rechtsanwaelte' => '⚖️',
                                            'other' => '📋'
                                        );
                                        echo $category_icons[$contact->display_category] ?? '📋';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($contact->first_name . ' ' . $contact->last_name); ?></strong>
                                    <?php if ($contact->contact_type === 'company'): ?>
                                        <br><small style="color: #666;">Unternehmen</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($contact->company_name ?: '-'); ?></td>
                                <td>
                                    <?php if ($contact->email): ?>
                                        <a href="mailto:<?php echo esc_attr($contact->email); ?>"><?php echo esc_html($contact->email); ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($contact->phone): ?>
                                        <a href="tel:<?php echo esc_attr($contact->phone); ?>"><?php echo esc_html($contact->phone); ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="editContact(<?php echo $contact->id; ?>)" title="Bearbeiten">
                                        ✏️
                                    </button>
                                    <button type="button" class="button button-small button-link-delete" onclick="deleteContact(<?php echo $contact->id; ?>, '<?php echo esc_js($contact->first_name . ' ' . $contact->last_name); ?>')" title="Löschen">
                                        🗑️
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        // Live search functionality
        jQuery(document).ready(function($) {
            $('#contact-search').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();
                
                $('.contact-row').each(function() {
                    var contactName = $(this).data('name');
                    if (contactName.indexOf(searchTerm) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get contact statistics by category
     */
    private function get_contact_statistics() {
        global $wpdb;
        
        $stats = array(
            'mandanten' => 0,
            'partner' => 0,
            'rechtsanwaelte' => 0,
            'other' => 0
        );
        
        $results = $wpdb->get_results("
            SELECT 
                COALESCE(contact_category, category, 'other') as cat,
                COUNT(*) as count
            FROM {$wpdb->prefix}klage_contacts 
            WHERE active_status = 1
            GROUP BY COALESCE(contact_category, category, 'other')
        ");
        
        foreach ($results as $result) {
            if (isset($stats[$result->cat])) {
                $stats[$result->cat] = (int) $result->count;
            } else {
                $stats['other'] += (int) $result->count;
            }
        }
        
        return $stats;
    }
    
    /**
     * AJAX: Create new contact
     */
    public function ajax_create_contact() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_contact')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wpdb;
        
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'contact_type' => sanitize_text_field($_POST['contact_type'] ?? 'individual'),
            'contact_category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'active_status' => 1,
            'created_at' => current_time('mysql')
        );
        
        // Check for duplicate email
        if (!empty($data['email'])) {
            $existing = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}klage_contacts 
                WHERE email = %s AND active_status = 1
            ", $data['email']));
            
            if ($existing) {
                wp_send_json_error(array('message' => 'Ein Kontakt mit dieser E-Mail existiert bereits.'));
            }
        }
        
        $result = $wpdb->insert($wpdb->prefix . 'klage_contacts', $data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Kontakt erfolgreich erstellt',
                'contact_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }
    
    /**
     * AJAX: Get contact data
     */
    public function ajax_get_contact() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_contact')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wpdb;
        
        $contact_id = intval($_POST['contact_id']);
        $contact = $wpdb->get_row($wpdb->prepare("
            SELECT *, COALESCE(contact_category, category, 'other') as category
            FROM {$wpdb->prefix}klage_contacts 
            WHERE id = %d AND active_status = 1
        ", $contact_id));
        
        if ($contact) {
            wp_send_json_success((array) $contact);
        } else {
            wp_send_json_error(array('message' => 'Kontakt nicht gefunden'));
        }
    }
    
    /**
     * AJAX: Update contact
     */
    public function ajax_update_contact() {
        if (!wp_verify_nonce($_POST['edit_contact_nonce'], 'la_crm_contact')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wpdb;
        
        $contact_id = intval($_POST['contact_id']);
        
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'street' => sanitize_text_field($_POST['street'] ?? ''),
            'postal_code' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? ''),
            'contact_type' => sanitize_text_field($_POST['contact_type'] ?? 'individual'),
            'contact_category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_contacts',
            $data,
            array('id' => $contact_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Kontakt erfolgreich aktualisiert'));
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }
    
    /**
     * AJAX: Delete contact
     */
    public function ajax_delete_contact() {
        if (!wp_verify_nonce($_POST['nonce'], 'la_crm_contact')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wpdb;
        
        $contact_id = intval($_POST['contact_id']);
        
        // Soft delete - set active_status to 0
        $result = $wpdb->update(
            $wpdb->prefix . 'klage_contacts',
            array('active_status' => 0, 'updated_at' => current_time('mysql')),
            array('id' => $contact_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(array('message' => 'Kontakt erfolgreich gelöscht'));
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }
}