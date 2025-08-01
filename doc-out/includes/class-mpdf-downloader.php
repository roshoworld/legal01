<?php
/**
 * mPDF Downloader Class
 * 
 * Downloads and installs mPDF library on first use
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_mPDF_Downloader {
    
    private $mpdf_dir;
    private $download_url = 'https://github.com/mpdf/mpdf/releases/download/v8.2.5/mpdf-8.2.5.zip';
    
    public function __construct() {
        $this->mpdf_dir = WP_CONTENT_DIR . '/uploads/klage-mpdf/';
    }
    
    /**
     * Check if mPDF is installed
     */
    public function is_mpdf_installed() {
        return file_exists($this->mpdf_dir . 'vendor/autoload.php');
    }
    
    /**
     * Download and install mPDF
     */
    public function install_mpdf() {
        try {
            // Create directory if it doesn't exist
            if (!file_exists($this->mpdf_dir)) {
                wp_mkdir_p($this->mpdf_dir);
            }
            
            // Download the zip file
            $zip_file = $this->mpdf_dir . 'mpdf.zip';
            $download_result = $this->download_file($this->download_url, $zip_file);
            
            if (!$download_result) {
                throw new Exception('Failed to download mPDF');
            }
            
            // Extract the zip file
            $extract_result = $this->extract_zip($zip_file, $this->mpdf_dir);
            
            if (!$extract_result) {
                throw new Exception('Failed to extract mPDF');
            }
            
            // Clean up zip file
            unlink($zip_file);
            
            // Create a simple composer.json for mPDF
            $this->create_composer_setup();
            
            return true;
            
        } catch (Exception $e) {
            error_log('mPDF installation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Download file using WordPress HTTP API
     */
    private function download_file($url, $destination) {
        $response = wp_remote_get($url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $destination
        ));
        
        if (is_wp_error($response)) {
            error_log('Download error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }
    
    /**
     * Extract zip file
     */
    private function extract_zip($zip_file, $destination) {
        $zip = new ZipArchive();
        $result = $zip->open($zip_file);
        
        if ($result !== TRUE) {
            error_log('Cannot open zip file: ' . $zip_file);
            return false;
        }
        
        $extract_result = $zip->extractTo($destination);
        $zip->close();
        
        return $extract_result;
    }
    
    /**
     * Create minimal composer setup
     */
    private function create_composer_setup() {
        $composer_content = '{
    "require": {
        "mpdf/mpdf": "^8.2"
    },
    "autoload": {
        "psr-4": {
            "Mpdf\\\\": "src/"
        }
    }
}';
        
        file_put_contents($this->mpdf_dir . 'composer.json', $composer_content);
    }
    
    /**
     * Get mPDF autoloader path
     */
    public function get_autoloader_path() {
        if ($this->is_mpdf_installed()) {
            return $this->mpdf_dir . 'vendor/autoload.php';
        }
        return false;
    }
    
    /**
     * Get installation status message
     */
    public function get_status_message() {
        if ($this->is_mpdf_installed()) {
            return 'mPDF is installed and ready';
        } else {
            return 'mPDF needs to be installed';
        }
    }
}