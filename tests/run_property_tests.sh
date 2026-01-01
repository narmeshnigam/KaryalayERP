#!/bin/bash
# Property Test Runner for Unified Module Installer

echo "=========================================="
echo "Running Property-Based Tests"
echo "=========================================="
echo ""

# Run module discovery tests
echo "1. Module Discovery Tests"
echo "------------------------------------------"
php tests/module_discovery_property_test.php
DISCOVERY_EXIT=$?
echo ""

# Run module categories tests
echo "2. Module Categories Tests"
echo "------------------------------------------"
php tests/module_categories_property_test.php
CATEGORIES_EXIT=$?
echo ""

# Summary
echo "=========================================="
echo "Test Summary"
echo "=========================================="
if [ $DISCOVERY_EXIT -eq 0 ]; then
    echo "✓ Module Discovery Tests: PASSED"
else
    echo "✗ Module Discovery Tests: FAILED"
fi

if [ $CATEGORIES_EXIT -eq 0 ]; then
    echo "✓ Module Categories Tests: PASSED"
else
    echo "✗ Module Categories Tests: FAILED"
fi
echo ""

# Exit with failure if any test failed
if [ $DISCOVERY_EXIT -ne 0 ] || [ $CATEGORIES_EXIT -ne 0 ]; then
    exit 1
fi

exit 0
