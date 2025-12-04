# Work Orders Management Module - Complete Implementation

## 📋 Overview

The Work Orders Management module provides a comprehensive system for creating, tracking, and managing work orders for clients and leads. The module includes full CRUD operations, deliverables tracking, team assignment, file attachments, approval workflows, and activity logging.

## ✅ Implementation Status: COMPLETE (100%)

All components have been successfully implemented and are ready for use.

## 🗄️ Database Schema

### Tables Created (5 total)

#### 1. `work_orders` (Main table)
- **Primary Key**: `id` (auto-increment)
- **Unique Code**: `work_order_code` (format: WO-YY-MM-XXX)
- **Core Fields**:
  - `order_date`, `start_date`, `due_date`
  - `linked_type` (Lead/Client), `linked_id` (foreign key reference)
  - `service_type`, `priority` (Low/Medium/High), `status`
  - `description`, `dependencies`, `exceptions`, `remarks`
- **Approval Fields**:
  - `internal_approver` (user_id FK)
  - `internal_approval_status`, `internal_approval_date`
  - `client_approval_status`, `client_approval_date`
- **Computed**: `TAT_days` (generated column: DATEDIFF)
- **Timestamps**: `created_at`, `updated_at`, `created_by`
- **Indexes**: work_order_code, status, order_date, due_date, linked_type+linked_id

#### 2. `work_order_team`
- Links employees to work orders with roles
- Fields: `employee_id`, `role`, `remarks`
- Foreign keys: CASCADE on work_order delete

#### 3. `work_order_deliverables`
- Tracks individual deliverables with deadlines
- Fields: `deliverable_name`, `description`, `assigned_to`, `start_date`, `due_date`, `status`, `delivered_at`
- Foreign keys: CASCADE on work_order delete

#### 4. `work_order_files`
- Stores file attachments (up to 10MB each)
- Fields: `file_name`, `file_path`, `file_type`, `uploaded_by`, `uploaded_at`
- Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP
- Foreign keys: CASCADE on work_order delete

#### 5. `work_order_activity_log`
- Comprehensive audit trail
- Fields: `action`, `performed_by`, `performed_at`, `remarks`
- Foreign keys: CASCADE on work_order delete

### Foreign Key Relationships
- `work_orders.linked_id` → `leads.id` / `clients.id` (contextual)
- `work_orders.internal_approver` → `users.id`
- `work_orders.created_by` → `users.id`
- `work_order_team.employee_id` → `employees.id`
- `work_order_deliverables.assigned_to` → `employees.id`
- `work_order_files.uploaded_by` → `users.id`

## 📁 File Structure

```
KaryalayERP/
├── scripts/
│   └── setup_workorders_tables.php          # Database migration script
├── public/
│   └── workorders/
│       ├── index.php                        # Dashboard with filters & stats
│       ├── create.php                       # Create new work order form
│       ├── edit.php                         # Edit existing work order
│       ├── view.php                         # Detailed read-only view
│       └── api/
│           ├── create.php                   # POST: Create work order handler
│           ├── update.php                   # POST: Update work order handler
│           ├── delete_file.php              # POST: Delete file attachment
│           └── export.php                   # GET: Export to CSV
├── uploads/
│   └── workorders/                          # File upload directory (auto-created)
├── assets/
│   └── css/
│       └── style.css                        # Contains 400+ lines of WO styles
├── includes/
│   └── sidebar.php                          # Updated with Work Orders menu
└── config/
    ├── module_dependencies.php              # Dependencies: employees, clients
    └── table_access_map.php                 # Permission mappings

```

## 🎨 Frontend Pages

### 1. Dashboard (`index.php`)
**Features**:
- 4 statistics cards: Total Orders, In Progress, Completed, Overdue
- 6-field filter form:
  - Date range (From/To)
  - Search (code/service type/company)
  - Status dropdown (Draft/In Progress/On Hold/Completed/Cancelled)
  - Priority dropdown (Low/Medium/High)
  - Type filter (Lead/Client)
- Sortable table columns (9 total):
  - Work Order Code (clickable sort)
  - Order Date (sortable)
  - Linked To (Lead/Client badge + name)
  - Service Type
  - Priority (color-coded badge)
  - Status (color-coded badge)
  - Due Date
  - TAT (Days)
  - Actions (View/Edit buttons)
- Pagination (20 per page)
- Export to CSV button (respects filters)
- Empty state with setup instructions
- Responsive design (breakpoints: 600px, 768px, 1200px)

