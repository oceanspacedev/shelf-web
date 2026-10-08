<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Name',
    'column.guard_name' => 'Guard Name',
    'column.roles' => 'Roles',
    'column.permissions' => 'Permissions',
    'column.updated_at' => 'Updated At',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Name',
    'field.guard_name' => 'Guard Name',
    'field.permissions' => 'Permissions',
    'field.select_all.name' => 'Select All',
    'field.select_all.message' => 'Enable all Permissions currently <span class="text-primary font-medium">Enabled</span> for this role',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Filament Shield',
    'nav.role.label' => 'Roles',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Role',
    'resource.label.roles' => 'Roles',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Entities',
    'resources' => 'Resources',
    'widgets' => 'Widgets',
    'pages' => 'Pages',
    'custom' => 'Custom Permissions',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'You do not have permission to access',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'View',
        'view_any' => 'View Any',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'delete_any' => 'Delete Any',
        'force_delete' => 'Force Delete',
        'force_delete_any' => 'Force Delete Any',
        'restore' => 'Restore',
        'reorder' => 'Reorder',
        'restore_any' => 'Restore Any',
        'replicate' => 'Replicate',
        'export' => 'Export',
        'import' => 'Import',
        'impersonate' => 'Impersonate',
        'manage_stock' => 'Manage Stock BA',
        'manage_condition' => 'Manage Condition (Sold/Damaged/Lost/Repair)',
        'repair_validation' => 'Repair Recipient Validation',
        'regenerate_qr' => 'Regenerate QR',
        'update_recipient' => 'Update Recipient',
        'merge' => 'Merge Duplicates',
        'update_core' => 'Update Core BA Data',
        'delete_detail' => 'Detach History from Asset',
        'view_all' => "View Every User's Data",
        'update_all' => "Update Every User's Data",
        'delete_all' => "Delete Every User's Data",
        'manage_access' => 'Manage Access & Login',
        'manage_business_entity_access' => 'Manage Business Entity Access',

        // Custom permissions
        'view_horizon' => 'View Horizon',
        'view_log_viewer' => 'View Log Viewer',
        'download_log_viewer' => 'Download Log Files',
        'delete_log_viewer' => 'Delete Log Files',
        'view_pulse' => 'View Pulse',
    ],
];
