# Visitor Log Module - Documentation

## 📋 Overview

The Visitor Log module is a comprehensive digital visitor management system for tracking office visitors, recording check-in/check-out times, and maintaining compliance-ready logs. It replaces traditional paper-based visitor registers with a modern, searchable, and audit-ready digital solution.

---

## ✨ Key Features

### 1. **Digital Check-in System**
- Quick visitor entry at reception/security desk
- Record visitor name, phone, purpose of visit
- Link visitors to specific employees they're meeting
- Optional photo/ID document upload (up to 2 MB)
- Automatic timestamp recording

### 2. **Time Tracking**
- Automatic check-in time capture
- One-click check-out functionality
- Duration calculation between check-in and check-out
- Filter by checked-in vs checked-out status

### 3. **Smart Filtering & Search**
- Filter by date range
- Search by visitor name
- Filter by employee being visited
- Filter by check-in status (pending/completed)

### 4. **Reporting & Export**
- Daily summary reports (printable)
- CSV export with all filters applied
- Employee-wise visitor analytics
- Audit trail with logged-by information

### 5. **Security & Compliance**
- Soft delete (archive) for audit compliance
- Role-based access (Admin/Manager/Guard)
- Photo/ID document storage
- Complete audit trail with timestamps

---

## 🚀 Installation & Setup

### Prerequisites
1. **Employee Module**: Must be installed first (visitors are linked to employees)
2. **PHP 7.4+** with mysqli extension
3. **MySQL/MariaDB** database
4. **Web server**: Apache/Nginx with mod_rewrite

### Installation Steps

#### Step 1: Access the Module
Navigate to the Visitor Log from the sidebar or directly at:
```
https://your-domain.com/public/visitors/
```

#### Step 2: Run Setup Wizard
If the module isn't installed, you'll see an onboarding screen. Click **"Start Setup"** to:
- Create the `visitor_logs` database table
- Set up required indexes and foreign keys
- Create the upload directory at `uploads/visitor_logs/`

Alternatively, run the setup script directly:
```
https://your-domain.com/scripts/setup_visitor_logs_table.php
```

#### Step 3: Verify Installation
After successful setup, you'll be redirected to the main visitor log page where you can:
- Add new visitor entries
- View existing logs
- Generate reports

---

## 📊 Database Schema

### Table: `visitor_logs`

| Column         | Type         | Description                              |
|----------------|--------------|------------------------------------------|
| id             | INT (PK, AI) | Unique visitor log ID                    |
| visitor_name   | VARCHAR(100) | Full name of the visitor                 |
| phone          | VARCHAR(15)  | Visitor's phone number (optional)        |
| purpose        | VARCHAR(100) | Reason for visit                         |
| check_in_time  | DATETIME     | Entry timestamp                          |
| check_out_time | DATETIME     | Exit timestamp (NULL until checkout)     |
| employee_id    | INT (FK)     | Employee being visited                   |
| photo          | TEXT         | Path to visitor photo/ID (optional)      |
| added_by       | INT (FK)     | Employee who logged the entry            |
| created_at     | TIMESTAMP    | Record creation time                     |
| updated_at     | TIMESTAMP    | Last update time                         |
| deleted_at     | TIMESTAMP    | Soft delete timestamp (NULL = active)    |

**Indexes:**
- `idx_visitor_check_in` on `check_in_time`
- `idx_visitor_employee` on `employee_id`
- `idx_visitor_name` on `visitor_name`

**Foreign Keys:**
- `employee_id` → `employees.id` (ON DELETE RESTRICT)
- `added_by` → `employees.id` (ON DELETE RESTRICT)

---

## 🖥️ User Interface Pages

### 1. Main Listing (`/public/visitors/index.php`)
**Access:** Admin, Manager

**Features:**
- Tabular view of all visitor logs
- Multi-criteria filtering (date, employee, visitor name, status)
- Status badges (Checked-in / Checked-out)
- Duration display for completed visits
- Quick checkout button for pending visitors
- Archive functionality (Admin only)

**Actions:**
- View Details
- Mark Checkout (if pending)
- Archive Entry (Admin only)

---

### 2. Add Visitor (`/public/visitors/add.php`)
**Access:** Admin, Manager, Guard

