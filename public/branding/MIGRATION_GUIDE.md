# Branding Module - Square Logo Split Migration Guide

## Overview
This migration splits the single `logo_square` field into two separate fields to support different square logo variants for light and dark backgrounds.

## Database Changes

### New Columns Added
- `logo_square_light` - Square icon optimized for light backgrounds (256x256px)
- `logo_square_dark` - Square icon optimized for dark backgrounds (256x256px)

### Migration Steps

1. **Run the Migration Script**
   ```
   Navigate to: http://localhost/karyalayerp/scripts/migrate_branding_square_logos.php
   Click: "Run Migration"
   ```

2. **What the Migration Does**
   - Adds `logo_square_light` column (TEXT, nullable)
   - Adds `logo_square_dark` column (TEXT, nullable)
   - Migrates existing `logo_square` data to `logo_square_light`
   - Preserves old `logo_square` column for backward compatibility

3. **Post-Migration Actions**
   - Upload square logo variants for light and dark backgrounds
   - Optionally drop the old `logo_square` column manually if no longer needed:
     ```sql
     ALTER TABLE branding_settings DROP COLUMN logo_square;
     ```

## Code Changes

### Helper Functions (`public/branding/helpers.php`)
- Updated `branding_upload_logo()` to accept `square_light` and `square_dark` types
- Updated `branding_delete_logo()` to handle new logo types
- Logo types now supported: `light`, `dark`, `square_light`, `square_dark`

### API Endpoints
All API endpoints now support the new logo types:
- `public/api/branding/upload.php` - Accepts `square_light`, `square_dark`
- `public/api/branding/delete.php` - Handles deletion of new types
- `public/api/branding/index.php` - Returns all logo fields including new ones

### Frontend UI (`public/branding/index.php`)
**Complete UI Redesign** - Now matches `add_employee.php` styling:

#### Section-Based Layout
1. **🎨 Logo Assets** - 4 upload cards in 2x2 grid
   - Logo for Light Backgrounds
   - Logo for Dark Backgrounds
   - Square Icon (Light Background)
   - Square Icon (Dark Background)

2. **🏢 Organization Information** - 3-column grid
   - Organization Name (required)
   - Legal Name
   - GSTIN
   - Tagline (full width)

3. **📍 Address Information** - 2-column grid
   - Address Line 1 & 2 (full width)
   - City, State, ZIP, Country

4. **📞 Contact Information** - 3-column grid
   - Email, Phone, Website

5. **✨ Branding Elements**
   - Footer Text (with character counter)

#### UI Features
- Clean card-based sections with colored headers
- Consistent spacing and grid layouts
- Character counters for tagline (100) and footer (150)
- Real-time upload preview
- Delete confirmation dialogs
- Large, centered submit buttons

### View Page (`public/branding/view.php`)
- Updated to display all 4 logo variants in 2x2 grid
- Square logos shown with appropriate background colors
- Consistent section layout matching the settings page

## Logo Upload Specifications

### File Requirements
- **Formats**: PNG, JPG, SVG
- **Max Size**: 2MB per file
- **MIME Validation**: Server-side check for security

### Logo Variants

| Type | Purpose | Background | Recommended Size |
|------|---------|------------|------------------|
| `light` | Main logo | Light/white backgrounds | Width: 300-400px |
| `dark` | Main logo | Dark/colored backgrounds | Width: 300-400px |
| `square_light` | Icon/favicon | Light themes | 256x256px |
| `square_dark` | Icon/app | Dark themes | 256x256px |

## Testing Checklist

- [ ] Run migration script successfully
- [ ] Upload logo for light background
- [ ] Upload logo for dark background
- [ ] Upload square icon for light background
- [ ] Upload square icon for dark background
- [ ] Verify preview displays correctly
- [ ] Delete a logo and verify removal
- [ ] Update organization details
- [ ] View settings on view page
- [ ] Check character counters work
- [ ] Verify form validation

## Rollback Plan

If you need to rollback the migration:

```sql
-- Remove new columns
ALTER TABLE branding_settings 
DROP COLUMN logo_square_light,
DROP COLUMN logo_square_dark;
```

**Note**: This will delete any uploaded square logo variants.

## API Usage Examples

### Upload Square Logo (Light Background)
```javascript
const formData = new FormData();
formData.append('logo', fileInput.files[0]);
formData.append('type', 'square_light');

fetch('../api/branding/upload.php', {
  method: 'POST',
  body: formData
})
.then(res => res.json())
.then(data => console.log(data));
```

### Delete Square Logo (Dark Background)
```javascript
fetch('../api/branding/delete.php?type=square_dark', {
  method: 'POST'
})
.then(res => res.json())
.then(data => console.log(data));
```

## Support

For issues or questions:
1. Check error logs in browser console
2. Verify database migration completed
3. Ensure `uploads/branding` directory is writable
4. Check PHP error logs for server-side issues

---

**Migration Date**: October 26, 2025  
**Version**: 2.0  
**Compatibility**: Requires existing branding module v1.0
