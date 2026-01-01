# Module Installer - User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Understanding Modules](#understanding-modules)
4. [Using the Interface](#using-the-interface)
5. [Installation Process](#installation-process)
6. [Troubleshooting](#troubleshooting)
7. [Accessibility](#accessibility)
8. [FAQ](#faq)

## Introduction

The Module Installer is a unified interface that allows you to install multiple ERP modules at once. Instead of visiting each module's page individually, you can now:

- **Select multiple modules** for batch installation
- **View detailed information** about each module before installing
- **Automatic dependency management** - no need to worry about installation order
- **Track progress** in real-time during installation
- **Handle errors gracefully** with retry options

## Getting Started

### When You'll See the Module Installer

1. **During Initial Setup**: After creating your admin account (Step 3), you'll be automatically redirected to the Module Installer
2. **From Settings**: Access it anytime from Settings → Install Modules
3. **Direct Access**: Navigate to `/setup/module_installer.php` (requires admin privileges)

### First-Time Setup Flow

```
Database Config → Create Tables → Create Admin → Module Installer → Dashboard
    (Step 1)         (Step 2)        (Step 3)        (Step 4)
```

### Skipping the Installer

You can click **"Skip for Now"** to go directly to the dashboard. You can always come back later to install modules.

## Understanding Modules

### Module Categories

Modules are organized into six categories:

#### 🏢 Core
Essential business modules:
- **Employees**: Manage employee records, departments, and positions
- **Clients**: Customer/client database with contact information
- **Users**: System user accounts and authentication
- **Branding**: Customize company logo, colors, and branding

#### 💰 Finance
Financial management modules:
- **Invoices**: Create, send, and track invoices
- **Quotations**: Generate quotes and convert to invoices
- **Payments**: Record and allocate payments to invoices
- **Catalog**: Product/service catalog with inventory management

#### 👥 HR (Human Resources)
Employee management modules:
- **Attendance**: Track employee attendance and leave
- **Salary**: Define salary structures and components
- **Payroll**: Process monthly payroll and generate salary slips
- **Reimbursements**: Handle employee expense reimbursement requests

#### 🔧 Operations
Business operations modules:
- **Projects**: Project management with tasks and milestones
- **Work Orders**: Create and track work orders
- **Deliverables**: Manage project deliverables
- **Delivery**: Track deliveries and proof of delivery

#### 📞 CRM (Customer Relationship Management)
Customer engagement modules:
- **CRM**: Leads, tasks, calls, meetings, and field visits
- **Contacts**: Comprehensive contact management with groups

#### 📦 Other
Additional utility modules:
- **Documents**: Store and manage documents with access control
- **Visitors**: Visitor log and badge management
- **Expenses**: Track office expenses and budgets
- **Data Transfer**: Import/export data in CSV format
- **Notebook**: Collaborative note-taking with version history
- **Assets**: Asset tracking and maintenance

### Module Dependencies

Some modules require other modules to be installed first. The installer handles this automatically:

| Module | Requires |
|--------|----------|
| Invoices | Clients, Catalog |
| Quotations | Clients, Catalog |
| Payments | Clients |
| CRM | Employees |
| Projects | Clients |
| Work Orders | Clients |
| Deliverables | Clients |
| Delivery | Clients |
| Payroll | Employees, Salary |

**Don't worry about this!** When you select a module, all required dependencies are automatically selected for you.

## Using the Interface

### Module Cards

Each module is displayed as a card showing:
- **Icon**: Visual identifier for the module
- **Name**: Module display name
- **Description**: Brief description of what the module does
- **Checkbox**: Select for installation (disabled if already installed)
- **Status Badge**: "✓ Installed" for already installed modules
- **Details Button**: View comprehensive module information

### Selecting Modules

1. **Click the checkbox** on any module card to select it
2. **Dependencies auto-select**: Required modules are automatically checked
3. **Selection count updates**: See how many modules are selected at the bottom
4. **Install button enables**: The install button becomes active when modules are selected

### Viewing Module Details

Click the **"ℹ️ Details"** button on any module to see:

- **📝 Description**: Detailed explanation of module features
- **🔗 Dependencies**: Modules that must be installed first
- **⬅️ Required By**: Other modules that depend on this one
- **🗄️ Database Tables**: List of tables that will be created
- **📊 Installation Status**: Whether the module is ready to install

### Deselection Protection

If you try to deselect a module that other selected modules depend on:
- ⚠️ A warning message appears
- The checkbox remains checked
- The message tells you which modules depend on it
- Deselect the dependent modules first

## Installation Process

### Step-by-Step Installation

1. **Select Modules**
   - Browse through categories
   - Click checkboxes to select modules
   - Dependencies are automatically selected
   - Review your selection count

2. **Review Selection**
   - Check the selection count at the bottom
   - View details of any module if needed
   - Ensure all required modules are selected

3. **Start Installation**
   - Click **"Install Selected Modules"** button
   - A progress modal appears
   - Installation begins immediately

4. **Monitor Progress**
   - Progress bar shows overall completion
   - Current module being installed is displayed
   - Completed modules are listed with status
   - Real-time percentage updates

5. **View Results**
   - Success message if all modules installed
   - Detailed results showing successful and failed modules
   - Error messages for any failures
   - Option to retry failed modules

### Installation Order

The installer automatically determines the correct installation order based on dependencies. For example:

```
Selected: Invoices, CRM, Payroll

Installation Order:
1. Employees (required by CRM and Payroll)
2. Clients (required by Invoices)
3. Catalog (required by Invoices)
4. Salary (required by Payroll)
5. CRM
6. Invoices
7. Payroll
```

You don't need to think about this - it's all automatic!

### What Happens During Installation

For each module:
1. Database tables are created
2. Default data is inserted (if any)
3. Permissions are set up
4. Module is marked as installed
5. Progress is updated

### After Installation

- **All Successful**: Click "Go to Dashboard" to start using your modules
- **Some Failed**: Review error messages, retry failed modules, or skip them
- **Access Modules**: All installed modules appear in your navigation menu

## Troubleshooting

### Common Issues

#### "Cannot deselect module" Warning
**Cause**: Another selected module depends on it  
**Solution**: Deselect the dependent modules first, or keep it selected

#### Module Installation Fails
**Possible Causes**:
- Database permissions insufficient
- Required tables already exist with different structure
- Database connection lost during installation

**Solutions**:
1. Check the error message for specific details
2. Use the "Retry Failed Modules" button
3. Verify database user has CREATE TABLE privileges
4. Check database connection is stable

#### "No Modules Available" Message
**Cause**: No setup scripts found in the system  
**Solution**: Verify your installation is complete and setup scripts exist in `/scripts/` directory

#### Module Installer Not Accessible
**Cause**: Insufficient permissions  
**Solution**: Ensure you're logged in as Super Admin or Admin role

### Getting Help

If you encounter issues:
1. Check the error message details
2. Review this guide's troubleshooting section
3. Check the main setup README
4. Verify your PHP and MySQL versions meet requirements
5. Contact your system administrator

## Accessibility

The Module Installer is designed to be fully accessible:

### Keyboard Navigation

- **Tab**: Move between interactive elements
- **Shift + Tab**: Move backwards
- **Space/Enter**: Toggle module selection (when focused on module card)
- **i**: Open module details (when focused on module card)
- **Escape**: Close modal dialogs

### Screen Reader Support

- All interactive elements have descriptive labels
- Progress updates are announced automatically
- Selection changes are announced
- Error messages are announced with high priority
- Modal dialogs are properly labeled

### Visual Accessibility

- High contrast mode support
- Focus indicators on all interactive elements
- Color is not the only indicator of status
- Text alternatives for all icons
- Responsive design for all screen sizes

### Motion Sensitivity

- Respects `prefers-reduced-motion` setting
- Animations can be disabled in browser settings
- All functionality works without animations

## FAQ

### Can I install modules later?
Yes! Click "Skip for Now" during setup, or access the installer anytime from Settings → Install Modules.

### What if I don't know which modules I need?
Start with Core modules (Employees, Clients). You can always install more modules later as your needs grow.

### Can I uninstall modules?
Currently, modules cannot be uninstalled through the interface. Contact your system administrator if you need to remove a module.

### Do I need to install all modules?
No! Only install the modules you need for your business. You can always add more later.

### What happens if installation is interrupted?
Successfully installed modules remain installed. You can retry failed or incomplete modules.

### Can multiple users install modules simultaneously?
No, only one installation process should run at a time. The installer is typically used during initial setup.

### How long does installation take?
Most modules install in 1-3 seconds. Installing all modules typically takes less than a minute.

### Will installing modules affect existing data?
No, module installation only creates new tables. Existing data is not modified.

### Can I see what tables will be created?
Yes! Click the "Details" button on any module to see the list of database tables.

### What if a module fails to install?
Review the error message, fix any issues (like database permissions), and use the "Retry Failed Modules" button.

### Do I need technical knowledge to use this?
No! The installer is designed for non-technical users. Just select the modules you need and click install.

### Can I change my selection after starting installation?
No, once installation starts, you cannot modify the selection. Wait for it to complete, then install additional modules if needed.

---

## Quick Reference Card

### Module Selection
✅ Click checkbox to select  
✅ Dependencies auto-select  
✅ View details with ℹ️ button  
✅ Selection count at bottom  

### Installation
1️⃣ Select modules  
2️⃣ Click "Install Selected Modules"  
3️⃣ Watch progress  
4️⃣ Review results  
5️⃣ Go to dashboard  

### Keyboard Shortcuts
- **Tab**: Navigate
- **Space/Enter**: Select module
- **i**: View details
- **Escape**: Close modal

### Need Help?
- Check error messages
- Use retry button for failures
- Access from Settings later
- Contact administrator

---

**Version**: 1.0  
**Last Updated**: December 2025  
**For**: Karyalay ERP Module Installer
