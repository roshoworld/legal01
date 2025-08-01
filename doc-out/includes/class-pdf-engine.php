<?php
/**
 * PDF Engine Class
 * 
 * Handles PDF generation using mPDF library
 * 
 * @package KlageClickDocOut
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_PDF_Engine {
    
    private $mpdf;
    private $temp_dir;
    
    public function __construct() {
        $this->temp_dir = KCDO_PLUGIN_PATH . 'temp/';
        
        // Create temp directory if it doesn't exist
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
        
        // Initialize mPDF when needed
        add_action('init', array($this, 'maybe_load_mpdf'));
    }
    
    /**
     * Load mPDF library if needed
     */
    public function maybe_load_mpdf() {
        // Only load mPDF when actually generating PDFs
        if (!$this->is_pdf_generation_request()) {
            return;
        }
        
        $this->load_mpdf_library();
    }
    
    /**
     * Check if this is a PDF generation request
     * 
     * @return bool
     */
    private function is_pdf_generation_request() {
        return (
            (isset($_POST['action']) && $_POST['action'] === 'generate_pdf') ||
            (isset($_GET['action']) && $_GET['action'] === 'download_pdf') ||
            (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'klage-doc') !== false)
        );
    }
    
    /**
     * Load mPDF library
     */
    private function load_mpdf_library() {
        // Check for mPDF in common locations
        $mpdf_locations = array(
            KCDO_PLUGIN_PATH . 'vendor/autoload.php',
            ABSPATH . 'vendor/autoload.php',
            WP_CONTENT_DIR . '/vendor/autoload.php'
        );
        
        foreach ($mpdf_locations as $autoloader) {
            if (file_exists($autoloader)) {
                require_once $autoloader;
                if (class_exists('Mpdf\Mpdf')) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Initialize mPDF with default settings
     * 
     * @param array $config Configuration options
     * @return bool Success status
     */
    private function initialize_mpdf($config = array()) {
        if (!class_exists('Mpdf\Mpdf')) {
            return false;
        }
        
        $default_config = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 12,
            'default_font' => 'dejavusans',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
            'tempDir' => $this->temp_dir
        );
        
        $config = wp_parse_args($config, $default_config);
        
        try {
            $this->mpdf = new \Mpdf\Mpdf($config);
            
            // Set additional properties
            $this->mpdf->SetDisplayMode('fullpage');
            $this->mpdf->useAdobeCJK = true;
            $this->mpdf->autoScriptToLang = true;
            $this->mpdf->autoLangToFont = true;
            
            return true;
        } catch (Exception $e) {
            error_log('mPDF initialization error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate PDF from HTML content
     * 
     * @param string $html HTML content
     * @param array $options PDF generation options
     * @return string|WP_Error PDF file path or error
     */
    public function generate_pdf($html, $options = array()) {
        // Try mPDF first
        if ($this->load_mpdf_library() && $this->initialize_mpdf($options['config'] ?? array())) {
            return $this->generate_with_mpdf($html, $options);
        }
        
        // Fallback to simple PDF generator
        return $this->generate_with_fallback($html, $options);
    }
    
    /**
     * Generate PDF using mPDF
     */
    private function generate_with_mpdf($html, $options) {
        try {
            // Add custom CSS for legal documents
            $css = $this->get_default_pdf_styles();
            $this->mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
            
            // Add the HTML content
            $this->mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
            
            if ($options['return_content'] ?? false) {
                // Return PDF content as string
                return $this->mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
            } else {
                // Save to file
                $file_path = $this->temp_dir . sanitize_file_name($options['filename'] ?? 'document.pdf');
                $this->mpdf->Output($file_path, \Mpdf\Output\Destination::FILE);
                
                if (file_exists($file_path)) {
                    return $file_path;
                } else {
                    return new WP_Error('pdf_save_error', __('Failed to save PDF file.', 'klage-click-doc-out'));
                }
            }
            
        } catch (Exception $e) {
            error_log('mPDF generation error: ' . $e->getMessage());
            // Fallback to simple generator
            return $this->generate_with_fallback($html, $options);
        }
    }
    
    /**
     * Generate document using fallback method
     */
    private function generate_with_fallback($html, $options) {
        $simple_generator = new KCDO_Simple_PDF_Generator();
        
        $filename = $options['filename'] ?? 'document.pdf';
        $html_filename = str_replace('.pdf', '.html', $filename);
        
        // Generate HTML document
        $html_content = $simple_generator->generate_pdf_from_html($html, $html_filename);
        
        // Save to temp file
        $file_path = $this->temp_dir . sanitize_file_name($html_filename);
        file_put_contents($file_path, $html_content);
        
        return $file_path;
    }
    
    /**
     * Generate PDF from template and data
     * 
     * @param object $template Template object
     * @param array $data Data to replace placeholders
     * @param array $options PDF generation options
     * @return string|WP_Error PDF file path or error
     */
    public function generate_pdf_from_template($template, $data, $options = array()) {
        if (!$template || empty($template->template_html)) {
            return new WP_Error('invalid_template', __('Invalid template provided.', 'klage-click-doc-out'));
        }
        
        // Replace placeholders in template
        $html = $this->replace_placeholders($template->template_html, $data);
        
        // Set filename based on template name if not provided
        if (empty($options['filename'])) {
            $options['filename'] = sanitize_file_name($template->template_slug . '_' . date('Y-m-d_H-i-s') . '.pdf');
        }
        
        return $this->generate_pdf($html, $options);
    }
    
    /**
     * Replace placeholders in HTML with actual data
     * 
     * @param string $html HTML content with placeholders
     * @param array $data Data array
     * @return string Processed HTML
     */
    private function replace_placeholders($html, $data) {
        // Flatten nested arrays for easier placeholder replacement
        $flattened_data = $this->flatten_array($data);
        
        foreach ($flattened_data as $key => $value) {
            // Handle different data types
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif (is_object($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? __('Yes', 'klage-click-doc-out') : __('No', 'klage-click-doc-out');
            } elseif (is_null($value)) {
                $value = '';
            }
            
            // Escape HTML for security
            $value = esc_html((string) $value);
            
            // Replace placeholder
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        
        // Clean up any remaining placeholders
        $html = preg_replace('/\{\{[^}]+\}\}/', '', $html);
        
        return $html;
    }
    
    /**
     * Flatten multi-dimensional array
     * 
     * @param array $array Input array
     * @param string $prefix Key prefix
     * @return array Flattened array
     */
    private function flatten_array($array, $prefix = '') {
        $result = array();
        
        foreach ($array as $key => $value) {
            $new_key = $prefix . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten_array($value, $new_key . '_'));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Get default CSS styles for PDF documents
     * 
     * @return string CSS styles
     */
    private function get_default_pdf_styles() {
        return '
            <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 12pt;
                line-height: 1.4;
                color: #333;
                margin: 0;
                padding: 0;
            }
            
            h1, h2, h3, h4, h5, h6 {
                color: #2c3e50;
                margin-top: 20px;
                margin-bottom: 10px;
                font-weight: bold;
            }
            
            h1 { font-size: 18pt; }
            h2 { font-size: 16pt; }
            h3 { font-size: 14pt; }
            h4, h5, h6 { font-size: 12pt; }
            
            p {
                margin: 8px 0;
                text-align: justify;
            }
            
            .header {
                text-align: right;
                margin-bottom: 30px;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            
            .recipient {
                margin: 30px 0;
                padding: 15px;
                background: #f8f9fa;
                border-left: 4px solid #007cba;
            }
            
            .subject {
                font-weight: bold;
                font-size: 14pt;
                margin: 20px 0;
                text-align: center;
            }
            
            .content {
                margin: 20px 0;
            }
            
            .signature {
                margin-top: 40px;
                text-align: right;
            }
            
            .footer {
                margin-top: 30px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
                font-size: 10pt;
                color: #666;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            
            .amount {
                font-weight: bold;
                font-size: 14pt;
                color: #d32f2f;
            }
            
            .highlight {
                background-color: #fff3cd;
                padding: 10px;
                border: 1px solid #ffeaa7;
                margin: 10px 0;
            }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .font-bold { font-weight: bold; }
            .font-italic { font-style: italic; }
            
            @page {
                margin: 2cm;
                footer: html_footer;
            }
            </style>
        ';
    }
    
    /**
     * Serve PDF file for download
     * 
     * @param string $file_path PDF file path
     * @param string $filename Download filename
     * @param bool $delete_after_download Delete file after serving
     */
    public function serve_pdf_download($file_path, $filename = '', $delete_after_download = true) {
        if (!file_exists($file_path)) {
            wp_die(__('Document file not found.', 'klage-click-doc-out'));
        }
        
        if (empty($filename)) {
            $filename = basename($file_path);
        }
        
        // Determine file type
        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
        
        if ($file_extension === 'html') {
            // Serve HTML file for browser-based PDF generation
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            // Serve PDF file
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output file content
        readfile($file_path);
        
        // Clean up temporary file if requested
        if ($delete_after_download) {
            unlink($file_path);
        }
        
        exit;
    }
    
    /**
     * Clean up old temporary files
     * 
     * @param int $older_than_hours Delete files older than X hours
     */
    public function cleanup_temp_files($older_than_hours = 24) {
        if (!file_exists($this->temp_dir)) {
            return;
        }
        
        $files = glob($this->temp_dir . '*.pdf');
        $cutoff_time = time() - ($older_than_hours * 3600);
        
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get PDF engine status and information
     * 
     * @return array Status information
     */
    public function get_engine_status() {
        $status = array(
            'mpdf_available' => false,
            'mpdf_version' => null,
            'temp_dir_writable' => false,
            'temp_dir_path' => $this->temp_dir,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        );
        
        // Check mPDF availability
        if (class_exists('Mpdf\Mpdf')) {
            $status['mpdf_available'] = true;
            if (defined('Mpdf\VERSION')) {
                $status['mpdf_version'] = \Mpdf\VERSION;
            }
        }
        
        // Check temp directory
        $status['temp_dir_writable'] = is_writable($this->temp_dir);
        
        return $status;
    }
    
    /**
     * Display admin notice when mPDF is missing
     */
    public function mpdf_missing_notice() {
        echo '<div class="notice notice-warning"><p>';
        echo __('<strong>Document Generator:</strong> mPDF library not found. Documents will be generated as formatted HTML files that can be printed as PDF using your browser. For full PDF generation, please install mPDF using Composer.', 'klage-click-doc-out');
        echo '</p></div>';
    }
}