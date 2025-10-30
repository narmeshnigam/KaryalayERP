# 📇 Contacts Management Module - KaryalayERP

## Overview
The **Contacts Management Module** provides a centralized address book for storing, organizing, and sharing contact information across your organization. It seamlessly integrates with Clients, Projects, and CRM modules to maintain a unified contact database.

## ✨ Features

### Core Functionality
- ✅ **CRUD Operations**: Create, view, edit, and delete contacts
- 👥 **Contact Types**: Client, Vendor, Partner, Personal, Other
- 🔒 **Share Scopes**: Private, Team, Organization
- 🏷️ **Tagging System**: Organize contacts with custom tags (max 10 per contact)
- 🔗 **Entity Linking**: Link contacts to Clients, Projects, Leads, or Other records
- 🔍 **Advanced Search**: Filter by name, email, phone, organization, type, tag, scope
- 📤 **Export**: Export filtered contacts to CSV
- 📥 **Import**: Bulk import up to 500 contacts from CSV
- 👥 **Contact Groups**: Create groups and organize contacts (e.g., Vendors, Investors)

### Contact Information Fields
- **Basic Info**: Name, Organization, Designation, Contact Type
- **Contact Methods**: Phone, Alternate Phone, Email, WhatsApp, LinkedIn
- **Additional**: Address, Tags, Notes
- **Metadata**: Created by, Created at, Updated at

### Permission-Based Access
- **Admin**: Full access to all operations
- **Manager**: Create/edit/view team contacts, export data
- **Employee**: Create/view/edit own and shared contacts
- **Client** (future): Read-only access to shared project contacts

### Quick Actions
- 📞 Call (tel: link)
- ✉️ Email (mailto: link)
- 💬 WhatsApp (direct link)
- 🔗 LinkedIn (profile link)

## 📁 File Structure

```
public/contacts/
├── index.php           # Main contacts listing with filters
├── add.php             # Create new contact
├── view.php            # View contact details with quick actions
├── edit.php            # Edit existing contact
├── my.php              # View only user's own contacts
├── delete.php          # Delete contact handler
├── import.php          # CSV import wizard
├── export.php          # CSV export handler
├── groups.php          # Manage contact groups
└── helpers.php         # Core business logic functions
```

## 🗄️ Database Schema

### Table: `contacts`
Stores all contact information with permission-based visibility.

**Key Fields:**
- `id` - Primary key
- `name` - Full name (required)
- `organization` - Company name
- `designation` - Job title
- `contact_type` - ENUM('Client','Vendor','Partner','Personal','Other')
- `phone`, `alt_phone`, `email`, `whatsapp`, `linkedin` - Contact methods
- `address` - Physical location
- `tags` - Comma-separated tags
- `notes` - Additional remarks
- `linked_entity_id`, `linked_entity_type` - Optional entity linking
- `share_scope` - ENUM('Private','Team','Organization')
- `created_by` - Foreign key to users table
- `created_at`, `updated_at` - Timestamps

### Table: `contact_groups`
Organize contacts into named groups.

**Key Fields:**
- `id` - Primary key
- `name` - Group name
- `description` - Optional description
- `created_by` - Foreign key to users table
- `created_at` - Timestamp

### Table: `contact_group_map`
Many-to-many relationship between contacts and groups.

**Key Fields:**
- `id` - Primary key
- `group_id` - Foreign key to contact_groups
- `contact_id` - Foreign key to contacts

## 🚀 Installation & Setup

### Step 1: Run Database Setup Script
```bash
# Navigate to the scripts directory in browser
http://localhost/KaryalayERP/scripts/setup_contacts_tables.php
```

The setup script will:
- ✅ Create `contacts` table with indexes
- ✅ Create `contact_groups` table
- ✅ Create `contact_group_map` table with foreign keys
- ✅ Set up proper relationships and cascade rules

### Step 2: Configure Permissions
1. Navigate to **Settings → Roles & Permissions**
2. Add the following permissions for appropriate roles:
   - `contacts.view` - View contacts
   - `contacts.create` - Create new contacts
   - `contacts.edit` - Edit own contacts
   - `contacts.edit_all` - Edit any contact (Admin)
   - `contacts.delete` - Delete own contacts
   - `contacts.delete_all` - Delete any contact (Admin)
   - `contacts.export` - Export contacts to CSV
   - `contacts.import` - Import contacts from CSV

