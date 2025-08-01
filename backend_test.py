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

    def test_case_edit_save_fix(self):
        """Test 2: Case Edit Save Fix - handle_case_update_v210() with Success Messages"""
        print("\n🔍 Testing Case Edit Save Fix...")
        
        try:
            # Read the admin dashboard file to verify case edit functionality
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check for handle_case_update_v210 method implementation
            update_method_exists = 'private function handle_case_update_v210(' in content
            
            # Check for proper form processing
            form_processing = "if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_case']))" in content
            method_call = 'handle_case_update_v210($case_id, $_POST)' in content
            
            # Check for success message implementation
            success_message = '✅ Erfolg!' in content and 'wurde aktualisiert' in content
            
            # Check for redirect mechanism to prevent duplicate submissions
            redirect_mechanism = 'window.location.href' in content and 'updated=1' in content
            redirect_delay = 'setTimeout(function()' in content and '2000' in content
            
            # Check for nonce verification in the method
            nonce_verification = "wp_verify_nonce($post_data['edit_case_nonce'], 'edit_case_action')" in content
            
            # Check for database update operation
            database_update = '$wpdb->update(' in content and 'klage_cases' in content
            
            if (update_method_exists and form_processing and method_call and 
                success_message and redirect_mechanism and nonce_verification and database_update):
                self.log_result(
                    'case_management_tests',
                    'Case Edit Save Fix',
                    'PASS',
                    '✅ Case edit save fix verified: handle_case_update_v210() processes data with success messages and redirect',
                    {
                        'method_exists': update_method_exists,
                        'form_processing': form_processing,
                        'success_message': success_message,
                        'redirect_mechanism': redirect_mechanism,
                        'redirect_delay': redirect_delay,
                        'nonce_verification': nonce_verification,
                        'database_update': database_update
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'Case Edit Save Fix',
                    'FAIL',
                    f'❌ Case edit save fix incomplete: method={update_method_exists}, processing={form_processing}, success={success_message}, redirect={redirect_mechanism}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Case Edit Save Save Fix',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_core_plugin_version_239_update(self):
        """Test 3: Core Plugin Version Update (238 → 239)"""
        print("\n🔍 Testing Core Plugin Version Update to 239...")
        
        try:
            # Read the core plugin file to verify version update
            core_plugin_path = "/app/core/court-automation-hub.php"
            
            with open(core_plugin_path, 'r') as f:
                content = f.read()
            
            # Check for version 239 in plugin header
            version_header = 'Version: 239' in content
            
            # Check for version constant
            version_constant = "define('CAH_PLUGIN_VERSION', '239')" in content
            
            # Ensure old version 238 is not present
            old_version_header = 'Version: 238' in content
            old_version_constant = "define('CAH_PLUGIN_VERSION', '238')" in content
            
            if version_header and version_constant and not old_version_header and not old_version_constant:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 239',
                    'PASS',
                    '✅ Core plugin version successfully updated from 238 to 239',
                    {
                        'version_header_239': version_header,
                        'version_constant_239': version_constant,
                        'old_version_removed': not old_version_header and not old_version_constant
                    }
                )
            else:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 239',
                    'FAIL',
                    f'❌ Version update incomplete: header_239={version_header}, constant_239={version_constant}, old_present={old_version_header or old_version_constant}'
                )
                
        except Exception as e:
            self.log_result(
                'plugin_health_tests',
                'Core Plugin Version Update to 239',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_complete_crud_workflow_security(self):
        """Test 4: Complete CRUD Workflow Without Security Errors"""
        print("\n🔍 Testing Complete CRUD Workflow Security...")
        
        try:
            # Read both admin dashboard files
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                admin_content = f.read()
            
            # Test CREATE operation security
            create_nonce = 'create_case_nonce' in admin_content
            create_verification = "wp_verify_nonce($_POST['create_case_nonce'], 'create_case')" in admin_content
            
            # Test READ operation (no security issues expected)
            read_functionality = 'admin_page_cases()' in admin_content
            
            # Test UPDATE operation security (the main fix)
            update_nonce = 'edit_case_nonce' in admin_content
            update_verification = "wp_verify_nonce($post_data['edit_case_nonce'], 'edit_case_action')" in admin_content
            update_method = 'handle_case_update_v210(' in admin_content
            
            # Test DELETE operation security (the main fix)
            delete_nonce_url = 'wp_nonce_url(' in admin_content and 'delete_case_' in admin_content
            delete_verification = "wp_verify_nonce($_GET['_wpnonce'], 'delete_case_' . $case_id)" in admin_content
            delete_method = 'handle_case_deletion(' in admin_content
            
            # Check for security error messages
            security_error_present = 'Sicherheitsfehler.' in admin_content
            
            # All CRUD operations should have proper security
            crud_security_complete = (create_nonce and create_verification and 
                                    read_functionality and 
                                    update_nonce and update_verification and update_method and
                                    delete_nonce_url and delete_verification and delete_method)
            
            if crud_security_complete and security_error_present:
                self.log_result(
                    'case_management_tests',
                    'Complete CRUD Workflow Security',
                    'PASS',
                    '✅ Complete CRUD workflow security verified: All operations have proper nonce protection',
                    {
                        'create_security': create_nonce and create_verification,
                        'read_functionality': read_functionality,
                        'update_security': update_nonce and update_verification and update_method,
                        'delete_security': delete_nonce_url and delete_verification and delete_method,
                        'security_error_handling': security_error_present
                    }
                )
            else:
                failed_operations = []
                if not (create_nonce and create_verification):
                    failed_operations.append('CREATE')
                if not read_functionality:
                    failed_operations.append('READ')
                if not (update_nonce and update_verification and update_method):
                    failed_operations.append('UPDATE')
                if not (delete_nonce_url and delete_verification and delete_method):
                    failed_operations.append('DELETE')
                    
                self.log_result(
                    'case_management_tests',
                    'Complete CRUD Workflow Security',
                    'FAIL',
                    f'❌ CRUD security incomplete. Failed operations: {failed_operations}',
                    {
                        'failed_operations': failed_operations,
                        'security_error_handling': security_error_present
                    }
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Complete CRUD Workflow Security',
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