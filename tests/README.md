# Property-Based Tests for Unified Module Installer

This directory contains property-based tests for the unified module installer feature.

## Overview

Property-based testing (PBT) validates that universal properties hold across all valid inputs, rather than testing specific examples. Each test runs 100 iterations with randomly generated inputs to verify correctness properties.

## Test Files

### 1. `property_test_framework.php`
A lightweight property-based testing framework that provides:
- Test execution with configurable iterations (default: 100)
- Random data generators
- Result reporting and failure tracking

### 2. `module_discovery_property_test.php`
Tests for module discovery functionality:

**Property 1: Module Discovery Completeness** (Requirements 1.2, 6.3)
- Validates that all modules in the registry are discovered
- Ensures all required fields are present (name, display_name, description, icon, category, setup_script, tables, installed)
- Verifies installation status is boolean

**Additional Properties:**
- Module Metadata Consistency: Metadata should be identical across multiple calls
- Setup Script Paths Valid: All setup script paths should point to existing files
- Table Existence Check Consistency: Checking table existence multiple times gives same result
- Module Installation Status Deterministic: Installation status checks are consistent

### 3. `module_categories_property_test.php`
Tests for module category grouping:

**Property 5: Category Grouping Correctness** (Requirements 2.5)
- Each module appears exactly once across all categories
- All original modules are present in grouped result
- No extra modules are added during grouping

**Additional Properties:**
- Modules In Designated Category: Each module appears in its designated category
- All Categories Present: All defined categories exist in grouped result
- Category Order Preserved: Categories appear in the correct order
- get_module_category Correctness: Returns correct category for any module
- get_modules_in_category Correctness: Returns only modules from specified category

## Running the Tests

### Prerequisites
- PHP 8.0 or higher
- Database connection configured in `config/config.php`

### Run All Tests
```bash
./tests/run_property_tests.sh
```

### Run Individual Test Files
```bash
php tests/module_discovery_property_test.php
php tests/module_categories_property_test.php
```

## Test Results

Tests that don't require database connection will pass immediately:
- ✓ Module Metadata Consistency
- ✓ Setup Script Paths Valid
- ✓ get_module_category Correctness

Tests that require database connection will pass once database is configured:
- Module Discovery Completeness
- Table Existence Check Consistency
- Module Installation Status Deterministic
- Category Grouping Correctness
- Modules In Designated Category
- All Categories Present
- Category Order Preserved
- get_modules_in_category Correctness

## Implementation Notes

The property tests follow the design document specifications:
- Each test runs 100 iterations (configurable)
- Tests validate universal properties, not specific examples
- Failures include iteration number, input data, and reason
- Tests are tagged with feature name and property number
- Each property references the requirements it validates

## Database Configuration

For database-dependent tests to pass, ensure:
1. MySQL/MariaDB is running
2. Database credentials are correct in `config/config.php`
3. Database `karyalay_db` exists
4. User has appropriate permissions

The tests will automatically connect using the configuration from `config/db_connect.php`.