### Step 3: Access the Module
Navigate to **Contacts** from the sidebar menu or visit:
```
http://localhost/KaryalayERP/public/contacts/
```

## 📊 Usage Guide

### Creating a Contact
1. Click **➕ Add Contact** button
2. Fill required fields:
   - Name (required)
   - At least one contact method: Phone, Email, or WhatsApp
3. Optional fields:
   - Organization, Designation
   - All contact methods
   - Tags (comma-separated, max 10)
   - Notes
   - Entity linking (Client/Project/Lead)
   - Share scope (Private/Team/Organization)
4. Click **✅ Create Contact**

### Importing Contacts
1. Navigate to **Import** from the main contacts page
2. Download the sample CSV template
3. Prepare your CSV file with columns in exact order:
   - Name, Organization, Designation, Contact Type, Phone, Alt Phone, Email, WhatsApp, LinkedIn, Address, Tags, Notes, Share Scope
4. Upload the CSV file (max 5MB, 500 contacts per batch)
5. Review import results and errors

### CSV Format Example
```csv
Name,Organization,Designation,Contact Type,Phone,Alt Phone,Email,WhatsApp,LinkedIn,Address,Tags,Notes,Share Scope
John Doe,ABC Corp,Manager,Client,+91 98765 43210,,john@abc.com,+91 98765 43210,https://linkedin.com/in/johndoe,Mumbai,vip,Important client,Organization
Jane Smith,XYZ Ltd,CEO,Vendor,+91 87654 32109,,jane@xyz.com,,,Delhi,vendor,Regular supplier,Team
```

### Exporting Contacts
1. Apply any filters you want (search, type, tag, scope)
2. Click **📤 Export** button
3. CSV file will download with filtered contacts

### Managing Contact Groups
1. Click **👥 Groups** button
2. Create a new group with name and description
3. Select a group to view its contacts
4. Groups help organize contacts by category (e.g., Vendors, Investors, Partners)

### Search & Filter
- **Search**: Name, email, phone, organization
- **Filter by Type**: Client, Vendor, Partner, Personal, Other
- **Filter by Tag**: Select from existing tags
- **Filter by Scope**: Private, Team, Organization

### Quick Views
- **All Contacts**: Shows all accessible contacts
- **My Contacts**: Only contacts you created
- **Clients**: Filtered by Client type
- **Vendors**: Filtered by Vendor type

## 🔐 Permission Logic

### Share Scope Rules
1. **Private**: Only the creator can view/edit
2. **Team**: All users with the same role can view (creator can edit)
3. **Organization**: Everyone can view (creator can edit)

### Edit Permissions
- Contact owner can always edit their own contacts
- Admin can edit all contacts
- Other users cannot edit contacts they don't own (except Admin)

### Delete Permissions
- Same as edit permissions
- Deleting a contact cascades to remove group mappings

## 🎨 UI Components

### Contact Card (Grid View)
- Avatar with initials
- Name, Designation, Organization
- Contact type badge
- Share scope indicator
- Primary contact methods (phone, email, WhatsApp)
- Tags (first 3 shown)
- Created by and date

### Contact Details View
- Large avatar header
- Quick action buttons (Call, Email, WhatsApp, LinkedIn)
- Complete contact information
- Tags (clickable for filtering)
- Visibility indicator
- Entity linking info
- Metadata (created by, timestamps, contact ID)

### Statistics Dashboard
Shows 5 key metrics:
- 📊 Total Contacts
- 👤 My Contacts
- 🤝 Shared With Me
- 💼 Clients
- 🏢 Vendors

## 🔧 Helper Functions Reference

### Core Functions (helpers.php)
- `contacts_tables_exist($conn)` - Check if module is installed
- `get_all_contacts($conn, $user_id, $filters)` - Retrieve contacts with filters
- `get_contact_by_id($conn, $contact_id, $user_id)` - Get single contact
- `can_access_contact($conn, $contact_id, $user_id)` - Permission check
- `can_edit_contact($conn, $contact_id, $user_id)` - Edit permission check
- `create_contact($conn, $data, $user_id)` - Create new contact
- `update_contact($conn, $contact_id, $data, $user_id)` - Update contact
- `delete_contact($conn, $contact_id, $user_id)` - Delete contact
- `validate_contact_data($data)` - Validate form data
- `find_duplicate_contacts($conn, $email, $phone, $exclude_id)` - Duplicate detection
- `get_all_contact_tags($conn)` - Get unique tags list
- `get_contacts_statistics($conn, $user_id)` - Get dashboard stats

