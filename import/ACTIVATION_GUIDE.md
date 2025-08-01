# Legal Automation - Import Plugin v200
## Activation Guide

### Prerequisites
- Legal Automation Core v219+ must be installed and activated
- WordPress 5.0+
- PHP 7.4+

### Installation Steps

1. **Upload Plugin Files**
   ```
   /wp-content/plugins/legal-automation-import/
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Legal Automation - Import"
   - Click "Activate"

3. **Verify Dependencies**
   - Plugin will check for Legal Automation Core v219+
   - If missing, you'll see an admin notice

### Quick Start

#### Option 1: Standalone Interface
- Navigate to WordPress Admin → Import/Export
- Access full import/export functionality

#### Option 2: Integrated Interface (Requires Admin Plugin)
- If Legal Automation Admin plugin is active
- Navigate to existing admin dashboard
- Find new "Import/Export" tab

### Initial Configuration

#### CSV Import
1. Go to Import/Export → Import Data
2. Select "CSV File Import"
3. Upload CSV or paste content
4. Configure field mappings
5. Preview and process

#### Airtable Integration
1. Go to Import/Export → Airtable Integration
2. Enter your Airtable API key
3. Provide Base ID and Table Name
4. Test connection
5. Configure field mappings
6. Set up sync schedule

#### Pipedream Webhooks
1. Go to Import/Export → Pipedream Webhooks
2. Create new webhook source
3. Generate webhook secret
4. Copy webhook URL
5. Configure in your Pipedream workflow

### Troubleshooting

#### Activation Error: "Class not found"
- Ensure all plugin files are uploaded correctly
- Check file permissions (755 for folders, 644 for files)

#### "Core plugin missing" Notice
- Install and activate Legal Automation Core v219+
- Refresh the page after activation

#### Import Fails Silently
- Check PHP error logs
- Verify database table permissions
- Ensure CSV format is correct

#### Airtable Connection Issues
- Verify API key has correct permissions
- Check Base ID format (starts with 'app')
- Ensure table name matches exactly

### Support

For technical issues:
1. Check WordPress debug logs
2. Verify plugin file integrity
3. Test with sample data first
4. Contact support with specific error messages

### File Structure Verification

Ensure these key files exist:
```
legal-automation-import.php
includes/class-import-manager.php
includes/class-export-manager.php
includes/class-admin-interface.php
includes/class-field-mapper.php
includes/class-client-configs.php
includes/sources/class-csv-source.php
includes/sources/class-airtable-source.php
includes/sources/class-pipedream-source.php
assets/css/admin.css
assets/js/admin.js
```

### Next Steps After Activation

1. **Test Basic CSV Import**
   - Download contact template
   - Import sample data
   - Verify records in database

2. **Configure Partner Integrations**
   - Set up Airtable if needed
   - Create Pipedream webhooks
   - Test data flow

3. **Customize Field Mappings**
   - Create client-specific configurations
   - Test with real partner data
   - Document successful configurations

The plugin is now ready for use with comprehensive multi-source import capabilities!