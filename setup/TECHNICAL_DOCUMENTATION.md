# Module Installer - Technical Documentation

## Overview

The Module Installer is a unified interface for installing multiple ERP modules with automatic dependency resolution, progress tracking, and error handling.

## Architecture

### Components

```
┌─────────────────────────────────────────────────────────┐
│                  Module Installer UI                     │
│              (setup/module_installer.php)                │
└─────────────────────────────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
        ▼                 ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│   Module     │  │  Dependency  │  │ Installation │
│  Discovery   │  │   Resolver   │  │    Engine    │
└──────────────┘  └──────────────┘  └──────────────┘
        │                 │                 │
        └─────────────────┼─────────────────┘
                          │
                          ▼
                  ┌──────────────┐
                  │ AJAX Handler │
                  └──────────────┘
```

### File Structure

```
setup/
├── module_installer.php          # Main UI page
├── ajax_install_modules.php      # Installation endpoint
├── ajax_installation_status.php  # Progress polling endpoint
├── skip_module_installer.php     # Skip handler
├── README.md                      # User documentation
├── MODULE_INSTALLER_GUIDE.md     # Comprehensive guide
├── QUICK_START.md                # Quick start guide
└── TECHNICAL_DOCUMENTATION.md    # This file

includes/
├── module_discovery.php          # Module scanning and metadata
├── module_categories.php         # Category definitions
├── dependency_resolver.php       # Dependency management
└── installation_engine.php       # Installation orchestration

config/
├── module_dependencies.php       # Dependency definitions
└── setup_helper.php              # Setup status tracking

scripts/
└── setup_*_tables.php            # Individual module setup scripts
```

## Core Components

### 1. Module Discovery (`includes/module_discovery.php`)

**Purpose**: Scan and identify available modules

**Key Functions**:
```php
discover_modules(mysqli $conn): array
// Returns array of all modules with metadata and installation status

get_module_metadata(string $module_name): array
// Returns display name, description, icon, category, tables

check_module_installed(mysqli $conn, string $module_name): bool
// Checks if module tables exist in database
```

**Module Metadata Structure**:
```php
[
    'name' => 'crm',
    'display_name' => 'CRM',
    'description' => 'Customer Relationship Management...',
    'icon' => '📇',
    'category' => 'CRM',
    'setup_script' => 'scripts/setup_crm_tables.php',
    'dependencies' => ['employees'],
    'installed' => false,
    'tables' => ['crm_tasks', 'crm_calls', ...]
]
```

### 2. Module Categories (`includes/module_categories.php`)

**Purpose**: Organize modules into logical groups

**Categories**:
- Core: Essential business modules
- Finance: Financial management
- HR: Human resources
- Operations: Business operations
- CRM: Customer relationship management
- Other: Utility modules

**Key Functions**:
```php
get_category_info(): array
// Returns category metadata (icon, description)

get_modules_by_category(array $modules): array
// Groups modules by category

get_category_for_module(string $module_name): string
// Returns category for a specific module
```

### 3. Dependency Resolver (`includes/dependency_resolver.php`)

**Purpose**: Manage module dependencies and installation order

**Key Functions**:
```php
load_module_dependencies(): array
// Loads dependency map from config

get_module_dependencies(string $module_name): array
// Returns direct dependencies for a module

get_dependent_modules(string $module_name): array
// Returns modules that depend on this module

resolve_installation_order(array $modules): array
// Returns topologically sorted installation order

validate_module_selection(array $modules): array
// Validates selection for missing dependencies
```

**Algorithm**: Kahn's topological sort
- Builds dependency graph
- Calculates in-degrees
- Processes nodes with zero in-degree
- Ensures dependencies install before dependents

### 4. Installation Engine (`includes/installation_engine.php`)

**Purpose**: Execute module installations

**Key Functions**:
```php
install_modules(array $modules, int $user_id): array
// Installs multiple modules in dependency order

execute_module_setup(string $module_name, int $user_id): array
// Executes individual module setup script

track_installation_progress(string $module, int $completed, int $total): void
// Updates session with progress information

get_installation_progress(): array
// Returns current installation progress
```

**Installation Flow**:
1. Resolve installation order
2. Initialize progress tracking
3. For each module:
   - Update progress
   - Execute setup script
   - Log result
   - Continue on error
4. Return results array

### 5. AJAX Handlers

#### Installation Handler (`setup/ajax_install_modules.php`)

**Endpoint**: POST `/setup/ajax_install_modules.php`

**Request**:
```json
{
    "modules": ["employees", "clients", "crm"],
    "csrf_token": "..."
}
```

