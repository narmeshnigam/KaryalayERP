# Design Document

## Overview

The Unified Module Installer provides a streamlined onboarding experience for the Karyalay ERP system. Instead of requiring administrators to manually visit each module's page and install them one by one, this feature presents a single, comprehensive interface where multiple modules can be selected and installed in a single operation. The installer integrates seamlessly with the existing 3-step setup wizard (database configuration, table creation, admin account creation) and becomes the fourth step in the onboarding flow.

The design leverages the existing module setup scripts (`scripts/setup_*_tables.php`) and dependency management system (`config/module_dependencies.php`) to provide intelligent module installation with automatic dependency resolution.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Setup Wizard Flow                         │
│  Step 1: Database → Step 2: Tables → Step 3: Admin →        │
│  Step 4: Module Installer (NEW)                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              Module Installer Page                           │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Module Discovery & Status Check                     │   │
│  │  - Scan available setup scripts                      │   │
│  │  - Check installation status (table existence)       │   │
│  │  - Load dependency information                       │   │
│  └─────────────────────────────────────────────────────┘   │
│                              │                               │
│                              ▼                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Module Selection UI                                 │   │
│  │  - Categorized module cards                          │   │
│  │  - Dependency visualization                          │   │
│  │  - Installation status indicators                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                              │                               │
│                              ▼                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Installation Engine                                 │   │
│  │  - Dependency resolution                             │   │
│  │  - Sequential script execution                       │   │
│  │  - Progress tracking                                 │   │
│  │  - Error handling & rollback                         │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Component Interaction

The system consists of three main layers:

1. **Presentation Layer**: The module installer UI that displays available modules, handles user selection, and shows installation progress
2. **Business Logic Layer**: Module discovery, dependency resolution, and installation orchestration
3. **Data Layer**: Interaction with setup scripts and database table verification

## Components and Interfaces

### 1. Module Installer Page (`setup/module_installer.php`)

Main entry point for the unified module installation interface.

**Responsibilities:**
- Display available modules grouped by category
- Show installation status for each module
- Handle module selection and deselection
- Trigger installation process
- Display progress and results

**Key Functions:**
```php
function get_available_modules(): array
// Returns array of module definitions with metadata

function get_module_installation_status(mysqli $conn, string $module_name): bool
// Checks if module tables exist

function get_module_categories(): array
// Returns module groupings (Core, Finance, HR, Operations, CRM)
```

### 2. Module Discovery Service (`includes/module_discovery.php`)

Scans the system to identify available modules and their metadata.

**Responsibilities:**
- Scan `scripts/` directory for setup scripts
- Extract module metadata (name, description, icon, category)
- Determine installation status
- Load dependency information

**Key Functions:**
```php
function discover_modules(mysqli $conn): array
// Scans and returns all available modules with status

function get_module_metadata(string $module_name): array
// Returns module display name, description, icon, category

function check_module_installed(mysqli $conn, string $module_name): bool
// Verifies if module tables exist
```

### 3. Dependency Resolver (`includes/dependency_resolver.php`)

Handles module dependency analysis and installation ordering.

**Responsibilities:**
- Parse module dependencies from `config/module_dependencies.php`
- Resolve dependency chains
- Determine installation order (topological sort)
- Validate selection for missing dependencies

**Key Functions:**
```php
function resolve_dependencies(array $selected_modules): array
// Returns ordered list of modules to install (dependencies first)

function get_module_dependencies(string $module_name): array
// Returns direct dependencies for a module

function validate_selection(array $selected_modules): array
// Returns validation result with missing dependencies
```

### 4. Installation Engine (`includes/installation_engine.php`)

Executes module installations by invoking setup scripts.

**Responsibilities:**
- Execute setup scripts in dependency order
- Track installation progress
- Handle errors and continue with remaining modules
- Log installation results

**Key Functions:**
```php
function install_modules(array $modules, int $user_id): array
// Installs modules and returns results

function execute_setup_script(string $module_name, int $user_id): array
// Runs individual module setup script

function log_installation(string $module_name, bool $success, string $message): void
// Records installation attempt
```

### 5. AJAX Installation Handler (`setup/ajax_install_modules.php`)

Handles asynchronous installation requests from the frontend.

**Responsibilities:**
- Receive installation requests via POST
- Validate user permissions
- Invoke installation engine
- Return JSON progress updates

**API Endpoint:**
```
POST /setup/ajax_install_modules.php
Request: { "modules": ["employees", "clients", "crm"] }
Response: {
  "success": true,
  "results": [
    {"module": "employees", "success": true, "message": "..."},
    {"module": "clients", "success": true, "message": "..."},
    {"module": "crm", "success": true, "message": "..."}
  ]
}
```

## Data Models

### Module Definition

