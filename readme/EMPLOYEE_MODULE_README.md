# Employee Module - Karyalay ERP

## Overview
Comprehensive Employee Management System with 70+ fields covering personal information, employment details, salary, documents, and more.

## Database Tables Created

### 1. employees
Main employee master table with the following sections:
- **Personal Information**: Name, DOB, Gender, Blood Group, Marital Status, Nationality
- **Contact Information**: Emails, Mobile Numbers, Emergency Contacts
- **Address Information**: Current & Permanent Address with City, State, Pincode
- **Employment Information**: Department, Designation, Type, Joining Date, Reporting Manager, Work Location
- **Salary & Financial**: Basic Salary, Allowances (HRA, Conveyance, Medical, Special), PF, ESI, UAN, PAN
- **Bank Details**: Bank Name, Account Number, IFSC, Branch
- **Documents**: Aadhar, PAN, Passport, Driving License, Voter ID, Photo, Resume
- **Education**: Qualification, Specialization, University, Year of Passing
- **Experience**: Previous Company, Designation, Years of Experience
- **Additional**: Skills, Certifications, Notes, Status

### 2. departments
- Department master with name, code, head, and status
- Sample data: IT, HR, Finance, Sales, Operations, Admin

### 3. designations
- Designation master with name, code, department link, level
- Sample data: Manager, Senior Executive, Executive, Team Leader, Developer, etc.

## Files Created

### Setup Script
**`scripts/setup_employees_table.php`**
- Creates all three tables (employees, departments, designations)
- Inserts sample department and designation data
- Visual setup interface with status feedback

### Main Module Pages
**`public/employees.php`** - Employee List Page
- Search by employee code, name, email, mobile
- Filter by department and status
- Pagination (20 records per page)
- Statistics cards (Active Employees, Departments, On Leave, New This Month)
- Photo display with fallback avatars
- Status badges with color coding
- View and Edit action buttons
- Export to Excel functionality

**`public/add_employee.php`** - Add New Employee
- Multi-section comprehensive form:
  - 👤 Personal Information (9 fields)
  - 📞 Contact Information (7 fields)
  - 🏠 Address Information (Current & Permanent with copy function)
  - 💼 Employment Information (9 fields)
  - 💰 Salary & Financial (11 fields with auto-calculation)
  - 🏦 Bank Details (4 fields)
  - 📄 Documents & Identification (7 fields with file upload)
  - 🎓 Education & Experience (8 fields)
  - ℹ️ Additional Information (Skills, Certifications, Notes)
- File upload support for Photo, Resume, Aadhar, PAN documents
- Auto employee code generation
- Gross salary auto-calculation
- Form validation
- Address copy functionality

## Key Features

### 1. Search & Filter
- Multi-field search (code, name, email, mobile)
- Department filter dropdown
- Status filter (Active, Inactive, On Leave, Terminated, Resigned)
- Maintains filter state during pagination

### 2. Employee Code Generation
- Auto-generated format: `{DEPT_CODE}{NUMBER}`
- Example: IT0001, HR0002, FIN0003
- Department code (first 3 letters) + sequential number
- Fallback to timestamp if duplicate

### 3. File Upload System
- Uploads directory: `uploads/employees/`
- Supported files: Photo (images), Resume (PDF/DOC), Documents (PDF/Images)
- Filename format: `{EMPLOYEE_CODE}_{TYPE}_{TIMESTAMP}.{EXT}`
- Automatic directory creation

### 4. Salary Calculation
- Components: Basic + HRA + Conveyance + Medical + Special
- Real-time gross salary calculation
- JavaScript auto-update on field change

### 5. Status Management
- Active: Currently working
- Inactive: Temporarily inactive
- On Leave: Currently on leave
- Terminated: Employment terminated
- Resigned: Employee resigned
- Color-coded badges for visual identification

### 6. Relationship Management
- Reporting Manager dropdown (links to other employees)
- Department and Designation from master tables
- Foreign key constraints for data integrity

## Usage Instructions

### Step 1: Setup Database
1. Navigate to `http://localhost/KaryalayERP/scripts/setup_employees_table.php`
2. Click "Create Employee Module Tables"
3. Wait for success confirmation
4. Tables created: employees, departments, designations

### Step 2: Access Employee Module
1. Go to Dashboard
2. Click "Employees" in sidebar
3. If not set up, you'll see setup prompt

