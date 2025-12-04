# 📇 Contacts Module - Quick Reference Card

## 🚀 Getting Started

### 1. Setup (One-Time)
```
1. Run: http://localhost/KaryalayERP/scripts/setup_contacts_tables.php
2. Go to Settings → Roles & Permissions
3. Add permissions: contacts.view, contacts.create, contacts.edit, contacts.delete, contacts.export
4. Access: http://localhost/KaryalayERP/public/contacts/
```

### 2. Required Permissions
```
contacts.view         - View contacts (Required for all users)
contacts.create       - Create new contacts (Employee+)
contacts.edit         - Edit own contacts (Employee+)
contacts.edit_all     - Edit any contact (Admin only)
contacts.delete       - Delete own contacts (Employee+)
contacts.delete_all   - Delete any contact (Admin only)
contacts.export       - Export contacts (Manager+)
contacts.import       - Import contacts (Manager+)
```

---

## 📝 Common Operations

### Add a Contact
1. Click **➕ Add Contact**
2. Fill: Name + at least one contact method (phone/email/WhatsApp)
3. Choose Share Scope: Private / Team / Organization
4. Click **✅ Create Contact**

### Import Contacts (CSV)
1. Click **📥 Import**
2. Download sample CSV
3. Prepare your data (max 500 rows, 5MB)
4. Upload and review results

### Export Contacts
1. Apply filters (optional)
2. Click **📤 Export**
3. CSV downloads automatically

### Create a Group
1. Click **👥 Groups**
2. Fill name and description
3. Click **✅ Create Group**

### Search & Filter
- **Search**: Name, email, phone, organization
- **Type**: Client, Vendor, Partner, Personal, Other
- **Tag**: Select from existing tags
- **Scope**: Private, Team, Organization

---

## 📋 CSV Import Format

### Column Order (13 Columns)
```
1.  Name             (Required)
2.  Organization     
3.  Designation      
4.  Contact Type     (Client/Vendor/Partner/Personal/Other)
5.  Phone            (At least one required: Phone/Email/WhatsApp)
6.  Alt Phone        
7.  Email            (At least one required: Phone/Email/WhatsApp)
8.  WhatsApp         (At least one required: Phone/Email/WhatsApp)
9.  LinkedIn         
10. Address          
11. Tags             (Comma-separated, max 10)
12. Notes            
13. Share Scope      (Private/Team/Organization)
```

### Example CSV Row
```csv
John Doe,ABC Corp,Manager,Client,+91 9876543210,,john@abc.com,+91 9876543210,https://linkedin.com/in/johndoe,Mumbai,vip,Important client,Organization
```

---

## 🔍 Filter Shortcuts

### Quick Views
- **All Contacts**: Main page (all accessible)
- **My Contacts**: `/my.php` (only yours)
- **Clients**: `?contact_type=Client`
- **Vendors**: `?contact_type=Vendor`

### URL Filters
```
?search=keyword           - Search text
?contact_type=Client      - Filter by type
?tag=important            - Filter by tag
?share_scope=Organization - Filter by scope
```

---

## 🏷️ Share Scope Guide

| Scope | Who Can View? | Who Can Edit? |
|-------|--------------|---------------|
| 🔒 **Private** | Only you | Only you |
| 👥 **Team** | Same role members | Only you |
| 🌐 **Organization** | Everyone | Only you |

**Note**: Admin can always view/edit all contacts

---

## 🎯 Contact Types

| Type | Icon | Use For |
|------|------|---------|
| Client | 👤 | Customer contacts |
| Vendor | 🏢 | Supplier contacts |
| Partner | 🤝 | Business partners |
| Personal | 📱 | Personal contacts |
| Other | 📇 | Miscellaneous |

---

## 🔗 Quick Actions

### From Contact Card
- Click anywhere → View details
- Click phone → Direct call (tel:)
- Click email → Compose email (mailto:)

### From Details View
- 📞 **Call** - Opens phone dialer
- ✉️ **Email** - Opens email client
- 💬 **WhatsApp** - Opens WhatsApp chat
- 🔗 **LinkedIn** - Opens profile in new tab

---

## ⚠️ Validation Rules

1. **Name**: Required
2. **Contact Method**: At least one (phone/email/WhatsApp)
3. **Email**: Must be valid format
4. **Duplicates**: Email and phone must be unique
5. **Tags**: Maximum 10 per contact
6. **CSV Batch**: Maximum 500 contacts
7. **File Size**: Maximum 5MB for CSV

---

## 💡 Pro Tips

1. **Use Organization Scope** for company-wide contacts
2. **Tag Consistently** - Use standard tags across team
3. **Link to Entities** - Connect to Clients/Projects for context
4. **Regular Exports** - Monthly backups recommended
5. **Clean Duplicates** - Check before creating new
6. **Complete Profiles** - Fill all fields for best results
7. **Use Groups** - Organize by category (Vendors, VIPs, etc.)

---

## 🛠️ Troubleshooting

### "Contact not found or access denied"
- Check share scope (might be Private)
- Verify you have `contacts.view` permission
- Contact might have been deleted

### "Duplicate contact exists"
- Email or phone already in database
- Search for existing contact first
- Admin can view all to check

### CSV Import Fails
- Check column order matches exactly
- Verify at least one contact method per row
- Ensure Contact Type and Share Scope values are valid
- File must be under 5MB
- Max 500 contacts per import

### Can't Edit Contact
- Only owner or Admin can edit
- Check if you have `contacts.edit` permission
- Shared contacts can only be edited by owner

---

## 📊 Dashboard Statistics

The main page shows 5 key metrics:
1. 📊 **Total Contacts** - All accessible
2. 👤 **My Contacts** - Created by you
3. 🤝 **Shared With Me** - Others' shared contacts
4. 💼 **Clients** - Client type contacts
5. 🏢 **Vendors** - Vendor type contacts

---

## 🔐 Security Notes

- All queries use prepared statements (SQL injection safe)
- Output is escaped (XSS safe)
- Permission checks on every page
- POST method for modifications (CSRF safe)
- File uploads validated (type and size)
- Duplicate prevention enforced

---

## 📞 Need Help?

1. Check **README.md** for detailed documentation
2. Verify permissions in Roles module
3. Check browser console for errors
4. Ensure database tables exist (run setup script)
5. Review validation errors on form submission

---

## ✅ Quick Checklist

Before using the module:
- [ ] Database tables created (run setup script)
- [ ] Permissions configured in Roles module
- [ ] Users assigned appropriate roles
- [ ] Sample CSV downloaded for reference
- [ ] Test contact created successfully

---

**Module**: Contacts Management  
**Version**: 1.0.0  
**Status**: Production Ready  
**Last Updated**: October 30, 2025
