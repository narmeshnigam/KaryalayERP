# Branding & Organization Settings Module

## 📘 Overview
The **Branding & Organization Settings Module** manages the visual identity and business information for your Karyalay ERP instance. This module ensures consistent branding across all parts of the system including reports, PDFs, headers, footers, and login screens.

---

## ✨ Features

### For Administrators
- **Logo Management**
  - Upload primary logo for light backgrounds
  - Upload primary logo for dark backgrounds  
  - Upload square icon for favicons and compact displays
  - Live preview before saving
  - Easy deletion and replacement

- **Organization Details**
  - Company name and legal name
  - Brand tagline/slogan (max 100 characters)
  - Complete address information
  - Contact details (email, phone, website)
  - Business registration (GSTIN, etc.)
  - Custom footer text (max 150 characters)

- **Validation & Safety**
  - File type validation (PNG, JPG, SVG only)
  - File size limit (2MB per logo)
  - Email and URL format validation
  - Character limits for tagline and footer

### For Employees/Managers
- View-only access to organization information
- Display of all configured logos
- Access to contact details and address

---

## 🚀 Setup Instructions

### 1. Run Database Setup
Navigate to the scripts directory and run:
```bash
php scripts/setup_branding_table.php
```

This will create the `branding_settings` table with a default skeleton record.

### 2. Access Branding Settings
- Login as an **Admin** user
- Navigate to **Settings > Branding Settings** in the sidebar
- Configure your organization details and upload logos

### 3. Initial Configuration
Required fields:
- Organization Name (required)

Optional but recommended:
- Light and dark background logos
- Square icon for favicons
- Complete address and contact information
- Tagline for login page branding

---

## 📂 Module Structure

```
public/
├── branding/
│   ├── index.php          # Admin settings page
│   ├── view.php           # Read-only view for all users
│   ├── onboarding.php     # Initial setup guide
│   ├── helpers.php        # Core functions
│   └── README.md          # This file
│
├── api/
│   └── branding/
│       ├── index.php      # GET settings endpoint
│       ├── update.php     # POST update settings
│       ├── upload.php     # POST upload logo
│       └── delete.php     # DELETE logo file
│
uploads/
└── branding/              # Logo file storage
    └── README.md

scripts/
└── setup_branding_table.php  # Database setup script
```

---

## 🔐 Access Control

| Role | Permissions |
|------|-------------|
| **Admin** | Full access: view, edit, upload logos, update settings |
| **Manager** | Read-only: view organization details and logos |
| **Employee** | Read-only: view organization details and logos |

Unauthorized access attempts to admin pages redirect to `/unauthorized.php`

---

## 🎨 Logo Guidelines

### Recommended Specifications
- **Light Background Logo**: 300×80px (or similar aspect ratio)
- **Dark Background Logo**: 300×80px with light/white elements
- **Square Icon**: 256×256px for favicons

### File Requirements
- **Formats**: PNG, JPG, SVG
- **Max Size**: 2MB per file
- **Color Mode**: RGB

### Best Practices
- Use transparent backgrounds for PNGs
- Ensure logos are crisp at different sizes
- Test visibility on both light and dark backgrounds
- Keep file sizes optimized for web

---

## 🔧 API Reference

### Get Settings
```
GET /api/branding/index.php
```
Returns current branding configuration (authenticated users only)

### Update Settings
```
POST /api/branding/update.php
Required: org_name
Optional: All other organization fields
Admin only
```

### Upload Logo
```
POST /api/branding/upload.php
Fields:
  - logo: file upload
  - type: 'light' | 'dark' | 'square'
Admin only
```

### Delete Logo
```
POST /api/branding/delete.php?type={light|dark|square}
Admin only
```

---

## 🔗 Integration Points

The branding module integrates with:
- **Header/Footer**: Displays appropriate logos based on theme
- **Login Page**: Shows tagline and light logo
- **PDF Reports**: Embeds organization details and logos
- **Email Templates**: Uses configured contact information
- **System Footer**: Displays custom footer text

---

## 🐛 Troubleshooting

### Upload Fails
- Check file size (must be ≤ 2MB)
- Verify file type (PNG, JPG, SVG only)
- Ensure `uploads/branding/` directory is writable

### Settings Not Saving
- Verify you're logged in as Admin
- Check required field: Organization Name
- Validate email format and URL structure

### Logos Not Displaying
- Clear browser cache
- Check file path in database
- Verify files exist in `uploads/branding/`

---

## 📋 Database Schema

Table: `branding_settings`

| Field | Type | Description |
|-------|------|-------------|
| id | INT PK | Unique identifier |
| org_name | VARCHAR(150) | Organization name (required) |
| legal_name | VARCHAR(150) | Legal entity name |
| tagline | VARCHAR(150) | Brand tagline/slogan |
| address_line1 | VARCHAR(255) | Address line 1 |
| address_line2 | VARCHAR(255) | Address line 2 |
| city | VARCHAR(100) | City |
| state | VARCHAR(100) | State/Province |
| zip | VARCHAR(20) | ZIP/Postal code |
| country | VARCHAR(100) | Country |
| email | VARCHAR(100) | Contact email |
| phone | VARCHAR(20) | Phone number |
| website | VARCHAR(150) | Website URL |
| gstin | VARCHAR(50) | GSTIN/Business reg |
| footer_text | TEXT | Custom footer text |
| logo_light | TEXT | Path to light logo |
| logo_dark | TEXT | Path to dark logo |
| logo_square | TEXT | Path to square icon |
| created_by | INT FK | Admin who created |
| updated_at | TIMESTAMP | Last update time |

---

## 🎯 Future Enhancements

Planned for Phase 2:
- [ ] UI theme color customization
- [ ] Favicon management with multiple resolutions
- [ ] Multi-branch branding (different logos per branch)
- [ ] Custom CSS injection for advanced branding
- [ ] Logo version history and rollback

---

## 📞 Support

For issues or feature requests related to the Branding module:
1. Check this documentation first
2. Verify database setup was successful
3. Review file permissions on uploads directory
4. Contact your system administrator

---

**Last Updated**: October 2025  
**Module Version**: 1.0.0  
**Compatible with**: Karyalay ERP v2.0+