### 2. Create Form (`create.php`)
**Sections**:
1. **Basic Information** (3-column grid):
   - Order Date, Linked Type (Lead/Client), Service Type
   - Priority, Status, Start Date, Due Date
   - Internal Approver, Description
   - Dependencies, Exceptions, Remarks

2. **Team Members** (repeatable rows):
   - Employee dropdown, Role/Responsibility, Remarks
   - Add/Remove member buttons

3. **Deliverables** (repeatable rows - minimum 1 required):
   - Deliverable Name, Assigned To, Start Date, Due Date
   - Description
   - Add/Remove deliverable buttons

4. **Attachments**:
   - Multiple file upload (10MB limit per file)
   - Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP

**Validation**:
- Required fields marked with red asterisk
- JavaScript toggle for Lead/Client selection
- At least one deliverable required
- Date validation (start < due)
- File size and format validation

### 3. View Page (`view.php`)
**Layout**: Two-column grid (left: main content, right: sidebar)

**Left Column**:
- Status banner (color-coded with overdue/due soon alerts)
- Basic Information card (all work order details)
- Deliverables list (cards with status badges)
- Team Members list (employee cards with roles)

**Right Column**:
- Approval Status card (internal + client approvals)
- Attachments list (downloadable files)
- Activity Log (timeline view)
- Metadata card (created by, timestamps)

**Features**:
- Read-only view with conditional edit button
- Color-coded status/priority badges
- Downloadable file attachments
- Chronological activity timeline
- Responsive breakpoints

### 4. Edit Form (`edit.php`)
**Capabilities**:
- Modify all basic work order details
- Change status (Draft → In Progress → Completed)
- Update team members (add/remove)
- Edit deliverables (modify existing, add new)
- Update deliverable status individually
- Upload additional files
- Delete existing files (with confirmation)

**Constraints**:
- Work order code is read-only
- At least one deliverable must remain
- Activity logged on save

## 🔌 API Endpoints

### 1. `api/create.php` (POST)
**Process Flow**:
1. Auto-generate work_order_code (WO-YY-MM-XXX format)
2. Validate required fields and deliverables
3. Begin database transaction
4. Insert work_order record
5. Insert team members (if any)
6. Insert deliverables (required)
7. Handle file uploads (validate size/format)
8. Log "Created" activity
9. Commit transaction
10. Redirect to view page

**Error Handling**:
- Rollback on failure
- Flash error message
- Return to create form

### 2. `api/update.php` (POST)
**Process Flow**:
1. Validate work_order_id
2. Update work_order record
3. Delete + re-insert team members
4. Update existing deliverables or insert new ones
5. Handle new file uploads
6. Log "Updated" activity
7. Redirect to view page

### 3. `api/delete_file.php` (POST, AJAX)
**Returns**: JSON response
```json
{
  "success": true/false,
  "message": "File deleted successfully"
}
```
- Deletes physical file from uploads/workorders/
- Removes database record
- Logs activity

### 4. `api/export.php` (GET)
**Output**: CSV file download
**Filename**: `work_orders_YYYY-MM-DD.csv`
**Columns**: 14 fields (code, dates, linked info, status, approvals, etc.)
**Respects**: All dashboard filters (search, status, priority, dates)

## 🎨 CSS Styles

### Added to `assets/css/style.css` (~400 lines)

**Class Prefixes**: `wo-*` (work orders module)

**Key Components**:
- `.wo-stats-grid`: 4-column stat cards (responsive to 1/2 columns)
- `.wo-filter-form`: Multi-field filter grid (responsive)
- `.wo-table`: Sortable table with hover effects
- `.wo-status-badge`: Color-coded status indicators (7 variants)
- `.wo-priority-badge`: Color-coded priority tags (3 variants)
- `.wo-type-badge`: Lead/Client badges
- `.wo-form-grid-3`: 3-column form layout (responsive)
- `.wo-view-grid`: Two-column detail view (responsive to single column)
- `.wo-deliverable-card`: Deliverable item styling
- `.wo-team-card`: Team member cards
- `.wo-activity-timeline`: Vertical timeline with dots
- `.wo-files-list`: File attachment list

**Responsive Breakpoints**:
- **1200px**: 4→3 columns for stats
- **768px**: 3→2 columns for forms, 4→2 for stats
- **600px**: All grids become single column

## 🔐 Permissions & Access Control

### Table: `work_orders`

**Required Permissions**:
- `read`: View dashboard, view details, export
- `create`: Create new work orders
- `update`: Edit work orders, delete files
- `delete`: (Reserved for future delete functionality)