```php
[
    'name' => 'crm',                    // Internal identifier
    'display_name' => 'CRM',            // User-facing name
    'description' => 'Customer Relationship Management...',
    'icon' => '📇',                     // Emoji or icon class
    'category' => 'Operations',         // Core, Finance, HR, Operations, CRM
    'setup_script' => 'scripts/setup_crm_tables.php',
    'dependencies' => ['employees'],    // Required modules
    'installed' => false,               // Installation status
    'tables' => ['crm_tasks', 'crm_calls', 'crm_meetings', 'crm_visits', 'crm_leads']
]
```

### Module Categories

```php
[
    'Core' => ['employees', 'clients', 'users', 'branding'],
    'Finance' => ['invoices', 'quotations', 'payments', 'catalog'],
    'HR' => ['attendance', 'salary', 'payroll', 'reimbursements'],
    'Operations' => ['projects', 'workorders', 'deliverables', 'delivery'],
    'CRM' => ['crm', 'contacts'],
    'Other' => ['documents', 'visitors', 'expenses', 'data-transfer', 'notebook', 'assets']
]
```

### Installation Result

```php
[
    'module' => 'crm',
    'success' => true,
    'message' => 'CRM tables created successfully.',
    'duration' => 1.23,  // seconds
    'timestamp' => '2025-12-06 10:30:45'
]
```

## Error Handling

### Error Categories

1. **Prerequisite Errors**: Missing dependencies
2. **Database Errors**: Connection failures, table creation errors
3. **Permission Errors**: Insufficient user permissions
4. **Script Errors**: Setup script not found or execution failure

### Error Handling Strategy

- **Graceful Degradation**: If one module fails, continue with remaining modules
- **Detailed Logging**: Record all errors with context for troubleshooting
- **User Feedback**: Display clear error messages with actionable guidance
- **Retry Mechanism**: Allow users to retry failed installations
- **Rollback**: Individual module failures don't affect successfully installed modules

### Error Response Format

```php
[
    'success' => false,
    'module' => 'invoices',
    'error_code' => 'PREREQUISITE_MISSING',
    'message' => 'Required table items_master not found. Please install Catalog module first.',
    'details' => ['missing_tables' => ['items_master', 'clients']]
]
```

## Testing Strategy

The testing strategy combines unit tests for individual components and property-based tests for universal correctness properties.

### Unit Testing

Unit tests will verify specific behaviors and edge cases:

- Module discovery correctly identifies all setup scripts
- Dependency resolver handles circular dependencies
- Installation engine executes scripts in correct order
- AJAX handler validates user permissions
- Error messages are clear and actionable

### Property-Based Testing

Property-based tests will verify universal properties across all inputs using a PHP property testing library (e.g., Eris or php-quickcheck). Each test should run a minimum of 100 iterations.

The following sections detail the correctness properties that must be tested.


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Module Discovery Completeness
*For any* system state, when the module installer loads, all available modules (those with setup scripts in the scripts/ directory) should be displayed with their current installation status, name, icon, category, and description.
**Validates: Requirements 1.2, 6.3**

### Property 2: Access Control Enforcement
*For any* user attempting to access the module installer page, access should be granted if and only if the user is authenticated and has Super Admin or Admin role.
**Validates: Requirements 1.3**

### Property 3: Module Rendering Consistency
*For any* module in the system, when displayed in the module list, it should have a checkbox (disabled if installed), and show all required information (name, icon, category, description, installation status indicator).
**Validates: Requirements 2.1, 2.2, 3.1**

### Property 4: Selection State Management
*For any* selection state, the installation button should be enabled if and only if at least one uninstalled module is selected.
**Validates: Requirements 2.3, 2.4**

### Property 5: Category Grouping Correctness
*For any* set of modules, when displayed, each module should appear exactly once under its designated category, and all categories should be displayed in the correct order.
**Validates: Requirements 2.5**

### Property 6: Dependency Information Display
*For any* module with dependencies, when its details are displayed, all dependency relationships (both modules it depends on and modules that depend on it) should be clearly shown, including table information.
**Validates: Requirements 3.2, 3.3, 3.4, 7.4**

### Property 7: Automatic Dependency Selection
*For any* module selection, if the selected module has dependencies, all required dependency modules should be automatically selected (if not already installed).
**Validates: Requirements 7.1**

### Property 8: Dependency Deselection Prevention
*For any* attempt to deselect a module, if other selected modules depend on it, the deselection should be prevented and a warning message should be displayed.
**Validates: Requirements 7.2**

### Property 9: Topological Installation Order
*For any* set of selected modules, the installation order should respect dependency relationships such that no module is installed before its dependencies (topological sort property).
**Validates: Requirements 7.3**

### Property 10: Installation Execution Completeness
*For any* set of selected modules, when installation is triggered, the setup script for each module should be executed exactly once.
**Validates: Requirements 4.1**

