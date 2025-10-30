# Karyalay ERP: Clients Module

## 📋 Overview

The **Clients Module** is a comprehensive client relationship management system designed to serve as the single source of truth for all client-related information in your organization. It provides a 360° view of each client, including their contact persons, addresses, documents, projects, and interaction history.

## ✨ Key Features

### Client Management
- **Unique Client Codes**: Auto-generated codes (e.g., ACME-001) for easy reference
- **Client Lifecycle**: Active/Inactive status management
- **Ownership Assignment**: Assign client owners for account management
- **Rich Metadata**: Industry, tags, GSTIN, website, contact details
- **Custom Fields**: Extensible key-value pairs for additional data

### 360° Client Profile
- **Overview Tab**: Complete client information at a glance
- **Contacts Tab**: Linked contact persons with roles
- **Addresses Tab**: Multiple addresses (billing, site, HQ) with default flag
- **Documents Tab**: Attached files (NDAs, contracts, certificates)
- **Projects Tab**: All projects associated with the client
- **Timeline Tab**: Activity history and milestones

### Advanced Features
- **Duplicate Detection**: Automatic detection of duplicate emails/phones
- **Lead Conversion**: Convert CRM leads to clients seamlessly
- **Bulk Import/Export**: CSV-based bulk operations
- **Advanced Filtering**: Search, filter by status, owner, industry, tags
- **Multi-address Support**: Store multiple locations per client
- **Contact Mapping**: Link existing contacts to clients
- **Document Management**: Upload and organize client documents

## 🚀 Setup Instructions

### 1. Database Setup

Navigate to the setup script in your browser:

```
http://localhost/KaryalayERP/scripts/setup_clients_tables.php
```

This will create 5 tables:
- `clients` - Main client information
- `client_addresses` - Multiple addresses per client
- `client_contacts_map` - Links contacts to clients
- `client_documents` - File attachments
- `client_custom_fields` - Extensible custom data

### 2. Permissions Setup

Ensure users have appropriate permissions in **Roles & Permissions**:

| Permission | Description |
|------------|-------------|
| `clients.view` | View client list and profiles |
| `clients.create` | Add new clients |
| `clients.update` | Edit client information |
| `clients.delete` | Delete clients (use cautiously) |

### 3. Navigation

The **Clients** menu item appears in the sidebar for users with `clients.view` permission.

## 📖 User Guide

### Adding a New Client

1. Click **"+ Add New Client"** button
2. Fill in basic information (name is required)
3. Add contact details, industry, owner
4. Set status and tags
5. System checks for duplicates automatically
6. Click **"Create Client"** to save

### Converting a Lead to Client

From CRM module:
1. Open a lead with status "Qualified"
2. Click **"Convert to Client"** button
3. Review pre-filled information from lead
4. Add additional details
5. System creates client and links lead_id
6. Lead status automatically updates to "Converted"

### Managing Client Information

#### Editing Client Details
- Open client profile
- Click **"Edit"** button
- Modify information (client code cannot be changed)
- Save changes

#### Adding Addresses
- Go to **Addresses** tab
- Click **"+ Add Address"**
- Enter address details
- Mark as default if needed
- Multiple addresses supported (Billing, Site, HQ, Other)

#### Linking Contacts
- Go to **Contacts** tab
- Click **"Add from Contacts"**
- Select contact from Contacts module
- Specify role at client (e.g., "CEO", "Project Manager")
- Contact appears in both modules

#### Uploading Documents
- Go to **Documents** tab
- Click **"+ Upload Document"**
- Select file (PDF, DOCX, XLSX, PNG, JPG - max 10MB)
- Choose document type (NDA, Contract, PO, Certificate)
- File stored in `/uploads/clients/`

### Filtering and Searching

**Search Bar**: Search by name, email, phone, or client code

**Filters Available**:
- Status (Active/Inactive)
- Owner (assigned user)
- Industry
- Tags

**Quick Views**:
- **All Clients**: Complete list with filters
- **My Clients**: Only clients owned by you

