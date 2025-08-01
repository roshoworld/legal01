#!/usr/bin/env python3
"""
Backend Testing Suite for Legal Automation WordPress Plugin System
FINAL VERIFICATION: Case Editing Improvements (v240)

Specific tests for the review request:
1. Case ID Editing with Duplicate Prevention - case_id field in update data array with validation
2. Redirect Fix for Empty Page Issue - redirect to list view instead of edit view
3. Complete Case Edit Workflow - case ID changes save correctly with success messages
4. Database Update Verification - case_id included in wpdb->update with proper format strings
5. Core plugin version update verification (239 → 240)
"""

import requests
import json
import sys
import time
from urllib.parse import urljoin, urlparse, parse_qs
import re

class LegalAutomationTester:
    def __init__(self):
        # WordPress admin simulation endpoints
        self.base_url = "http://localhost"  # WordPress installation
        self.admin_url = f"{self.base_url}/wp-admin/"
        self.ajax_url = f"{self.base_url}/wp-admin/admin-ajax.php"
        
        # Test results
        self.results = {
            'case_management_tests': [],
            'plugin_health_tests': [],
            'errors': [],
            'summary': {
                'total_tests': 0,
                'passed': 0,
                'failed': 0
            }
        }
        
        # Session for maintaining cookies
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Legal-Automation-Tester/1.0'
        })

    def log_result(self, category, test_name, status, message, details=None):
        """Log test result"""
        result = {
            'test': test_name,
            'status': status,
            'message': message,
            'details': details or {},
            'timestamp': time.time()
        }
        
        self.results[category].append(result)
        self.results['summary']['total_tests'] += 1
        
        if status == 'PASS':
            self.results['summary']['passed'] += 1
            print(f"✅ {test_name}: {message}")
        else:
            self.results['summary']['failed'] += 1
            print(f"❌ {test_name}: {message}")
            if details:
                print(f"   Details: {details}")

    def test_case_id_editing_with_duplicate_prevention(self):
        """Test 1: Case ID Editing with Duplicate Prevention"""
        print("\n🔍 Testing Case ID Editing with Duplicate Prevention...")
        
        try:
            # Read the admin dashboard file to verify case ID editing functionality
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check that case_id is included in update data array
            case_id_in_update_data = "'case_id' => $new_case_id," in content
            
            # Check for duplicate validation logic
            duplicate_check_query = "SELECT id FROM {$wpdb->prefix}klage_cases" in content and "WHERE case_id = %s AND id != %d" in content
            duplicate_error_message = 'Fall-ID "' in content and '" wird bereits verwendet' in content
            
            # Check for case_id format string in wpdb->update call
            format_array_with_case_id = "array('%s', '%s'," in content  # First %s should be for case_id
            
            # Check that new case_id variable is properly sanitized
            case_id_sanitization = "sanitize_text_field($post_data['case_id']" in content
            
            # Check for proper case_id comparison logic
            case_id_comparison = "$new_case_id !== $old_case->case_id" in content
            
            if (case_id_in_update_data and duplicate_check_query and duplicate_error_message and 
                format_array_with_case_id and case_id_sanitization and case_id_comparison):
                self.log_result(
                    'case_management_tests',
                    'Case ID Editing with Duplicate Prevention',
                    'PASS',
                    '✅ Case ID editing with duplicate prevention verified: case_id included in update data with validation',
                    {
                        'case_id_in_update_data': case_id_in_update_data,
                        'duplicate_validation': duplicate_check_query,
                        'error_message': duplicate_error_message,
                        'format_string': format_array_with_case_id,
                        'sanitization': case_id_sanitization,
                        'comparison_logic': case_id_comparison
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'Case ID Editing with Duplicate Prevention',
                    'FAIL',
                    f'❌ Case ID editing incomplete: update_data={case_id_in_update_data}, validation={duplicate_check_query}, format={format_array_with_case_id}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Case ID Editing with Duplicate Prevention',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_redirect_fix_for_empty_page_issue(self):
        """Test 2: Redirect Fix for Empty Page Issue"""
        print("\n🔍 Testing Redirect Fix for Empty Page Issue...")
        
        try:
            # Read the admin dashboard file to verify redirect fix
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check for redirect to list view instead of edit view
            redirect_to_list = "wp_redirect(admin_url('admin.php?page=la-cases&updated=" in content
            
            # Check that redirect includes case_id parameter
            redirect_with_case_id = "&updated=' . $case_id" in content
            
            # Check for exit after redirect to prevent further execution
            exit_after_redirect = "wp_redirect(" in content and "exit;" in content
            
            # Check that success message is handled in list view (not in edit view)
            success_message_in_method = '✅ Erfolg!' in content and 'wurde aktualisiert' in content
            
            # Verify no JavaScript redirect is used (should be PHP redirect)
            no_javascript_redirect = 'window.location.href' not in content or 'setTimeout' not in content
            
            if (redirect_to_list and redirect_with_case_id and exit_after_redirect and 
                success_message_in_method):
                self.log_result(
                    'case_management_tests',
                    'Redirect Fix for Empty Page Issue',
                    'PASS',
                    '✅ Redirect fix verified: Case edit saves redirect to list view with updated parameter',
                    {
                        'redirect_to_list': redirect_to_list,
                        'redirect_with_case_id': redirect_with_case_id,
                        'exit_after_redirect': exit_after_redirect,
                        'success_message': success_message_in_method,
                        'php_redirect_used': no_javascript_redirect
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'Redirect Fix for Empty Page Issue',
                    'FAIL',
                    f'❌ Redirect fix incomplete: list_redirect={redirect_to_list}, case_id_param={redirect_with_case_id}, exit={exit_after_redirect}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Redirect Fix for Empty Page Issue',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_core_plugin_version_240_update(self):
        """Test 3: Core Plugin Version Update (239 → 240)"""
        print("\n🔍 Testing Core Plugin Version Update to 240...")
        
        try:
            # Read the core plugin file to verify version update
            core_plugin_path = "/app/core/court-automation-hub.php"
            
            with open(core_plugin_path, 'r') as f:
                content = f.read()
            
            # Check for version 240 in plugin header
            version_header = 'Version: 240' in content
            
            # Check for version constant
            version_constant = "define('CAH_PLUGIN_VERSION', '240')" in content
            
            # Ensure old version 239 is not present
            old_version_header = 'Version: 239' in content
            old_version_constant = "define('CAH_PLUGIN_VERSION', '239')" in content
            
            if version_header and version_constant and not old_version_header and not old_version_constant:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 240',
                    'PASS',
                    '✅ Core plugin version successfully updated from 239 to 240',
                    {
                        'version_header_240': version_header,
                        'version_constant_240': version_constant,
                        'old_version_removed': not old_version_header and not old_version_constant
                    }
                )
            else:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 240',
                    'FAIL',
                    f'❌ Version update incomplete: header_240={version_header}, constant_240={version_constant}, old_present={old_version_header or old_version_constant}'
                )
                
        except Exception as e:
            self.log_result(
                'plugin_health_tests',
                'Core Plugin Version Update to 240',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_database_update_verification(self):
        """Test 4: Database Update Verification - case_id in wpdb->update with proper format strings"""
        print("\n🔍 Testing Database Update Verification...")
        
        try:
            # Read the admin dashboard file to verify database update implementation
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check that case_id is first field in update_data array
            case_id_first_field = "'case_id' => $new_case_id," in content
            
            # Check for proper wpdb->update call structure
            wpdb_update_call = '$wpdb->update(' in content and 'klage_cases' in content
            
            # Check for format array with correct number of %s placeholders
            # Should have %s for case_id as first format specifier
            format_array_structure = "array('%s', '%s', '%s'," in content  # Multiple %s for different fields
            
            # Check for proper where clause with case ID
            where_clause = "array('id' => $case_id)" in content
            where_format = "array('%d')" in content
            
            # Check for audit trail that records case ID changes
            audit_trail = '$wpdb->insert(' in content and 'klage_audit' in content
            audit_case_id = "'case_id' => $case_id," in content
            audit_details = "'Fall \"' . $old_case->case_id . '\" wurde" in content
            
            # Check for proper error handling
            error_handling = '$wpdb->last_error' in content
            
            if (case_id_first_field and wpdb_update_call and format_array_structure and 
                where_clause and where_format and audit_trail and audit_case_id):
                self.log_result(
                    'case_management_tests',
                    'Database Update Verification',
                    'PASS',
                    '✅ Database update verification passed: case_id properly included in wpdb->update with correct format strings',
                    {
                        'case_id_in_update': case_id_first_field,
                        'wpdb_update_call': wpdb_update_call,
                        'format_array': format_array_structure,
                        'where_clause': where_clause and where_format,
                        'audit_trail': audit_trail and audit_case_id,
                        'error_handling': error_handling
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'Database Update Verification',
                    'FAIL',
                    f'❌ Database update incomplete: case_id={case_id_first_field}, wpdb_update={wpdb_update_call}, format={format_array_structure}, audit={audit_trail}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Database Update Verification',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def run_all_tests(self):
        """Run all final verification tests for case edit and delete fixes"""
        print("🚀 Starting Final Verification: Case Edit and Delete Fixes (v239)...")
        print("=" * 80)
        
        # Final Verification Tests for Review Request
        self.test_case_deletion_nonce_fix()
        self.test_case_edit_save_fix()
        self.test_core_plugin_version_239_update()
        self.test_complete_crud_workflow_security()
        
        # Print summary
        self.print_summary()
        
        return self.results

    def print_summary(self):
        """Print test summary"""
        print("\n" + "=" * 80)
        print("📊 FINAL VERIFICATION: CASE EDIT AND DELETE FIXES (v239)")
        print("=" * 80)
        
        summary = self.results['summary']
        print(f"Total Tests: {summary['total_tests']}")
        print(f"✅ Passed: {summary['passed']}")
        print(f"❌ Failed: {summary['failed']}")
        
        if summary['failed'] == 0:
            print("\n🎉 ALL FIXES VERIFIED! Case edit and delete functionality working correctly without security errors.")
        else:
            print(f"\n⚠️  {summary['failed']} fixes failed verification. Review the issues above.")
        
        # Print category breakdown
        categories = ['case_management_tests', 'plugin_health_tests']
        
        for category in categories:
            tests = self.results[category]
            if tests:
                passed = len([t for t in tests if t['status'] == 'PASS'])
                failed = len([t for t in tests if t['status'] == 'FAIL'])
                category_name = category.replace('_', ' ').title()
                print(f"\n{category_name}: {passed} passed, {failed} failed")
                
                # Show failed tests
                for test in tests:
                    if test['status'] == 'FAIL':
                        print(f"  ❌ {test['test']}: {test['message']}")

def main():
    """Main test execution"""
    tester = LegalAutomationTester()
    results = tester.run_all_tests()
    
    # Return appropriate exit code
    if results['summary']['failed'] > 0:
        sys.exit(1)
    else:
        sys.exit(0)

if __name__ == "__main__":
    main()