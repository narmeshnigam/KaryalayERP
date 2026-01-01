# Implementation Plan

- [x] 1. Create module discovery and metadata system
  - [x] 1.1 Create `includes/module_discovery.php` with module scanning functionality
    - Implement function to scan `scripts/` directory for setup scripts
    - Implement function to extract module metadata (name, description, icon, category)
    - Implement function to check module installation status by verifying table existence
    - Create module metadata registry with all available modules
    - _Requirements: 1.2, 6.3_

  - [x] 1.2 Write property test for module discovery
    - **Property 1: Module Discovery Completeness**
    - **Validates: Requirements 1.2, 6.3**

  - [x] 1.3 Create `includes/module_categories.php` with category definitions
    - Define module categories (Core, Finance, HR, Operations, CRM, Other)
    - Implement function to get modules by category
    - Implement function to get category for a module
    - _Requirements: 2.5_

  - [x] 1.4 Write property test for category grouping
    - **Property 5: Category Grouping Correctness**
    - **Validates: Requirements 2.5**

- [x] 2. Implement dependency resolution system
  - [x] 2.1 Create `includes/dependency_resolver.php` with dependency management
    - Implement function to load dependencies from `config/module_dependencies.php`
    - Implement function to get direct dependencies for a module
    - Implement function to get all modules that depend on a given module
    - Implement topological sort algorithm (Kahn's algorithm) for installation ordering
    - Implement function to validate module selection for missing dependencies
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [x] 2.2 Write property test for automatic dependency selection
    - **Property 7: Automatic Dependency Selection**
    - **Validates: Requirements 7.1**

  - [x] 2.3 Write property test for dependency deselection prevention
    - **Property 8: Dependency Deselection Prevention**
    - **Validates: Requirements 7.2**

  - [x] 2.4 Write property test for topological installation order
    - **Property 9: Topological Installation Order**
    - **Validates: Requirements 7.3**

- [ ] 3. Refactor existing setup scripts to be callable
  - [x] 3.1 Update setup scripts to expose callable functions
    - Refactor `scripts/setup_employees_table.php` to expose `setup_employees_module()`
    - Refactor `scripts/setup_clients_tables.php` to expose `setup_clients_module()`
    - Refactor `scripts/setup_crm_tables.php` to expose `setup_crm_module()`
    - Refactor `scripts/setup_invoices_tables.php` to expose `setup_invoices_module()`
    - Refactor `scripts/setup_catalog_tables.php` to expose `setup_catalog_module()`
    - Refactor remaining setup scripts following the same pattern
    - Ensure backward compatibility (scripts still work when accessed directly)
    - _Requirements: 4.1_

  - [x] 3.2 Write unit tests for refactored setup functions
    - Test that each setup function returns proper result format
    - Test that setup functions handle database errors gracefully
    - Test that setup functions check prerequisites correctly
    - _Requirements: 4.1, 5.1_

- [x] 4. Create installation engine
  - [x] 4.1 Create `includes/installation_engine.php` with installation orchestration
    - Implement function to execute module setup by calling refactored setup functions
    - Implement function to install multiple modules in dependency order
    - Implement error handling that continues with remaining modules on failure
    - Implement installation logging functionality
    - Implement progress tracking mechanism
    - _Requirements: 4.1, 4.2, 4.3, 5.2, 5.5_

  - [x] 4.2 Write property test for installation execution completeness
    - **Property 10: Installation Execution Completeness**
    - **Validates: Requirements 4.1**

  - [x] 4.3 Write property test for error isolation
    - **Property 13: Error Isolation**
    - **Validates: Requirements 5.2**

  - [x] 4.4 Write property test for error logging completeness
    - **Property 14: Error Logging Completeness**
    - **Validates: Requirements 5.5**

- [x] 5. Create AJAX installation handler
  - [x] 5.1 Create `setup/ajax_install_modules.php` for asynchronous installation
    - Implement POST endpoint to receive module installation requests
    - Validate user authentication and authorization (Super Admin or Admin only)
    - Implement CSRF token validation
    - Invoke installation engine with selected modules
    - Return JSON response with installation results
    - _Requirements: 4.1, 5.1, 5.3_

  - [x] 5.2 Create `setup/ajax_installation_status.php` for progress polling
    - Implement GET endpoint to check installation progress
    - Return current module being installed and completion percentage
    - Return list of completed modules with success/failure status
    - _Requirements: 4.2, 4.3_

  - [x] 5.3 Write property test for access control enforcement
    - **Property 2: Access Control Enforcement**
    - **Validates: Requirements 1.3**

- [x] 6. Build module installer UI page
  - [x] 6.1 Create `setup/module_installer.php` main page
    - Implement authentication check (redirect to login if not authenticated)
    - Implement authorization check (require Super Admin or Admin role)
    - Load available modules using module discovery service
    - Check installation status for each module
    - Render module installer interface with setup wizard styling
    - Include "Skip for Now" button that redirects to dashboard
    - _Requirements: 1.1, 1.3, 6.1, 6.2_

  - [x] 6.2 Implement module card rendering
    - Create HTML/CSS for module cards grouped by category
    - Display module icon, name, description, and category
    - Show installation status indicator (checkmark for installed modules)
    - Add checkbox for module selection (disabled for installed modules)
    - Add info icon/button to show detailed module information
    - _Requirements: 2.1, 2.2, 2.5, 3.1_

  - [x] 6.3 Write property test for module rendering consistency
    - **Property 3: Module Rendering Consistency**
    - **Validates: Requirements 2.1, 2.2, 3.1**

  - [x] 6.4 Implement module detail modal/panel
    - Create modal or expandable panel for detailed module information
    - Display module features, prerequisites, and dependencies
    - Show list of database tables that will be created
    - Display dependency relationships (both directions)
    - _Requirements: 3.2, 3.3, 3.4_

  - [x] 6.5 Write property test for dependency information display
    - **Property 6: Dependency Information Display**
    - **Validates: Requirements 3.2, 3.3, 3.4, 7.4**

- [x] 7. Implement frontend selection logic
  - [x] 7.1 Create JavaScript for module selection handling
    - Implement checkbox change handlers
    - Implement automatic dependency selection when module is selected
    - Implement deselection prevention for modules with selected dependents
    - Show warning message when deselection is prevented
    - Enable/disable installation button based on selection state
    - _Requirements: 2.3, 2.4, 7.1, 7.2_

  - [x] 7.2 Write property test for selection state management
    - **Property 4: Selection State Management**
    - **Validates: Requirements 2.3, 2.4**

  - [x] 7.3 Create JavaScript for installation process
    - Implement installation button click handler
    - Send AJAX request to installation endpoint with selected modules
    - Display progress modal/overlay during installation
    - Poll status endpoint for progress updates
    - Update progress indicator as modules are installed
    - Handle installation completion (success or with errors)
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [x] 7.4 Write property test for progress tracking accuracy
    - **Property 11: Progress Tracking Accuracy**
    - **Validates: Requirements 4.2, 4.3**

- [x] 8. Implement installation results display
  - [x] 8.1 Create results summary UI
    - Display success message when all modules install successfully
    - Display mixed results summary showing successful and failed modules
    - Show error messages for failed modules with module name and reason
    - Provide "Retry Failed" button for modules that failed
    - Provide "Go to Dashboard" button after installation completes
    - _Requirements: 4.4, 4.5, 5.1, 5.3, 5.4_

  - [x] 8.2 Write property test for installation completion summary
    - **Property 12: Installation Completion Summary**
    - **Validates: Requirements 4.4, 5.3**

  - [x] 8.3 Write property test for retry functionality availability
    - **Property 15: Retry Functionality Availability**
    - **Validates: Requirements 5.4**

- [x] 9. Integrate with setup wizard flow
  - [x] 9.1 Update `setup/create_admin.php` to redirect to module installer
    - Change redirect from branding onboarding to module installer
    - Pass setup completion flag to module installer
    - _Requirements: 1.1_

  - [x] 9.2 Update `config/setup_helper.php` to track module installer completion
    - Add function to check if module installer has been completed
    - Add function to mark module installer as completed
    - Update setup status check to include module installer step
    - _Requirements: 8.4_

  - [x] 9.3 Write property test for setup completion state update
    - **Property 19: Setup Completion State Update**
    - **Validates: Requirements 8.4**

  - [x] 9.4 Write property test for session persistence
    - **Property 18: Session Persistence**
    - **Validates: Requirements 8.3**

- [x] 10. Implement alternative access paths
  - [x] 10.1 Add module installer link to dashboard/settings
    - Add "Install Modules" link in settings or admin panel
    - Filter to show only uninstalled modules when accessed from dashboard
    - _Requirements: 6.3, 6.5_

  - [x] 10.2 Write property test for uninstalled module filtering
    - **Property 16: Uninstalled Module Filtering**
    - **Validates: Requirements 6.5**

  - [x] 10.3 Update existing module onboarding pages
    - Add link to unified module installer on each onboarding page
    - Update onboarding page text to mention unified installer option
    - _Requirements: 6.4_

  - [x] 10.4 Write property test for fallback onboarding consistency
    - **Property 17: Fallback Onboarding Consistency**
    - **Validates: Requirements 6.4**

- [x] 11. Add edge case handling
  - [x] 11.1 Handle "all modules installed" state
    - Display completion message when all modules are already installed
    - Show link to dashboard
    - Hide module selection UI
    - _Requirements: 1.4_

  - [x] 11.2 Handle empty module list
    - Display appropriate message if no modules are available
    - Provide link back to dashboard
    - _Requirements: 1.2_

- [x] 12. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. Final polish and documentation
  - [x] 13.1 Add CSS styling consistent with setup wizard
    - Match color scheme, typography, and layout of existing setup pages
    - Ensure responsive design for mobile devices
    - Add loading animations and transitions
    - _Requirements: 8.1, 8.2_

  - [x] 13.2 Add accessibility features
    - Add ARIA labels and roles for dynamic content
    - Ensure keyboard navigation works properly
    - Add screen reader announcements for progress updates
    - Test with screen readers
    - _Requirements: All_

  - [x] 13.3 Create user documentation
    - Document the unified module installer feature
    - Create screenshots and usage guide
    - Update setup README with new flow
    - _Requirements: All_

- [x] 14. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
