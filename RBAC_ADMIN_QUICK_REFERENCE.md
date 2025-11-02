# Quick Reference: Payments & Data Transfer RBAC Setup

## For System Administrators

This guide helps you set up permissions for the Payments and Data Transfer modules after implementing RBAC.

---

## Step 1: Verify Permission Entries Exist

Navigate to: **Settings → Permissions**

### Check for these table permissions:
1. **payments** - Should display "Payments" module
2. **data_transfer_logs** - Should display "Data Transfer" module

### If they don't exist:
The system will auto-create them on first sync, or you can manually add them via SQL:

```sql
-- Add Payments permission
INSERT INTO permissions (table_name, display_name, module, is_active, created_at) 
VALUES ('payments', 'Payments', 'Finance', 1, NOW());

-- Add Data Transfer permission  
INSERT INTO permissions (table_name, display_name, module, is_active, created_at)
VALUES ('data_transfer_logs', 'Data Transfer', 'System', 1, NOW());
```

---

## Step 2: Configure Role Permissions

Navigate to: **Settings → Permissions**

### Recommended Permission Matrix:

#### **Payments Module**

| Role | View All | Create | Edit All | Delete All | Export |
|------|----------|--------|----------|------------|--------|
| Finance Manager | ✅ | ✅ | ✅ | ✅ | ✅ |
| Finance Staff | ✅ | ✅ | ❌ | ❌ | ✅ |
| Accountant | ✅ | ✅ | ✅ | ❌ | ✅ |
| Manager | ✅ | ❌ | ❌ | ❌ | ✅ |
| Employee | ❌ | ❌ | ❌ | ❌ | ❌ |

#### **Data Transfer Module**

| Role | View All | Create (Import) | Export | Delete All |
|------|----------|-----------------|--------|------------|
| Super Admin | ✅ | ✅ | ✅ | ✅ |
| System Admin | ✅ | ✅ | ✅ | ❌ |
| IT Staff | ✅ | ✅ | ✅ | ❌ |
| Manager | ✅ | ❌ | ✅ | ❌ |
| Employee | ❌ | ❌ | ❌ | ❌ |

---

## Step 3: Assign Roles to Users

Navigate to: **Settings → Assign Roles**

1. Select user from the list
2. Click "Edit Roles"
3. Check appropriate role(s)
4. Click "Save Changes"

---

## Testing Access

### Test Payments Module:
1. **Full Access User** (Finance Manager):
   - Visit `/public/payments/` - Should see list
   - Click "Add Payment" - Should access form
   - Click "Edit" on payment - Should access edit form
   - Export button should be visible

2. **Read-Only User** (Manager):
   - Visit `/public/payments/` - Should see list
   - Add Payment button should be hidden
   - Edit/Delete buttons should be hidden
   - Export button should be visible

3. **No Access User** (Employee):
   - Visit `/public/payments/` - Should redirect to unauthorized page

### Test Data Transfer Module:
1. **Full Access User** (System Admin):
   - Visit `/public/data-transfer/` - Should see dashboard
   - Click "Import Data" - Should access import form
   - Click "Export Data" - Should access export form
   - Can view logs

2. **Limited Access User** (Manager):
   - Visit `/public/data-transfer/` - Should see dashboard
   - Import button should be hidden
   - Export button should be visible
   - Can view logs

3. **No Access User** (Employee):
   - Visit `/public/data-transfer/` - Should redirect to unauthorized page

---

## Troubleshooting

### Problem: All users getting "Unauthorized" error
**Solution:** Check if permissions table has entries for 'payments' and 'data_transfer_logs'

### Problem: Admin can't access modules
**Solution:** 
1. Verify admin has a role assigned
2. Check role has permissions enabled
3. Verify role status is "Active"

### Problem: Buttons still showing for users without permission
**Solution:** Clear browser cache and refresh page

### Problem: Users with permission still can't access
**Solution:** 
1. Check user has an active role assigned
2. Verify role has required permission checked
3. Check if permission is marked as active
4. Verify role_permissions table has correct entries

---

## SQL Queries for Verification

### Check if permissions exist:
```sql
SELECT * FROM permissions 
WHERE table_name IN ('payments', 'data_transfer_logs');
```

### Check user's roles:
```sql
SELECT u.username, r.name as role_name
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.id
WHERE u.username = 'your_username';
```

### Check role's permissions for payments:
```sql
SELECT r.name as role_name, p.display_name, 
       rp.can_view_all, rp.can_create, rp.can_edit_all, 
       rp.can_delete_all, rp.can_export
FROM roles r
JOIN role_permissions rp ON r.id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
WHERE p.table_name IN ('payments', 'data_transfer_logs')
ORDER BY r.name, p.table_name;
```

---

## Permission Definitions

### Payments Module
- **view_all**: Can view payments list and details
- **create**: Can add new payments
- **edit_all**: Can edit payment details and allocate to invoices
- **delete_all**: Can delete unallocated payments
- **export**: Can export payments to Excel/CSV

### Data Transfer Module
- **view_all**: Can view dashboard and activity logs
- **create**: Can import data from CSV files
- **export**: Can export table data to CSV
- **delete_all**: Can delete transfer logs (if implemented)

---

## Common Scenarios

### Scenario 1: New Finance Employee
**Grant:** Finance Staff role
**Result:** Can view and create payments, cannot edit or delete

### Scenario 2: Department Manager
**Grant:** Manager role  
**Result:** Can view payments and export reports, cannot modify

### Scenario 3: IT Administrator
**Grant:** System Admin role
**Result:** Full access to Data Transfer, can import/export

### Scenario 4: Auditor
**Grant:** Custom "Auditor" role with only view_all + export
**Result:** Can view all data and export reports, cannot modify

---

## Security Best Practices

✅ **Principle of Least Privilege**: Grant minimum required permissions  
✅ **Regular Audits**: Review user permissions quarterly  
✅ **Role Separation**: Different roles for different responsibilities  
✅ **Test Changes**: Test permission changes in development first  
✅ **Document Changes**: Keep log of permission modifications  
✅ **Revoke Access**: Remove permissions immediately when roles change  

---

## Support Contact

For issues with RBAC implementation:
1. Check this guide first
2. Review main documentation: `RBAC_PAYMENTS_DATA_TRANSFER_IMPLEMENTATION.md`
3. Check system logs in Data Transfer → Activity Logs
4. Contact IT administrator or system developer

---

**Last Updated:** November 3, 2025  
**Version:** 1.0  
**Status:** Production Ready
