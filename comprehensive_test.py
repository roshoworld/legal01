#!/usr/bin/env python3
"""
Comprehensive Backend Testing for Legal Automation Plugin Fixes
Tests specific issues mentioned in the review request with actual file analysis
"""

import os
import re
import json
import sys
from pathlib import Path

class LegalAutomationFixValidator:
    def __init__(self):
        self.base_path = Path('/app')
        self.results = {
            'database_issues': [],
            'page_reference_issues': [],
            'menu_system_issues': [],
            'plugin_health_issues': [],
            'summary': {
                'total_issues': 0,
                'critical_issues': 0,
                'fixed_issues': 0
            }
        }

    def log_issue(self, category, severity, issue_type, message, file_path=None, line_number=None, details=None):
        """Log an issue found during testing"""
        issue = {
            'severity': severity,  # 'critical', 'major', 'minor'
            'type': issue_type,
            'message': message,
            'file': str(file_path) if file_path else None,
            'line': line_number,
            'details': details or {}
        }
        
        self.results[category].append(issue)
        self.results['summary']['total_issues'] += 1
        
        if severity == 'critical':
            self.results['summary']['critical_issues'] += 1
            print(f"🔴 CRITICAL - {issue_type}: {message}")
        elif severity == 'major':
            print(f"🟡 MAJOR - {issue_type}: {message}")
        else:
            print(f"🟢 MINOR - {issue_type}: {message}")
            
        if file_path:
            print(f"   📁 File: {file_path}")
        if line_number:
            print(f"   📍 Line: {line_number}")

    def test_database_foreign_key_fixes(self):
        """Test 1: Database Foreign Key Constraint Fixes"""
        print("\n🔍 Testing Database Foreign Key Constraint Fixes...")
        
        # Check finance plugin database manager
        finance_db_file = self.base_path / 'finance' / 'includes' / 'class-finance-db-manager.php'
        
        if finance_db_file.exists():
            with open(finance_db_file, 'r', encoding='utf-8') as f:
                content = f.read()
                
            # Look for foreign key constraints that might cause MySQL syntax errors
            foreign_key_patterns = [
                r'FOREIGN\s+KEY',
                r'REFERENCES\s+\w+',
                r'ON\s+DELETE\s+CASCADE',
                r'ON\s+UPDATE\s+CASCADE'
            ]
            
            foreign_keys_found = []
            for pattern in foreign_key_patterns:
                matches = re.finditer(pattern, content, re.IGNORECASE)
                for match in matches:
                    line_num = content[:match.start()].count('\n') + 1
                    foreign_keys_found.append((pattern, line_num))
            
            if foreign_keys_found:
                self.log_issue(
                    'database_issues',
                    'major',
                    'Foreign Key Constraints Found',
                    f'Found {len(foreign_keys_found)} foreign key constraints that may cause MySQL errors',
                    finance_db_file,
                    details={'constraints': foreign_keys_found}
                )
            else:
                print("✅ No problematic foreign key constraints found in finance plugin")
                self.results['summary']['fixed_issues'] += 1
        else:
            self.log_issue(
                'database_issues',
                'critical',
                'Missing Finance DB Manager',
                'Finance database manager file not found'
            )

    def test_page_reference_updates(self):
        """Test 2: Page Reference Updates (klage-click-cases -> la-cases)"""
        print("\n🔍 Testing Page Reference Updates...")
        
        # Find all PHP files that might contain page references
        php_files = list(self.base_path.rglob('*.php'))
        old_page_references = []
        
        for php_file in php_files:
            try:
                with open(php_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                    
                # Look for old page slug references
                old_slug_pattern = r'klage-click-cases'
                matches = re.finditer(old_slug_pattern, content)
                
                for match in matches:
                    line_num = content[:match.start()].count('\n') + 1
                    line_content = content.split('\n')[line_num - 1].strip()
                    
                    # Skip if it's in a comment about the fix
                    if 'should use' in line_content.lower() or 'instead of' in line_content.lower():
                        continue
                        
                    old_page_references.append({
                        'file': php_file,
                        'line': line_num,
                        'content': line_content
                    })
                    
            except Exception as e:
                continue
        
        if old_page_references:
            self.log_issue(
                'page_reference_issues',
                'critical',
                'Old Page References Found',
                f'Found {len(old_page_references)} references to old page slug "klage-click-cases"',
                details={'references': old_page_references[:5]}  # Show first 5
            )
            
            # Show details of problematic files
            for ref in old_page_references[:10]:  # Show first 10
                print(f"   📁 {ref['file']}")
                print(f"   📍 Line {ref['line']}: {ref['content'][:100]}...")
        else:
            print("✅ All page references updated to use 'la-cases'")
            self.results['summary']['fixed_issues'] += 1

    def test_unified_menu_consistency(self):
        """Test 3: Unified Menu System Consistency"""
        print("\n🔍 Testing Unified Menu System Consistency...")
        
        # Check unified menu file
        unified_menu_file = self.base_path / 'core' / 'includes' / 'class-unified-menu.php'
        
        if unified_menu_file.exists():
            with open(unified_menu_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Check for consistent page slug usage
            page_slug_patterns = {
                'cases': r"'la-cases'",
                'dashboard': r"'legal-automation'",
                'settings': r"'legal-automation-settings'"
            }
            
            inconsistencies = []
            for page_type, pattern in page_slug_patterns.items():
                if not re.search(pattern, content):
                    inconsistencies.append(page_type)
            
            if inconsistencies:
                self.log_issue(
                    'menu_system_issues',
                    'major',
                    'Menu System Inconsistencies',
                    f'Inconsistent page slugs found for: {inconsistencies}',
                    unified_menu_file
                )
            else:
                print("✅ Unified menu system uses consistent page slugs")
                self.results['summary']['fixed_issues'] += 1
        else:
            self.log_issue(
                'menu_system_issues',
                'critical',
                'Missing Unified Menu',
                'Unified menu system file not found'
            )

    def test_core_plugin_version_update(self):
        """Test 4: Core Plugin Version Update (235 -> 236)"""
        print("\n🔍 Testing Core Plugin Version Update...")
        
        # Check core plugin file
        core_plugin_file = self.base_path / 'core' / 'court-automation-hub.php'
        
        if core_plugin_file.exists():
            with open(core_plugin_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Check version number
            version_pattern = r"Version:\s*(\d+)"
            version_match = re.search(version_pattern, content)
            
            if version_match:
                version = int(version_match.group(1))
                if version >= 236:
                    print(f"✅ Core plugin version updated to {version}")
                    self.results['summary']['fixed_issues'] += 1
                else:
                    self.log_issue(
                        'plugin_health_issues',
                        'major',
                        'Version Not Updated',
                        f'Core plugin version is {version}, should be 236 or higher',
                        core_plugin_file
                    )
            else:
                self.log_issue(
                    'plugin_health_issues',
                    'major',
                    'Version Not Found',
                    'Could not find version number in core plugin file',
                    core_plugin_file
                )
        else:
            self.log_issue(
                'plugin_health_issues',
                'critical',
                'Missing Core Plugin',
                'Core plugin file not found'
            )

    def test_case_management_crud_structure(self):
        """Test 5: Case Management CRUD Structure"""
        print("\n🔍 Testing Case Management CRUD Structure...")
        
        # Check admin dashboard file
        admin_files = [
            self.base_path / 'core' / 'admin' / 'class-admin-dashboard.php',
            self.base_path / 'admin' / 'includes' / 'class-admin-dashboard-v210.php'
        ]
        
        crud_methods_found = {
            'create': False,
            'read': False,
            'update': False,
            'delete': False
        }
        
        for admin_file in admin_files:
            if admin_file.exists():
                with open(admin_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                # Look for CRUD method patterns
                crud_patterns = {
                    'create': r'(create_case|add_case|new_case)',
                    'read': r'(get_case|view_case|admin_page_cases)',
                    'update': r'(update_case|edit_case|save_case)',
                    'delete': r'(delete_case|remove_case)'
                }
                
                for operation, pattern in crud_patterns.items():
                    if re.search(pattern, content, re.IGNORECASE):
                        crud_methods_found[operation] = True
        
        missing_operations = [op for op, found in crud_methods_found.items() if not found]
        
        if missing_operations:
            self.log_issue(
                'page_reference_issues',
                'major',
                'Missing CRUD Operations',
                f'Missing CRUD operations: {missing_operations}',
                details={'found_operations': crud_methods_found}
            )
        else:
            print("✅ All CRUD operations found in case management")
            self.results['summary']['fixed_issues'] += 1

    def test_finance_plugin_integration(self):
        """Test 6: Finance Plugin Integration"""
        print("\n🔍 Testing Finance Plugin Integration...")
        
        # Check finance plugin main file
        finance_plugin_file = self.base_path / 'finance' / 'legal-automation-finance.php'
        
        if finance_plugin_file.exists():
            with open(finance_plugin_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Check for proper integration patterns
            integration_checks = {
                'core_dependency': r'CourtAutomationHub',
                'database_manager': r'LAF_Database_Manager',
                'table_creation': r'create_tables',
                'version_check': r'Version:\s*2\.0\.1'
            }
            
            missing_integrations = []
            for check_name, pattern in integration_checks.items():
                if not re.search(pattern, content):
                    missing_integrations.append(check_name)
            
            if missing_integrations:
                self.log_issue(
                    'plugin_health_issues',
                    'major',
                    'Finance Plugin Integration Issues',
                    f'Missing integration components: {missing_integrations}',
                    finance_plugin_file
                )
            else:
                print("✅ Finance plugin integration looks correct")
                self.results['summary']['fixed_issues'] += 1
        else:
            self.log_issue(
                'plugin_health_issues',
                'critical',
                'Missing Finance Plugin',
                'Finance plugin main file not found'
            )

    def run_comprehensive_tests(self):
        """Run all comprehensive tests"""
        print("🚀 Starting Comprehensive Legal Automation Fix Validation...")
        print("=" * 70)
        
        # Run all tests
        self.test_database_foreign_key_fixes()
        self.test_page_reference_updates()
        self.test_unified_menu_consistency()
        self.test_core_plugin_version_update()
        self.test_case_management_crud_structure()
        self.test_finance_plugin_integration()
        
        # Print comprehensive summary
        self.print_comprehensive_summary()
        
        return self.results

    def print_comprehensive_summary(self):
        """Print comprehensive test summary"""
        print("\n" + "=" * 70)
        print("📊 COMPREHENSIVE FIX VALIDATION SUMMARY")
        print("=" * 70)
        
        summary = self.results['summary']
        print(f"Total Issues Found: {summary['total_issues']}")
        print(f"🔴 Critical Issues: {summary['critical_issues']}")
        print(f"✅ Fixed Issues: {summary['fixed_issues']}")
        
        # Calculate fix percentage
        total_expected_fixes = 6  # Number of main areas tested
        fix_percentage = (summary['fixed_issues'] / total_expected_fixes) * 100
        print(f"📈 Fix Completion: {fix_percentage:.1f}%")
        
        if summary['critical_issues'] == 0:
            print("\n🎉 NO CRITICAL ISSUES FOUND!")
        else:
            print(f"\n⚠️  {summary['critical_issues']} CRITICAL ISSUES NEED ATTENTION")
        
        # Print detailed breakdown
        categories = ['database_issues', 'page_reference_issues', 'menu_system_issues', 'plugin_health_issues']
        
        for category in categories:
            issues = self.results[category]
            if issues:
                category_name = category.replace('_', ' ').title()
                print(f"\n{category_name}:")
                
                for issue in issues:
                    severity_icon = "🔴" if issue['severity'] == 'critical' else "🟡" if issue['severity'] == 'major' else "🟢"
                    print(f"  {severity_icon} {issue['type']}: {issue['message']}")

def main():
    """Main test execution"""
    validator = LegalAutomationFixValidator()
    results = validator.run_comprehensive_tests()
    
    # Return appropriate exit code based on critical issues
    if results['summary']['critical_issues'] > 0:
        print(f"\n❌ VALIDATION FAILED: {results['summary']['critical_issues']} critical issues found")
        sys.exit(1)
    else:
        print(f"\n✅ VALIDATION PASSED: No critical issues found")
        sys.exit(0)

if __name__ == "__main__":
    main()