### Permission Checks:
- `authz_require_permission($conn, 'workorders', 'read')` - Dashboard
- `authz_require_permission($conn, 'workorders', 'create')` - Create page
- `authz_require_permission($conn, 'workorders', 'update')` - Edit page
- `authz_user_can($conn, 'workorders', 'update')` - Edit button visibility

### Sidebar Integration:
```php
[
    'icon' => 'documents.png',
    'label' => 'Work Orders',
    'link' => APP_URL . '/public/workorders/index.php',
    'active' => (strpos($current_path, '/workorders/') !== false),
    'requires' => ['table' => 'work_orders', 'permission' => 'read']
]
```

## 📦 Module Dependencies

### Prerequisites:
1. **employees** table (for team assignment)
2. **clients** table (for client linking)
3. **leads** table (for lead linking)
4. **users** table (for approvers, created_by)

### Defined in `config/module_dependencies.php`:
```php
'workorders' => ['employees', 'clients']
```

### Setup Script Path:
```php
'workorders' => 'scripts/setup_workorders_tables.php'
```

## 🚀 Installation & Setup

### Step 1: Run Database Migration
```
Navigate to: http://your-domain/scripts/setup_workorders_tables.php
```
- Creates 5 tables with proper indexes and foreign keys
- Safe to re-run (uses `IF NOT EXISTS`)

### Step 2: Configure Permissions
1. Go to Settings → Permissions → Table-Based Permissions
2. Find `work_orders` table
3. Assign permissions to roles:
   - `read`: View work orders
   - `create`: Create new work orders
   - `update`: Edit work orders
   - `delete`: (Reserved)

### Step 3: Verify Sidebar Access
- Work Orders menu item should appear automatically
- Visible only to users with `workorders.read` permission

### Step 4: Create Upload Directory
```bash
mkdir uploads/workorders
chmod 755 uploads/workorders
```
- Directory auto-created on first file upload
- Ensure web server has write permissions

## 📊 Business Rules

### Work Order Code Generation
- **Format**: `WO-YY-MM-XXX`
- **Example**: `WO-25-11-001` (November 2025, sequence 1)
- Auto-increments within each month
- Zero-padded to 3 digits

### Status Workflow
1. **Draft** → Initial creation state
2. **In Progress** → Work has started
3. **On Hold** → Temporarily paused
4. **Completed** → All deliverables finished
5. **Cancelled** → Work order cancelled

### Priority Levels
- **Low**: Standard work, no urgency
- **Medium**: Normal priority (default)
- **High**: Urgent, requires immediate attention

### Deliverable Status
- **Pending**: Not started
- **In Progress**: Work ongoing
- **Completed**: Finished, awaiting delivery
- **Delivered**: Delivered to client

### Approval Workflow
1. **Internal Approval**: Assigned approver must approve
2. **Client Approval**: Client must sign off
3. Both can be: Pending / Approved / Rejected

### TAT Calculation
- Automatically computed: `DATEDIFF(due_date, start_date)`
- Updates dynamically when dates change
- Displayed in dashboard and view page

## 🔔 Activity Logging

### Auto-logged Actions:
- `Created`: On work order creation
- `Updated`: On work order edit
- `File Deleted`: On file removal
- (Extensible for future actions: Approved, Status Changed, etc.)

### Log Format:
```
Action: Updated
Performed By: username
Performed At: 24 Nov 2025 15:30
Remarks: Work order updated
```

## 📈 Dashboard Statistics

### Computed Metrics:
1. **Total Orders**: Count of all work orders
2. **In Progress**: Count where status = 'In Progress'
3. **Completed**: Count where status = 'Completed'
4. **Overdue**: Count where due_date < TODAY and status NOT IN ('Completed', 'Cancelled')

### Filters Applied to Stats:
- Date range (order_date BETWEEN from_date AND to_date)
- All selected filters (status, priority, type) affect counts

## 🎯 Usage Examples

### Creating a Work Order
1. Click "Create Work Order" button
2. Select "Client" and choose company
3. Enter service type (e.g., "Website Redesign")
4. Set priority: High
5. Add deliverable: "Homepage Mockup" → Assign to designer
6. Add team member: Project Manager
7. Upload project brief PDF
8. Submit → Auto-generates WO-25-11-001

### Tracking Progress
1. View dashboard → See order in "In Progress" section
2. Click "View" → See deliverable status
3. Check activity log → See all changes
4. Monitor TAT → Alert if approaching due date

### Editing Deliverables
1. Click "Edit" on work order
2. Update deliverable status: Pending → In Progress
3. Add new deliverable if scope changes
4. Upload additional files
5. Save → Activity logged

