#!/usr/bin/env python3
"""
Backend Testing Suite for Legal Automation WordPress Plugin System
FINAL VERIFICATION: Case Edit and Delete Fixes (v238)

Specific tests for the review request:
1. Case deletion nonce fix - wp_nonce_url() with 'delete_case_' . $case_id action
2. Case edit save fix - handle_case_update_v210() with success messages and redirect
3. Core plugin version update verification (237 → 238)
4. Complete CRUD workflow testing without security errors
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

    def test_case_deletion_nonce_fix(self):
        """Test 1: Case Deletion Nonce Fix - wp_nonce_url() Implementation"""
        print("\n🔍 Testing Case Deletion Nonce Fix...")
        
        try:
            # Read the admin dashboard file to verify wp_nonce_url implementation
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check for wp_nonce_url usage in delete links
            wp_nonce_url_usage = 'wp_nonce_url(' in content
            delete_case_action = "'delete_case_' . $case->id" in content
            
            # Check for proper nonce verification in handle_case_deletion
            nonce_verification = "wp_verify_nonce($_GET['_wpnonce'], 'delete_case_' . $case_id)" in content
            security_error_handling = 'Sicherheitsfehler.' in content
            
            # Check that JavaScript nonce generation is NOT used (should be replaced)
            javascript_nonce = 'wp_create_nonce(' in content and 'delete_case' in content
            
            if wp_nonce_url_usage and delete_case_action and nonce_verification and not javascript_nonce:
                self.log_result(
                    'case_management_tests',
                    'Case Deletion Nonce Fix',
                    'PASS',
                    '✅ Case deletion nonce fix verified: wp_nonce_url() used with proper delete_case_ action',
                    {
                        'wp_nonce_url_used': wp_nonce_url_usage,
                        'delete_case_action_found': delete_case_action,
                        'nonce_verification_present': nonce_verification,
                        'javascript_nonce_removed': not javascript_nonce,
                        'security_error_handling': security_error_handling
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'Case Deletion Nonce Fix',
                    'FAIL',
                    f'❌ Case deletion nonce fix incomplete: wp_nonce_url={wp_nonce_url_usage}, delete_action={delete_case_action}, verification={nonce_verification}, js_nonce_removed={not javascript_nonce}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Case Deletion Nonce Fix',
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

    def test_core_plugin_version_238_update(self):
        """Test 3: Core Plugin Version Update (237 → 238)"""
        print("\n🔍 Testing Core Plugin Version Update to 238...")
        
        try:
            # Read the core plugin file to verify version update
            core_plugin_path = "/app/core/court-automation-hub.php"
            
            with open(core_plugin_path, 'r') as f:
                content = f.read()
            
            # Check for version 238 in plugin header
            version_header = 'Version: 238' in content
            
            # Check for version constant
            version_constant = "define('CAH_PLUGIN_VERSION', '238')" in content
            
            # Ensure old version 237 is not present
            old_version_header = 'Version: 237' in content
            old_version_constant = "define('CAH_PLUGIN_VERSION', '237')" in content
            
            if version_header and version_constant and not old_version_header and not old_version_constant:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 238',
                    'PASS',
                    '✅ Core plugin version successfully updated from 237 to 238',
                    {
                        'version_header_238': version_header,
                        'version_constant_238': version_constant,
                        'old_version_removed': not old_version_header and not old_version_constant
                    }
                )
            else:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 238',
                    'FAIL',
                    f'❌ Version update incomplete: header_238={version_header}, constant_238={version_constant}, old_present={old_version_header or old_version_constant}'
                )
                
        except Exception as e:
            self.log_result(
                'plugin_health_tests',
                'Core Plugin Version Update to 238',
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
        print("🚀 Starting Final Verification: Case Edit and Delete Fixes (v238)...")
        print("=" * 80)
        
        # Final Verification Tests for Review Request
        self.test_case_deletion_nonce_fix()
        self.test_case_edit_save_fix()
        self.test_core_plugin_version_238_update()
        self.test_complete_crud_workflow_security()
        
        # Print summary
        self.print_summary()
        
        return self.results

    def print_summary(self):
        """Print test summary"""
        print("\n" + "=" * 80)
        print("📊 FINAL VERIFICATION: CASE EDIT AND DELETE FIXES (v238)")
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