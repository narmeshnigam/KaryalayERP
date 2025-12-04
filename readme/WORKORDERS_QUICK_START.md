# Work Orders Module - Quick Start Guide

## 🚀 Getting Started (3 Steps)

### Step 1: Setup the Module (One-Time)
1. Navigate to: `http://your-domain/scripts/setup_workorders_tables.php`
2. Click "Setup Work Orders Module"
3. Wait for success message
4. Return to dashboard

### Step 2: Configure Permissions
1. Go to **Settings** → **Permissions** → **Table-Based Permissions**
2. Scroll to `work_orders` table
3. Assign to your role:
   - ✅ `read` - View work orders
   - ✅ `create` - Create new work orders
   - ✅ `update` - Edit work orders
4. Save changes

### Step 3: Access the Module
1. Look for **Work Orders** in the sidebar menu
2. Click to open the dashboard
3. Start creating your first work order!

---

## 📋 Creating Your First Work Order

### Quick Creation Process:
```
1. Click "Create Work Order" button
2. Fill Basic Information:
   - Order Date: (auto-filled to today)
   - Linked To: Select "Client" or "Lead"
   - Select company from dropdown
   - Service Type: e.g., "Website Development"
   - Priority: Medium (default)
   - Start Date & Due Date

3. Add Description (required)

4. Add At Least One Deliverable:
   - Name: e.g., "Homepage Design"
   - Assigned To: Select employee
   - Start & Due dates
   
5. Optional: Add team members, upload files

6. Click "Create Work Order"
```

**Result**: Work order created with code like `WO-25-11-001`

---

## 🔍 Finding Work Orders

### Using Filters:
- **Date Range**: Filter by order date (From/To)
- **Search Box**: Find by code, service type, or company name
- **Status Dropdown**: Draft, In Progress, Completed, etc.
- **Priority**: Low, Medium, High
- **Type**: Show only Leads or Clients

### Using Sort:
- Click any column header to sort (code, date, priority, status)
- Click again to reverse order

### Quick Stats:
Dashboard shows 4 cards:
- 📊 Total Orders
- ⏳ In Progress
- ✅ Completed
- ⚠️ Overdue

---

## ✏️ Editing Work Orders

### To Edit:
1. Find work order in list
2. Click **Edit** button (or open View page and click Edit)
3. Modify any fields
4. Add/remove deliverables or team members
5. Upload additional files
6. Change status (Draft → In Progress → Completed)
7. Click "Update Work Order"

### What You Can Edit:
- ✅ All basic information (dates, priority, status, description)
- ✅ Team members (add/remove)
- ✅ Deliverables (add new, update existing, change status)
- ✅ Upload more files
- ❌ Work order code (auto-generated, cannot change)

---

## 👁️ Viewing Details

### View Page Shows:
- **Header**: Code, status, priority badges
- **Basic Info**: All work order details
- **Deliverables**: List with status indicators
- **Team Members**: Assigned employees with roles
- **Approval Status**: Internal & client approvals
- **Attachments**: Downloadable files
- **Activity Log**: Complete history of changes
- **Metadata**: Created by, timestamps

### Status Badges:
- 🟦 **Draft** - Blue
- 🟡 **In Progress** - Yellow
- 🟠 **On Hold** - Orange
- 🟢 **Completed** - Green
- 🔴 **Cancelled** - Red

### Priority Badges:
- 🟢 **Low** - Green
- 🟡 **Medium** - Yellow
- 🔴 **High** - Red

---

## 📦 Managing Deliverables

### Deliverable Lifecycle:
```
Pending → In Progress → Completed → Delivered
```

### To Update Deliverable Status:
1. Open work order in **Edit** mode
2. Find deliverable in list
3. Change status dropdown
4. Click "Update Work Order"

### Each Deliverable Tracks:
- Name
- Description
- Assigned employee
- Start date & due date
- Current status
- Delivered date (auto-set when marked Delivered)

---

## 📎 Working with Files

### Uploading Files:
1. **During Creation**: Use "Attachments" section at bottom
2. **After Creation**: Open Edit page, use "Upload New Attachments"
3. **Allowed Formats**: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP
4. **Size Limit**: 10MB per file
5. **Multiple Files**: Select multiple files at once

### Deleting Files:
1. Open Edit page
2. Find file in "Existing Attachments" section
3. Click "Delete" button
4. Confirm deletion

### Downloading Files:
1. Open View or Edit page
2. Click file name in attachments list
3. File opens in new tab/downloads

---

## 👥 Managing Team Members

### Adding Team Members:
1. In Create/Edit form, find "Team Members" section
2. Click "+ Add Member" button
3. Select employee from dropdown
4. Enter role (e.g., "Project Manager", "Developer")
5. Optional: Add remarks

