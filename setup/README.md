# Karyalay ERP - First-Time Setup Guide

## Overview

When you install Karyalay ERP for the first time, the system will automatically guide you through a 3-step setup wizard to configure your installation.

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
- Creates the `users` table required for authentication
- This is a one-click process
- The table structure includes:
  - User credentials (username, password)
  - User information (full name, email)
  - Role-based access control (admin, manager, employee)
  - Account status (active/inactive)

### Step 3: Create Admin Account
- Set up your first administrator account
- Required fields:
  - **Username** (minimum 4 characters)
  - **Full Name**
  - **Password** (minimum 6 characters, strong password recommended)
- Optional fields:
  - **Email Address** (recommended for password recovery)
- After creating the admin account, you'll be automatically logged in

### Step 4: Branding Setup (Post-Setup)
- After completing the initial setup, you'll be redirected to the branding module
- Configure your organization's visual identity
- All other modules can be installed after login

## Accessing the Setup Wizard

1. Navigate to your installation URL (e.g., `http://localhost/KaryalayERP`)
2. You'll be automatically redirected to the setup wizard if setup is incomplete
3. Follow the on-screen instructions for each step

## What Happens Next?

Once setup is complete:
- You can log in with your admin credentials
- Install additional modules (Employees, Salary, Attendance, CRM, etc.)
- Each module will create its own database tables when first accessed
- Configure system settings and add users

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

## Support

For issues or questions:
- Check the main README.md file
- Review the setup wizard error messages
- Verify your PHP and MySQL versions meet requirements
