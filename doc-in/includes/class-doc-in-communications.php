<?php
/**
 * Communications Custom Post Type Management
 * Handles the custom post type for storing communication records
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Document_in_Communications {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Register the communications custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Communications', CAH_DOC_IN_TEXT_DOMAIN),
            'singular_name' => __('Communication', CAH_DOC_IN_TEXT_DOMAIN),
            'menu_name' => __('Communications', CAH_DOC_IN_TEXT_DOMAIN),
            'all_items' => __('All Communications', CAH_DOC_IN_TEXT_DOMAIN),
            'add_new' => __('Add New', CAH_DOC_IN_TEXT_DOMAIN),
            'add_new_item' => __('Add New Communication', CAH_DOC_IN_TEXT_DOMAIN),
            'edit_item' => __('Edit Communication', CAH_DOC_IN_TEXT_DOMAIN),
            'new_item' => __('New Communication', CAH_DOC_IN_TEXT_DOMAIN),
            'view_item' => __('View Communication', CAH_DOC_IN_TEXT_DOMAIN),
            'search_items' => __('Search Communications', CAH_DOC_IN_TEXT_DOMAIN),
            'not_found' => __('No communications found', CAH_DOC_IN_TEXT_DOMAIN),
            'not_found_in_trash' => __('No communications found in trash', CAH_DOC_IN_TEXT_DOMAIN),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it to our custom admin menu
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'read_post' => 'manage_options',
                'edit_post' => 'manage_options',
                'delete_post' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
            ),
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => false
        );

        register_post_type('cah_communication', $args);
    }
    
    /**
     * Register taxonomies for communications
     */
    public function register_taxonomies() {
        // Communication categories taxonomy
        $labels = array(
            'name' => __('Communication Categories', CAH_DOC_IN_TEXT_DOMAIN),
            'singular_name' => __('Category', CAH_DOC_IN_TEXT_DOMAIN),
            'menu_name' => __('Categories', CAH_DOC_IN_TEXT_DOMAIN),
        );

        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => false,
            'capabilities' => array(
                'manage_terms' => 'manage_options',
                'edit_terms' => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options'
            )
        );

        register_taxonomy('communication_category', array('cah_communication'), $args);
        
        // Assignment status taxonomy
        $labels_status = array(
            'name' => __('Assignment Status', CAH_DOC_IN_TEXT_DOMAIN),
            'singular_name' => __('Status', CAH_DOC_IN_TEXT_DOMAIN),
        );

        $args_status = array(
            'labels' => $labels_status,
            'hierarchical' => false,
            'public' => false,
            'show_ui' => false,
            'query_var' => false,
            'rewrite' => false
        );

        register_taxonomy('communication_status', array('cah_communication'), $args_status);
    }
    
    /**
     * Add meta boxes for communication details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'communication_details',
            __('Communication Details', CAH_DOC_IN_TEXT_DOMAIN),
            array($this, 'communication_details_meta_box'),
            'cah_communication',
            'normal',
            'high'
        );
        
        add_meta_box(
            'case_assignment',
            __('Case Assignment', CAH_DOC_IN_TEXT_DOMAIN),
            array($this, 'case_assignment_meta_box'),
            'cah_communication',
            'side',
            'high'
        );
        
        add_meta_box(
            'ai_analysis',
            __('AI Analysis Results', CAH_DOC_IN_TEXT_DOMAIN),
            array($this, 'ai_analysis_meta_box'),
            'cah_communication',
            'normal',
            'default'
        );
    }
    
    /**
     * Communication details meta box
     */
    public function communication_details_meta_box($post) {
        wp_nonce_field('communication_details_nonce', 'communication_details_nonce');
        
        $case_number = get_post_meta($post->ID, '_case_number', true);
        $debtor_name = get_post_meta($post->ID, '_debtor_name', true);
        $email_sender = get_post_meta($post->ID, '_email_sender', true);
        $email_received_date = get_post_meta($post->ID, '_email_received_date', true);
        $has_attachment = get_post_meta($post->ID, '_has_attachment', true);
        $message_id = get_post_meta($post->ID, '_message_id', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="case_number"><?php _e('Case Number', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="text" id="case_number" name="case_number" value="<?php echo esc_attr($case_number); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="debtor_name"><?php _e('Debtor Name', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="text" id="debtor_name" name="debtor_name" value="<?php echo esc_attr($debtor_name); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="email_sender"><?php _e('Email Sender', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="email" id="email_sender" name="email_sender" value="<?php echo esc_attr($email_sender); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="email_received_date"><?php _e('Received Date', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="datetime-local" id="email_received_date" name="email_received_date" value="<?php echo esc_attr($email_received_date); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="has_attachment"><?php _e('Has Attachment', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="checkbox" id="has_attachment" name="has_attachment" value="1" <?php checked($has_attachment, 1); ?> /></td>
            </tr>
            <tr>
                <th scope="row"><label for="message_id"><?php _e('Message ID', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="text" id="message_id" name="message_id" value="<?php echo esc_attr($message_id); ?>" class="regular-text" readonly /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Case assignment meta box
     */
    public function case_assignment_meta_box($post) {
        wp_nonce_field('case_assignment_nonce', 'case_assignment_nonce');
        
        $assignment_status = get_post_meta($post->ID, '_assignment_status', true);
        $matched_case_id = get_post_meta($post->ID, '_matched_case_id', true);
        $match_confidence = get_post_meta($post->ID, '_match_confidence', true);
        
        // Get existing cases for dropdown
        global $wpdb;
        $cases = $wpdb->get_results("SELECT case_id, case_number, debtor_name FROM {$wpdb->prefix}klage_cases ORDER BY case_creation_date DESC LIMIT 100");
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="assignment_status"><?php _e('Assignment Status', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td>
                    <select id="assignment_status" name="assignment_status">
                        <option value="unassigned" <?php selected($assignment_status, 'unassigned'); ?>><?php _e('Unassigned', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                        <option value="assigned" <?php selected($assignment_status, 'assigned'); ?>><?php _e('Assigned', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                        <option value="new_case_created" <?php selected($assignment_status, 'new_case_created'); ?>><?php _e('New Case Created', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="matched_case_id"><?php _e('Assigned Case', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td>
                    <select id="matched_case_id" name="matched_case_id">
                        <option value=""><?php _e('Select Case...', CAH_DOC_IN_TEXT_DOMAIN); ?></option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo esc_attr($case->case_id); ?>" <?php selected($matched_case_id, $case->case_id); ?>>
                                <?php echo esc_html($case->case_number . ' - ' . $case->debtor_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="match_confidence"><?php _e('Match Confidence %', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><input type="number" id="match_confidence" name="match_confidence" value="<?php echo esc_attr($match_confidence); ?>" min="0" max="100" /></td>
            </tr>
        </table>
        
        <div class="case-assignment-actions" style="margin-top: 15px;">
            <button type="button" class="button" id="create-new-case-btn"><?php _e('Create New Case', CAH_DOC_IN_TEXT_DOMAIN); ?></button>
        </div>
        <?php
    }
    
    /**
     * AI analysis results meta box
     */
    public function ai_analysis_meta_box($post) {
        $summary = get_post_meta($post->ID, '_summary', true);
        $category = get_post_meta($post->ID, '_category', true);
        $is_new_case = get_post_meta($post->ID, '_is_new_case', true);
        $confidence_score = get_post_meta($post->ID, '_confidence_score', true);
        $extracted_entities = get_post_meta($post->ID, '_extracted_entities', true);
        $pipedream_execution_id = get_post_meta($post->ID, '_pipedream_execution_id', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php _e('AI Summary', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><p><?php echo esc_html($summary); ?></p></td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e('Category', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><strong><?php echo esc_html($category); ?></strong></td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e('AI Assessment', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><?php echo esc_html($is_new_case); ?></td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e('Confidence Score', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><?php echo esc_html($confidence_score); ?>%</td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e('Extracted Entities', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><?php echo esc_html($extracted_entities); ?></td>
            </tr>
            <tr>
                <th scope="row"><label><?php _e('Pipedream Execution ID', CAH_DOC_IN_TEXT_DOMAIN); ?></label></th>
                <td><code><?php echo esc_html($pipedream_execution_id); ?></code></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'cah_communication') {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save communication details
        if (isset($_POST['communication_details_nonce']) && wp_verify_nonce($_POST['communication_details_nonce'], 'communication_details_nonce')) {
            $fields = array('case_number', 'debtor_name', 'email_sender', 'email_received_date', 'has_attachment', 'message_id');
            
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
                }
            }
        }
        
        // Save case assignment
        if (isset($_POST['case_assignment_nonce']) && wp_verify_nonce($_POST['case_assignment_nonce'], 'case_assignment_nonce')) {
            $fields = array('assignment_status', 'matched_case_id', 'match_confidence');
            
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
                }
            }
            
            // Update the database table record as well
            global $wpdb;
            $db_record = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}cah_document_in_communications WHERE post_id = %d",
                $post_id
            ));
            
            if ($db_record) {
                $db_manager = new CAH_Document_in_DB_Manager();
                $db_manager->update_communication($db_record->id, array(
                    'assignment_status' => sanitize_text_field($_POST['assignment_status']),
                    'matched_case_id' => intval($_POST['matched_case_id']),
                    'match_confidence' => intval($_POST['match_confidence']),
                    'processed_by_user_id' => get_current_user_id(),
                    'processed_at' => current_time('mysql')
                ));
                
                // Log audit trail
                $db_manager->log_audit(array(
                    'communication_id' => $db_record->id,
                    'action_type' => 'manual_assignment',
                    'action_details' => 'Communication manually assigned to case ID: ' . intval($_POST['matched_case_id'])
                ));
            }
        }
    }
    
    /**
     * Create communication post from Pipedream data
     */
    public function create_communication_from_pipedream($data) {
        // Create the post
        $post_data = array(
            'post_title' => 'Communication from ' . $data['debtorName'] . ' - Case ' . $data['caseNumber'],
            'post_content' => $data['emailMetadata']['subject'],
            'post_type' => 'cah_communication',
            'post_status' => 'publish',
            'meta_input' => array(
                '_case_number' => $data['caseNumber'],
                '_debtor_name' => $data['debtorName'],
                '_email_sender' => $data['emailMetadata']['sender'],
                '_email_received_date' => $data['emailMetadata']['receivedDate'],
                '_has_attachment' => $data['emailMetadata']['hasAttachment'] ? 1 : 0,
                '_message_id' => $data['emailMetadata']['messageId'],
                '_summary' => $data['analysis']['summary'],
                '_category' => $data['analysis']['category'],
                '_is_new_case' => $data['analysis']['isNewCase'],
                '_confidence_score' => $data['analysis']['confidenceScore'],
                '_extracted_entities' => is_array($data['analysis']['extractedEntities']) ? implode(', ', $data['analysis']['extractedEntities']) : $data['analysis']['extractedEntities'],
                '_pipedream_execution_id' => $data['pipedream_execution_id'] ?? '',
                '_assignment_status' => 'unassigned'
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Set category taxonomy
            wp_set_object_terms($post_id, $data['analysis']['category'], 'communication_category');
            wp_set_object_terms($post_id, 'unassigned', 'communication_status');
            
            return $post_id;
        }
        
        return false;
    }
}