**Response**:
```json
{
    "success": true,
    "results": [
        {
            "module": "employees",
            "success": true,
            "message": "Employees module installed successfully",
            "duration": 1.23
        },
        ...
    ]
}
```

**Security**:
- Authentication check
- Authorization check (Super Admin or Admin)
- CSRF token validation
- Input sanitization

#### Status Handler (`setup/ajax_installation_status.php`)

**Endpoint**: GET `/setup/ajax_installation_status.php`

**Response**:
```json
{
    "success": true,
    "in_progress": true,
    "current_module": "crm",
    "completed_count": 2,
    "total": 5,
    "percentage": 40,
    "completed": [
        {"module": "employees", "success": true},
        {"module": "clients", "success": true}
    ]
}
```

## Frontend Architecture

### JavaScript Components

#### State Management
```javascript
// Global state
const allModules = {...};           // Module metadata
const moduleDependencies = {...};   // Dependency map
const installedModules = [...];     // Already installed
let selectedModules = new Set();    // User selection
let failedModules = [];             // Failed installations
```

#### Event Handlers
- `handleModuleSelection()`: Checkbox change handler
- `autoSelectDependencies()`: Automatic dependency selection
- `hasSelectedDependents()`: Deselection validation
- `updateSelectionCount()`: UI update
- `showModuleDetails()`: Modal display
- `startInstallation()`: Installation trigger

#### Progress Tracking
- `showProgressModal()`: Display progress UI
- `startProgressPolling()`: Begin polling
- `pollInstallationProgress()`: Fetch updates
- `updateProgressDisplay()`: Update UI
- `handleInstallationComplete()`: Show results

#### Accessibility
- `announceToScreenReader()`: Screen reader announcements
- `trapFocusInModal()`: Modal focus management
- `handleCardKeydown()`: Keyboard navigation
- `handleGlobalKeydown()`: Global shortcuts

### CSS Architecture