**Form Fields:**
- **Visitor Name** (required)
- **Phone** (optional)
- **Check-in Time** (datetime picker, default: now)
- **Purpose of Visit** (required)
- **Meeting With** (employee dropdown, required)
- **Photo/ID Proof** (optional file upload, JPG/PNG/PDF, max 2 MB)

**Validations:**
- Name and purpose are mandatory
- Valid employee selection required
- File type and size restrictions
- Check-in time cannot be in the future

---

### 3. View Details (`/public/visitors/view.php?id={id}`)
**Access:** Admin, Manager

**Displays:**
- Complete visitor information
- Check-in and check-out timestamps
- Visit duration (if checked out)
- Employee being visited
- Logged-by employee details
- Attached photo/document preview (if available)

**Actions:**
- Mark Checkout (if pending)
- Archive Entry (Admin only)

---

### 4. Daily Summary (`/public/visitors/summary.php?date={date}`)
**Access:** Admin, Manager

**Features:**
- Date-specific visitor list
- Summary cards: Total / Checked-out / Pending
- Printable format (via browser print)
- Quick date selector

**Use Case:**
- End-of-day register printing
- Daily compliance reports
- Security log archival

---

### 5. CSV Export (`/public/visitors/export.php`)
**Access:** Admin, Manager

**Exports:**
- All filtered visitor logs to CSV
- Includes: Visitor name, phone, purpose, check-in/out times, employee details
- Filename format: `visitor_logs_YYYYMMDD_HHMMSS.csv`

---

## 🔌 REST API Endpoints

### Base URL: `/public/api/visitors/`

All endpoints require authentication (`$_SESSION['user_id']` must be set).

---

### 1. **GET** `/api/visitors/index.php`
**Description:** List all visitor logs with filters  
**Access:** Admin, Manager  
**Query Parameters:**
- `from_date` (YYYY-MM-DD, default: first day of month)
- `to_date` (YYYY-MM-DD, default: today)
- `employee_id` (int)
- `visitor` (string, partial name match)
- `status` (checked_in | checked_out)

**Response:**
```json
{
  "success": true,
  "count": 15,
  "data": [
    {
      "id": 1,
      "visitor_name": "John Doe",
      "phone": "9876543210",
      "purpose": "Business meeting",
      "check_in_time": "2025-10-25 10:30:00",
      "check_out_time": "2025-10-25 12:45:00",
      "visiting_code": "EMP001",
      "visiting_first": "Jane",
      "visiting_last": "Smith",
      ...
    }
  ]
}
```

---

### 2. **GET** `/api/visitors/view.php?id={id}`
**Description:** Get single visitor log details  
**Access:** Admin, Manager  
**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "visitor_name": "John Doe",
    ...
  }
}
```

---

### 3. **POST** `/api/visitors/add.php`
**Description:** Create new visitor log  
**Access:** Admin, Manager, Guard  
**POST Data:**
- `visitor_name` (required)
- `phone` (optional)
- `purpose` (required)
- `check_in_time` (required, ISO 8601 format)
- `employee_id` (required)
- `photo` (optional file upload)

**Response:**
```json
{
  "success": true,
  "message": "Visitor log created",
  "id": 42
}
```

---

### 4. **POST** `/api/visitors/checkout.php`
**Description:** Mark visitor as checked out  
**Access:** Admin, Manager, Guard  
**POST Data:**
- `id` (required, visitor log ID)
- `checkout_time` (optional, default: current time)

**Response:**
```json
{
  "success": true,
  "message": "Checkout time updated"
}
```

---

### 5. **DELETE** `/api/visitors/delete.php?id={id}`
**Description:** Soft delete (archive) visitor log  
**Access:** Admin only  
**Response:**
```json
{
  "success": true,
  "message": "Visitor log archived"
}
```

---

## 👥 User Roles & Permissions

| Role    | List Logs | Add Entry | Checkout | View Details | Archive | Export |
|---------|-----------|-----------|----------|--------------|---------|--------|
| Admin   | ✅        | ✅        | ✅       | ✅           | ✅      | ✅     |
| Manager | ✅        | ✅        | ✅       | ✅           | ❌      | ✅     |
| Guard   | ❌        | ✅        | ✅       | ❌           | ❌      | ❌     |
| User    | ❌        | ❌        | ❌       | ❌           | ❌      | ❌     |

**Note:** Guard role is intended for reception/security personnel who only need to log entries and mark checkouts.

---

## 📁 File Structure

```
KaryalayERP/
├── public/
│   ├── visitors/
│   │   ├── index.php          # Main listing page
│   │   ├── add.php            # Add visitor form
│   │   ├── view.php           # Visitor detail view
│   │   ├── summary.php        # Daily summary report
│   │   ├── export.php         # CSV export
│   │   └── onboarding.php     # Setup wizard UI
│   ├── api/
│   │   └── visitors/
│   │       ├── index.php      # List API
│   │       ├── view.php       # Detail API
│   │       ├── add.php        # Create API
│   │       ├── checkout.php   # Checkout API
│   │       └── delete.php     # Archive API
│   └── visitors.php           # Redirect to index
├── scripts/
│   └── setup_visitor_logs_table.php  # Database setup
└── uploads/
    └── visitor_logs/          # Photo/ID storage