### Step 3: Add First Employee
1. Click "Add New Employee" button
2. Fill required fields (marked with *)
   - First Name, Last Name
   - Date of Birth, Gender
   - Official Email, Mobile Number
   - Department, Designation
   - Date of Joining
3. Fill optional fields as needed
4. Upload documents (optional)
5. Click "Add Employee"

### Step 4: Manage Employees
- **View List**: Main employees page shows all records
- **Search**: Use search box for quick find
- **Filter**: Filter by department or status
- **View Details**: Click "View" button (to be implemented)
- **Edit**: Click "Edit" button (to be implemented)
- **Export**: Click "Export to Excel" (to be implemented)

## Pending Features (To Be Implemented)

1. **view_employee.php** - Employee Profile View
   - Comprehensive employee details display
   - Organized sections
   - Document previews
   - Edit and Print buttons

2. **edit_employee.php** - Edit Employee
   - Pre-filled form with existing data
   - Update functionality
   - File replacement option
   - Audit trail

3. **Employee Status Management**
   - Quick status change
   - Resignation process
   - Termination process
   - Re-activation

4. **Export Functionality**
   - Export to Excel with filters
   - PDF export
   - Custom field selection

5. **Advanced Features**
   - Employee attendance integration
   - Leave management integration
   - Payroll integration
   - Performance reviews
   - Document expiry alerts (Passport, License, etc.)
   - Birthday/Anniversary notifications

## Database Schema Reference

```sql
employees Table (Key Fields):
- id (PK, Auto Increment)
- employee_code (Unique, Indexed)
- first_name, middle_name, last_name
- official_email (Unique, Indexed)
- department (Indexed)
- designation
- status (Indexed)
- reporting_manager_id (FK to employees.id)
- user_id (FK to users.id)
- created_at, updated_at
- created_by, updated_by
```

## File Upload Paths

```
uploads/
└── employees/
    ├── {EMPLOYEE_CODE}_photo_{TIMESTAMP}.{ext}
    ├── {EMPLOYEE_CODE}_resume_{TIMESTAMP}.{ext}
    ├── {EMPLOYEE_CODE}_aadhar_{TIMESTAMP}.{ext}
    └── {EMPLOYEE_CODE}_pan_{TIMESTAMP}.{ext}
```

## Security Features

- Session-based authentication required
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars)
- File upload validation
- MIME type checking
- Secure file naming

## Performance Optimizations

- Database indexes on frequently searched fields
- Pagination for large datasets
- Efficient query structure
- Minimal joins in list view
- Lazy loading of related data

## API Endpoints (To Be Created)

Future AJAX endpoints:
- `api/employees/get.php` - Get employee by ID
- `api/employees/update_status.php` - Update employee status
- `api/employees/delete.php` - Soft delete employee
- `api/employees/export.php` - Export data
- `api/employees/search.php` - Advanced search

## Integration Points

The Employee module is designed to integrate with:
- **Users Module**: Create login accounts for employees
- **Attendance Module**: Track employee attendance
- **Leave Module**: Manage employee leaves
- **Payroll Module**: Calculate salaries
- **Performance Module**: Employee reviews and ratings
- **Training Module**: Employee training records

## Customization

### Adding New Fields
1. Alter `employees` table to add column
2. Update `add_employee.php` form
3. Update insert query parameters
4. Update display pages

### Changing Employee Code Format
Modify code generation logic in `add_employee.php`:
```php
$employee_code = $dept_code . str_pad($count, 4, '0', STR_PAD_LEFT);
```

### Adding New Departments
1. Go to database
2. Insert into `departments` table
3. Or create department management page

## Troubleshooting

**Issue**: Employee code already exists
- **Solution**: System auto-generates with timestamp fallback

**Issue**: File upload not working
- **Solution**: Check uploads/ folder permissions (777)
- Verify max_upload_filesize in php.ini

**Issue**: Search not returning results
- **Solution**: Check search syntax, use partial matches

**Issue**: Gross salary not calculating
- **Solution**: Check JavaScript console, verify field names

## Current Status

✅ Database Tables Created  
✅ Employee List Page  
✅ Add Employee Form  
✅ Search & Filter System  
✅ File Upload System  
⚠️ View Employee Page (Pending)  
⚠️ Edit Employee Page (Pending)  
⚠️ Export Functionality (Pending)  
⚠️ Advanced Features (Planned)  

---

**Module Version**: 1.0  
**Last Updated**: October 24, 2025  
**Developed By**: Karyalay ERP Team