### Import/Export

#### Exporting Clients
1. Apply filters (optional)
2. Go to **Import/Export** page
3. Click **"Export All Clients to CSV"**
4. Opens in Excel/Google Sheets

#### Importing Clients
1. Download sample CSV file
2. Fill in client data following the format
3. Upload CSV file
4. Preview and confirm import
5. System detects duplicates automatically
6. Choose to skip or merge duplicates

**CSV Format**:
```csv
Name,Legal Name,Industry,Email,Phone,Website,GSTIN,Status,Owner,Tags,Notes
"Acme Corp","Acme Corporation Ltd","IT Services","info@acme.com","9876543210","https://acme.com","22AAAAA0000A1Z5","Active","admin","VIP,Enterprise","Important client"
```

## 🔧 Technical Details

### Database Schema

#### clients
```sql
id, code, name, legal_name, industry, website, 
email, phone, gstin, status, owner_id, lead_id, 
tags, notes, created_by, created_at, updated_at
```

#### client_addresses
```sql
id, client_id, label, line1, line2, city, 
state, zip, country, is_default
```

#### client_contacts_map
```sql
id, client_id, contact_id, role_at_client
UNIQUE constraint on (client_id, contact_id)
```

#### client_documents
```sql
id, client_id, file_name, file_path, doc_type, 
uploaded_by, uploaded_at
```

#### client_custom_fields
```sql
id, client_id, field_key, field_value
UNIQUE constraint on (client_id, field_key)
```

### Foreign Key Relationships

**RESTRICT Deletes** (prevents deletion):
- `clients.owner_id` → `users.id`
- `clients.created_by` → `users.id`
- `client_documents.uploaded_by` → `users.id`

**CASCADE Deletes** (auto-removes related data):
- `client_addresses.client_id` → `clients.id`
- `client_contacts_map.client_id` → `clients.id`
- `client_documents.client_id` → `clients.id`
- `client_custom_fields.client_id` → `clients.id`

### Helper Functions

Located in `/public/clients/helpers.php`:

**Client Operations**:
- `get_all_clients($conn, $user_id, $filters)` - List with filters
- `get_client_by_id($conn, $client_id)` - Single client details
- `create_client($conn, $data, $user_id)` - Create new client
- `update_client($conn, $client_id, $data)` - Update existing
- `validate_client_data($data)` - Validation rules
- `find_duplicate_clients($conn, $email, $phone, $exclude_id)` - Duplicate check
- `generate_client_code($conn, $name)` - Auto-generate unique code

**Address Management**:
- `get_client_addresses($conn, $client_id)` - List addresses
- `add_client_address($conn, $client_id, $data)` - Create address
- `update_client_address($conn, $address_id, $data)` - Update address
- `delete_client_address($conn, $address_id)` - Remove address

**Contact Mapping**:
- `get_client_contacts($conn, $client_id)` - List linked contacts
- `link_contact_to_client($conn, $client_id, $contact_id, $role)` - Create link
- `unlink_contact_from_client($conn, $map_id)` - Remove link

**Document Management**:
- `get_client_documents($conn, $client_id)` - List documents
- `upload_client_document($conn, $client_id, $file, $doc_type, $user_id)` - Upload file
- `delete_client_document($conn, $doc_id)` - Remove document

**Custom Fields**:
- `get_client_custom_fields($conn, $client_id)` - Get all fields
- `set_client_custom_field($conn, $client_id, $key, $value)` - Set field
- `delete_client_custom_field($conn, $field_id)` - Remove field

**Lead Conversion**:
- `convert_lead_to_client($conn, $lead_id, $user_id)` - Convert lead

**Statistics**:
- `get_clients_statistics($conn, $user_id)` - Dashboard stats
- `get_all_industries($conn)` - Unique industries list
- `get_all_client_tags($conn)` - Unique tags list

### File Structure

