# 🚚 Delivery Management Module - Complete Documentation

## Overview
The **Delivery Management Module** handles the final handover of client-approved deliverables to clients. It provides end-to-end tracking from delivery preparation through client confirmation, with support for multiple delivery channels, proof of delivery (POD) management, and comprehensive activity logging.

---

## 📋 Table of Contents
1. [Features](#features)
2. [Database Schema](#database-schema)
3. [File Structure](#file-structure)
4. [Installation](#installation)
5. [User Guide](#user-guide)
6. [API Endpoints](#api-endpoints)
7. [Workflow](#workflow)
8. [Configuration](#configuration)

---

## ✨ Features

### Core Functionality
- ✅ **Multi-Channel Delivery**: Email, Portal, WhatsApp, Physical, Courier, Cloud Link
- ✅ **Pipeline View**: Kanban-style board with 5 status stages
- ✅ **POD Management**: Upload and track proof of delivery documents
- ✅ **Status Tracking**: Pending → In Progress → Ready → Delivered → Confirmed
- ✅ **File Attachments**: Support for delivery files with validation
- ✅ **Activity Logging**: Complete audit trail of all actions
- ✅ **Export to CSV**: Download delivery data for reporting
- ✅ **Recipient Tracking**: Record delivery contact details
- ✅ **Integration**: Links to Deliverables, Work Orders, Clients, Employees

### User Interface
- 📊 **Dashboard**: Pipeline view with statistics cards
- 📝 **Create Form**: Quick delivery item creation
- 👁️ **Detail View**: Comprehensive delivery information
- ✏️ **Edit Form**: Update delivery details
- 📸 **POD Upload**: Dedicated interface for proof submission
- 🔍 **Search & Filter**: By status, channel, and keyword

---

## 🗄️ Database Schema

### 1. delivery_items
Primary table storing delivery information.

```sql
CREATE TABLE delivery_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deliverable_id INT NOT NULL,
    work_order_id INT,
    client_id INT,
    lead_id INT,
    status ENUM('Pending', 'In Progress', 'Ready to Deliver', 'Delivered', 'Confirmed', 'Failed') DEFAULT 'Pending',
    channel ENUM('Email', 'Portal', 'WhatsApp', 'Physical', 'Courier', 'Cloud Link', 'Other') NOT NULL,
    delivered_by INT,
    main_link TEXT,
    delivered_to_name VARCHAR(255),
    delivered_to_contact VARCHAR(255),
    delivered_at DATETIME,
    notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_client_or_lead CHECK (
        (client_id IS NOT NULL AND lead_id IS NULL) OR 
        (client_id IS NULL AND lead_id IS NOT NULL)
    ),
    FOREIGN KEY (deliverable_id) REFERENCES deliverables(id) ON DELETE CASCADE,
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE SET NULL,
    FOREIGN KEY (delivered_by) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_deliverable (deliverable_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);
```

**Key Fields:**
- `status`: Current delivery stage
- `channel`: Delivery method
- `delivered_by`: Employee responsible
- `main_link`: Cloud link or portal URL
- `delivered_at`: Timestamp of actual delivery

### 2. delivery_files
Stores delivery file attachments.

```sql
CREATE TABLE delivery_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES delivery_items(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_delivery (delivery_id)
);
```

**Usage:**
- Attach final delivery files (reports, documents, media)
- Max 20MB per file
- Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP

### 3. delivery_pod
Proof of Delivery documentation.

```sql
CREATE TABLE delivery_pod (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    notes TEXT,
    uploaded_by INT NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES delivery_items(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_delivery (delivery_id)
);
```

**POD Types:**
- Signed receipts
- Email confirmations
- Screenshots of portal uploads
- Photos of physical handover
- Courier tracking receipts

### 4. delivery_activity_log
Complete audit trail.

```sql
CREATE TABLE delivery_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    activity_type ENUM('created', 'updated', 'status_changed', 'file_uploaded', 'pod_uploaded', 'confirmed') NOT NULL,
    description TEXT NOT NULL,
    performed_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES delivery_items(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_delivery (delivery_id),
    INDEX idx_created (created_at)
);
```

---

## 📁 File Structure

```
public/delivery/
├── index.php                    # Dashboard with pipeline view
├── create.php                   # Create new delivery form
├── view.php                     # Detailed delivery view
├── edit.php                     # Edit delivery details
├── pod.php                      # Upload proof of delivery
├── export.php                   # CSV export functionality
├── api/
│   ├── create.php              # Create delivery endpoint
│   ├── update.php              # Update delivery endpoint
│   ├── start_delivery.php      # Pending → In Progress
│   ├── mark_ready.php          # In Progress → Ready
│   ├── mark_delivered.php      # Ready → Delivered
│   ├── upload_pod.php          # Upload POD files
│   └── confirm.php             # Mark as Confirmed
└── README.md                    # This documentation

scripts/
└── setup_delivery_tables.php    # Database setup wizard

uploads/delivery/
├── [delivery files]
└── pod/
    └── [POD files]
```

---

## 🚀 Installation

### Step 1: Run Database Setup
```bash
# Navigate to the setup script
http://yourdomain.com/scripts/setup_delivery_tables.php

# OR run SQL directly
mysql -u root -p your_database < delivery_tables.sql
```

### Step 2: Verify Directory Permissions
```bash
chmod 755 public/delivery
chmod 755 public/delivery/api
chmod 777 uploads/delivery
chmod 777 uploads/delivery/pod
```

### Step 3: Configuration
Ensure `config/db_connect.php` is properly configured:
```php
function createConnection() {
    $conn = mysqli_connect('localhost', 'username', 'password', 'database');
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}
```

### Step 4: Test Access
```
✓ Dashboard: /public/delivery/index.php
✓ Create: /public/delivery/create.php
✓ API: /public/delivery/api/create.php
```

---

## 📖 User Guide

### Creating a Delivery

1. **Navigate to Dashboard**
   - Go to `/public/delivery/index.php`
   - Click "➕ Create Delivery"

2. **Select Deliverable**
   - Choose from client-approved deliverables
   - Only completed/approved items shown

3. **Set Delivery Details**
   - **Channel**: Email, Portal, WhatsApp, Physical, Courier, Cloud Link, Other
   - **Delivered By**: Assign responsible employee
   - **Main Link**: Cloud storage or portal URL (optional)

4. **Add Recipient Info**
   - Recipient name
   - Contact (email/phone)

5. **Attach Files** (Optional)
   - Upload final delivery files
   - Max 20MB per file

6. **Submit**
   - Delivery created in "Pending" status

---

### Delivery Workflow

#### Status Progression

```
Pending → In Progress → Ready to Deliver → Delivered → Confirmed
```

#### 1. Pending
**Initial state when delivery is created**
- Action: Review delivery details
- Button: "🔄 Start Delivery"

#### 2. In Progress
**Delivery preparation underway**
- Action: Prepare files, packaging, communications
- Button: "📦 Mark Ready"

#### 3. Ready to Deliver
**All preparations complete**
- Action: Execute delivery (send email, ship courier, etc.)
- Button: "✅ Mark Delivered"

#### 4. Delivered
**Delivery sent/handed over**
- Action: Upload Proof of Delivery
- Button: "📸 Upload POD"
- **Note**: Requires POD upload for completion

#### 5. Confirmed
**Client confirmation received**
- Final status
- POD uploaded and verified
- Delivery cycle complete

---

### Viewing Delivery Details

**Access:** Click any delivery card in dashboard

**Information Displayed:**
- ✅ Status badge with color coding
- 📋 Deliverable and work order info
- 🚚 Delivery method and personnel
- 👤 Recipient details
- 📎 Attached files with download links
- 📸 POD files (if uploaded)
- 📜 Complete activity timeline

**Available Actions:**
- Status transitions (context-aware)
- Edit delivery details
- Upload POD
- Download files
- Export to CSV

---

### Uploading Proof of Delivery

1. **Access POD Upload**
   - From delivery view, click "📸 Upload POD"
   - Only available for "Delivered" status

2. **Select Files**
   - Click upload zone or drag files
   - Accepted: PDF, JPG, PNG, DOC, DOCX
   - Max 20MB per file

3. **Add Notes** (Optional)
   - Describe POD contents
   - Special delivery notes

4. **Submit**
   - Files uploaded to secure directory
   - Status auto-updates to "Confirmed"
   - Activity logged

---

### Using the Pipeline View

**Dashboard Layout:**
- 6 statistics cards (Total, Pending, In Progress, Ready, Delivered, Confirmed)
- 5 pipeline columns (status-based)
- Color-coded cards by channel
- Real-time counts

**Interactions:**
- Click card → View details
- Search bar → Filter by deliverable/WO
- Status filter → Show specific stage
- Channel filter → Filter by method
- Export button → Download CSV

---

## 🔌 API Endpoints

### 1. Create Delivery
**Endpoint:** `POST /public/delivery/api/create.php`

**Parameters:**
```php
deliverable_id (required) - INT
channel (required) - ENUM
delivered_by (optional) - INT
main_link (optional) - TEXT
delivered_to_name (optional) - VARCHAR
delivered_to_contact (optional) - VARCHAR
notes (optional) - TEXT
files[] (optional) - FILE array
```

**Response:**
```json
{
  "success": true,
  "message": "Delivery item created successfully",
  "delivery_id": 42,
  "files_uploaded": 3
}
```

---

### 2. Update Delivery
**Endpoint:** `POST /public/delivery/api/update.php`

**Parameters:**
```php
delivery_id (required) - INT
channel (required) - ENUM
delivered_by (optional) - INT
main_link (optional) - TEXT
delivered_to_name (optional) - VARCHAR
delivered_to_contact (optional) - VARCHAR
notes (optional) - TEXT
```

**Response:**
```json
{
  "success": true,
  "message": "Delivery updated successfully"
}
```

---

### 3. Start Delivery
**Endpoint:** `POST /public/delivery/api/start_delivery.php`

**Parameters:**
```php
delivery_id (required) - INT
```

**Effect:** Pending → In Progress

---

### 4. Mark Ready
**Endpoint:** `POST /public/delivery/api/mark_ready.php`

**Parameters:**
```php
delivery_id (required) - INT
```

**Effect:** In Progress → Ready to Deliver

---

### 5. Mark Delivered
**Endpoint:** `POST /public/delivery/api/mark_delivered.php`

**Parameters:**
```php
delivery_id (required) - INT
```

**Effect:** Ready to Deliver → Delivered (sets delivered_at timestamp)

---

### 6. Upload POD
**Endpoint:** `POST /public/delivery/api/upload_pod.php`

**Parameters:**
```php
delivery_id (required) - INT
pod_files[] (required) - FILE array
notes (optional) - TEXT
```

**Effect:** Auto-updates to Confirmed status

**Response:**
```json
{
  "success": true,
  "message": "POD uploaded successfully",
  "files_uploaded": 2
}
```

---

### 7. Confirm Delivery
**Endpoint:** `POST /public/delivery/api/confirm.php`

**Parameters:**
```php
delivery_id (required) - INT
```

**Effect:** Delivered → Confirmed (manual confirmation)

---

## 🔄 Complete Workflow Example

### Scenario: Email Delivery of Website Design

**Step 1: Create Delivery**
```
User: Project Manager
Action: Create delivery for "Website Mockups v3.0"
Channel: Email
Delivered By: Sarah Johnson
Recipient: john@clientcompany.com
Status: Pending
```

**Step 2: Start Delivery**
```
User: Sarah Johnson
Action: Click "Start Delivery"
Activity: Preparing email with attachment links
Status: In Progress
```

**Step 3: Mark Ready**
```
User: Sarah Johnson
Action: Click "Mark Ready"
Activity: Email drafted, files staged
Status: Ready to Deliver
```

**Step 4: Mark Delivered**
```
User: Sarah Johnson
Action: Click "Mark Delivered"
Activity: Email sent successfully
Status: Delivered
Timestamp: 2025-01-15 14:30:00
```

**Step 5: Upload POD**
```
User: Sarah Johnson
Action: Upload POD
Files: 
  - email_confirmation_screenshot.png
  - client_acknowledgment.pdf
Notes: "Client confirmed receipt via email reply"
Status: Confirmed (auto-updated)
```

**Result:**
- Complete audit trail logged
- 5 activity log entries
- 2 POD files attached
- Delivery cycle complete

---

## 🎨 Status Color Coding

| Status | Color | Icon | Meaning |
|--------|-------|------|---------|
| Pending | Orange | ⏳ | Awaiting start |
| In Progress | Blue | 🔄 | Being prepared |
| Ready to Deliver | Purple | 📦 | Ready to send |
| Delivered | Green | ✅ | Sent/Handed over |
| Confirmed | Dark Green | ✓✓ | Client confirmed |
| Failed | Red | ❌ | Delivery failed |

---

## 📊 Channel Types

| Channel | Icon | Use Case | Color |
|---------|------|----------|-------|
| Email | 📧 | Email attachments/links | Blue |
| Portal | 🌐 | Client portal upload | Purple |
| WhatsApp | 💬 | WhatsApp file sharing | Green |
| Physical | 🤝 | In-person handover | Orange |
| Courier | 📦 | Shipping service | Red |
| Cloud Link | ☁️ | Google Drive/Dropbox | Cyan |
| Other | ➕ | Custom methods | Gray |

---

## 🔒 Security Features

1. **SQL Injection Protection**: Prepared statements throughout
2. **File Validation**: Type and size restrictions
3. **Unique Filenames**: Prevents overwrites
4. **Directory Permissions**: Controlled upload access
5. **Activity Logging**: Complete audit trail
6. **Foreign Key Constraints**: Data integrity
7. **Transaction Management**: Rollback on errors

---

## 📈 Reporting & Analytics

### Available Metrics
- Total deliveries by status
- Deliveries by channel
- Average delivery time
- Pending deliveries count
- Confirmation rate
- POD upload compliance

### Export Options
- **CSV Export**: Full delivery data
- **Filters**: By status, channel, date range
- **Fields**: All delivery details + timestamps

---

## 🔧 Configuration

### File Upload Limits
```php
// In api/create.php and api/upload_pod.php
$max_file_size = 20 * 1024 * 1024; // 20MB
$allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
```

### Upload Directories
```php
// Delivery files
$upload_dir = __DIR__ . '/../../../uploads/delivery/';

// POD files
$upload_dir = __DIR__ . '/../../../uploads/delivery/pod/';
```

### Status Transitions
```php
// Allowed transitions
Pending → In Progress
In Progress → Ready to Deliver
Ready to Deliver → Delivered
Delivered → Confirmed (with POD upload)
```

---

## 🆘 Troubleshooting

### Issue: Files not uploading
**Solution:**
1. Check directory permissions: `chmod 777 uploads/delivery`
2. Verify PHP upload limits in `php.ini`:
   ```ini
   upload_max_filesize = 20M
   post_max_size = 25M
   ```
3. Check `error_log` for PHP errors

### Issue: Status transition fails
**Solution:**
1. Verify current status matches required state
2. Check database foreign key constraints
3. Review activity log for errors

### Issue: POD upload doesn't auto-confirm
**Solution:**
1. Verify delivery status is "Delivered"
2. Check POD files uploaded successfully
3. Review database transaction logs

---

## 📞 Support & Maintenance

### Database Maintenance
```sql
-- Check delivery statistics
SELECT status, COUNT(*) as count 
FROM delivery_items 
GROUP BY status;

-- Find deliveries without POD (delivered but not confirmed)
SELECT id, deliverable_id, delivered_at 
FROM delivery_items 
WHERE status = 'Delivered' 
AND id NOT IN (SELECT DISTINCT delivery_id FROM delivery_pod);

-- Clean old activity logs (older than 1 year)
DELETE FROM delivery_activity_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Backup Commands
```bash
# Backup delivery tables
mysqldump -u root -p database_name delivery_items delivery_files delivery_pod delivery_activity_log > delivery_backup.sql

# Backup upload directory
tar -czf delivery_uploads_backup.tar.gz uploads/delivery/
```

---

## 🎯 Best Practices

1. **Always Upload POD**: Ensures accountability
2. **Use Descriptive Notes**: Helps with future reference
3. **Assign Delivery Personnel**: Clear responsibility
4. **Verify Links Before Delivery**: Test cloud links
5. **Export Regularly**: Maintain external records
6. **Monitor Pending Items**: Avoid bottlenecks
7. **Archive Completed Deliveries**: Keep system performant

---

## 📝 Change Log

### Version 1.0 (2025-01-15)
- Initial release
- Pipeline view dashboard
- 5-stage workflow
- POD management
- Activity logging
- CSV export
- Multi-channel support

---

## 🔗 Integration Points

### Deliverables Module
- Pulls "Client Approved" deliverables
- Links back to deliverable details

### Work Orders Module
- Inherits work_order_id
- Displays WO code in UI

### Clients/CRM Module
- Links to client/lead records
- Uses for recipient prefill

### Employees Module
- Assignment of delivery personnel
- Activity log attribution

---

## 📚 Additional Resources

- **Deliverables Module**: `/public/deliverables/README.md`
- **Database Schema**: `/readme/MODULE_PREREQUISITES_STATUS.md`
- **RBAC Guide**: `/readme/RBAC_IMPLEMENTATION_SUMMARY.md`
- **ERP Dashboard**: `/readme/ERP_DASHBOARD_README.md`

---

## ✅ Module Status: COMPLETE

**Implementation Date**: January 15, 2025
**Version**: 1.0
**Status**: Production Ready ✓

**Completed Components:**
- ✅ Database schema (4 tables)
- ✅ Frontend pages (6 pages)
- ✅ API endpoints (7 endpoints)
- ✅ File upload system
- ✅ POD management
- ✅ Activity logging
- ✅ CSV export
- ✅ Pipeline view
- ✅ Status workflows
- ✅ Comprehensive documentation

---

**Module Complete** | Built with ❤️ for KaryalayERP
