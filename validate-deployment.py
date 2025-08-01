#!/usr/bin/env python3
"""
WordPress Plugin Deployment Validation Script
Validates WordPress plugins before deployment according to Rule #12
"""

import os
import sys
import re
import json
from typing import Dict, List, Tuple

def validate_php_syntax(plugin_path: str) -> Dict:
    """Validate PHP syntax for all PHP files in the plugin"""
    errors = []
    files_checked = 0
    
    for root, dirs, files in os.walk(plugin_path):
        for file in files:
            if file.endswith('.php'):
                file_path = os.path.join(root, file)
                files_checked += 1
                
                # Use php -l to check syntax
                result = os.system(f'php -l "{file_path}" > /dev/null 2>&1')
                if result != 0:
                    errors.append(f"PHP syntax error in: {file_path}")
    
    return {
        'files_checked': files_checked,
        'errors': errors,
        'passed': len(errors) == 0
    }

def validate_wordpress_compliance(plugin_path: str) -> Dict:
    """Check WordPress plugin compliance"""
    issues = []
    main_file = None
    
    # Find main plugin file
    for file in os.listdir(plugin_path):
        if file.endswith('.php'):
            file_path = os.path.join(plugin_path, file)
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                if 'Plugin Name:' in content:
                    main_file = file_path
                    break
    
    if not main_file:
        issues.append("No main plugin file found with 'Plugin Name:' header")
        return {'issues': issues, 'passed': False}
    
    # Validate plugin headers
    with open(main_file, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
        
        required_headers = ['Plugin Name:', 'Version:', 'Description:']
        for header in required_headers:
            if header not in content:
                issues.append(f"Missing required header: {header}")
        
        # Check for security: prevent direct access
        if 'ABSPATH' not in content:
            issues.append("Missing ABSPATH security check")
    
    return {
        'issues': issues,
        'passed': len(issues) == 0,
        'main_file': main_file
    }

def validate_naming_convention(plugin_path: str, expected_prefix: str = "legal-automation") -> Dict:
    """Validate naming convention compliance"""
    issues = []
    
    # Check plugin name in header
    main_files = []
    for file in os.listdir(plugin_path):
        if file.endswith('.php'):
            file_path = os.path.join(plugin_path, file)
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                if 'Plugin Name:' in content:
                    main_files.append(file_path)
                    
                    # Extract plugin name
                    plugin_name_match = re.search(r'Plugin Name:\s*(.+)', content)
                    if plugin_name_match:
                        plugin_name = plugin_name_match.group(1).strip()
                        if not plugin_name.startswith("Legal Automation"):
                            issues.append(f"Plugin name doesn't follow naming convention: {plugin_name}")
                    
                    # Extract text domain
                    text_domain_match = re.search(r'Text Domain:\s*(.+)', content)
                    if text_domain_match:
                        text_domain = text_domain_match.group(1).strip()
                        if not text_domain.startswith(expected_prefix):
                            issues.append(f"Text domain doesn't follow naming convention: {text_domain}")
    
    return {
        'issues': issues,
        'passed': len(issues) == 0,
        'main_files': main_files
    }

def extract_version(plugin_path: str) -> str:
    """Extract version from plugin header"""
    for file in os.listdir(plugin_path):
        if file.endswith('.php'):
            file_path = os.path.join(plugin_path, file)
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                if 'Plugin Name:' in content:
                    version_match = re.search(r'Version:\s*(.+)', content)
                    if version_match:
                        return version_match.group(1).strip()
    return "Unknown"

def calculate_confidence_score(results: Dict) -> int:
    """Calculate deployment confidence score (0-100)"""
    score = 100
    
    # PHP syntax errors are critical
    if not results['php_syntax']['passed']:
        score -= 50
    
    # WordPress compliance issues
    if not results['wordpress_compliance']['passed']:
        score -= 30
    
    # Naming convention issues
    if not results['naming_convention']['passed']:
        score -= 20
    
    return max(0, score)

def main():
    if len(sys.argv) != 3:
        print("Usage: python3 validate-deployment.py /path/to/plugin VERSION")
        sys.exit(1)
    
    plugin_path = sys.argv[1]
    expected_version = sys.argv[2]
    
    if not os.path.exists(plugin_path):
        print(f"Error: Plugin path does not exist: {plugin_path}")
        sys.exit(1)
    
    print(f"🔍 Validating plugin: {plugin_path}")
    print(f"📋 Expected version: {expected_version}")
    print("=" * 60)
    
    # Run all validations
    results = {
        'php_syntax': validate_php_syntax(plugin_path),
        'wordpress_compliance': validate_wordpress_compliance(plugin_path),
        'naming_convention': validate_naming_convention(plugin_path)
    }
    
    # Extract actual version
    actual_version = extract_version(plugin_path)
    results['version_info'] = {
        'expected': expected_version,
        'actual': actual_version,
        'match': actual_version == expected_version
    }
    
    # Calculate confidence score
    confidence_score = calculate_confidence_score(results)
    results['confidence_score'] = confidence_score
    
    # Display results
    print("📊 VALIDATION RESULTS:")
    print("-" * 40)
    
    print(f"✅ PHP Syntax: {'PASSED' if results['php_syntax']['passed'] else '❌ FAILED'}")
    if not results['php_syntax']['passed']:
        for error in results['php_syntax']['errors']:
            print(f"   - {error}")
    else:
        print(f"   - {results['php_syntax']['files_checked']} PHP files checked")
    
    print(f"✅ WordPress Compliance: {'PASSED' if results['wordpress_compliance']['passed'] else '❌ FAILED'}")
    if not results['wordpress_compliance']['passed']:
        for issue in results['wordpress_compliance']['issues']:
            print(f"   - {issue}")
    
    print(f"✅ Naming Convention: {'PASSED' if results['naming_convention']['passed'] else '❌ FAILED'}")
    if not results['naming_convention']['passed']:
        for issue in results['naming_convention']['issues']:
            print(f"   - {issue}")
    
    print(f"📦 Version Check: {'PASSED' if results['version_info']['match'] else '❌ FAILED'}")
    print(f"   - Expected: {expected_version}")
    print(f"   - Actual: {actual_version}")
    
    print("-" * 40)
    print(f"🎯 DEPLOYMENT CONFIDENCE SCORE: {confidence_score}%")
    
    if confidence_score >= 95:
        print("✅ DEPLOYMENT APPROVED - Confidence score ≥ 95%")
        sys.exit(0)
    else:
        print("❌ DEPLOYMENT NOT RECOMMENDED - Confidence score < 95%")
        print("   Please fix the issues above before deployment.")
        sys.exit(1)

if __name__ == "__main__":
    main()