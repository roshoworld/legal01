# Legal Automation - Import Plugin v200

## Overview

The Legal Automation Import Plugin provides comprehensive import/export functionality for the Legal Automation Suite. It supports multiple data sources including traditional CSV files, Airtable databases, and real-time Pipedream webhooks.

## Features

### Multi-Source Data Integration
- **CSV Import**: Traditional file upload with intelligent field mapping
- **Airtable Integration**: Direct API sync with Airtable databases
- **Pipedream Webhooks**: Real-time case feeds via automation workflows
- **REST API**: Generic API integration for custom data sources

### Smart Field Mapping
- Automatic field detection and data type analysis
- Intelligent mapping suggestions based on field names
- Visual field mapping interface with sample data preview
- Support for custom field transformations and validation

### Partner Integration Ready
- **Airtable**: Perfect for partners using Airtable for case management
- **Pipedream**: Enables real-time case feeds from partner systems
- **Webhook Security**: HMAC signature verification for secure data transfer
- **Custom Field Mappings**: Configurable per partner/source

### Export Capabilities
- CSV export with date range filtering
- Template downloads for various import formats
- Custom field selection for exports
- Excel format support

## Architecture

### Plugin Structure
```
/app/import/
├── legal-automation-import.php       # Main plugin file
├── includes/
│   ├── class-import-manager.php      # Core import orchestration
│   ├── class-export-manager.php      # Export functionality
│   ├── class-admin-interface.php     # Admin UI components
│   ├── class-field-mapper.php        # Field mapping utilities
│   ├── class-client-configs.php      # Partner configurations
│   └── sources/
│       ├── class-csv-source.php      # CSV import handler
│       ├── class-airtable-source.php # Airtable integration
│       ├── class-pipedream-source.php # Pipedream webhooks
│       └── class-api-source.php      # Generic API source
├── assets/
│   ├── css/admin.css                 # Admin interface styles
│   └── js/admin.js                   # Admin interface scripts
└── README.md
```

### Data Sources

#### 1. CSV Source (`class-csv-source.php`)
- File upload or paste content
- Automatic delimiter detection (comma/semicolon)
- Field validation and data type conversion
- Preview before processing
- Batch import with transaction safety

#### 2. Airtable Source (`class-airtable-source.php`)
- Personal Access Token authentication
- Base and table selection
- Incremental sync (new/updated records only)
- Full sync option
- View and formula filtering
- Rate limiting compliance

#### 3. Pipedream Source (`class-pipedream-source.php`)
- HMAC signature verification
- Flexible payload structure handling
- Real-time case creation
- Duplicate detection via external_id
- Nested object flattening

#### 4. Generic API Source (`class-api-source.php`)
- REST API integration
- Configurable authentication methods
- Custom endpoint configuration
- Scheduled sync capabilities

### Field Mapping System

The field mapper (`class-field-mapper.php`) provides:
- **Pattern Recognition**: Intelligent field name matching
- **Data Type Validation**: Email, phone, URL, date validation
- **Value Transformation**: Data type conversion and formatting
- **Mapping Templates**: Pre-configured mappings for common scenarios
- **Custom Rules**: Partner-specific field mapping configurations

### Database Integration

All sources integrate with the v2.0.0 contact-centric database architecture:
- **klage_contacts**: Contact information
- **klage_cases**: Case data
- **klage_case_contacts**: Relationship mapping
- **klage_financials**: Financial data
- **klage_audit**: Import activity logging

## Installation & Setup

### Dependencies
- Legal Automation Core v219+
- WordPress 5.0+
- PHP 7.4+

### Installation Steps
1. Upload plugin files to `/wp-content/plugins/legal-automation-import/`
2. Activate the plugin through WordPress admin
3. Configure data sources as needed
4. Set up field mappings for your partners

### Configuration

#### Airtable Setup
1. Create Personal Access Token in Airtable
2. Get Base ID from Airtable API documentation
3. Enter table name exactly as it appears in Airtable
4. Test connection and configure field mappings
5. Set up scheduled sync if needed

#### Pipedream Setup
1. Create source identifier (e.g., "partner-name")
2. Generate webhook secret key
3. Configure Pipedream workflow with webhook URL
4. Add X-Pipedream-Signature header with secret
5. Send data as JSON payload

### Field Mapping Examples

#### CSV Mapping
```php
$field_mappings = [
    'Email' => [
        'target_table' => 'klage_contacts',
        'target_field' => 'email',
        'data_type' => 'email',
        'required' => true
    ],
    'Case ID' => [
        'target_table' => 'klage_cases',
        'target_field' => 'case_id',
        'data_type' => 'string',
        'required' => true
    ]
];
```

#### Airtable Mapping
```php
$airtable_mappings = [
    'Contact Email' => [
        'target_table' => 'klage_contacts',
        'target_field' => 'email',
        'data_type' => 'email'
    ],
    'Claim Amount' => [
        'target_table' => 'klage_cases',
        'target_field' => 'claim_amount',
        'data_type' => 'decimal'
    ]
];
```

