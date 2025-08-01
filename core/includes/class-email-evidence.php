<?php
/**
 * Email Evidence class - Simplified version
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAH_Email_Evidence {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Process email evidence
     */
    public function process_email_evidence($email_data) {
        // Basic email evidence processing
        return array(
            'sender_email' => $email_data['emails_sender_email'],
            'user_email' => $email_data['emails_user_email'],
            'subject' => $email_data['emails_subject'],
            'content' => $email_data['emails_content'],
            'gdpr_violation' => true
        );
    }
}