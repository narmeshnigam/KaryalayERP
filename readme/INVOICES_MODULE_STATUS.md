# Invoices Module - Implementation Status

## ✅ Completed Components

### 1. Database Setup (`scripts/setup_invoices_tables.php`)
**Status:** ✅ Complete

Creates three tables:
- `invoices` - Master table with all invoice fields, payment tracking, status workflow
- `invoice_items` - Line items with pricing, tax, discount calculations
- `invoice_activity_log` - Activity tracking and audit trail

**Features:**
- Foreign keys to clients, users, quotations (if exists)
- Proper indexes on invoice_no, client_id, status, dates
- Upload directory creation for attachments
- Prerequisites check for required modules (Catalog, Clients)

### 2. Business Logic (`public/invoices/helpers.php`)
**Status:** ✅ Complete

**Functions Implemented:**
- `invoices_tables_exist()` - Check module setup
- `generate_invoice_no()` - Auto-numbering with year prefix (INV-2025-0001)
- `get_all_invoices()` - List with filters (search, status, client, date, overdue)
- `get_invoice_by_id()` - Single invoice with calculations
- `get_invoice_items()` - Fetch line items with item details
- `create_invoice()` - Create draft invoice
- `update_invoice()` - Update draft invoices only
- `add_invoice_item()` - Add line item
- `delete_invoice_items()` - Clear items (for updates)
- `issue_invoice()` - Mark as issued + deduct inventory
- `cancel_invoice()` - Cancel with optional inventory restoration
- `get_invoice_statistics()` - Dashboard metrics
- `log_invoice_activity()` - Activity logging
- `get_invoice_activity_log()` - Fetch activity history

**Inventory Integration:**
- `get_item_available_stock()` - Check stock availability
- `deduct_inventory_for_invoice()` - Deduct on issue
- `restore_inventory_for_invoice()` - Restore on cancel

**Helper Functions:**
- `get_client_primary_address_text()` - Format client address
- `get_active_clients()` - Client dropdown
- `calculate_due_date()` - Auto-calculate from payment terms

### 3. Onboarding Page (`public/invoices/onboarding.php`)
**Status:** ✅ Complete

- User-friendly setup prompt
- Feature list display
- Direct link to setup script
- Consistent styling with other modules

### 4. Main List Page (`public/invoices/index.php`)
**Status:** ✅ Complete

**Features:**
- Statistics cards with gradients:
  - Total Invoices
  - Draft, Issued, Overdue, Paid counts
  - Outstanding Amount
- Advanced filters:
  - Search (invoice no, client)
  - Status dropdown
  - Client dropdown
  - Date range (from/to)
  - Overdue only checkbox
- Sortable table with columns:
  - Invoice No, Client, Issue Date, Due Date
  - Total, Paid, Balance amounts
  - Status badge, Actions
- Color-coded overdue indicators
- Permissions-based action buttons
- Export button (if permitted)
- Responsive design

---

## 🚧 Remaining Components to Build

### 5. Add Invoice Page (`public/invoices/add.php`)
**Required Features:**
- Client selector (required)
- Project selector (optional)
- Issue date, Due date fields
- Payment terms dropdown (NET 7/15/30/45/60/90)
- Dynamic item picker from Catalog
- Line items table with:
  - Item name, description override
  - Quantity, Unit, Unit Price
  - Discount (Amount/Percent)
  - Tax Percent
  - Line Total (auto-calculated)
- Totals panel (live calculation):
  - Subtotal
  - Discount
  - Tax
  - Round-off
  - Grand Total
- Notes and Terms text areas
- Attachment upload (PO/WO/Contract)
- Action buttons:
  - Save as Draft
  - Save & Issue (triggers inventory deduction)
  - Cancel

**JavaScript Requirements:**
- Item autocomplete/search
- Live calculation of line totals
- Aggregate totals calculation
- Add/remove item rows
- Discount type toggle (Amount/Percent)

### 6. Edit Invoice Page (`public/invoices/edit.php`)
**Required Features:**
- Same structure as add.php
- Pre-populate all fields from existing invoice
- Load existing line items
- Only allow editing if status = 'Draft'
- Redirect with error if already issued