### Removing Team Members:
- Click "Remove" button next to team member row
- First row cannot be removed (keeps at least one)

### Team Member Roles:
Examples: Project Manager, Lead Developer, Designer, QA Tester, Account Manager

---

## 📊 Exporting Data

### To Export Work Orders:
1. Set filters as desired (date range, status, etc.)
2. Click "📥 Export CSV" button (top right)
3. File downloads: `work_orders_YYYY-MM-DD.csv`

### CSV Contains:
- Work Order Code
- Order Date
- Linked Type & Name
- Service Type
- Priority & Status
- Start & Due Dates
- TAT (Days)
- Approval Statuses
- Description
- Created At timestamp

---

## ⚡ Quick Tips

### 💡 Best Practices:
1. **Use Descriptive Service Types**: Makes filtering easier
2. **Set Realistic Due Dates**: Helps track TAT accurately
3. **Update Deliverable Status**: Keep stakeholders informed
4. **Assign Team Members**: Clarifies responsibilities
5. **Log Activity**: Edit remarks when making major changes
6. **Use Priority Wisely**: High priority should be truly urgent

### ⚠️ Common Mistakes to Avoid:
- ❌ Forgetting to add deliverables (minimum 1 required)
- ❌ Not selecting Lead or Client (required field)
- ❌ Setting due date before start date
- ❌ Uploading files over 10MB (will fail)
- ❌ Deleting all team members (keep at least one)

### ⏱️ Time-Saving Shortcuts:
- Use **date range** filter to see current month orders
- Sort by **Due Date** to find urgent tasks
- Filter by **In Progress** status for active work
- Search by **company name** to see all client orders
- Export regularly for backup/reporting

---

## 🎯 Common Workflows

### Workflow 1: New Client Project
```
1. Client signs contract
2. Create Work Order:
   - Linked To: Client → Select company
   - Service: "Website Development"
   - Priority: High (deadline approaching)
3. Add Deliverables:
   - "Requirements Document" → Assign to BA
   - "Design Mockups" → Assign to Designer
   - "Development" → Assign to Developer
4. Add Team: PM, Designer, Developer
5. Upload signed proposal PDF
6. Status: In Progress
7. Track progress, update deliverable statuses
8. Mark Completed when done
```

### Workflow 2: Lead Follow-up Task
```
1. Lead shows interest
2. Create Work Order:
   - Linked To: Lead → Select lead
   - Service: "Product Demo Preparation"
   - Priority: Medium
3. Add Deliverable:
   - "Demo Presentation" → Assign to Sales
4. Upload product brochure
5. Complete deliverable
6. Mark order Completed
7. Use for lead conversion tracking
```

### Workflow 3: Monthly Reporting
```
1. Set date range: First to last day of month
2. Filter by Status: Completed
3. Export CSV
4. Open in Excel
5. Create pivot table for metrics:
   - Orders by priority
   - TAT analysis
   - Service type distribution
```

---

## 🔔 Notifications & Alerts

### Dashboard Alerts:
- **⚠️ Overdue Badge**: Shows when due_date has passed
- **⏰ Due Soon**: Appears when 3 or fewer days remaining
- **Red Status Banner**: Highlights overdue work orders in view page

### Visual Indicators:
- **Color-coded Status**: Quick status recognition
- **Priority Colors**: Urgent items stand out
- **Stats Cards**: At-a-glance metrics

---

## ❓ Troubleshooting

### Problem: Can't see Work Orders menu
**Solution**: Ask admin to grant you `work_orders.read` permission

### Problem: "Setup Required" message
**Solution**: Run setup script (Step 1 in Getting Started)

### Problem: Can't upload file
**Check**:
- File size < 10MB?
- File format allowed (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP)?
- Folder permissions correct?

### Problem: Work order code not displaying
**Solution**: Code auto-generates on create. If missing, check database setup.

### Problem: Can't edit work order
**Solution**: Ask admin for `work_orders.update` permission

---

## 📱 Mobile/Tablet Usage

### Responsive Features:
- ✅ Dashboard fully responsive (1-column on mobile)
- ✅ Forms adapt to smaller screens
- ✅ Tables scroll horizontally on narrow screens
- ✅ Touch-friendly buttons and dropdowns

### Mobile Tips:
- Rotate to landscape for better table viewing
- Use filters to reduce list size
- Pinch-to-zoom on detailed view

---

## 📞 Need Help?

1. **Check this guide** for common tasks
2. **View Activity Log** to understand what changed
3. **Contact your system admin** for permission issues
4. **Check README** (`WORKORDERS_MODULE_COMPLETE.md`) for technical details

---

**Module Version**: 1.0.0  
**Last Updated**: November 24, 2025  
**Quick Start Guide** - For End Users
