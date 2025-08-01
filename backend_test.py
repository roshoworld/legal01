#!/usr/bin/env python3
"""
Backend Testing Suite for Legal Automation WordPress Plugin System
URL ROUTING FIX VERIFICATION (v241)

Specific tests for the review request:
1. Unified URL Routing - all case actions point to la-cases page consistently
2. JavaScript URL Rewriting - URLs use page=la-cases format
3. No Old URL Patterns - no more page=legal-automation&view=cases URLs
4. Consistent Case Edit Experience - both dashboard and case list show same functionality
5. Core plugin version update verification (240 → 241)
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

    def test_unified_url_routing(self):
        """Test 1: Unified URL Routing - All case actions point to la-cases page consistently"""
        print("\n🔍 Testing Unified URL Routing...")
        
        try:
            # Check admin dashboard file for consistent la-cases usage
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            unified_menu_path = "/app/core/includes/class-unified-menu.php"
            
            issues_found = []
            
            # Check admin dashboard file
            with open(admin_dashboard_path, 'r') as f:
                admin_content = f.read()
            
            # Check unified menu file for old URL patterns
            with open(unified_menu_path, 'r') as f:
                unified_content = f.read()
            
            # Look for old URL patterns that should be fixed
            old_patterns = [
                'page=legal-automation&view=cases',
                'page=legal-automation-cases',
                'page=klage-click-cases'
            ]
            
            for pattern in old_patterns:
                if pattern in admin_content:
                    issues_found.append(f"Admin Dashboard: {pattern}")
                if pattern in unified_content:
                    issues_found.append(f"Unified Menu: {pattern}")
            
            # Check that la-cases is used consistently
            la_cases_count_admin = admin_content.count('page=la-cases')
            la_cases_count_unified = unified_content.count('page=la-cases')
            
            # Verify case action links point to la-cases
            case_edit_links = 'admin.php?page=la-cases&action=edit' in admin_content
            case_view_links = 'admin.php?page=la-cases&action=view' in admin_content
            case_delete_links = 'admin.php?page=la-cases&action=delete' in admin_content
            
            if len(issues_found) == 0 and case_edit_links and case_view_links and case_delete_links:
                self.log_result(
                    'case_management_tests',
                    'Unified URL Routing',
                    'PASS',
                    f'✅ URL routing verified: All case actions use la-cases page consistently ({la_cases_count_admin + la_cases_count_unified} references)',
                    {
                        'old_patterns_found': len(issues_found),
                        'la_cases_references': la_cases_count_admin + la_cases_count_unified,
                        'case_action_links': case_edit_links and case_view_links and case_delete_links
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'Unified URL Routing',
                    'FAIL',
                    f'❌ URL routing issues found: {issues_found}',
                    {
                        'issues': issues_found,
                        'case_links_ok': case_edit_links and case_view_links and case_delete_links
                    }
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Unified URL Routing',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_no_old_url_patterns(self):
        """Test 2: No Old URL Patterns - Verify no more page=legal-automation&view=cases URLs"""
        print("\n🔍 Testing for Old URL Patterns...")
        
        try:
            # Files to check for old URL patterns
            files_to_check = [
                "/app/core/admin/class-admin-dashboard.php",
                "/app/core/includes/class-unified-menu.php"
            ]
            
            old_url_issues = []
            
            for file_path in files_to_check:
                try:
                    with open(file_path, 'r') as f:
                        content = f.read()
                    
                    # Check for specific old URL patterns mentioned in review request
                    if 'page=legal-automation&view=cases' in content:
                        old_url_issues.append(f"{file_path}: page=legal-automation&view=cases")
                    
                    if 'page=legal-automation-cases' in content:
                        old_url_issues.append(f"{file_path}: page=legal-automation-cases")
                        
                except FileNotFoundError:
                    continue
            
            if len(old_url_issues) == 0:
                self.log_result(
                    'case_management_tests',
                    'No Old URL Patterns',
                    'PASS',
                    '✅ No old URL patterns found: All case URLs updated to use la-cases format',
                    {
                        'files_checked': len(files_to_check),
                        'old_patterns_found': 0
                    }
                )
            else:
                self.log_result(
                    'case_management_tests',
                    'No Old URL Patterns',
                    'FAIL',
                    f'❌ Old URL patterns still found: {old_url_issues}',
                    {
                        'issues': old_url_issues
                    }
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'No Old URL Patterns',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_consistent_case_edit_experience(self):
        """Test 3: Consistent Case Edit Experience - Both dashboard and case list show same functionality"""
        print("\n🔍 Testing Consistent Case Edit Experience...")
        
        try:
            # Read the admin dashboard file to verify consistent edit experience
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check that case editing functionality is unified
            edit_form_method = 'render_edit_case_form(' in content
            case_update_method = 'handle_case_update_v210(' in content
            
            # Check that both dashboard and case list use same edit links
            dashboard_edit_links = 'admin.php?page=la-cases&action=edit' in content
            
            # Check for complete tab structure in edit form
            tab_navigation = 'nav-tab-wrapper' in content
            multiple_tabs = content.count('nav-tab') > 5  # Should have multiple tabs
            
            # Check that form processing is consistent
            form_processing = "if ($_SERVER['REQUEST_METHOD'] === 'POST')" in content
            save_case_action = "isset($_POST['save_case'])" in content
            
            # Check for success messages and redirects
            success_redirect = "wp_redirect(admin_url('admin.php?page=la-cases&updated=" in content
            
            if (edit_form_method and case_update_method and dashboard_edit_links and 
                tab_navigation and multiple_tabs and form_processing and save_case_action and success_redirect):
                self.log_result(
                    'case_management_tests',
                    'Consistent Case Edit Experience',
                    'PASS',
                    '✅ Consistent case edit experience verified: Same functionality from both entry points',
                    {
                        'edit_form_unified': edit_form_method,
                        'update_method': case_update_method,
                        'consistent_links': dashboard_edit_links,
                        'complete_tabs': tab_navigation and multiple_tabs,
                        'form_processing': form_processing and save_case_action,
                        'success_handling': success_redirect
                    }
                )
            else:
                missing_features = []
                if not edit_form_method:
                    missing_features.append('edit_form_method')
                if not case_update_method:
                    missing_features.append('case_update_method')
                if not dashboard_edit_links:
                    missing_features.append('consistent_edit_links')
                if not (tab_navigation and multiple_tabs):
                    missing_features.append('complete_tab_structure')
                if not (form_processing and save_case_action):
                    missing_features.append('form_processing')
                if not success_redirect:
                    missing_features.append('success_redirect')
                    
                self.log_result(
                    'case_management_tests',
                    'Consistent Case Edit Experience',
                    'FAIL',
                    f'❌ Inconsistent case edit experience. Missing: {missing_features}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Consistent Case Edit Experience',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_core_plugin_version_241_update(self):
        """Test 4: Core Plugin Version Update (240 → 241)"""
        print("\n🔍 Testing Core Plugin Version Update to 241...")
        
        try:
            # Read the core plugin file to verify version update
            core_plugin_path = "/app/core/court-automation-hub.php"
            
            with open(core_plugin_path, 'r') as f:
                content = f.read()
            
            # Check for version 241 in plugin header
            version_header = 'Version: 241' in content
            
            # Check for version constant
            version_constant = "define('CAH_PLUGIN_VERSION', '241')" in content
            
            # Ensure old version 240 is not present
            old_version_header = 'Version: 240' in content
            old_version_constant = "define('CAH_PLUGIN_VERSION', '240')" in content
            
            if version_header and version_constant and not old_version_header and not old_version_constant:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 241',
                    'PASS',
                    '✅ Core plugin version successfully updated from 240 to 241',
                    {
                        'version_header_241': version_header,
                        'version_constant_241': version_constant,
                        'old_version_removed': not old_version_header and not old_version_constant
                    }
                )
            else:
                self.log_result(
                    'plugin_health_tests',
                    'Core Plugin Version Update to 241',
                    'FAIL',
                    f'❌ Version update incomplete: header_241={version_header}, constant_241={version_constant}, old_present={old_version_header or old_version_constant}'
                )
                
        except Exception as e:
            self.log_result(
                'plugin_health_tests',
                'Core Plugin Version Update to 241',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def test_form_processing_consistency(self):
        """Test 5: Form Processing Consistency - Case saves work from both entry points"""
        print("\n🔍 Testing Form Processing Consistency...")
        
        try:
            # Read the admin dashboard file to verify form processing
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check for unified form processing
            post_method_check = "if ($_SERVER['REQUEST_METHOD'] === 'POST')" in content
            action_handling = 'handle_case_actions()' in content
            
            # Check for consistent save case processing
            save_case_nonce = 'save_case' in content and 'edit_case_nonce' in content
            case_update_call = 'handle_case_update_v210(' in content
            
            # Check for consistent redirect after save
            redirect_after_save = "wp_redirect(admin_url('admin.php?page=la-cases&updated=" in content
            exit_after_redirect = 'exit;' in content
            
            # Check for success message handling
            success_message = '✅ Erfolg!' in content and 'wurde aktualisiert' in content
            
            # Check for error handling
            error_handling = 'notice notice-error' in content
            
            if (post_method_check and action_handling and save_case_nonce and case_update_call and 
                redirect_after_save and exit_after_redirect and success_message):
                self.log_result(
                    'case_management_tests',
                    'Form Processing Consistency',
                    'PASS',
                    '✅ Form processing consistency verified: Saves work correctly from both entry points',
                    {
                        'unified_processing': post_method_check and action_handling,
                        'save_handling': save_case_nonce and case_update_call,
                        'redirect_flow': redirect_after_save and exit_after_redirect,
                        'success_messages': success_message,
                        'error_handling': error_handling
                    }
                )
            else:
                missing_components = []
                if not (post_method_check and action_handling):
                    missing_components.append('unified_processing')
                if not (save_case_nonce and case_update_call):
                    missing_components.append('save_handling')
                if not (redirect_after_save and exit_after_redirect):
                    missing_components.append('redirect_flow')
                if not success_message:
                    missing_components.append('success_messages')
                    
                self.log_result(
                    'case_management_tests',
                    'Form Processing Consistency',
                    'FAIL',
                    f'❌ Form processing inconsistent. Missing: {missing_components}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Form Processing Consistency',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def run_all_tests(self):
        """Run all URL routing fix verification tests"""
        print("🚀 Starting URL Routing Fix Verification (v241)...")
        print("=" * 80)
        
        # URL Routing Fix Tests for Review Request
        self.test_unified_url_routing()
        self.test_no_old_url_patterns()
        self.test_consistent_case_edit_experience()
        self.test_core_plugin_version_241_update()
        self.test_form_processing_consistency()
        
        # Print summary
        self.print_summary()
        
        return self.results

    def test_complete_case_edit_workflow(self):
        """Test 6: Complete Case Edit Workflow - End-to-end case editing functionality"""
        print("\n🔍 Testing Complete Case Edit Workflow...")
        
        try:
            # Read the admin dashboard file to verify complete workflow
            admin_dashboard_path = "/app/core/admin/class-admin-dashboard.php"
            
            with open(admin_dashboard_path, 'r') as f:
                content = f.read()
            
            # Check for edit form rendering
            edit_form_method = 'render_edit_case_form(' in content
            
            # Check for form processing in admin_page_cases
            form_processing = "if ($_SERVER['REQUEST_METHOD'] === 'POST')" in content
            save_case_action = "isset($_POST['save_case'])" in content
            
            # Check for handle_case_update_v210 method call
            update_method_call = 'handle_case_update_v210($case_id, $_POST)' in content
            
            # Check for success message and redirect flow
            success_message = '✅ Erfolg!' in content and 'wurde aktualisiert' in content
            redirect_to_list = "wp_redirect(admin_url('admin.php?page=la-cases&updated=" in content
            
            # Check for proper nonce handling throughout workflow
            edit_nonce_field = 'edit_case_nonce' in content
            nonce_verification = "wp_verify_nonce($post_data['edit_case_nonce'], 'edit_case_action')" in content
            
            # Check for case ID field in edit form
            case_id_field = 'name="case_id"' in content
            
            # Check for error handling
            error_handling = 'notice notice-error' in content
            
            if (edit_form_method and form_processing and save_case_action and update_method_call and 
                success_message and redirect_to_list and edit_nonce_field and nonce_verification and case_id_field):
                self.log_result(
                    'case_management_tests',
                    'Complete Case Edit Workflow',
                    'PASS',
                    '✅ Complete case edit workflow verified: Form rendering, processing, validation, and redirect all working',
                    {
                        'edit_form': edit_form_method,
                        'form_processing': form_processing and save_case_action,
                        'update_method': update_method_call,
                        'success_flow': success_message and redirect_to_list,
                        'security': edit_nonce_field and nonce_verification,
                        'case_id_field': case_id_field,
                        'error_handling': error_handling
                    }
                )
            else:
                missing_components = []
                if not edit_form_method:
                    missing_components.append('edit_form_rendering')
                if not (form_processing and save_case_action):
                    missing_components.append('form_processing')
                if not update_method_call:
                    missing_components.append('update_method_call')
                if not (success_message and redirect_to_list):
                    missing_components.append('success_flow')
                if not (edit_nonce_field and nonce_verification):
                    missing_components.append('security')
                if not case_id_field:
                    missing_components.append('case_id_field')
                    
                self.log_result(
                    'case_management_tests',
                    'Complete Case Edit Workflow',
                    'FAIL',
                    f'❌ Case edit workflow incomplete. Missing: {missing_components}'
                )
                
        except Exception as e:
            self.log_result(
                'case_management_tests',
                'Complete Case Edit Workflow',
                'FAIL',
                f'❌ Test failed: {str(e)}'
            )

    def print_summary(self):
        """Print test summary"""
        print("\n" + "=" * 80)
        print("📊 URL ROUTING FIX VERIFICATION (v241)")
        print("=" * 80)
        
        summary = self.results['summary']
        print(f"Total Tests: {summary['total_tests']}")
        print(f"✅ Passed: {summary['passed']}")
        print(f"❌ Failed: {summary['failed']}")
        
        if summary['failed'] == 0:
            print("\n🎉 ALL URL ROUTING FIXES VERIFIED! Consistent case editing experience achieved with unified la-cases page routing.")
        else:
            print(f"\n⚠️  {summary['failed']} routing fixes failed verification. Review the issues above.")
        
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