### 7. View Invoice Page (`public/invoices/view.php`)
**Required Features:**
- Tabbed interface:
  - **Overview Tab:** Invoice header, client info, totals, status
  - **Items Tab:** Table of all line items
  - **Payments Tab:** Linked payments (future integration)
  - **Activity Log Tab:** All activities with timestamps
- Action buttons in header:
  - Edit (if Draft)
  - Export to Excel
  - Mark as Issued (if Draft)
  - Cancel (if no payments)
  - Record Payment (future)
- Status badge prominent display
- Balance and payment tracking
- Overdue warning if applicable

### 8. Excel Export (`public/invoices/excel.php`)
**Required Features:**
- Individual invoice export
- Professional formatting
- Company branding (from branding_settings)
- All invoice details:
  - Header with invoice no, dates
  - Bill To section
  - Items table with all columns
  - Totals breakdown
  - Notes and Terms
  - Footer
- Filename: `Invoice_INV-2025-0001_20251101.xls`

### 9. List Export (`public/invoices/export.php`)
**Required Features:**
- Export filtered list to Excel
- Columns: Invoice No, Client, Dates, Total, Paid, Balance, Status
- Apply all active filters
- Summary row with totals
- Filename: `Invoices_Export_20251101_143022.xls`

### 10. API Endpoints (`public/api/invoices/`)

#### `add.php`
- Accept POST with invoice data + items array
- Validate all fields
- Create invoice via `create_invoice()`
- Loop through items and call `add_invoice_item()`
- Return JSON with success/error

#### `update.php`
- Accept POST with invoice_id + updated data + items
- Validate invoice exists and is Draft
- Delete old items via `delete_invoice_items()`
- Update invoice via `update_invoice()`
- Re-add items
- Return JSON

#### `issue.php`
- Accept POST with invoice_id
- Call `issue_invoice()` which:
  - Validates stock availability
  - Updates status to 'Issued'
  - Deducts inventory for product items
  - Logs activity
- Return JSON

#### `cancel.php`
- Accept POST with invoice_id
- Optional: restore_inventory flag
- Call `cancel_invoice()`
- Return JSON

#### `delete.php`
- Accept POST with invoice_id
- Check if invoice can be deleted (usually only Draft)
- Delete invoice (CASCADE deletes items + activity)
- Return JSON

#### `convert_from_quotation.php`
- Accept POST with quotation_id
- Fetch quotation details
- Create new invoice with:
  - `quotation_id` link
  - Copy client, project, notes, terms
  - Copy all quotation items to invoice items
  - Status = 'Draft'
- Return JSON with new invoice_id

### 11. Navigation & Permissions

#### Update `includes/sidebar.php`
Add menu item:
```php
'invoices' => [
    'label' => 'Invoices',
    'icon' => '🧾',
    'url' => APP_URL . '/public/invoices/index.php',
    'table' => 'invoices'
]
```

#### Update `config/table_access_map.php`
Add entry:
```php
'invoices' => [
    'category' => 'Sales',
    'label' => 'Invoices',
    'description' => 'Create and manage client invoices with payments'
]
```

---

## 📋 Implementation Checklist

- [x] Database setup script
- [x] Helper functions with complete business logic
- [x] Onboarding page
- [x] Main index/list page with filters & stats
- [ ] Add invoice page (form + item picker + calculations)
- [ ] Edit invoice page
- [ ] View invoice page (tabs: overview, items, payments, activity)
- [ ] Excel export for individual invoice
- [ ] Excel export for list
- [ ] API endpoint: add.php
- [ ] API endpoint: update.php
- [ ] API endpoint: issue.php
- [ ] API endpoint: cancel.php
- [ ] API endpoint: delete.php
- [ ] API endpoint: convert_from_quotation.php
- [ ] Update sidebar navigation
- [ ] Update permissions system

---

## 🔗 Integration Points

### With Quotations Module
- Conversion endpoint to create invoice from accepted quotation
- Link back to source quotation in invoice view
- Update quotation status when converted

### With Catalog (Items Master)
- Item picker in add/edit forms
- Price and tax defaults from catalog
- Stock availability check before issue

### With Inventory Module
- Deduct stock on invoice issue (Product items only)
- Restore stock on invoice cancellation
- Log all transactions in item_inventory_log

### With Clients Module
- Client selection dropdown
- Pull primary address for invoice
- Link to client profile from invoice

