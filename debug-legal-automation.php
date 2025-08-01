<?php
/**
 * Debug helper to test if new functionality is loading
 * Add this to WordPress to see what's happening
 */

// Check if unified menu class exists
function debug_legal_automation() {
    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
    echo "<h3>Legal Automation Debug Info</h3>";
    
    // Check if classes exist
    echo "<p><strong>Core Plugin Active:</strong> " . (class_exists('CourtAutomationHub') ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Unified Menu Class:</strong> " . (class_exists('Legal_Automation_Unified_Menu') ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Enhanced CRM Class:</strong> " . (class_exists('Enhanced_CRM_Contacts_Manager') ? 'YES' : 'NO') . "</p>";
    
    // Check if unified menu instance exists
    if (class_exists('Legal_Automation_Unified_Menu')) {
        echo "<p><strong>Unified Menu Instance:</strong> YES</p>";
    }
    
    // Check current menu structure
    global $menu;
    echo "<p><strong>Current Admin Menus:</strong></p><ul>";
    foreach ($menu as $menu_item) {
        if (isset($menu_item[0]) && $menu_item[0]) {
            echo "<li>" . strip_tags($menu_item[0]) . " (" . $menu_item[2] . ")</li>";
        }
    }
    echo "</ul>";
    
    echo "</div>";
}

// Add to admin pages to debug
add_action('admin_notices', 'debug_legal_automation');
?>