<?php
/**
 * Simple PDF Generator using TCPDF (WordPress Core)
 * 
 * Fallback for when mPDF is not available
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KCDO_Simple_PDF_Generator {
    
    public function generate_pdf_from_html($html, $filename = 'document.pdf') {
        // Check if TCPDF is available in WordPress
        if (class_exists('TCPDF')) {
            return $this->generate_with_tcpdf($html, $filename);
        }
        
        // Fallback: Return HTML content for download
        return $this->generate_html_download($html, $filename);
    }
    
    private function generate_with_tcpdf($html, $filename) {
        // Use TCPDF if available
        $pdf = new TCPDF();
        $pdf->SetCreator('Klage.Click Document Generator');
        $pdf->SetTitle($filename);
        $pdf->SetSubject('Generated Document');
        
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        return $pdf->Output($filename, 'S'); // Return as string
    }
    
    private function generate_html_download($html, $filename) {
        // Create a formatted HTML document for download
        $html_document = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html(str_replace('.pdf', '', $filename)) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .print-notice { background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-left: 4px solid #0073aa; }
    </style>
</head>
<body>
    <div class="print-notice">
        <strong>Note:</strong> This document is ready for printing. Use your browser\'s print function (Ctrl+P) to save as PDF.
    </div>
    ' . $html . '
</body>
</html>';
        
        return $html_document;
    }
}