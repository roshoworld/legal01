# mPDF Manual Installation Guide for Siteground
## Document Generator Plugin v1.0.7

### Overview
This guide helps you manually install mPDF library on Siteground with smart font loading (10MB base + additional fonts as needed).

---

## Step 1: Download Required Files

### 1.1 Download mPDF Core Package
```bash
# Download from: https://github.com/mpdf/mpdf/releases/download/v8.2.5/mpdf-8.2.5.zip
# Or use this direct link in your browser
```

### 1.2 Essential Files to Extract
From the downloaded zip, you need:
- `vendor/mpdf/mpdf/src/` (3.5MB) - Core mPDF library
- `vendor/mpdf/mpdf/data/` (2.8MB) - Essential data files
- `vendor/autoload.php` - Autoloader
- `vendor/composer/` - Composer files

### 1.3 Basic Fonts Package (4MB)
From `vendor/mpdf/mpdf/ttfonts/`, copy only these essential fonts:
- `DejaVuSans.ttf`
- `DejaVuSans-Bold.ttf`
- `DejaVuSansCondensed.ttf`
- `DejaVuSansCondensed-Bold.ttf`
- `DejaVuSerif.ttf`
- `DejaVuSerif-Bold.ttf`

---

## Step 2: Siteground Upload Structure

### 2.1 Create Directory Structure
Upload to: `/public_html/wp-content/uploads/klage-mpdf/`

```
/public_html/wp-content/uploads/klage-mpdf/
├── vendor/
│   ├── autoload.php
│   ├── composer/
│   │   ├── autoload_*.php
│   │   └── ClassLoader.php
│   ├── mpdf/
│   │   └── mpdf/
│   │       ├── src/          (3.5MB - Core library)
│   │       ├── data/         (2.8MB - Essential data)
│   │       └── ttfonts/      (4MB - Basic fonts only)
│   ├── setasign/
│   │   └── fpdi/
│   └── psr/
│       ├── log/
│       └── http-message/
└── fonts/                    (For additional fonts)
```

### 2.2 File Permissions
Set permissions via Siteground File Manager:
- Directories: `755`
- Files: `644`

---

## Step 3: Smart Font Loading Setup

### 3.1 Additional Fonts Directory
Create: `/public_html/wp-content/uploads/klage-mpdf/fonts/`

### 3.2 Download Additional Fonts As Needed
For international characters, download to `fonts/` folder:
- `NotoSans-Regular.ttf` (2MB) - International support
- `NotoSans-Bold.ttf` (2MB)
- `Arial.ttf` (1.5MB) - Windows compatibility

### 3.3 Font Loading Configuration
The plugin automatically detects available fonts and loads them as needed.

---

## Step 4: Verification

### 4.1 Test Installation
1. Log into WordPress Admin
2. Go to Klage.Click → Document Generator
3. Check System Status:
   - mPDF Library: Should show "OK"
   - Template Manager: Should show "OK"
   - Document Generator: Should show "OK"

### 4.2 Test PDF Generation
1. Create a simple template
2. Generate a document
3. Create PDF - should work without errors

---

## Step 5: Troubleshooting

### 5.1 Common Issues

**Error: "mPDF not found"**
- Check file path: `/wp-content/uploads/klage-mpdf/vendor/autoload.php`
- Verify file permissions (644 for files, 755 for directories)

**Error: "Font not found"**
- Add missing fonts to `/wp-content/uploads/klage-mpdf/fonts/`
- Check font file extensions (.ttf)

**Error: "Memory exceeded"**
- Contact Siteground to increase PHP memory limit
- Or use fewer fonts simultaneously

### 5.2 Siteground-Specific Notes
- Use File Manager, not FTP, for better compatibility
- Enable PHP error logging in cPanel
- Check PHP version compatibility (>= 7.4)

---

## Step 6: Maintenance

### 6.1 Plugin Updates
- mPDF installation persists through plugin updates
- Only update mPDF if issues arise

### 6.2 Adding New Fonts
- Download font files (.ttf format)
- Upload to `/wp-content/uploads/klage-mpdf/fonts/`
- Clear any caches

---

## File Size Summary
- **Base Installation**: ~10MB
- **With Additional Fonts**: ~15MB
- **Plugin Size**: 209KB (separate from mPDF)

## Download Links
- **mPDF v8.2.5**: https://github.com/mpdf/mpdf/releases/download/v8.2.5/mpdf-8.2.5.zip
- **Additional Fonts**: https://fonts.google.com/noto (Noto Sans family)

---

## Support
If you encounter issues, check:
1. PHP error logs in Siteground cPanel
2. WordPress debug log
3. Plugin system status page

**Installation Complete!** The plugin should now have full PDF generation capabilities with smart font loading.