```

---

## 🛠️ Configuration

### Upload Settings
Default settings in `add.php` and API endpoints:
```php
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 2 * 1024 * 1024; // 2 MB
$upload_dir = 'uploads/visitor_logs/';
```

To modify, edit these values in:
- `/public/visitors/add.php` (line ~155)
- `/public/api/visitors/add.php` (line ~130)

### Role Access
To add/modify allowed roles, update arrays in each file:
```php
$allowed_roles = ['admin', 'manager', 'guard'];
```

---

## 🔧 Troubleshooting

### Common Issues

**1. "Visitor Log module not ready" message**
- **Cause:** Database table not created
- **Fix:** Click "Start Setup" on onboarding page or run setup script

**2. Upload directory permission errors**
- **Cause:** Web server lacks write permissions
- **Fix:** 
  ```bash
  chmod 755 uploads/visitor_logs
  chown www-data:www-data uploads/visitor_logs
  ```

**3. Foreign key constraint errors**
- **Cause:** Employee module not installed
- **Fix:** Install Employee module first, then run Visitor Log setup

**4. Photos not displaying**
- **Cause:** Incorrect APP_URL in config or file path issue
- **Fix:** Verify `APP_URL` in `config/config.php` matches your domain

---

## 📊 Usage Examples

### Example Workflow: Reception Check-in

1. **Visitor arrives** → Reception opens "Add Visitor"
2. **Enter details:**
   - Name: "Rajesh Kumar"
   - Phone: "9876543210"
   - Purpose: "Job Interview"
   - Meeting: "Narmesh Nigam (HR Manager)"
3. **Optional:** Capture photo using webcam/upload ID
4. **Save** → Visitor gets entry in system
5. **On exit** → Reception marks checkout → Duration auto-calculated

### Example: Daily Register Export

1. Navigate to "Daily Summary"
2. Select date (e.g., 25-Oct-2025)
3. Review summary cards (Total: 15, Checked-out: 12, Pending: 3)
4. Click "Print" for physical register
5. Or use "Export CSV" for digital archival

---

## 🔐 Security Considerations

1. **Soft Delete:** Archived entries remain in database with `deleted_at` timestamp
2. **File Validation:** MIME type and size checks prevent malicious uploads
3. **SQL Injection:** All queries use prepared statements
4. **Access Control:** Role-based permissions enforced on every page
5. **Audit Trail:** `added_by` field tracks who created each entry

---

## 🚧 Future Enhancements (Roadmap)

### Phase 2 Features
- [ ] WhatsApp/Email notifications to employees when visitor checks in
- [ ] OTP verification for visitor mobile numbers
- [ ] Auto-checkout after configurable hours
- [ ] Visitor badge printing with QR codes
- [ ] Facial recognition integration
- [ ] Visitor pre-registration portal
- [ ] Recurring visitor profiles (frequent visitors)
- [ ] Analytics dashboard (peak hours, most visited employees)

---

## 📞 Support

For issues, feature requests, or contributions:
- **Developer:** Narmesh Nigam
- **Repository:** github.com/narmeshnigam/KaryalayERP
- **Email:** Contact your system administrator

---

## 📜 License

This module is part of the KaryalayERP system. All rights reserved.

---

## 📝 Changelog

### Version 1.0.0 (October 2025)
- ✅ Initial release
- ✅ Basic check-in/checkout functionality
- ✅ Photo/ID upload support
- ✅ Daily summary reports
- ✅ CSV export
- ✅ REST API endpoints
- ✅ Role-based access control
- ✅ Soft delete (archive) support

---

**Last Updated:** October 25, 2025
