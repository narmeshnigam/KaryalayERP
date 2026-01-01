# Karyalay ERP - First-Time Setup Guide

## Overview

When you install Karyalay ERP for the first time, the system will automatically guide you through a 4-step setup wizard to configure your installation and install the modules you need.

## Setup Workflow

### Step 1: Database Configuration
- Enter your MySQL database credentials
  - **Host**: Usually `localhost` for local installations
  - **Database Name**: The database will be created if it doesn't exist (default: `karyalay_db`)
  - **Username**: Your MySQL username (default: `root` for XAMPP)
  - **Password**: Your MySQL password (leave empty for XAMPP default)
- The system will test the connection and create the database if needed
- Database credentials are saved to `config/config.php`

### Step 2: Create Tables
- Creates the `users` table and core RBAC tables required for authentication
- This is a one-click process
- The table structure includes:
  - User credentials (username, password)
  - User information (full name, email)
  - Role-based access control (Super Admin, Admin, Manager, Employee)
  - Account status (active/inactive)
  - Permissions and role assignments

### Step 3: Create Admin Account
- Set up your first administrator account
- Required fields:
  - **Username** (minimum 4 characters)
  - **Full Name**
  - **Password** (minimum 6 characters, strong password recommended)
- Optional fields:
  - **Email Address** (recommended for password recovery)
- After creating the admin account, you'll be automatically logged in
- The account is assigned the "Super Admin" role with full system access

### Step 4: Module Installer (NEW!)
- **Unified module installation interface** - Install multiple modules at once
- Select the modules you need for your business
- Features:
  - **Automatic Dependency Resolution**: Dependencies are automatically selected
  - **Category Organization**: Modules grouped by Core, Finance, HR, Operations, CRM, and Other
  - **Detailed Information**: View module features, dependencies, and database tables
  - **Batch Installation**: Install multiple modules in one operation
  - **Progress Tracking**: Real-time progress updates during installation
  - **Error Handling**: Clear error messages with retry options
