# 🛍️ Catalog Module - Products & Services

## Overview
The **Catalog Module** is a comprehensive inventory management system for products and services in the Karyalay ERP. It provides full CRUD operations, rich content management, inventory tracking, and complete audit trails.

## 🎯 Features

### ✅ Implemented Features
1. **Item Management**
   - Add/Edit/View Products and Services
   - Auto-generated or manual SKU assignment
   - Rich-text description editor (TinyMCE)
   - Category organization
   - Active/Inactive status control

2. **Pricing & Tax**
   - Base price configuration
   - Tax percentage (GST/VAT)
   - Default discount settings
   - Computed totals (for integration)

3. **Inventory Tracking (Products)**
   - Current stock display
   - Stock adjustment operations (Add/Reduce/Correction)
   - Low stock threshold alerts
   - Automatic stock deduction on invoice (integration ready)
   - Complete movement history with audit trail

4. **File Attachments**
   - Primary image upload (PNG/JPG, max 2MB)
   - Brochure PDF upload (max 10MB)
   - File history tracking
   - Version management

5. **Advanced Features**
   - Expiry date tracking
   - Expired item alerts
   - Low stock warnings
   - Comprehensive search and filters
   - CSV export functionality
   - Change history audit log

6. **Search & Filters**
   - Search by name, SKU, or category
   - Filter by type (Product/Service)
   - Filter by status (Active/Inactive)
   - Filter by category
   - Filter by low stock
   - Filter by expiring items (30/60/90 days)

7. **Statistics Dashboard**
   - Total items count
   - Products vs Services breakdown
   - Active items count
   - Low stock alerts
   - Expiring soon count
   - Total stock value calculation

8. **Permissions & Access Control**
   - Integrated with RBAC system
   - View/Create/Edit/Delete/Export permissions
   - Role-based access to all features

## 📁 File Structure

```
public/catalog/
├── index.php           # Main catalog list with filters
├── add.php            # Add new item form
├── edit.php           # Edit existing item
├── view.php           # Item detail view with tabs
├── stock_adjust.php   # Stock adjustment interface
└── helpers.php        # All business logic functions

public/api/catalog/
└── export.php         # CSV export endpoint

scripts/
└── setup_catalog_tables.php  # Database setup script

uploads/catalog/
├── images/            # Product/service images
└── brochures/         # PDF brochures
```

## 🗄️ Database Tables

### `items_master`
Main catalog table storing all products and services.

**Key Fields:**
- `id` - Primary key
- `sku` - Unique stock keeping unit
- `name` - Item name
- `type` - Product or Service
- `category` - Optional grouping
- `description_html` - Rich text content
- `base_price` - Base selling price
- `tax_percent` - GST/VAT percentage
- `default_discount` - Pre-applied discount
- `primary_image` - Path to main image
- `brochure_pdf` - Path to PDF brochure
- `expiry_date` - Optional validity date
- `current_stock` - Current inventory (products only)
- `low_stock_threshold` - Alert threshold
- `status` - Active/Inactive

### `item_inventory_log`
Complete audit trail of all stock movements.

**Key Fields:**
- `action` - Add/Reduce/InvoiceDeduct/Correction
- `quantity_delta` - Change amount (+ or -)
- `qty_before` - Stock before change
- `qty_after` - Stock after change
- `reason` - Human-readable explanation
- `reference_type` - Invoice/Manual/Other
- `reference_id` - Linked document ID

### `item_files`
File attachment history.

**Key Fields:**
- `file_type` - PrimaryImage/Brochure
- `file_path` - Storage location
- `uploaded_by` - User who uploaded
- `uploaded_at` - Upload timestamp

### `item_change_log`
Item modification audit trail.

**Key Fields:**
- `change_type` - Create/Update/Activate/Deactivate/PriceChange/FileChange
- `changed_fields` - JSON of before/after values
- `changed_by` - User who made change
- `created_at` - Change timestamp

## 🚀 Setup Instructions

### 1. Run Database Setup
```bash
# Option 1: Direct script execution
http://localhost/KaryalayERP/scripts/setup_catalog_tables.php

# Option 2: Via setup wizard
http://localhost/KaryalayERP/setup/index.php
```

### 2. Verify Permissions
The module automatically registers these permissions in RBAC:
- `items_master` - Main catalog permissions
- `item_inventory_log` - Inventory log access

Assign appropriate permissions to roles via the Settings → Roles module.

### 3. Access Module
Navigate to: **Catalog** in the sidebar menu.

## 📖 Usage Guide

### Adding a New Item

1. Click **➕ Add Item** from catalog list
2. Select **Item Type** (Product or Service)
   - Products: Enable inventory tracking
   - Services: No inventory tracking
3. Fill in required fields:
   - Name (required)
   - Base Price (required)
   - SKU (auto-generated if left blank)
4. Optional fields:
   - Category, Description, Tax, Discount
   - Expiry Date (for perishables)
   - Initial Stock & Low Stock Threshold (products only)
5. Upload files:
   - Primary Image (PNG/JPG, max 2MB)
   - Brochure (PDF, max 10MB)
