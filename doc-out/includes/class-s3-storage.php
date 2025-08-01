<?php
/**
 * S3 Storage Class
 * 
 * Handles encrypted template storage to external S3 (IONOS) cloud storage
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_S3_Storage {
    
    private $s3_client;
    private $bucket_name;
    private $encryption_key;
    private $is_enabled;
    
    public function __construct() {
        $this->is_enabled = get_option('klage_doc_s3_enabled', false);
        $this->bucket_name = get_option('klage_doc_s3_bucket', '');
        $this->encryption_key = get_option('klage_doc_encryption_key', '');
        
        if ($this->is_enabled) {
            $this->initialize_s3_client();
        }
        
        // Add settings hooks
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Initialize S3 client (AWS SDK or compatible)
     */
    private function initialize_s3_client() {
        // Check if AWS SDK is available
        if (!class_exists('Aws\S3\S3Client')) {
            add_action('admin_notices', array($this, 'aws_sdk_missing_notice'));
            return false;
        }
        
        try {
            $this->s3_client = new Aws\S3\S3Client([
                'version' => 'latest',
                'region' => get_option('klage_doc_s3_region', 'eu-central-1'),
                'endpoint' => get_option('klage_doc_s3_endpoint', ''),
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => get_option('klage_doc_s3_access_key', ''),
                    'secret' => get_option('klage_doc_s3_secret_key', '')
                ]
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('S3 client initialization error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload encrypted template to S3
     * 
     * @param int $template_id Template ID
     * @param string $template_html Template HTML content
     * @return string|WP_Error S3 URL or error
     */
    public function upload_template($template_id, $template_html) {
        if (!$this->is_enabled || !$this->s3_client) {
            return new WP_Error('s3_not_configured', __('S3 storage is not properly configured.', 'klage-click-doc-out'));
        }
        
        try {
            // Encrypt template content
            $encrypted_content = $this->encrypt_content($template_html);
            
            if (is_wp_error($encrypted_content)) {
                return $encrypted_content;
            }
            
            // Generate S3 key
            $s3_key = 'templates/' . $template_id . '/' . date('Y/m/d') . '/' . uniqid() . '.enc';
            
            // Upload to S3
            $result = $this->s3_client->putObject([
                'Bucket' => $this->bucket_name,
                'Key' => $s3_key,
                'Body' => $encrypted_content,
                'ContentType' => 'application/octet-stream',
                'ServerSideEncryption' => 'AES256',
                'Metadata' => [
                    'template-id' => (string) $template_id,
                    'upload-date' => date('c'),
                    'encrypted' => 'true'
                ]
            ]);
            
            if (isset($result['ObjectURL'])) {
                return $result['ObjectURL'];
            } else {
                return new WP_Error('s3_upload_failed', __('Failed to upload template to S3.', 'klage-click-doc-out'));
            }
            
        } catch (Exception $e) {
            error_log('S3 upload error: ' . $e->getMessage());
            return new WP_Error('s3_upload_error', sprintf(__('S3 upload error: %s', 'klage-click-doc-out'), $e->getMessage()));
        }
    }
    
    /**
     * Download and decrypt template from S3
     * 
     * @param string $s3_url S3 URL
     * @return string|WP_Error Decrypted content or error
     */
    public function download_template($s3_url) {
        if (!$this->is_enabled || !$this->s3_client) {
            return new WP_Error('s3_not_configured', __('S3 storage is not properly configured.', 'klage-click-doc-out'));
        }
        
        try {
            // Extract bucket and key from URL
            $url_parts = parse_url($s3_url);
            $path_parts = explode('/', trim($url_parts['path'], '/'));
            $bucket = array_shift($path_parts);
            $key = implode('/', $path_parts);
            
            // Download from S3
            $result = $this->s3_client->getObject([
                'Bucket' => $bucket ?: $this->bucket_name,
                'Key' => $key
            ]);
            
            $encrypted_content = (string) $result['Body'];
            
            // Decrypt content
            $decrypted_content = $this->decrypt_content($encrypted_content);
            
            if (is_wp_error($decrypted_content)) {
                return $decrypted_content;
            }
            
            return $decrypted_content;
            
        } catch (Exception $e) {
            error_log('S3 download error: ' . $e->getMessage());
            return new WP_Error('s3_download_error', sprintf(__('S3 download error: %s', 'klage-click-doc-out'), $e->getMessage()));
        }
    }
    
    /**
     * Delete template from S3
     * 
     * @param string $s3_url S3 URL
     * @return bool|WP_Error Success or error
     */
    public function delete_template($s3_url) {
        if (!$this->is_enabled || !$this->s3_client) {
            return new WP_Error('s3_not_configured', __('S3 storage is not properly configured.', 'klage-click-doc-out'));
        }
        
        try {
            // Extract bucket and key from URL
            $url_parts = parse_url($s3_url);
            $path_parts = explode('/', trim($url_parts['path'], '/'));
            $bucket = array_shift($path_parts);
            $key = implode('/', $path_parts);
            
            // Delete from S3
            $this->s3_client->deleteObject([
                'Bucket' => $bucket ?: $this->bucket_name,
                'Key' => $key
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('S3 delete error: ' . $e->getMessage());
            return new WP_Error('s3_delete_error', sprintf(__('S3 delete error: %s', 'klage-click-doc-out'), $e->getMessage()));
        }
    }
    
    /**
     * Encrypt content using AES-256-CBC
     * 
     * @param string $content Content to encrypt
     * @return string|WP_Error Encrypted content or error
     */
    private function encrypt_content($content) {
        if (empty($this->encryption_key)) {
            return new WP_Error('no_encryption_key', __('Encryption key is not set.', 'klage-click-doc-out'));
        }
        
        try {
            $method = 'AES-256-CBC';
            $key = hash('sha256', $this->encryption_key, true);
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
            
            $encrypted = openssl_encrypt($content, $method, $key, OPENSSL_RAW_DATA, $iv);
            
            if ($encrypted === false) {
                return new WP_Error('encryption_failed', __('Content encryption failed.', 'klage-click-doc-out'));
            }
            
            // Combine IV and encrypted content
            return base64_encode($iv . $encrypted);
            
        } catch (Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            return new WP_Error('encryption_error', sprintf(__('Encryption error: %s', 'klage-click-doc-out'), $e->getMessage()));
        }
    }
    
    /**
     * Decrypt content using AES-256-CBC
     * 
     * @param string $encrypted_content Encrypted content
     * @return string|WP_Error Decrypted content or error
     */
    private function decrypt_content($encrypted_content) {
        if (empty($this->encryption_key)) {
            return new WP_Error('no_encryption_key', __('Encryption key is not set.', 'klage-click-doc-out'));
        }
        
        try {
            $method = 'AES-256-CBC';
            $key = hash('sha256', $this->encryption_key, true);
            $data = base64_decode($encrypted_content);
            
            $iv_length = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);
            
            $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                return new WP_Error('decryption_failed', __('Content decryption failed.', 'klage-click-doc-out'));
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            return new WP_Error('decryption_error', sprintf(__('Decryption error: %s', 'klage-click-doc-out'), $e->getMessage()));
        }
    }
    
    /**
     * Test S3 connection
     * 
     * @return bool|WP_Error Success or error
     */
    public function test_connection() {
        if (!$this->is_enabled || !$this->s3_client) {
            return new WP_Error('s3_not_configured', __('S3 storage is not properly configured.', 'klage-click-doc-out'));
        }
        
        try {
            // Try to list objects (minimal operation)
            $this->s3_client->listObjects([
                'Bucket' => $this->bucket_name,
                'MaxKeys' => 1
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('S3 connection test error: ' . $e->getMessage());
            return new WP_Error('s3_connection_failed', sprintf(__('S3 connection test failed: %s', 'klage-click-doc-out'), $e->getMessage()));
        }
    }
    
    /**
     * Get S3 storage statistics
     * 
     * @return array Storage statistics
     */
    public function get_storage_stats() {
        $stats = array(
            's3_enabled' => $this->is_enabled,
            'bucket_name' => $this->bucket_name,
            'connection_status' => false,
            'total_templates' => 0,
            'storage_used' => 0
        );
        
        if ($this->is_enabled && $this->s3_client) {
            try {
                // Test connection
                $connection_test = $this->test_connection();
                $stats['connection_status'] = !is_wp_error($connection_test);
                
                if ($stats['connection_status']) {
                    // Get storage statistics
                    $result = $this->s3_client->listObjects([
                        'Bucket' => $this->bucket_name,
                        'Prefix' => 'templates/'
                    ]);
                    
                    if (isset($result['Contents'])) {
                        $stats['total_templates'] = count($result['Contents']);
                        $stats['storage_used'] = array_sum(array_column($result['Contents'], 'Size'));
                    }
                }
                
            } catch (Exception $e) {
                error_log('S3 stats error: ' . $e->getMessage());
            }
        }
        
        return $stats;
    }
    
    /**
     * Register S3 settings
     */
    public function register_settings() {
        register_setting('klage_doc_s3_settings', 'klage_doc_s3_enabled');
        register_setting('klage_doc_s3_settings', 'klage_doc_s3_access_key');
        register_setting('klage_doc_s3_settings', 'klage_doc_s3_secret_key');
        register_setting('klage_doc_s3_settings', 'klage_doc_s3_bucket');
        register_setting('klage_doc_s3_settings', 'klage_doc_s3_region');
        register_setting('klage_doc_s3_settings', 'klage_doc_s3_endpoint');
        register_setting('klage_doc_s3_settings', 'klage_doc_encryption_key');
    }
    
    /**
     * Display notice when AWS SDK is missing
     */
    public function aws_sdk_missing_notice() {
        echo '<div class="notice notice-warning"><p>';
        echo __('AWS SDK for PHP is required for S3 storage functionality but not found. Please install the SDK using Composer.', 'klage-click-doc-out');
        echo '</p></div>';
    }
    
    /**
     * Check if S3 is properly configured
     * 
     * @return bool Configuration status
     */
    public function is_configured() {
        return $this->is_enabled && 
               !empty($this->bucket_name) && 
               !empty(get_option('klage_doc_s3_access_key')) && 
               !empty(get_option('klage_doc_s3_secret_key'));
    }
    
    /**
     * Generate encryption key
     * 
     * @return string Generated key
     */
    public static function generate_encryption_key() {
        return bin2hex(random_bytes(32));
    }
}