<?php
/**
 * Database Diagnostic Script
 * Run this to see exactly what tables exist and their data
 */

function diagnose_database_tables() {
    global $wpdb;
    
    echo '<div class="wrap">';
    echo '<h1>Database Diagnostic</h1>';
    
    // Get all tables
    $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_A);
    $existing_tables = array();
    foreach ($all_tables as $table) {
        $table_name = array_values($table)[0];
        if (strpos($table_name, $wpdb->prefix) === 0) {
            $existing_tables[] = $table_name;
        }
    }
    
    echo '<h2>All WordPress Tables:</h2>';
    echo '<ul>';
    foreach ($existing_tables as $table) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        $color = $count > 0 ? 'color: red; font-weight: bold;' : 'color: green;';
        echo '<li style="' . $color . '">' . esc_html($table) . ' (' . $count . ' records)</li>';
    }
    echo '</ul>';
    
    // Focus on case-related tables
    $case_tables = array_filter($existing_tables, function($table) {
        return (strpos($table, 'case') !== false || 
                strpos($table, 'klage') !== false || 
                strpos($table, 'legal') !== false ||
                strpos($table, 'court') !== false ||
                strpos($table, 'cah_') !== false ||
                strpos($table, 'la_') !== false);
    });
    
    echo '<h2>Case-Related Tables with Data:</h2>';
    if (empty($case_tables)) {
        echo '<p>No case-related tables found.</p>';
    } else {
        foreach ($case_tables as $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            if ($count > 0) {
                echo '<h3>' . esc_html($table) . ' (' . $count . ' records)</h3>';
                
                // Show sample data
                $sample_data = $wpdb->get_results("SELECT * FROM `$table` LIMIT 3", ARRAY_A);
                if (!empty($sample_data)) {
                    echo '<table border="1" style="border-collapse: collapse; margin: 10px 0;">';
                    echo '<tr>';
                    foreach (array_keys($sample_data[0]) as $column) {
                        echo '<th style="padding: 5px; background: #f0f0f0;">' . esc_html($column) . '</th>';
                    }
                    echo '</tr>';
                    
                    foreach ($sample_data as $row) {
                        echo '<tr>';
                        foreach ($row as $value) {
                            echo '<td style="padding: 5px;">' . esc_html(substr($value, 0, 50)) . (strlen($value) > 50 ? '...' : '') . '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
                // Provide manual purge button
                echo '<form method="post" style="margin: 10px 0;">';
                wp_nonce_field('manual_purge_table', 'manual_purge_nonce');
                echo '<input type="hidden" name="table_to_purge" value="' . esc_attr($table) . '">';
                echo '<input type="submit" name="manual_purge" value="🗑️ Purge This Table" class="button button-secondary" onclick="return confirm(\'Really purge ' . esc_js($table) . '?\');">';
                echo '</form>';
            }
        }
    }
    
    // Handle manual purge
    if (isset($_POST['manual_purge']) && wp_verify_nonce($_POST['manual_purge_nonce'], 'manual_purge_table')) {
        $table_to_purge = sanitize_text_field($_POST['table_to_purge']);
        if (in_array($table_to_purge, $existing_tables)) {
            $count_before = $wpdb->get_var("SELECT COUNT(*) FROM `$table_to_purge`");
            $wpdb->query("TRUNCATE TABLE `$table_to_purge`");
            $wpdb->query("ALTER TABLE `$table_to_purge` AUTO_INCREMENT = 1");
            echo '<div class="notice notice-success"><p>✅ Purged ' . esc_html($table_to_purge) . ' (' . $count_before . ' records deleted)</p></div>';
        }
    }
    
    echo '</div>';
}

// Add this as a temporary admin page
add_action('admin_menu', function() {
    add_submenu_page(
        'legal-automation',
        'Database Diagnostic',
        '🔍 DB Diagnostic',
        'manage_options',
        'database-diagnostic',
        'diagnose_database_tables'
    );
});
?>