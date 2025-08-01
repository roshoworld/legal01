<?php
/**
 * Dynamic Form Generator - Creates forms based on database schema
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Form_Generator {
    
    private $schema_manager;
    
    public function __construct() {
        $this->schema_manager = new CAH_Schema_Manager();
    }
    
    /**
     * Generate form based on table schema
     */
    public function generate_form($table_name, $data = array(), $exclude_fields = array()) {
        $schema = $this->schema_manager->get_complete_schema_definition()[$table_name] ?? null;
        
        if (!$schema) {
            return '<div class="error">Table schema not found</div>';
        }
        
        $form_html = '';
        $form_html .= '<div class="dynamic-form" data-table="' . esc_attr($table_name) . '">';
        
        // Group fields by category
        $field_groups = $this->group_fields_by_category($table_name, $schema['columns']);
        
        foreach ($field_groups as $group_name => $fields) {
            $form_html .= $this->render_field_group($group_name, $fields, $data, $exclude_fields);
        }
        
        $form_html .= '</div>';
        
        return $form_html;
    }
    
    /**
     * Group fields by logical categories
     */
    private function group_fields_by_category($table_name, $columns) {
        $groups = array();
        
        if ($table_name === 'klage_cases') {
            $groups = array(
                'Grundinformationen' => array('case_id', 'case_status', 'case_priority', 'mandant', 'submission_date', 'case_notes'),
                'Verfahrensinformationen' => array('verfahrensart', 'rechtsgrundlage', 'kategorie', 'schadenhoehe', 'verfahrenswert'),
                'Zeitraum und Deadlines' => array('zeitraum_von', 'zeitraum_bis', 'deadline_antwort', 'deadline_zahlung'),
                'Bewertung' => array('erfolgsaussicht', 'risiko_bewertung', 'komplexitaet', 'prioritaet_intern'),
                'Kommunikation' => array('kommunikation_sprache', 'bevorzugter_kontakt', 'bearbeitungsstatus'),
                'Dokumente und Beweise' => array('beweise', 'dokumente', 'links_zu_dokumenten'),
                'Gerichtsverfahren' => array('egvp_aktenzeichen', 'xjustiz_uuid', 'gericht_zustaendig'),
                'Status-Tracking' => array('brief_status', 'anwaltsschreiben_status', 'mahnung_status', 'klage_status', 'vollstreckung_status')
            );
        } elseif ($table_name === 'klage_debtors') {
            $groups = array(
                'Persönliche Informationen' => array('debtors_first_name', 'debtors_last_name', 'debtors_company', 'rechtsform'),
                'Kontaktdaten' => array('debtors_email', 'debtors_phone', 'debtors_fax', 'website'),
                'Adresse' => array('debtors_address', 'debtors_street', 'debtors_house_number', 'debtors_postal_code', 'debtors_city', 'debtors_state', 'debtors_country'),
                'Rechtliche Informationen' => array('handelsregister_nr', 'ustid', 'geschaeftsfuehrer'),
                'Finanzielle Bewertung' => array('finanzielle_situation', 'zahlungsverhalten', 'bonität', 'insolvenz_status', 'pfändung_status'),
                'Kommunikation' => array('bevorzugte_sprache', 'kommunikation_email', 'kommunikation_post'),
                'Metadata' => array('datenquelle', 'verifiziert', 'social_media')
            );
        // Financial table removed in v1.4.7 - moved to separate plugin
        } elseif ($table_name === 'klage_financial') {
            $groups = array(
                'DSGVO Standard-Beträge' => array('damages_loss', 'partner_fees', 'communication_fees', 'vat', 'court_fees', 'total'),
                'Detaillierte Kosten' => array('streitwert', 'schadenersatz', 'anwaltskosten', 'gerichtskosten', 'nebenkosten', 'auslagen'),
                'Zusätzliche Kosten' => array('mahnkosten', 'vollstreckungskosten', 'zinsen'),
                'Zahlungsinformationen' => array('payment_status', 'payment_date', 'payment_amount', 'payment_method'),
                'Kostenstruktur' => array('kostenkategorie', 'gebuehrenstruktur', 'calculation_template_id'),
                'Erweiterte Felder' => array('custom_fields')
            );
        } else {
            // Default grouping for other tables
            $groups['Alle Felder'] = array_keys($columns);
        }
        
        // Filter out non-existent fields and add any missing fields
        $filtered_groups = array();
        $used_fields = array();
        
        foreach ($groups as $group_name => $field_list) {
            $filtered_fields = array();
            foreach ($field_list as $field_name) {
                if (isset($columns[$field_name])) {
                    $filtered_fields[] = $field_name;
                    $used_fields[] = $field_name;
                }
            }
            if (!empty($filtered_fields)) {
                $filtered_groups[$group_name] = $filtered_fields;
            }
        }
        
        // Add any remaining fields to "Sonstige" group
        $remaining_fields = array_diff(array_keys($columns), $used_fields);
        $system_fields = array('id', 'created_at', 'updated_at', 'letzte_aktualisierung');
        $remaining_fields = array_diff($remaining_fields, $system_fields);
        
        if (!empty($remaining_fields)) {
            $filtered_groups['Sonstige'] = $remaining_fields;
        }
        
        return $filtered_groups;
    }
    
    /**
     * Render a field group
     */
    private function render_field_group($group_name, $fields, $data, $exclude_fields) {
        $html = '<div class="postbox">';
        $html .= '<h2 class="hndle">' . esc_html($group_name) . '</h2>';
        $html .= '<div class="inside" style="padding: 20px;">';
        $html .= '<table class="form-table">';
        
        foreach ($fields as $field_name) {
            if (in_array($field_name, $exclude_fields)) {
                continue;
            }
            
            $html .= $this->render_field_row($field_name, $data);
        }
        
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render individual field row
     */
    private function render_field_row($field_name, $data) {
        $field_config = $this->get_field_config($field_name);
        $value = $data[$field_name] ?? $field_config['default'] ?? '';
        
        $html = '<tr>';
        $html .= '<th scope="row">';
        $html .= '<label for="' . esc_attr($field_name) . '">' . esc_html($field_config['label']) . '</label>';
        if ($field_config['required']) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</th>';
        $html .= '<td>';
        
        $html .= $this->render_field_input($field_name, $field_config, $value);
        
        if (!empty($field_config['description'])) {
            $html .= '<p class="description">' . esc_html($field_config['description']) . '</p>';
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        
        return $html;
    }
    
    /**
     * Render field input based on type
     */
    private function render_field_input($field_name, $config, $value) {
        $attributes = array(
            'id' => $field_name,
            'name' => $field_name,
            'class' => $config['class'] ?? 'regular-text'
        );
        
        if ($config['required']) {
            $attributes['required'] = 'required';
        }
        
        switch ($config['type']) {
            case 'text':
                $attributes['type'] = 'text';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'email':
                $attributes['type'] = 'email';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'tel':
                $attributes['type'] = 'tel';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'number':
                $attributes['type'] = 'number';
                $attributes['value'] = esc_attr($value);
                if (isset($config['min'])) $attributes['min'] = $config['min'];
                if (isset($config['max'])) $attributes['max'] = $config['max'];
                if (isset($config['step'])) $attributes['step'] = $config['step'];
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'date':
                $attributes['type'] = 'date';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'datetime':
                $attributes['type'] = 'datetime-local';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'textarea':
                unset($attributes['type']);
                $attributes['rows'] = $config['rows'] ?? 4;
                return '<textarea ' . $this->build_attributes($attributes) . '>' . esc_textarea($value) . '</textarea>';
                
            case 'select':
                unset($attributes['type']);
                $html = '<select ' . $this->build_attributes($attributes) . '>';
                foreach ($config['options'] as $option_value => $option_label) {
                    $selected = ($option_value == $value) ? ' selected' : '';
                    $html .= '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>';
                }
                $html .= '</select>';
                return $html;
                
            case 'checkbox':
                $attributes['type'] = 'checkbox';
                $attributes['value'] = '1';
                if ($value) $attributes['checked'] = 'checked';
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            case 'decimal':
                $attributes['type'] = 'number';
                $attributes['step'] = '0.01';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
                
            default:
                $attributes['type'] = 'text';
                $attributes['value'] = esc_attr($value);
                return '<input ' . $this->build_attributes($attributes) . '>';
        }
    }
    
    /**
     * Get field configuration with auto-detection for new columns
     */
    private function get_field_config($field_name) {
        $configs = array(
            // Cases table fields
            'case_id' => array('label' => 'Fall-ID', 'type' => 'text', 'required' => true, 'description' => 'Eindeutige Fall-Kennung'),
            'case_status' => array('label' => 'Status', 'type' => 'select', 'options' => array('draft' => 'Entwurf', 'pending' => 'Ausstehend', 'processing' => 'In Bearbeitung', 'completed' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen')),
            'case_priority' => array('label' => 'Priorität', 'type' => 'select', 'options' => array('low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch', 'urgent' => 'Dringend')),
            'mandant' => array('label' => 'Mandant', 'type' => 'text', 'required' => true, 'description' => 'Mandant/Kanzlei'),
            'submission_date' => array('label' => 'Einreichungsdatum', 'type' => 'date', 'required' => true, 'description' => 'Datum der Falleinreichung'),
            'case_notes' => array('label' => 'Notizen', 'type' => 'textarea', 'rows' => 4, 'description' => 'Interne Notizen zum Fall'),
            'verfahrensart' => array('label' => 'Verfahrensart', 'type' => 'select', 'options' => array('mahnverfahren' => 'Mahnverfahren', 'klage' => 'Klage', 'vollstreckung' => 'Vollstreckung')),
            'rechtsgrundlage' => array('label' => 'Rechtsgrundlage', 'type' => 'text', 'default' => 'DSGVO Art. 82'),
            'kategorie' => array('label' => 'Kategorie', 'type' => 'select', 'options' => array('GDPR_SPAM' => 'GDPR Spam', 'GDPR_GENERAL' => 'GDPR Allgemein', 'OTHER' => 'Sonstiges')),
            'schadenhoehe' => array('label' => 'Schadenhöhe', 'type' => 'decimal', 'default' => '548.11'),
            'verfahrenswert' => array('label' => 'Verfahrenswert', 'type' => 'decimal', 'default' => '548.11'),
            'erfolgsaussicht' => array('label' => 'Erfolgsaussicht', 'type' => 'select', 'options' => array('hoch' => 'Hoch', 'mittel' => 'Mittel', 'niedrig' => 'Niedrig')),
            'risiko_bewertung' => array('label' => 'Risiko-Bewertung', 'type' => 'select', 'options' => array('niedrig' => 'Niedrig', 'mittel' => 'Mittel', 'hoch' => 'Hoch')),
            'komplexitaet' => array('label' => 'Komplexität', 'type' => 'select', 'options' => array('einfach' => 'Einfach', 'standard' => 'Standard', 'komplex' => 'Komplex')),
            'kommunikation_sprache' => array('label' => 'Sprache', 'type' => 'select', 'options' => array('de' => 'Deutsch', 'en' => 'Englisch')),
            'bevorzugter_kontakt' => array('label' => 'Bevorzugter Kontakt', 'type' => 'select', 'options' => array('email' => 'E-Mail', 'phone' => 'Telefon', 'post' => 'Post')),
            'bearbeitungsstatus' => array('label' => 'Bearbeitungsstatus', 'type' => 'select', 'options' => array('neu' => 'Neu', 'bearbeitung' => 'In Bearbeitung', 'review' => 'Überprüfung', 'abgeschlossen' => 'Abgeschlossen')),
            'zeitraum_von' => array('label' => 'Zeitraum von', 'type' => 'date'),
            'zeitraum_bis' => array('label' => 'Zeitraum bis', 'type' => 'date'),
            'deadline_antwort' => array('label' => 'Antwort-Deadline', 'type' => 'date'),
            'deadline_zahlung' => array('label' => 'Zahlungs-Deadline', 'type' => 'date'),
            'beweise' => array('label' => 'Beweise', 'type' => 'textarea', 'rows' => 6),
            'dokumente' => array('label' => 'Dokumente', 'type' => 'textarea', 'rows' => 4),
            'links_zu_dokumenten' => array('label' => 'Links zu Dokumenten', 'type' => 'textarea', 'rows' => 3),
            'egvp_aktenzeichen' => array('label' => 'EGVP Aktenzeichen', 'type' => 'text'),
            'xjustiz_uuid' => array('label' => 'XJustiz UUID', 'type' => 'text'),
            'gericht_zustaendig' => array('label' => 'Zuständiges Gericht', 'type' => 'text'),
            'brief_status' => array('label' => 'Brief Status', 'type' => 'select', 'options' => array('pending' => 'Ausstehend', 'sent' => 'Gesendet', 'delivered' => 'Zugestellt', 'failed' => 'Fehlgeschlagen')),
            
            // Debtors table fields
            'debtors_first_name' => array('label' => 'Vorname', 'type' => 'text'),
            'debtors_last_name' => array('label' => 'Nachname', 'type' => 'text', 'required' => true),
            'debtors_company' => array('label' => 'Firma', 'type' => 'text'),
            'debtors_email' => array('label' => 'E-Mail', 'type' => 'email'),
            'debtors_phone' => array('label' => 'Telefon', 'type' => 'tel'),
            'debtors_address' => array('label' => 'Adresse', 'type' => 'text'),
            'debtors_postal_code' => array('label' => 'PLZ', 'type' => 'text'),
            'debtors_city' => array('label' => 'Stadt', 'type' => 'text'),
            'debtors_country' => array('label' => 'Land', 'type' => 'text', 'default' => 'Deutschland'),
            'rechtsform' => array('label' => 'Rechtsform', 'type' => 'select', 'options' => array('natuerliche_person' => 'Natürliche Person', 'gmbh' => 'GmbH', 'ag' => 'AG', 'ohg' => 'OHG', 'kg' => 'KG')),
            'zahlungsverhalten' => array('label' => 'Zahlungsverhalten', 'type' => 'select', 'options' => array('unbekannt' => 'Unbekannt', 'gut' => 'Gut', 'mittel' => 'Mittel', 'schlecht' => 'Schlecht')),
            'bonität' => array('label' => 'Bonität', 'type' => 'select', 'options' => array('unbekannt' => 'Unbekannt', 'hoch' => 'Hoch', 'mittel' => 'Mittel', 'niedrig' => 'Niedrig')),
            'bevorzugte_sprache' => array('label' => 'Bevorzugte Sprache', 'type' => 'select', 'options' => array('de' => 'Deutsch', 'en' => 'Englisch')),
            'kommunikation_email' => array('label' => 'E-Mail-Kommunikation', 'type' => 'checkbox'),
            'kommunikation_post' => array('label' => 'Post-Kommunikation', 'type' => 'checkbox'),
            'datenquelle' => array('label' => 'Datenquelle', 'type' => 'select', 'options' => array('manual' => 'Manuell', 'forderungen_com' => 'Forderungen.com', 'email' => 'E-Mail')),
            'verifiziert' => array('label' => 'Verifiziert', 'type' => 'checkbox'),
            
            // Financial table fields removed in v1.4.7
            'damages_loss' => array('label' => 'Grundschaden', 'type' => 'decimal', 'default' => '350.00'),
            'partner_fees' => array('label' => 'Anwaltskosten', 'type' => 'decimal', 'default' => '96.90'),
            'communication_fees' => array('label' => 'Kommunikationskosten', 'type' => 'decimal', 'default' => '13.36'),
            'court_fees' => array('label' => 'Gerichtskosten', 'type' => 'decimal', 'default' => '32.00'),
            'vat' => array('label' => 'MwSt (19%)', 'type' => 'decimal', 'default' => '87.85'),
            'total' => array('label' => 'Gesamtsumme', 'type' => 'decimal', 'default' => '548.11'),
            'payment_status' => array('label' => 'Zahlungsstatus', 'type' => 'select', 'options' => array('offen' => 'Offen', 'teilweise' => 'Teilweise', 'vollständig' => 'Vollständig')),
            'payment_date' => array('label' => 'Zahlungsdatum', 'type' => 'date'),
            'payment_amount' => array('label' => 'Zahlungsbetrag', 'type' => 'decimal'),
            'payment_method' => array('label' => 'Zahlungsmethode', 'type' => 'select', 'options' => array('bank_transfer' => 'Überweisung', 'check' => 'Scheck', 'cash' => 'Bar', 'other' => 'Sonstiges'))
        );
        
        // If configuration exists, use it
        if (isset($configs[$field_name])) {
            return array_merge($this->get_default_field_config($field_name), $configs[$field_name]);
        }
        
        // Auto-generate configuration for new columns
        return $this->auto_generate_field_config($field_name);
    }
    
    /**
     * Auto-generate field configuration for new columns
     */
    private function auto_generate_field_config($field_name) {
        $config = $this->get_default_field_config($field_name);
        
        // Auto-detect field type based on name patterns
        if (strpos($field_name, 'email') !== false) {
            $config['type'] = 'email';
        } elseif (strpos($field_name, 'phone') !== false) {
            $config['type'] = 'tel';
        } elseif (strpos($field_name, 'date') !== false) {
            $config['type'] = 'date';
        } elseif (strpos($field_name, 'datetime') !== false) {
            $config['type'] = 'datetime';
        } elseif (strpos($field_name, 'amount') !== false || strpos($field_name, 'cost') !== false) {
            $config['type'] = 'decimal';
        } elseif (strpos($field_name, 'count') !== false || strpos($field_name, 'number') !== false) {
            $config['type'] = 'number';
        } elseif (strpos($field_name, 'notes') !== false || strpos($field_name, 'content') !== false) {
            $config['type'] = 'textarea';
        }
        
        return $config;
    }
    
    /**
     * Get default field configuration
     */
    private function get_default_field_config($field_name) {
        return array(
            'label' => $this->generate_german_label($field_name),
            'type' => 'text',
            'required' => false,
            'description' => '',
            'class' => 'regular-text'
        );
    }
    
    /**
     * Generate German label from field name
     */
    private function generate_german_label($field_name) {
        $translations = array(
            'deadline' => 'Deadline',
            'response' => 'Antwort',
            'payment' => 'Zahlung',
            'complexity' => 'Komplexität',
            'processing' => 'Verarbeitung',
            'risk' => 'Risiko',
            'score' => 'Bewertung',
            'document' => 'Dokument',
            'language' => 'Sprache',
            'type' => 'Typ',
            'case' => 'Fall',
            'debtor' => 'Schuldner',
            'status' => 'Status',
            'date' => 'Datum',
            'amount' => 'Betrag',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'address' => 'Adresse',
            'city' => 'Stadt',
            'country' => 'Land',
            'name' => 'Name',
            'company' => 'Firma'
        );
        
        $parts = explode('_', $field_name);
        $label_parts = array();
        
        foreach ($parts as $part) {
            $label_parts[] = $translations[$part] ?? ucfirst($part);
        }
        
        return implode(' ', $label_parts);
    }
    
    /**
     * Build HTML attributes string
     */
    private function build_attributes($attributes) {
        $attr_strings = array();
        
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attr_strings[] = $key;
                }
            } else {
                $attr_strings[] = $key . '="' . esc_attr($value) . '"';
            }
        }
        
        return implode(' ', $attr_strings);
    }
    
    /**
     * Generate JavaScript for form validation
     */
    public function generate_form_validation_js($table_name) {
        $js = '
        <script>
        jQuery(document).ready(function($) {
            // Dynamic form validation
            $(".dynamic-form").on("submit", function(e) {
                var valid = true;
                
                // Check required fields
                $(this).find("[required]").each(function() {
                    if (!$(this).val()) {
                        $(this).addClass("error");
                        valid = false;
                    } else {
                        $(this).removeClass("error");
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert("Bitte füllen Sie alle erforderlichen Felder aus.");
                }
            });
            
            // Remove error styling on input
            $(".dynamic-form input, .dynamic-form textarea, .dynamic-form select").on("input change", function() {
                $(this).removeClass("error");
            });
        });
        </script>
        
        <style>
        .dynamic-form .error {
            border-color: #dc3232 !important;
            box-shadow: 0 0 2px rgba(220, 50, 50, 0.5) !important;
        }
        .required {
            color: #dc3232;
        }
        </style>
        ';
        
        return $js;
    }
}