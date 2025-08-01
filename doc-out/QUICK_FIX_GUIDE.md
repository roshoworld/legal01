# Quick Fix Guide for System Status Errors

## Current Status Issues:
- ❌ mPDF Library: Error
- ❌ Template Manager: Error  
- ❌ Document Generator: Error
- ❌ S3 Storage: Error (not configured)
- ❌ Debtors Table: Missing

## Fix 1: Install mPDF Library

### Option A: Download Package from Development Environment
1. Get `mpdf-smart-package.tar.gz` (6.3MB) from this development environment
2. Extract to `/wp-content/uploads/klage-mpdf/` on Siteground
3. Set permissions: files 644, directories 755

### Option B: Create mPDF Package Manually
1. Download: https://github.com/mpdf/mpdf/releases/download/v8.2.5/mpdf-8.2.5.zip
2. Extract and copy only these folders to `/wp-content/uploads/klage-mpdf/vendor/`:
   - `vendor/mpdf/mpdf/src/` (3.5MB)
   - `vendor/mpdf/mpdf/data/` (2.8MB)
   - `vendor/mpdf/mpdf/ttfonts/DejaVu*` (4MB - only DejaVu fonts)
   - `vendor/autoload.php`
   - `vendor/composer/`
   - `vendor/setasign/`
   - `vendor/psr/`

## Fix 2: Debtors Table Issue

### Automatic Fix (Recommended)
1. Go to WordPress Admin → Klage.Click → Document Generator
2. The plugin will automatically detect missing debtors table
3. It will create the table automatically and show a success notice

### Manual Fix (If Automatic Fails)
1. Go to WordPress Admin → Plugins
2. Deactivate "Court Automation Hub" (core plugin)
3. Reactivate "Court Automation Hub" 
4. This will recreate the debtors table

## Fix 3: S3 Storage Configuration

### Configure S3 Storage
1. Go to WordPress Admin → Klage.Click → Document Generator → Settings
2. Enter your IONOS S3 credentials:
   - **Access Key**: Your S3 access key
   - **Secret Key**: Your S3 secret key  
   - **Bucket Name**: Your S3 bucket name
   - **Region**: Your S3 region
3. Save settings

## Expected Results After Fixes:

```
✅ Core Plugin: OK
✅ Financial Plugin: OK
✅ mPDF Library: OK
✅ Template Manager: OK
✅ Document Generator: OK
✅ S3 Storage: OK (after configuration)
✅ Cases Table: 4 cases
✅ Debtors Table: X debtors
```

## Testing Steps:
1. Fix mPDF installation first
2. Check system status → Should show mPDF, Template Manager, Document Generator as OK
3. Fix debtors table → Should show debtors table as OK
4. Configure S3 storage → Should show S3 storage as OK

## If Issues Persist:
- Check PHP error logs in Siteground cPanel
- Verify file permissions (644 for files, 755 for directories)
- Ensure WordPress has write permissions to `/wp-content/uploads/`

## File Structure After mPDF Fix:
```
/wp-content/uploads/klage-mpdf/
├── vendor/
│   ├── autoload.php
│   ├── composer/
│   ├── mpdf/mpdf/
│   │   ├── src/          (3.5MB)
│   │   ├── data/         (2.8MB)
│   │   └── ttfonts/      (4MB - DejaVu fonts)
│   ├── setasign/fpdi/
│   └── psr/
```

This should resolve all the system status errors!