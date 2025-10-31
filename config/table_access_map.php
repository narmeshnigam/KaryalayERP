<?php
/**
 * Declarative mapping between application routes and table-based permissions.
 *
 * Each entry defines a pattern (directory prefix relative to project root) and
 * the target table used for permission checks. Routes within the pattern can
 * override the default permission or supply additional conditions.
 */

return [
    [
        'pattern' => 'public/employee/',
        'table' => 'employees',
        'default' => 'view_all',
        'routes' => [
            'add_employee.php' => 'create',
            'edit_employee.php' => 'edit_all',
            'export_employees.php' => 'export',
            'view_employee.php' => 'view_all',
        ],
    ],
    [
        'pattern' => 'public/attendance/',
        'table' => 'attendance',
        'default' => 'view_all',
        'routes' => [
            'mark_attendance.php' => 'create',
            'approve_leave.php' => 'edit_all',
            'export_attendance.php' => 'export',
        ],
    ],
    [
        'pattern' => 'public/reimbursements/',
        'table' => 'reimbursements',
        'default' => 'view_all',
        'routes' => [
            'review.php' => 'edit_all',
            'export.php' => 'export',
        ],
    ],
    [
        'pattern' => 'public/expenses/',
        'table' => 'office_expenses',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'export.php' => 'export',
            'reports.php' => 'view_all',
        ],
    ],
    [
        'pattern' => 'public/salary/',
        'table' => 'salary_records',
        'default' => 'view_all',
        'routes' => [
            'upload.php' => 'create',
            'edit.php' => 'edit_all',
            'view.php' => [
                'requires_any' => [
                    ['table' => 'salary_records', 'permission' => 'view_all'],
                    ['table' => 'salary_records', 'permission' => 'view_own'],
                ],
                'permission' => 'view_all',
            ],
            'admin.php' => 'view_all',
        ],
    ],
    [
        'pattern' => 'public/documents/',
        'table' => 'documents',
        'default' => 'view_all',
        'routes' => [
            'upload.php' => 'create',
            'edit.php' => 'edit_all',
            'my.php' => 'view_own',
            'helpers.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/notebook/',
        'table' => 'notebook_notes',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'delete.php' => 'delete_all',
            'my.php' => 'view_own',
            'shared.php' => 'view_all',
            'helpers.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/visitors/',
        'table' => 'visitor_logs',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'export.php' => 'export',
            'view.php' => 'view_all',
        ],
    ],
    [
        'pattern' => 'public/crm/leads/',
        'table' => 'crm_leads',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'my.php' => 'view_own',
            'common.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/crm/calls/',
        'table' => 'crm_calls',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'my.php' => 'view_own',
            'common.php' => ['skip' => true],
            'migrate_schema.php' => 'edit_all',
        ],
    ],
    [
        'pattern' => 'public/crm/meetings/',
        'table' => 'crm_meetings',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'my.php' => 'view_own',
            'common.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/crm/tasks/',
        'table' => 'crm_tasks',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'my.php' => 'view_own',
            'common.php' => ['skip' => true],
            'add_old_backup.php' => ['skip' => true],
            'edit.php.backup' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/crm/visits/',
        'table' => 'crm_visits',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'my.php' => 'view_own',
            'common.php' => ['skip' => true],
            'add_old.php' => ['skip' => true],
            'edit_old.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/crm/',
        'table' => 'crm_leads',
        'default' => 'view_all',
        'routes' => [
            'dashboard.php' => 'view_all',
            'helpers.php' => ['skip' => true],
            'index_old_backup.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/users/',
        'table' => 'users',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'delete.php' => 'delete_all',
            'my-account.php' => 'view_own',
            'helpers.php' => ['skip' => true],
            'activity-log.php' => 'view_all',
        ],
    ],
    [
        'pattern' => 'public/settings/roles/',
        'table' => 'roles',
        'default' => 'view_all',
        'routes' => [
            'add.php' => 'create',
            'edit.php' => 'edit_all',
            'delete.php' => 'delete_all',
            'helpers.php' => ['skip' => true],
        ],
    ],
    [
        'pattern' => 'public/settings/permissions/',
        'table' => 'permissions',
        'default' => 'view_all',
        'routes' => [
            'helpers_table_based.php' => ['skip' => true],
            'index_table_based.php' => 'view_all',
            'index.php' => 'view_all',
        ],
    ],
    [
        'pattern' => 'public/settings/assign-roles/',
        'table' => 'user_roles',
        'default' => 'view_all',
        'routes' => [
            'index.php' => 'view_all',
            'edit.php' => 'edit_all',
        ],
    ],
    [
        'pattern' => 'public/settings/branding/',
        'table' => 'branding_settings',
        'default' => 'view_all',
    ],
    [
        'pattern' => 'public/branding/',
        'table' => 'branding_settings',
        'default' => 'view_all',
    ],
    [
        'pattern' => 'public/documents.php',
        'table' => 'documents',
        'default' => 'view_all',
    ],
    [
        'pattern' => 'public/expenses.php',
        'table' => 'office_expenses',
        'default' => 'view_all',
    ],
    [
        'pattern' => 'public/salary.php',
        'table' => 'salary_records',
        'default' => 'view_all',
    ],
    [
        'pattern' => 'public/visitors.php',
        'table' => 'visitor_logs',
        'default' => 'view_all',
    ],
];