### Property 11: Progress Tracking Accuracy
*For any* installation in progress, the progress indicator should accurately reflect which module is currently being installed and update as each module completes.
**Validates: Requirements 4.2, 4.3**

### Property 12: Installation Completion Summary
*For any* completed installation (successful or with errors), a summary should be displayed showing all attempted modules with their success/failure status and relevant messages.
**Validates: Requirements 4.4, 5.3**

### Property 13: Error Isolation
*For any* module installation failure, the installation process should continue with remaining modules, and the failure should not affect previously successful installations.
**Validates: Requirements 5.2**

### Property 14: Error Logging Completeness
*For any* module installation failure, error details (module name, error message, timestamp) should be logged to the system.
**Validates: Requirements 5.5**

### Property 15: Retry Functionality Availability
*For any* installation that completes with one or more failures, a retry option should be provided for each failed module.
**Validates: Requirements 5.4**

### Property 16: Uninstalled Module Filtering
*For any* system state when the module installer is accessed from dashboard or settings (not initial setup), only modules that are not yet installed should be displayed for selection.
**Validates: Requirements 6.5**

### Property 17: Fallback Onboarding Consistency
*For any* uninstalled module, when a user attempts to access its main page, the system should display the module's onboarding page with a link to the unified module installer.
**Validates: Requirements 6.4**

### Property 18: Session Persistence
*For any* navigation between setup wizard steps and the module installer, user session state and authentication should be maintained without requiring re-login.
**Validates: Requirements 8.3**

### Property 19: Setup Completion State Update
*For any* successful completion of the module installer (whether modules were installed or skipped), the system setup status flags should be updated to indicate initialization is complete.
**Validates: Requirements 8.4**

## Implementation Notes

### Integration with Existing Setup Scripts

The module installer will not replace existing setup scripts but rather orchestrate their execution. Each setup script in `scripts/setup_*_tables.php` will be refactored to expose a callable function that can be invoked programmatically:

```php
// Before: Script executes on page load
// After: Script provides a function that can be called

function setup_crm_module(mysqli $conn, int $user_id): array {
    // Setup logic here
    return ['success' => true, 'message' => '...'];
}

// Script can still be accessed directly for backward compatibility
if (basename($_SERVER['PHP_SELF']) === 'setup_crm_tables.php') {
    // Display UI and handle form submission
}
```

### Dependency Resolution Algorithm

The system will use Kahn's algorithm for topological sorting to determine installation order:

1. Build a dependency graph from `config/module_dependencies.php`
2. Calculate in-degree (number of dependencies) for each module
3. Start with modules that have zero dependencies
4. As each module is installed, decrement in-degree of dependent modules
5. Continue until all modules are processed

### Progress Tracking Implementation

Installation progress will be tracked using AJAX polling:

1. Frontend initiates installation via AJAX POST
2. Backend starts installation in a tracked session
3. Frontend polls a status endpoint every 500ms
4. Backend returns current module being installed and completion percentage
5. Frontend updates UI with progress information

### Error Recovery Strategy

- Each module installation is wrapped in a try-catch block
- Errors are logged but don't halt the installation process
- Successfully installed modules are marked as complete
- Failed modules can be retried individually
- Database transactions are used within each module's setup to ensure atomicity

## Security Considerations

1. **Authentication**: Module installer requires active session with admin privileges
2. **Authorization**: Only Super Admin and Admin roles can access the installer
3. **CSRF Protection**: Installation requests include CSRF tokens
4. **Input Validation**: Module names are validated against whitelist of known modules
5. **SQL Injection**: All database queries use prepared statements
6. **Path Traversal**: Setup script paths are validated to prevent directory traversal attacks

## Performance Considerations

1. **Lazy Loading**: Module metadata is loaded on-demand rather than all at once
2. **Caching**: Installation status is cached to avoid repeated database queries
3. **Async Installation**: Long-running installations use AJAX to prevent timeouts
4. **Batch Operations**: Multiple table creations within a module use transactions
5. **Progress Feedback**: Real-time progress updates keep users informed during long operations

## Accessibility Considerations

1. **Keyboard Navigation**: All interactive elements are keyboard accessible
2. **Screen Reader Support**: ARIA labels and roles for dynamic content
3. **Focus Management**: Focus is managed appropriately during installation
4. **Status Announcements**: Progress updates are announced to screen readers
5. **Color Independence**: Status indicators don't rely solely on color

## Future Enhancements

1. **Module Marketplace**: Integration with external module repository
2. **Version Management**: Support for module updates and version tracking
3. **Rollback Capability**: Ability to uninstall modules and rollback changes
4. **Bulk Operations**: Export/import module configurations
5. **Installation Profiles**: Pre-defined module sets for different use cases (e.g., "Manufacturing", "Services", "Retail")