### Exporting Reports
1. Set date range: 2025-11-01 to 2025-11-30
2. Filter by status: Completed
3. Click "Export CSV"
4. Opens file: `work_orders_2025-11-24.csv`

## 🐛 Known Limitations

1. **No Bulk Actions**: Edit one work order at a time
2. **No Inline Editing**: Must use edit page (no table cell editing)
3. **No Notifications**: Approval workflow has no email alerts (future enhancement)
4. **No Time Tracking**: No built-in time logging for deliverables
5. **Fixed Permissions**: No row-level permissions (all users with 'read' see all orders)

## 🔮 Future Enhancements (Roadmap)

### Phase 2 Features:
- [ ] Email notifications for approvals and overdue orders
- [ ] Gantt chart view for deliverables timeline
- [ ] Client portal for approval submission
- [ ] Time tracking per deliverable
- [ ] Invoice integration (link work orders to invoices)
- [ ] Recurring work orders (templates)
- [ ] Advanced reporting (charts, trends)
- [ ] API endpoints for mobile app integration
- [ ] Deliverable dependencies (task chains)
- [ ] Budget/cost tracking per work order

## 📝 Developer Notes

### Code Patterns
- **File Naming**: `{action}.php` (index, create, edit, view)
- **API Naming**: `api/{action}.php`
- **CSS Prefix**: `wo-*` for all work orders styles
- **Permission Checks**: Use `authz_require_permission()` at page top
- **Flash Messages**: `$_SESSION['flash_message']` for user feedback
- **Transactions**: Wrap multi-table operations in `mysqli_begin_transaction()`

### Database Conventions
- **Timestamps**: `created_at` (NOT NULL, DEFAULT NOW), `updated_at` (NULL, ON UPDATE NOW)
- **Foreign Keys**: CASCADE on delete for child tables
- **Indexes**: Add on frequently filtered columns (status, dates, codes)
- **Enum-like**: Use VARCHAR with CHECK constraints for status fields

### Security Measures
- **SQL Injection**: All queries use prepared statements (`mysqli_prepare`)
- **XSS Prevention**: All output uses `htmlspecialchars()`
- **File Upload**: Whitelist extensions, size limit, unique filenames
- **Path Traversal**: Use absolute paths, validate file existence
- **CSRF**: (Implement tokens in future version)

## 🆘 Troubleshooting

### Issue: "Work Orders module not set up"
**Solution**: Run `/scripts/setup_workorders_tables.php`

### Issue: "Permission denied" on dashboard
**Solution**: 
1. Check user has role with `work_orders.read` permission
2. Verify table exists: `SHOW TABLES LIKE 'work_orders'`

### Issue: File upload fails
**Solution**:
1. Check `uploads/workorders/` directory exists
2. Verify web server write permissions: `chmod 755 uploads/workorders`
3. Check file size < 10MB
4. Verify file extension is allowed

### Issue: Work order code not generating
**Solution**:
1. Check date format is correct (Y-m-d)
2. Verify work_order_code column has UNIQUE constraint
3. Check for database errors in logs

### Issue: Deliverables not saving
**Solution**:
1. Verify at least one deliverable has name filled
2. Check all required fields: name, assigned_to, start_date, due_date
3. Look for database foreign key errors (invalid employee_id)

## 📚 Related Modules

- **Employees**: Required for team assignment and deliverable assignment
- **Clients**: Required for linking work orders to clients
- **CRM (Leads)**: Required for linking work orders to leads
- **Projects**: (Future integration - link work orders to projects)
- **Invoices**: (Future integration - convert work orders to invoices)
- **Quotations**: (Future integration - create work orders from quotations)

## ✅ Completion Checklist

- [x] Database schema (5 tables)
- [x] Setup migration script
- [x] Dashboard page with filters & stats
- [x] Create form with repeatable sections
- [x] View page with two-column layout
- [x] Edit form with update logic
- [x] API endpoints (create, update, delete_file, export)
- [x] CSS styles (400+ lines)
- [x] Sidebar integration
- [x] Module dependencies configuration
- [x] Permission mappings
- [x] File upload handling
- [x] Activity logging
- [x] Responsive design
- [x] Export to CSV functionality

## 📞 Support

For issues or questions about the Work Orders module:
1. Check this README first
2. Review error messages in browser console/network tab
3. Check PHP error logs for backend issues
4. Verify database tables and foreign keys
5. Test permissions configuration

---

**Module Version**: 1.0.0  
**Last Updated**: November 24, 2025  
**Status**: Production Ready ✅