```
public/clients/
├── helpers.php              # Core business logic (38 functions)
├── index.php                # Main listing with filters
├── add.php                  # Create new client form
├── view.php                 # 360° client profile (tabbed)
├── edit.php                 # Edit client form
├── my.php                   # My clients view (filtered)
├── import_export.php        # Import/export landing page
└── sample_clients.csv       # Sample CSV template

scripts/
└── setup_clients_tables.php # Database setup script
```

## 🔗 Integration Points

### CRM Module
- Clients can be created from CRM leads
- `lead_id` field links back to original lead
- Lead status updates to "Converted"
- Lead data pre-fills client form

### Contacts Module
- Contact persons linked via `client_contacts_map`
- Role specification (CEO, PM, etc.)
- Bidirectional relationship
- View contacts from client profile
- View clients from contact profile

### Projects Module (if exists)
- Projects reference `client_id`
- View all client projects from profile
- Client-based project filtering

### Notebook Module
- Notes can link to clients via `linked_entity_type='client'`
- Client activity timeline integration

### Documents Module
- Separate client document system
- Files stored in `/uploads/clients/`
- Document type categorization

## 📊 Statistics & Reports

**Dashboard Metrics**:
- Total Clients
- Active Clients
- Inactive Clients
- My Clients (owned by user)
- Clients with Projects

**Per-Client Metrics**:
- Number of linked contacts
- Number of addresses
- Number of documents
- Associated projects count

## 🎨 UI Components

### Client Avatar
- Auto-generated from initials
- Gradient background
- Consistent across application

### Status Badges
- Active: Green badge with checkmark
- Inactive: Gray badge with circle

### Tabbed Interface
- 6 tabs: Overview, Contacts, Addresses, Documents, Projects, Timeline
- Tab counts show data availability
- Responsive design

## 🔒 Security Features

1. **Permission-based Access**: All operations check `clients.*` permissions
2. **SQL Injection Prevention**: All queries use prepared statements
3. **File Upload Validation**: Type and size restrictions
4. **Duplicate Prevention**: Email/phone uniqueness checks
5. **Foreign Key Constraints**: Data integrity enforcement

## 🐛 Troubleshooting

### Issue: "Clients tables not set up"
**Solution**: Run the setup script at `/scripts/setup_clients_tables.php`

### Issue: Cannot see Clients menu
**Solution**: Check that user has `clients.view` permission

### Issue: Cannot add clients
**Solution**: Verify `clients.create` permission is assigned

### Issue: File upload fails
**Solution**: 
- Check `/uploads/clients/` directory exists and is writable
- Verify file size under 10MB
- Ensure file type is allowed (PDF, DOCX, XLSX, PNG, JPG)

### Issue: Duplicate detection not working
**Solution**: Ensure email and phone fields are populated correctly

### Issue: Lead conversion fails
**Solution**: Verify CRM tables exist and lead_id is valid

## 📝 Best Practices

1. **Always assign an owner** - Ensures accountability
2. **Use tags consistently** - Improves filtering and reporting
3. **Link contacts early** - Maintains relationship context
4. **Upload key documents** - Centralized document management
5. **Convert leads promptly** - Maintains CRM pipeline integrity
6. **Review inactive clients** - Periodic cleanup and reactivation
7. **Use custom fields sparingly** - Only for truly unique data
8. **Export regularly** - Data backup and external reporting

## 🚀 Future Enhancements

Potential features for future versions:
- Client hierarchy (parent/child companies)
- Revenue tracking per client
- Communication history log
- Client portal access
- Contract expiry alerts
- Client segmentation (A/B/C tiers)
- Advanced analytics dashboard
- Email integration
- Calendar integration for meetings
- Client satisfaction scoring

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review error messages in browser console
3. Check PHP error logs
4. Verify database connection
5. Contact system administrator

## 📄 License

Part of Karyalay ERP System - Internal Use Only

---

**Module Version**: 1.0  
**Last Updated**: 2024  
**Compatibility**: KaryalayERP v1.0+  
**Dependencies**: Contacts Module (optional), CRM Module (optional)
