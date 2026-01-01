# Requirements Document

## Introduction

This document specifies the requirements for a unified module installation system for the Karyalay ERP. Currently, users must complete database setup, create a root user, and then install each module individually by visiting each module's page and clicking through separate onboarding flows. The new system will streamline this process by providing a single installation page where users can select and install multiple modules at once, immediately after the initial setup (database configuration, table creation, and admin account creation).

## Glossary

- **System**: The Karyalay ERP application
- **Module**: A functional component of the ERP (e.g., CRM, Invoices, Payroll, Assets)
- **Setup Wizard**: The initial 3-step configuration process (database, tables, admin account)
- **Module Installer**: The new unified interface for installing multiple modules
- **Setup Script**: A PHP script that creates database tables for a specific module
- **Admin User**: The first user created during initial setup with Super Admin role
- **Installation Status**: Whether a module's database tables exist and are ready for use

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want to see a module installation page immediately after completing the initial setup, so that I can install all required modules in one session.

#### Acceptance Criteria

1. WHEN the admin user completes the initial setup (Step 3: Create Admin Account) THEN the System SHALL redirect to the module installer page instead of the branding onboarding page
2. WHEN the module installer page loads THEN the System SHALL display all available modules with their descriptions and current installation status
3. WHEN a user accesses the module installer page THEN the System SHALL require authentication and Super Admin or Admin role
4. WHEN the module installer page is accessed and all modules are already installed THEN the System SHALL display a completion message with a link to the dashboard

### Requirement 2

**User Story:** As a system administrator, I want to select multiple modules for installation, so that I can install everything I need in one operation.

#### Acceptance Criteria

1. WHEN the module installer page displays the module list THEN the System SHALL show each module with a checkbox for selection
2. WHEN a module is already installed THEN the System SHALL display a visual indicator (checkmark, disabled state, or "Installed" badge) and disable the checkbox
3. WHEN a user selects one or more modules THEN the System SHALL enable the installation button
4. WHEN no modules are selected THEN the System SHALL disable the installation button
5. WHEN the module list is displayed THEN the System SHALL group modules by category (Core, Finance, HR, Operations, CRM)

### Requirement 3

**User Story:** As a system administrator, I want to see detailed information about each module before installing it, so that I can make informed decisions about which modules to install.

#### Acceptance Criteria

1. WHEN a module is displayed in the list THEN the System SHALL show the module name, icon, category, and a brief description
2. WHEN a user clicks on a module card or info icon THEN the System SHALL display detailed information including features, prerequisites, and dependencies
3. WHEN module details are shown THEN the System SHALL include information about what database tables will be created
4. WHEN a module has dependencies on other modules THEN the System SHALL display those dependencies clearly

### Requirement 4

**User Story:** As a system administrator, I want to install selected modules with a single action, so that I can complete the setup process efficiently.

#### Acceptance Criteria

1. WHEN a user clicks the "Install Selected Modules" button THEN the System SHALL execute the setup scripts for all selected modules
2. WHEN installation begins THEN the System SHALL display a progress indicator showing which module is currently being installed
3. WHEN a module installation completes successfully THEN the System SHALL update the progress indicator and proceed to the next module
4. WHEN all selected modules are installed successfully THEN the System SHALL display a success message with a summary of installed modules
5. WHEN installation completes THEN the System SHALL provide a button to proceed to the dashboard

### Requirement 5

**User Story:** As a system administrator, I want to see clear feedback if any module installation fails, so that I can troubleshoot and resolve issues.

#### Acceptance Criteria

1. WHEN a module installation fails THEN the System SHALL display an error message with the module name and failure reason
2. WHEN an installation error occurs THEN the System SHALL continue attempting to install remaining selected modules
3. WHEN installation completes with errors THEN the System SHALL display a summary showing which modules succeeded and which failed
4. WHEN installation errors occur THEN the System SHALL provide an option to retry failed installations
5. WHEN a module fails to install THEN the System SHALL log the error details for administrator review

### Requirement 6

**User Story:** As a system administrator, I want to access the module installer later if I skip it initially, so that I can install additional modules as needed.

#### Acceptance Criteria

1. WHEN the module installer page is first displayed THEN the System SHALL provide a "Skip for Now" button
2. WHEN a user clicks "Skip for Now" THEN the System SHALL redirect to the dashboard without installing any modules
3. WHEN a user navigates to the module installer URL directly THEN the System SHALL display the module installer page with current installation status
4. WHEN a user accesses an uninstalled module's page THEN the System SHALL display the existing onboarding page with a link to the unified module installer
5. WHEN the module installer is accessed from the dashboard or settings THEN the System SHALL show only uninstalled modules

### Requirement 7

**User Story:** As a system administrator, I want the installation process to handle module dependencies automatically, so that I don't have to manually determine the correct installation order.

#### Acceptance Criteria

1. WHEN a user selects a module with dependencies THEN the System SHALL automatically select the required dependency modules
2. WHEN a user attempts to deselect a module that other selected modules depend on THEN the System SHALL prevent deselection and display a warning message
3. WHEN modules are installed THEN the System SHALL install them in dependency order (dependencies first)
4. WHEN displaying module information THEN the System SHALL clearly indicate which modules are dependencies and which modules depend on the current module

### Requirement 8

**User Story:** As a system administrator, I want the module installer to integrate seamlessly with the existing setup wizard, so that the onboarding experience feels cohesive.

#### Acceptance Criteria

1. WHEN the setup wizard completes THEN the System SHALL maintain the same visual design and branding in the module installer
2. WHEN the module installer page loads THEN the System SHALL display progress indicators consistent with the setup wizard style
3. WHEN navigation occurs between setup wizard and module installer THEN the System SHALL maintain session state and authentication
4. WHEN the module installer completes THEN the System SHALL update any setup status flags to indicate full system initialization