### With Projects Module (if exists)
- Optional project linking
- Project-wise invoice reports

### With Payments Module (future)
- Record payments against invoices
- Update amount_paid and status
- Link payments in view page

### With Branding Module
- Company details on Excel/PDF exports
- Logo display
- Footer text and tagline

---

## 🎨 UI/UX Consistency

All pages follow the established patterns:
- Blue gradient cards for statistics (#003581 to #004aad)
- Card-based layouts
- Consistent button styling
- Alert components for messages
- Table formatting with hover effects
- Badge colors for status indicators
- Responsive grid layouts
- Icon usage (emojis for visual clarity)

---

## 🧪 Testing Checklist

### Setup & Prerequisites
- [ ] Run setup script successfully
- [ ] Verify all tables created with proper structure
- [ ] Check foreign keys and indexes
- [ ] Confirm upload directory created

### Create Invoice
- [ ] Create draft invoice with client
- [ ] Add multiple line items
- [ ] Test calculations (subtotal, tax, discount)
- [ ] Save as draft
- [ ] Edit draft invoice
- [ ] Issue invoice (check inventory deduction)

### Invoice Lifecycle
- [ ] Draft → Issued (stock deducted)
- [ ] Issued → Partially Paid (payment recorded)
- [ ] Partially Paid → Paid (full payment)
- [ ] Issued → Cancelled (stock restored)

### Validations
- [ ] Cannot edit issued invoice
- [ ] Cannot issue without items
- [ ] Cannot issue with insufficient stock
- [ ] Cannot cancel with payments
- [ ] Overdue status auto-updates

### Filters & Search
- [ ] Search by invoice no
- [ ] Filter by status
- [ ] Filter by client
- [ ] Filter by date range
- [ ] Overdue only filter

### Exports
- [ ] Individual invoice Excel export
- [ ] List export with filters applied
- [ ] Proper formatting and branding

### Permissions
- [ ] View-only users cannot create/edit
- [ ] Admin can perform all actions
- [ ] Unauthorized access redirects

### Integration
- [ ] Convert quotation to invoice
- [ ] Inventory deduction logs correctly
- [ ] Activity log captures all actions

---

## 📊 Database Schema Reference

### invoices
- id, invoice_no (unique), quotation_id, client_id, project_id
- issue_date, due_date, payment_terms, currency
- subtotal, tax_amount, discount_amount, round_off, total_amount, amount_paid
- status (Draft/Issued/Partially Paid/Paid/Overdue/Cancelled)
- notes, terms, attachment
- created_by, created_at, updated_at

### invoice_items
- id, invoice_id, item_id
- description, quantity, unit, unit_price
- discount, discount_type (Amount/Percent), tax_percent, line_total

### invoice_activity_log
- id, invoice_id, user_id, action, description, created_at

---

## 🚀 Next Steps

1. **Priority 1:** Create add.php and edit.php (forms are most complex)
2. **Priority 2:** Create view.php (important for user workflow)
3. **Priority 3:** Create API endpoints (needed for forms to work)
4. **Priority 4:** Create exports (nice-to-have feature)
5. **Priority 5:** Update navigation and permissions (final integration)

Each file should be created with:
- Proper error handling
- Input validation
- Permission checks
- Consistent styling
- Responsive design
- JavaScript for dynamic features

---

## 📚 Code Patterns to Follow

### Permission Checks
```php
authz_require_permission($conn, 'invoices', 'view_all');
$permissions = authz_get_permission_set($conn, 'invoices');
$can_create = !empty($permissions['can_create']);
```

### Flash Messages
```php
$_SESSION['flash_success'] = 'Invoice created successfully!';
$_SESSION['flash_error'] = 'Failed to create invoice.';
```

### Activity Logging
```php
log_invoice_activity($conn, $invoice_id, $user_id, 'Create', 'Invoice created');
```

### JSON API Response
```php
header('Content-Type: application/json');
echo json_encode(['success' => true, 'invoice_id' => $invoice_id]);
exit;
```

---

**Module Development Progress: 40% Complete**
- ✅ Core foundation and business logic
- ✅ List/index page
- 🚧 CRUD pages in progress
- ⏳ API endpoints pending
- ⏳ Exports pending
- ⏳ Integration pending
