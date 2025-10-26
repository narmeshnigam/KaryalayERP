# Branding Module - Quick Start Guide

## 🚀 Installation Steps

### Step 1: Run Database Setup
Open your terminal/command prompt and navigate to the Karyalay ERP root directory:

```powershell
cd c:\xampp\htdocs\KaryalayERP
php scripts\setup_branding_table.php
```

You should see:
```
✅ branding_settings table created successfully.
✅ Default branding record created.
🎉 Branding Settings module setup complete!
```

### Step 2: Access the Module
1. Login to Karyalay ERP as an **Admin** user
2. Look for **🏢 Branding Settings** in the sidebar (under Admin section)
3. Click to open the branding configuration page

### Step 3: Configure Your Branding
1. **Upload Logos** (optional but recommended)
   - Click "📤 Upload Logo" for each type (light, dark, square)
   - Select your logo file (PNG, JPG, or SVG, max 2MB)
   - Preview appears immediately

2. **Fill Organization Details**
   - Organization Name (required)
   - Add tagline, address, contact info
   - Set custom footer text

3. **Save Settings**
   - Click "💾 Save Branding Settings"
   - Confirmation appears on success

---

## 📋 What Gets Created

### Database
- `branding_settings` table with default record

### Files
```
public/branding/
├── index.php           # Admin settings interface
├── view.php            # Read-only view for all users
├── onboarding.php      # Setup guide page
├── helpers.php         # Backend functions
└── README.md           # Full documentation

public/api/branding/
├── index.php           # GET settings API
├── update.php          # POST update API
├── upload.php          # POST logo upload API
└── delete.php          # DELETE logo API

uploads/branding/       # Logo storage folder
```

### Sidebar Integration
- New menu item: **🏢 Branding Settings** (Admin only)

---

## ✅ Verification Checklist

After installation, verify:
- [ ] Database table `branding_settings` exists
- [ ] Default record inserted with org_name = "Karyalay ERP"
- [ ] Sidebar shows "Branding Settings" link (Admin users)
- [ ] Can access `/public/branding/index.php`
- [ ] Upload folder `uploads/branding/` is writable
- [ ] Can upload and preview logos
- [ ] Can save organization details
- [ ] Non-admin users can access view-only page

---

## 🔧 Troubleshooting

### "Table already exists" error
The module is already installed. Navigate directly to the settings page.

### "Permission denied" on uploads
Make sure the `uploads/branding/` directory has write permissions:
```powershell
# Windows (run as administrator)
icacls "c:\xampp\htdocs\KaryalayERP\uploads\branding" /grant Everyone:F
```

### Sidebar link not showing
- Verify you're logged in as **Admin** role
- Check `includes/sidebar.php` has the branding entry
- Clear browser cache

### Logo upload fails
- Check file size (must be ≤ 2MB)
- Verify file type (PNG, JPG, SVG only)
- Ensure uploads directory is writable

---

## 🎯 Next Steps

1. Upload your company logos (all three variants recommended)
2. Complete organization details
3. Set custom tagline for login page
4. Configure footer text for reports
5. Share view-only link with team: `/public/branding/view.php`

---

## 📚 Additional Resources

- Full documentation: `public/branding/README.md`
- Database schema: See `scripts/setup_branding_table.php`
- Logo guidelines: `uploads/branding/README.md`

---

**Module Version**: 1.0.0  
**Installation Date**: <?php echo date('F j, Y'); ?>  
**Status**: ✅ Ready for configuration