6. Click **Create Item**

### Adjusting Stock (Products Only)

1. From item view, click **📊 Adjust Stock**
2. Select adjustment type:
   - **Add Stock** - Purchase/Receive goods
   - **Reduce Stock** - Damage/Loss/Manual sale
   - **Correction** - Set absolute value (admin override)
3. Enter quantity and reason (required for audit)
4. Optionally link to reference document
5. Review live preview of before/after stock
6. Click **Confirm Adjustment**

### Viewing Item Details

Item detail page has 4 tabs:

1. **📋 Overview** - Full item details, pricing, media
2. **📦 Inventory Log** - Complete stock movement history (products only)
3. **📎 Files** - All uploaded images and brochures
4. **📜 Change History** - Audit trail of all modifications

### Exporting Data

1. Apply desired filters on catalog list
2. Click **📥 Export**
3. Downloads filtered data as CSV with all key fields

## 🔐 Permissions

| Permission | Description |
|---|---|
| `view_all` | View all catalog items |
| `view_assigned` | View assigned items (future use) |
| `view_own` | View own created items |
| `create` | Add new items |
| `edit_all` | Edit any item |
| `edit_own` | Edit own items |
| `delete_all` | Delete any item |
| `export` | Export catalog data |

## ⚙️ Business Rules

1. **SKU Uniqueness** - Every item must have a unique SKU
2. **Service Stock** - Services cannot have inventory (always 0)
3. **Negative Stock** - Prevented unless using Correction action
4. **Status Control** - Inactive items hidden from selection lists
5. **Expiry Alerts** - Items expiring within configured days show warnings
6. **Low Stock Alerts** - Items below threshold highlighted
7. **Price Changes** - Logged in change history for audit

## 🔗 Integration Points

### Ready for Integration

1. **Quotations Module**
   - Item picker (by name/SKU)
   - Pull price, tax, discount from catalog
   - Read-only (no stock impact)

2. **Invoices Module**
   - Item picker with quantity
   - Auto-deduct stock on invoice creation
   - Log reference in `item_inventory_log`
   - Prevent over-selling (stock validation)

3. **Dashboard**
   - Low stock count widget
   - Expiring items widget
   - Total stock value display

## 📊 Statistics & Reports

Available statistics:
- Total items count
- Products vs Services breakdown
- Active items count
- Low stock items (below threshold)
- Expiring soon (next 30 days)
- Total stock value (current_stock × base_price)

## 🎨 UI Components

- **TinyMCE Editor** - Rich text descriptions
- **File Upload** - Image and PDF support
- **Live Preview** - Stock adjustment preview
- **Tabbed Interface** - Organized item details
- **Responsive Tables** - Mobile-friendly lists
- **Badge System** - Visual status indicators
- **Alert System** - Low stock, expired, warnings

## 🔮 Future Enhancements (Phase 2)

Planned features not yet implemented:
- [ ] Barcode/QR code generation and scanning
- [ ] Supplier linking and auto-reorder rules
- [ ] Tiered pricing (retail/wholesale/contract)
- [ ] Product bundles/kits (composite items)
- [ ] Multi-warehouse stock management
- [ ] Serial number tracking
- [ ] Batch/lot tracking
- [ ] Stock transfer between locations
- [ ] Purchase order integration
- [ ] Stock valuation methods (FIFO/LIFO/Weighted Avg)

## 🐛 Known Limitations

1. Single warehouse only (no multi-location support)
2. Manual invoice integration (auto-deduct requires custom code)
3. No batch operations (bulk price updates, bulk activate/deactivate)
4. Simple category structure (no hierarchy)
5. No product variants (size/color options)

## 📝 API Endpoints

### Available
- `GET /public/catalog/index.php` - List items with filters
- `GET /public/catalog/view.php?id={id}` - View item details
- `GET /public/api/catalog/export.php` - Export CSV

### Integration Helpers (in helpers.php)
```php
get_all_catalog_items($conn, $user_id, $filters)
get_item_by_id($conn, $item_id)
adjust_item_stock($conn, $item_id, $action, $quantity, $reason, $reference_type, $reference_id, $user_id)
```

## 🆘 Troubleshooting

### Module shows "Setup Required"
**Solution:** Run `scripts/setup_catalog_tables.php` or use setup wizard.

### Cannot see Catalog in sidebar
**Solution:** Check RBAC permissions. User role must have `view` permission on `items_master`.

### File upload fails
**Solution:** 
1. Check file size limits (2MB images, 10MB PDFs)
2. Verify `uploads/catalog/` directory has write permissions
3. Check PHP `upload_max_filesize` and `post_max_size` settings

### Stock goes negative
**Solution:** Use "Correction" action type with admin rights to override.

### Search not working
**Solution:** Ensure MySQL is running and catalog tables exist.

## 📞 Support

For issues or questions:
1. Check this README first
2. Review code comments in `helpers.php`
3. Check error logs in browser console
4. Contact system administrator

---

**Module Version:** 1.0.0  
**Last Updated:** November 2025  
**Maintained By:** Karyalay ERP Development Team
