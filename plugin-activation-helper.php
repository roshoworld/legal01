<?php
/**
 * Plugin Suite Activation Helper
 * Run this once to ensure all plugins are properly activated
 */

// Add this to a temporary PHP file and run it via WordPress admin

function reactivate_legal_automation_suite() {
    $plugins_to_activate = array(
        'legal-automation-core/court-automation-hub.php',
        'legal-automation-admin/legal-automation-admin.php', 
        'legal-automation-finance/legal-automation-finance.php',
        'legal-automation-doc-in/court-automation-hub-document-analysis.php',
        'legal-automation-doc-out/klage-click-doc-out.php',
        'legal-automation-crm/legal-automation-crm.php',
        'legal-automation-import/legal-automation-import.php'
    );
    
    foreach ($plugins_to_activate as $plugin) {
        if (!is_plugin_active($plugin)) {
            $result = activate_plugin($plugin);
            if (is_wp_error($result)) {
                echo "Error activating $plugin: " . $result->get_error_message() . "<br>";
            } else {
                echo "✅ Activated: $plugin<br>";
            }
        } else {
            echo "✅ Already active: $plugin<br>";
        }
    }
}

// Uncomment the line below to run
// reactivate_legal_automation_suite();
?>