### Group Functions
- `get_all_contact_groups($conn, $user_id)` - List all groups
- `create_contact_group($conn, $name, $description, $user_id)` - Create group
- `add_contact_to_group($conn, $contact_id, $group_id)` - Add to group
- `remove_contact_from_group($conn, $contact_id, $group_id)` - Remove from group
- `get_group_contacts($conn, $group_id, $user_id)` - Get contacts in group
- `delete_contact_group($conn, $group_id, $user_id)` - Delete group

### Import/Export Functions
- `export_contacts_to_csv($contacts)` - Generate CSV export
- `parse_contact_csv($file_path)` - Parse and validate CSV import

### Utility Functions
- `get_contact_initials($name)` - Generate 2-letter initials for avatar
- `get_contact_type_icon($type)` - Get emoji icon for contact type
- `get_share_scope_icon($scope)` - Get emoji icon for share scope

## 🔄 Integration Points

### CRM Module
Link contacts to CRM leads for tracking client relationships.

### Clients Module
Sync client contact persons automatically to maintain updated information.

### Projects Module
Add project stakeholder contacts for team collaboration.

### Notebook Module
Reference contacts within notes for meeting minutes or project documentation.

## 🛡️ Security Features

1. **SQL Injection Protection**: All queries use prepared statements
2. **XSS Protection**: All output uses `htmlspecialchars()`
3. **Permission Checks**: Every action validates user permissions
4. **CSRF Protection**: Forms use POST method for modifications
5. **File Upload Validation**: CSV files validated for size and type
6. **Duplicate Prevention**: Email and phone uniqueness enforced

## 📝 Validation Rules

1. **Name**: Required, cannot be empty
2. **Contact Methods**: At least one of phone/email/WhatsApp required
3. **Email**: Must be valid email format
4. **Tags**: Maximum 10 tags per contact
5. **Duplicates**: Email and phone must be unique per organization
6. **CSV Import**: Maximum 500 contacts per batch, 5MB file size limit

## 🚦 Error Handling

- Form validation errors displayed at top of page
- Flash messages for success/error feedback
- Import errors shown row-by-row with line numbers
- Permission denied redirects to unauthorized page
- Non-existent contacts redirect to listing page

## 🔮 Future Enhancements (Phase 2)

1. **Contact Activity Timeline**: Track calls, tasks, visits
2. **Google Contacts Sync**: Two-way sync with Google
3. **Outlook Integration**: Import/export Outlook contacts
4. **Contact Hierarchy**: Decision Maker, Coordinator roles
5. **@Mentions**: Tag users for internal collaboration
6. **Smart Duplicate Detection**: AI-based matching
7. **Predictive Tags**: Auto-suggest tags based on history
8. **Birthday Reminders**: Notification system for important dates
9. **Bulk Operations**: Multi-select for group actions
10. **Contact Merge**: Combine duplicate contacts

## 💡 Tips & Best Practices

1. **Use Share Scopes Wisely**: Set Organization scope for company-wide contacts
2. **Tag Consistently**: Use standard tags like "vip", "important", "follow-up"
3. **Link to Entities**: Connect contacts to Clients/Projects for better context
4. **Regular Exports**: Backup contacts monthly via export
5. **Clean Duplicates**: Use duplicate detection before creating new contacts
6. **Use Groups**: Organize contacts by category for quick access
7. **Complete Profiles**: Fill all available fields for comprehensive records

## 📞 Support

For issues or questions:
- Check validation errors on form submission
- Verify permissions in Roles & Permissions module
- Ensure database tables are created (run setup script)
- Review browser console for JavaScript errors

## 🎯 Module Status

✅ **Production Ready** - All core features implemented and tested

**Version**: 1.0.0  
**Last Updated**: 2025-10-30  
**Author**: KaryalayERP Development Team
