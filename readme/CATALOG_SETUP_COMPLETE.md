# 🛍️ Catalog Module - Quick Setup Guide

## ✅ What's Been Created

### 1. Database Setup
- ✓ **File:** `scripts/setup_catalog_tables.php`
- **Tables Created:**
  - `items_master` - Main catalog (products & services)
  - `item_inventory_log` - Stock movement audit trail
  - `item_files` - Image & brochure attachments
  - `item_change_log` - Item modification history

### 2. Frontend Pages
- ✓ `public/catalog/index.php` - Catalog list with filters
- ✓ `public/catalog/add.php` - Add new item form
- ✓ `public/catalog/edit.php` - Edit item form
- ✓ `public/catalog/view.php` - Item detail with 4 tabs
- ✓ `public/catalog/stock_adjust.php` - Stock adjustment interface
- ✓ `public/catalog/helpers.php` - All business logic

### 3. API Endpoints
- ✓ `public/api/catalog/export.php` - CSV export

### 4. File Storage
- ✓ `uploads/catalog/images/` - Product images
- ✓ `uploads/catalog/brochures/` - PDF brochures

### 5. Integration
- ✓ Sidebar navigation link added
- ✓ RBAC permissions registered:
  - `items_master` (Catalog Items)
  - `item_inventory_log` (Inventory Log)
- ✓ Auto-guard and auth checks on all pages

## 🚀 How to Use

### Step 1: Setup Database
Visit: `http://localhost/KaryalayERP/scripts/setup_catalog_tables.php`

This will create all 4 required tables.

### Step 2: Assign Permissions
1. Go to **Settings → Roles**
2. Edit role (e.g., Admin, Manager)
3. Find **Catalog** module permissions
4. Grant appropriate access (view, create, edit, export)

### Step 3: Access Module
1. Login to the system
2. Click **Catalog** in the sidebar
3. Start adding products/services!

## 📋 Features Available

### Item Management
- ✅ Add/Edit/View products and services
- ✅ Auto-generated SKU
- ✅ Rich-text description (TinyMCE editor)
- ✅ Category organization
- ✅ Active/Inactive status

### Pricing
- ✅ Base price
- ✅ Tax percentage (GST/VAT)
- ✅ Default discount
- ✅ Expiry date tracking

### Inventory (Products Only)
- ✅ Current stock display
- ✅ Stock adjustments (Add/Reduce/Correction)
- ✅ Low stock alerts
- ✅ Complete movement history
- ✅ Before/after preview

### Files
- ✅ Primary image upload (PNG/JPG, max 2MB)
- ✅ Brochure upload (PDF, max 10MB)
- ✅ File history tracking

### Search & Filters
- ✅ Search by name/SKU/category
- ✅ Filter by type (Product/Service)
- ✅ Filter by status
- ✅ Filter by category
- ✅ Low stock filter
- ✅ Expiring items filter (30/60/90 days)

### Reporting
- ✅ Statistics dashboard (6 metrics)
- ✅ CSV export with filters
- ✅ Complete audit trails

### Item Detail Tabs
1. **Overview** - Full details, pricing, media
2. **Inventory Log** - Stock movement history
3. **Files** - Image & brochure history
4. **Change History** - Modification audit

## 🔐 Permissions Structure

Each role can have these permissions:
- **View All** - See all catalog items
- **View Own** - See own created items
- **Create** - Add new items
- **Edit All** - Modify any item
- **Edit Own** - Modify own items
- **Delete All** - Remove items
- **Export** - Download CSV

## 🎯 Next Steps

### Immediate Actions
1. ✅ Run database setup
2. ✅ Assign permissions to roles
3. ✅ Add your first product/service
4. ✅ Test stock adjustments
5. ✅ Upload sample images

### Integration (Phase 2)
- [ ] Connect to Quotations module (item picker)
- [ ] Connect to Invoices module (auto-deduct stock)
- [ ] Add dashboard widgets (low stock, expiring items)
- [ ] Email/WhatsApp alerts for low stock

## 📝 Sample Test Flow

1. **Add a Product:**
   - Name: "Laptop Dell XPS 13"
   - Type: Product
   - SKU: (auto-generated)
   - Base Price: 85000
   - Tax: 18%
   - Initial Stock: 10
   - Low Stock Threshold: 3

2. **Upload Files:**
   - Add product image
   - Upload spec sheet as brochure

3. **Adjust Stock:**
   - Reduce 2 units (reason: "Sold to customer")
   - Check inventory log

4. **Add a Service:**
   - Name: "Annual Maintenance Contract"
   - Type: Service
   - Base Price: 12000
   - Tax: 18%
   - Note: No stock fields for services

5. **Test Filters:**
   - Search by name
   - Filter by low stock
   - Export to CSV

## 🐛 Troubleshooting

### "Module Not Set Up"
**Fix:** Run `scripts/setup_catalog_tables.php`

### "Permission Denied"
**Fix:** Grant `items_master` view permission to your role

### File Upload Fails
**Fix:** Check `uploads/catalog/` folder permissions (755)

### Sidebar Link Missing
**Fix:** User must have view permission on `items_master`

## ✨ Key Highlights

1. **Complete CRUD** - All operations implemented
2. **Full Audit Trail** - Every change logged
3. **Smart Validations** - Business rules enforced
4. **Rich UI** - TinyMCE editor, live previews
5. **Flexible Inventory** - Products tracked, services ignored
6. **Export Ready** - CSV download with filters
7. **Permission Ready** - Full RBAC integration
8. **Mobile Friendly** - Responsive design

## 📚 Documentation

Full documentation: `CATALOG_MODULE_README.md`

## 🎉 Module Complete!

The Catalog module is **fully functional** and ready for production use. All core features from the specification have been implemented:

✅ Items Master (Products & Services)  
✅ Inventory Tracking with Audit  
✅ File Attachments (Images & Brochures)  
✅ Rich Text Descriptions  
✅ Stock Adjustments (Add/Reduce/Correction)  
✅ Search, Filters & Export  
✅ Statistics Dashboard  
✅ Permission-Based Access Control  
✅ Complete Change History  
✅ Setup Workflow (like other modules)  

**Status:** Ready for deployment! 🚀
