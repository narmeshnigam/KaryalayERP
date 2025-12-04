# Payroll Transaction Number Migration - Quick Start

## ⚡ Quick Setup (5 Minutes)

### Step 1: Run Database Migration
Navigate to the migration script in your browser:
```
http://localhost/KaryalayERP/scripts/add_transaction_number_to_payroll_items.php
```

**What it does:**
- ✅ Adds `transaction_number` column to `payroll_items` table
- ✅ Creates unique index for fast lookups
- ✅ Auto-generates transaction numbers for existing records (format: PAY-YYYYMM-XXXXX)
- ✅ Safe to run multiple times (checks if column exists)

**Expected Output:**
```
✅ Successfully added 'transaction_number' column to payroll_items table
✅ Generated transaction numbers for 45 existing payroll items
✨ Migration Completed Successfully!
```

### Step 2: Verify Changes
Check your payroll items:
```sql
SELECT id, transaction_number, employee_id, payable 
FROM payroll_items 
LIMIT 10;
```

**Expected Result:**
```
| id | transaction_number  | employee_id | payable   |
|----|-------------------|-------------|-----------|
| 1  | PAY-202511-00001  | 15          | 58000.00  |
| 2  | PAY-202511-00002  | 16          | 52000.00  |
| 3  | PAY-202511-00003  | 17          | 61000.00  |
```

### Step 3: Test the Features

#### Test 1: View Payroll with Transaction Numbers
1. Go to: `Payroll → View any payroll`
2. Click on "Items" tab
3. You should see transaction numbers in the first column

#### Test 2: Inline Editing (Draft Payroll Only)
1. Open a **Draft** payroll
2. Go to "Items" tab
3. Click **"✏️ Edit Items"** button
4. Change any amount (base, allowances, or deductions)
5. Row turns yellow, payable auto-calculates
6. Click **"💾 Save Changes"**
7. Success message appears, changes persist

#### Test 3: Delete Individual Item
1. In Draft payroll items view
2. Click delete button (🗑️) next to any item
3. Confirm deletion
4. Item removed, total recalculated

#### Test 4: Create New Payroll
1. Go to: `Payroll → Generate Payroll`
2. Select type (Salary/Reimbursement)
3. Choose month and employees
4. Click "Create Payroll Draft"
5. View the created payroll
6. Check Items tab - transaction numbers auto-assigned sequentially

---

## 🎯 Key Features Now Available

### 1. Unique Transaction Numbers
- **Format**: `PAY-YYYYMM-XXXXX`
- **Example**: `PAY-202511-00001`, `PAY-202511-00002`...
- **Auto-Generated**: Created automatically when items are added
- **Editable**: Can be changed in Edit Mode (with uniqueness validation)

### 2. Inline Table Editing
- **Edit Mode**: Click "Edit Items" to enable
- **Editable Fields**:
  - Transaction Number
  - Base Salary
  - Allowances
  - Deductions
- **Auto-Calculation**: Payable = Base + Allowances - Deductions
- **Visual Feedback**: Changed rows highlighted in yellow
- **Bulk Save**: Save all changes at once

### 3. Individual Item Operations
- **Delete Items**: Remove single item from payroll
- **Update Amounts**: Adjust salary components per employee
- **Change Transaction IDs**: Custom numbering for bank integration

### 4. Real-Time Updates
- **Payable Calculation**: Updates as you type
- **Total Recalculation**: Footer totals update live
- **Change Tracking**: Only modified items saved

---

## 📋 Common Tasks

### Task: Adjust Employee Salary Mid-Payroll
```
Problem: Employee worked only 15 days, need to pro-rate salary
Solution:
1. Open Draft payroll
2. Click "Edit Items"
3. Find employee row
4. Change base_salary from 50,000 to 25,000
5. Allowances and deductions adjust proportionally
6. Payable auto-updates
7. Click "Save Changes"
✅ Done! Individual salary adjusted without recreating payroll
```

### Task: Update Transaction Numbers for Banking System
```
Problem: Bank requires specific format for transaction IDs
Solution:
1. Open Draft payroll
2. Click "Edit Items"
3. Change transaction numbers to bank format
   Example: PAY-202511-00001 → BANK-TXN-00001
4. System validates uniqueness
5. Click "Save Changes"
✅ Done! Transaction numbers updated for bank reconciliation
```

### Task: Remove Employee from Payroll
```
Problem: Employee resigned, shouldn't be paid
Solution:
1. Open Draft payroll → Items tab
2. Find employee row
3. Click delete button (🗑️)
4. Confirm deletion
5. Total automatically recalculates
✅ Done! Employee removed, amount adjusted
```