- You can skip this step and install modules later from the dashboard
- See the [Module Installer Guide](#module-installer-guide) below for detailed instructions

## Accessing the Setup Wizard

1. Navigate to your installation URL (e.g., `http://localhost/KaryalayERP`)
2. You'll be automatically redirected to the setup wizard if setup is incomplete
3. Follow the on-screen instructions for each step

## Module Installer Guide

### Overview
The Module Installer is a unified interface for installing multiple ERP modules at once. It replaces the old method of visiting each module's page individually.

### Available Modules

#### Core Modules
- **Employees**: Employee management and records
- **Clients**: Client/customer database
- **Users**: User account management
- **Branding**: Company branding and customization

#### Finance Modules
- **Invoices**: Invoice creation and management
- **Quotations**: Quote generation
- **Payments**: Payment tracking and allocation
- **Catalog**: Product/service catalog with inventory

#### HR Modules
- **Attendance**: Employee attendance tracking
- **Salary**: Salary structure management
- **Payroll**: Payroll processing
- **Reimbursements**: Expense reimbursement requests

#### Operations Modules
- **Projects**: Project management
- **Work Orders**: Work order tracking
- **Deliverables**: Deliverable management
- **Delivery**: Delivery tracking

#### CRM Modules
- **CRM**: Customer relationship management (leads, tasks, calls, meetings, visits)
- **Contacts**: Contact management

#### Other Modules
- **Documents**: Document storage and management
- **Visitors**: Visitor log management
- **Expenses**: Office expense tracking
- **Data Transfer**: Import/export functionality
- **Notebook**: Note-taking and collaboration
- **Assets**: Asset management

### Using the Module Installer

#### 1. Selecting Modules
- Browse modules organized by category
- Click the checkbox on any module card to select it for installation
- **Automatic Dependencies**: When you select a module, any required dependencies are automatically selected
- **Deselection Protection**: You cannot deselect a module if other selected modules depend on it

#### 2. Viewing Module Details
- Click the "ℹ️ Details" button on any module card
- View comprehensive information:
  - Module description and features
  - Required dependencies
  - Modules that depend on this one
  - Database tables that will be created
  - Installation status

#### 3. Installing Modules
- Select one or more modules
- Click "Install Selected Modules" button
- Watch real-time progress as modules are installed
- Installation order is automatically determined based on dependencies

#### 4. Installation Results
- **All Successful**: See a success message and proceed to dashboard
- **Some Failed**: View detailed results showing which modules succeeded and which failed
- **Retry Option**: Retry failed modules without reinstalling successful ones

### Keyboard Shortcuts
- **Tab**: Navigate between elements
- **Space/Enter**: Toggle module selection (when focused on a module card)
- **i**: Open module details (when focused on a module card)
- **Escape**: Close modal dialogs

### Accessibility Features
- Full keyboard navigation support
- Screen reader announcements for all actions
- ARIA labels and roles for assistive technologies
- High contrast mode support
- Reduced motion support for users with motion sensitivity

### Accessing Later
If you skip the module installer during setup:
1. Go to **Settings** in the main navigation
2. Click **"Install Modules"** link
3. Only uninstalled modules will be shown

### Module Dependencies

Some modules require other modules to be installed first:

- **Invoices** requires: Clients, Catalog
- **Quotations** requires: Clients, Catalog
- **Payments** requires: Clients
- **CRM** requires: Employees
- **Projects** requires: Clients
- **Work Orders** requires: Clients
- **Deliverables** requires: Clients
- **Delivery** requires: Clients
- **Payroll** requires: Employees, Salary

The installer handles these dependencies automatically - you don't need to worry about installation order!

## What Happens Next?

Once setup is complete:
- You can log in with your admin credentials
- Access all installed modules from the dashboard
- Install additional modules anytime from Settings
- Configure system settings and add users
- Set up role-based permissions for your team

## Technical Details

### Files Created/Modified During Setup
- **config/config.php** - Updated with database credentials
- **Database** - Created if it doesn't exist
- **users table** - Created with the first admin user

### Setup Detection
The system automatically detects if setup is needed by checking:
1. Database connectivity
2. Database existence
3. Users table existence
4. At least one admin user exists
5. Module installer completion status (optional)

### Resetting Setup
To run the setup wizard again:
1. Delete the `users` table from your database
2. Or drop the entire database
3. Clear your browser cookies/session
4. Navigate to the root URL

## Security Notes

- The setup wizard is only accessible when setup is incomplete
- Once an admin user exists, the setup wizard redirects to the login page
- Always use a strong password for the admin account
- Change default credentials if you're migrating from a previous installation

## Troubleshooting

### Cannot Connect to Database
- Verify MySQL service is running
- Check database credentials are correct
- Ensure the database user has CREATE DATABASE privileges

### Setup Keeps Redirecting
- Clear browser cookies and cache
- Check if the users table exists and has an admin user
- Verify file permissions on `config/config.php`

### Configuration File Not Writable
- Ensure `config/config.php` has write permissions
- On Linux/Mac: `chmod 644 config/config.php`
- On Windows: Check file properties → Security

### Module Installation Fails
- **Check Prerequisites**: Ensure all dependency modules are installed
- **Database Permissions**: Verify the database user has CREATE TABLE privileges
- **View Error Details**: The installer shows specific error messages for each failed module
- **Retry Installation**: Use the "Retry Failed Modules" button to try again
- **Manual Installation**: You can still access individual module onboarding pages as a fallback

### Module Installer Not Showing
- Verify you're logged in as Super Admin or Admin
- Check that you haven't already completed the module installer
- Try accessing directly: `http://your-domain/setup/module_installer.php`

### Cannot Deselect a Module
- This is by design - the module is required by another selected module
- Deselect the dependent modules first
- The warning message will tell you which modules depend on it

## Support

For issues or questions:
- Check the main README.md file
- Review the setup wizard error messages
- Verify your PHP and MySQL versions meet requirements