#### Pipedream Webhook Payload
```json
{
    "workflow_id": "workflow-123",
    "timestamp": "2024-01-01T12:00:00Z",
    "data": {
        "email": "contact@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "case_id": "CASE-001",
        "status": "new",
        "amount": 1000.00
    }
}
```

## Admin Interface

### Standalone Interface
- Accessible via "Import/Export" menu in WordPress admin
- Full-featured interface with all import/export options
- Data source configuration pages
- Import history and analytics

### Integrated Interface
- Integrates as tab in Legal Automation Admin plugin
- Seamless user experience within existing admin dashboard
- Shared styling and navigation
- Context-aware functionality

### Interface Features
- **Visual Source Selection**: Card-based interface for choosing data sources
- **Step-by-Step Workflow**: Guided import process with progress indicators
- **Field Mapping UI**: Interactive table for configuring field mappings
- **Preview Mode**: See exactly what will be imported before processing
- **Real-time Feedback**: Progress indicators and status updates
- **History Tracking**: Complete audit trail of all imports

## API Endpoints

### REST API
- `GET /wp-json/legal-automation/v1/import/detect-fields`
- `POST /wp-json/legal-automation/v1/import/preview`
- `POST /wp-json/legal-automation/v1/import/process`
- `GET /wp-json/legal-automation/v1/export/csv`
- `GET /wp-json/legal-automation/v1/export/template`

### AJAX Endpoints
- `lai_detect_fields`: Analyze uploaded data
- `lai_preview_import`: Preview import results
- `lai_process_import`: Execute full import
- `lai_airtable_connect`: Test Airtable connection
- `lai_airtable_sync`: Sync Airtable data
- `lai_pipedream_setup`: Configure webhook endpoints

### Webhook Endpoints
- `[site-url]/lai-webhook/pipedream/[source-id]`
- `[site-url]/lai-webhook/airtable/[source-id]`

## Security Features

### Webhook Security
- HMAC signature verification for all webhooks
- Configurable secret keys per webhook
- Source identifier validation
- Payload size limits

### Data Validation
- Comprehensive field validation
- Data type conversion with error handling
- Required field checking
- Email and URL format validation

### Database Security
- Transaction-based imports for data integrity
- Duplicate detection and prevention
- Audit logging for all import activities
- User permission checks

## Performance Considerations

### Large Dataset Handling
- Chunked processing for large CSV files
- Memory-efficient streaming for file uploads
- Transaction batching for database operations
- Progress tracking for long-running imports

### Rate Limiting
- Airtable API rate limit compliance
- Webhook request throttling
- Background processing for large imports
- Scheduled sync optimization

### Caching
- Field mapping cache for repeated operations
- Source configuration caching
- Template caching for export formats
- Temporary file cleanup

## Troubleshooting

### Common Issues

#### Airtable Connection Fails
- Verify API key has correct permissions
- Check Base ID format (starts with 'app')
- Ensure table name matches exactly
- Test with simple table first

#### CSV Import Errors
- Check file encoding (UTF-8 recommended)
- Verify delimiter detection
- Ensure required fields are mapped
- Check data format consistency

#### Webhook Not Receiving Data
- Verify webhook URL accessibility
- Check HMAC signature configuration
- Ensure JSON payload format
- Test with simple payload first

### Debug Mode
Enable WordPress debug mode for detailed error logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Files
- Import activity: `lai_import_history` option
- Webhook activity: `lai_webhook_history` option
- Error logs: WordPress debug.log
- Database queries: Enable query logging

## Roadmap

### Planned Features
- **Google Sheets Integration**: Direct sync with Google Sheets
- **Zapier Support**: Native Zapier integration
- **Scheduled Imports**: Automated import scheduling
- **Data Transformation**: Advanced data processing rules
- **Bulk Operations**: Mass update and delete operations
- **API Rate Limiting**: Advanced throttling controls
- **Custom Connectors**: Plugin architecture for custom sources

### Integration Enhancements
- **Multi-language Support**: Localization for international partners
- **Custom Field Types**: Support for complex data structures
- **Conditional Logic**: Advanced field mapping rules
- **Data Validation Rules**: Custom validation patterns
- **Export Templates**: Customizable export formats

## Support

### Documentation
- Complete field mapping reference
- Partner onboarding guides
- Troubleshooting procedures
- API documentation

### Partner Support
- Dedicated setup assistance
- Custom field mapping configuration
- Integration testing support
- Performance optimization guidance

## Version History

### v200 (Current)
- Initial release with multi-source support
- CSV, Airtable, and Pipedream integration
- Smart field mapping system
- Admin interface (standalone and integrated)
- REST API and webhook endpoints
- Export functionality with templates
- Comprehensive security features
- Performance optimizations
- Full audit logging

---

*This plugin represents a significant step forward in the Legal Automation Suite's capability to integrate with partner systems and handle diverse data sources efficiently and securely.*