### Task: Export Transaction List for Banking
```
Problem: Need to upload payment details to banking portal
Solution:
1. Open payroll → Items tab
2. Click "📊 Export Items"
3. Select format (CSV/Excel)
4. File downloads with transaction numbers and amounts
✅ Done! Ready for bank upload
```

---

## 🔍 Troubleshooting

### Issue: Transaction numbers showing as "N/A"
**Cause**: Migration not run yet  
**Fix**: Run `scripts/add_transaction_number_to_payroll_items.php`

### Issue: "Edit Items" button not visible
**Cause**: Payroll is Locked or Paid (read-only)  
**Fix**: Only Draft payroll can be edited. Create new draft or edit before locking.

### Issue: Changes not saving
**Cause 1**: User lacks `employees.create` permission  
**Fix**: Contact admin to grant permission

**Cause 2**: JavaScript error  
**Fix**: Open browser console (F12), check for errors, ensure `api_update_items.php` is accessible

### Issue: Duplicate transaction number error
**Cause**: Transaction number already exists in database  
**Fix**: Choose different number. System auto-increments, duplicates only occur with manual entry.

### Issue: Delete button not working
**Cause**: User lacks `employees.delete` permission  
**Fix**: Contact admin to grant permission

---

## 🎨 UI Elements Guide

### Buttons
| Button | Icon | Function | When Visible |
|--------|------|----------|--------------|
| Edit Items | ✏️ | Enable inline editing | Draft payroll only |
| Save Changes | 💾 | Save all modifications | Edit mode active |
| Cancel | ❌ | Revert changes | Edit mode active |
| Export Items | 📊 | Download Excel/CSV | Always |
| Delete Item | 🗑️ | Remove single item | Draft payroll, not in edit mode |

### Visual Indicators
| Color | Meaning |
|-------|---------|
| White background | Normal, unchanged |
| Yellow background (#fff9e6) | Item modified, not saved |
| Light gray on hover | Interactive element |
| Blue text (#003581) | Transaction number, important values |

### Status Badges
| Badge | Color | Meaning |
|-------|-------|---------|
| Draft | Gray | Editable, not finalized |
| Locked | Blue | Finalized, read-only |
| Paid | Green | Payment completed |
| Pending | Yellow | Payment pending |

---

## 📊 Database Schema

### New Column in `payroll_items`
```sql
CREATE TABLE payroll_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) NULL UNIQUE,  -- ✨ NEW
    payroll_id INT NOT NULL,
    employee_id INT NOT NULL,
    -- ... other columns
);
```

### Index Added
```sql
CREATE INDEX idx_transaction_number ON payroll_items(transaction_number);
```

---

## 🔐 Security & Permissions

### Required Permissions
| Action | Permission Required |
|--------|-------------------|
| View items | `employees.view` |
| Edit items | `employees.create` |
| Delete items | `employees.delete` |

### Business Rules
- ✅ Only **Draft** payroll can be edited
- ✅ **Locked** payroll is read-only
- ✅ Transaction numbers must be unique globally
- ✅ All changes logged in activity log
- ✅ Totals recalculated automatically

---

## 📞 Support

### Getting Help
1. Check this guide first
2. Review `PAYROLL_TRANSACTION_SYSTEM.md` for detailed documentation
3. Check browser console (F12) for JavaScript errors
4. Verify database migration completed successfully
5. Ensure user has correct permissions

### Reporting Issues
When reporting problems, include:
- Browser and version
- User role and permissions
- Payroll status (Draft/Locked/Paid)
- Steps to reproduce
- Error messages or screenshots

---

## ✅ Success Checklist

After migration, you should be able to:
- [ ] See transaction numbers in payroll items table
- [ ] Click "Edit Items" on Draft payroll
- [ ] Modify transaction numbers and amounts
- [ ] See yellow highlighting on changed rows
- [ ] Click "Save Changes" successfully
- [ ] Delete individual items
- [ ] See updated totals after changes
- [ ] Create new payroll with auto-generated transaction numbers
- [ ] Export items to Excel/CSV

---

## 🚀 What's Next?

Now that transaction numbers are working, consider these enhancements:
1. **Export to Banking Formats** (NEFT/RTGS CSV)
2. **Individual Payment Slips** (PDF per employee)
3. **Payment Status Tracking** (Mark items as paid individually)
4. **Approval Workflow** (Multi-level approval for changes)
5. **Advanced Analytics** (Transaction-wise reports)

See `PAYROLL_TRANSACTION_SYSTEM.md` for detailed enhancement roadmap.

---

**Last Updated**: November 5, 2025  
**Migration Script**: `scripts/add_transaction_number_to_payroll_items.php`  
**Status**: ✅ Ready for Production