#### Design System
- **Colors**: Blue gradient (#003581 to #004aad)
- **Typography**: Segoe UI, sans-serif
- **Spacing**: 4px base unit
- **Border Radius**: 6-16px
- **Shadows**: Layered elevation

#### Responsive Breakpoints
- Desktop: > 1024px
- Tablet: 768px - 1024px
- Mobile: < 768px
- Small Mobile: < 480px

#### Animations
- Fade in: 0.4-0.6s ease-out
- Slide in: 0.3s ease-out
- Hover: 0.3s ease
- Progress: 0.5s ease-out

#### Accessibility Features
- Focus indicators
- High contrast support
- Reduced motion support
- Screen reader only content

## Database Schema

### Module Installation Tracking

Modules are considered "installed" when their database tables exist. No separate installation tracking table is used.

**Detection Method**:
```php
// Check if all module tables exist
foreach ($module['tables'] as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$result || $result->num_rows === 0) {
        return false; // Not installed
    }
}
return true; // Installed
```

### Setup Status Tracking

Stored in session:
```php
$_SESSION['module_installer_complete'] = true;
$_SESSION['installation_progress'] = [
    'in_progress' => true,
    'current_module' => 'crm',
    'completed_count' => 2,
    'total' => 5,
    'completed' => [...]
];
```

## Security

### Authentication & Authorization

```php
// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Authorization check
authz_refresh_context($conn);
$has_access = authz_has_role('Super Admin') || authz_has_role('Admin');
if (!$has_access) {
    header('Location: /public/unauthorized.php');
    exit;
}
```

### CSRF Protection

```php
// Generate token
function ensure_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate token
function validate_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}
```

### Input Validation

```php
// Whitelist validation
$valid_modules = array_keys(discover_modules($conn));
$selected_modules = array_filter($input_modules, function($module) use ($valid_modules) {
    return in_array($module, $valid_modules);
});
```

### SQL Injection Prevention

All database queries use prepared statements or proper escaping:
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
```

## Error Handling

### Error Categories

1. **Prerequisite Errors**: Missing dependencies
2. **Database Errors**: Connection, permission, or query failures
3. **Permission Errors**: Insufficient user permissions
4. **Script Errors**: Setup script not found or execution failure

### Error Response Format

```php
[
    'success' => false,
    'module' => 'invoices',
    'error_code' => 'PREREQUISITE_MISSING',
    'message' => 'Required table items_master not found...',
    'details' => ['missing_tables' => ['items_master']]
]
```

### Error Handling Strategy

- **Graceful Degradation**: Continue with remaining modules on error
- **Detailed Logging**: Record all errors with context
- **User Feedback**: Display clear, actionable error messages
- **Retry Mechanism**: Allow retry of failed installations
- **No Rollback**: Successful modules remain installed

## Testing

### Property-Based Tests

Located in `tests/` directory:
- `module_discovery_property_test.php`
- `dependency_resolver_property_test.php`
- `installation_engine_property_test.php`
- `ajax_access_control_property_test.php`
- `module_rendering_property_test.php`
- And more...

### Running Tests

```bash
cd tests
php run_property_tests.sh
```

### Test Coverage

- Module discovery completeness
- Access control enforcement
- Module rendering consistency
- Selection state management
- Category grouping correctness
- Dependency information display
- Automatic dependency selection
- Dependency deselection prevention
- Topological installation order
- Installation execution completeness
- Progress tracking accuracy
- Error isolation
- Error logging completeness
- Retry functionality
- Session persistence
- Setup completion state

## Performance Considerations

### Optimization Strategies

1. **Lazy Loading**: Module metadata loaded on-demand
2. **Caching**: Installation status cached in session
3. **Async Operations**: AJAX for long-running installations
4. **Batch Processing**: Multiple modules in single request
5. **Progress Feedback**: Real-time updates prevent timeout perception

### Polling Strategy

- Interval: 500ms
- Timeout: None (continues until completion)
- Fallback: Installation completes even if polling fails

### Database Optimization

- Prepared statements for repeated queries
- Transactions for atomic operations
- Index usage for table existence checks
- Connection reuse across operations

## Extending the System

### Adding a New Module

1. **Create Setup Script**: `scripts/setup_newmodule_tables.php`
2. **Define Metadata**: Add to `module_discovery.php`
3. **Add Dependencies**: Update `config/module_dependencies.php`
4. **Assign Category**: Update `module_categories.php`
5. **Test**: Verify installation through UI

### Adding a New Category

1. Update `includes/module_categories.php`:
```php
'NewCategory' => [
    'icon' => '🆕',
    'description' => 'Description of category'
]
```

2. Assign modules to category in metadata

### Custom Installation Logic

Extend `installation_engine.php`:
```php
function execute_module_setup_with_hooks(string $module, int $user_id): array {
    // Pre-installation hook
    do_action('before_module_install', $module);
    
    // Standard installation
    $result = execute_module_setup($module, $user_id);
    
    // Post-installation hook
    do_action('after_module_install', $module, $result);
    
    return $result;
}
```

## API Reference

### Module Discovery API

```php
// Get all modules
$modules = discover_modules($conn);

// Get module metadata
$metadata = get_module_metadata('crm');

// Check installation status
$installed = check_module_installed($conn, 'crm');
```

### Dependency Resolution API

```php
// Load dependencies
$deps = load_module_dependencies();

// Get dependencies for module
$required = get_module_dependencies('invoices');
// Returns: ['clients', 'catalog']

// Get dependent modules
$dependents = get_dependent_modules('clients');
// Returns: ['invoices', 'quotations', 'projects', ...]

// Resolve installation order
$order = resolve_installation_order(['invoices', 'crm', 'payroll']);
// Returns: ['employees', 'clients', 'catalog', 'salary', 'crm', 'invoices', 'payroll']
```

### Installation API

```php
// Install modules
$results = install_modules(['employees', 'clients'], $user_id);

// Get progress
$progress = get_installation_progress();

// Track progress
track_installation_progress('crm', 3, 5);
```

## Troubleshooting

### Common Development Issues

#### Module Not Appearing
- Check setup script exists in `scripts/`
- Verify metadata in `module_discovery.php`
- Ensure proper naming convention

#### Dependency Not Working
- Verify dependency defined in `config/module_dependencies.php`
- Check module names match exactly
- Test dependency resolution function

#### Installation Fails
- Check database permissions
- Verify setup script syntax
- Review error logs
- Test script directly

#### Progress Not Updating
- Check session is started
- Verify polling endpoint accessible
- Review browser console for errors
- Check AJAX request/response

## Best Practices

### For Developers

1. **Follow Naming Conventions**: Use consistent module names
2. **Document Dependencies**: Clearly define in config
3. **Handle Errors Gracefully**: Return structured error responses
4. **Test Thoroughly**: Use property-based tests
5. **Maintain Backward Compatibility**: Don't break existing modules

### For System Administrators

1. **Backup Before Installation**: Always backup database
2. **Test in Staging**: Test module installation in non-production
3. **Monitor Logs**: Check for errors during installation
4. **Verify Permissions**: Ensure database user has required privileges
5. **Document Custom Modules**: Keep records of custom installations

## Changelog

### Version 1.0 (December 2025)
- Initial release
- Unified module installation interface
- Automatic dependency resolution
- Progress tracking and error handling
- Full accessibility support
- Comprehensive documentation

---

**For Questions or Issues**: Contact the development team or refer to the user guides.
