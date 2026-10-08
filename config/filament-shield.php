<?php

declare(strict_types=1);

use App\Filament\Resources\AssetReconciliationResource;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetServiceResource;
use App\Filament\Resources\AssetTransferResource;
use App\Filament\Resources\DivisionResource;
use App\Filament\Resources\ObChecksheetResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleChecksheetResource;
use App\Support\ObservabilityAccess;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [
    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    'tenant_model' => null,

    'auth_provider_model' => 'App\\Models\\User',

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    'permissions' => [
        'separator' => ':',
        'case' => 'pascal',
        'generate' => true,
    ],

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => true,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'export',
            'import',
            'impersonate',
            'manageStock',
            'manageCondition',
            'repairValidation',
            'regenerateQr',
            'updateRecipient',
            'merge',
            'updateCore',
            'deleteDetail',
            'viewAll',
            'updateAll',
            'deleteAll',
            'manageAccess',
            'manageBusinessEntityAccess',
        ],
    ],

    // Custom permission labels (Horizon, Log Viewer) are translated from
    // lang/vendor/filament-shield/{locale}/filament-shield.php.
    'localization' => [
        'enabled' => true,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    'resources' => [
        'subject' => 'model',
        'manage' => [
            AssetResource::class => [
                'export',
                'import',
                // Tandai dijual/rusak/hilang, selesaikan perbaikan, audit penjualan.
                'manageCondition',
                'repairValidation',
                'regenerateQr',
                'updateRecipient',
                'merge',
            ],
            AssetRequestResource::class => [
                'export',
                'restore',
                'restoreAny',
                'forceDelete',
                'forceDeleteAny',
            ],
            AssetTransferResource::class => [
                'export',
                // BA Serah Terima/Pengembalian atas nama staf GA mana pun.
                'manageStock',
                // Ubah jenis BA, pihak, dan aset setelah BA dibuat.
                'updateCore',
                // Lepas riwayat transfer dari halaman aset.
                'deleteDetail',
            ],
            // viewAll/updateAll/deleteAll: data milik user lain; tanpa izin ini
            // user hanya mengelola datanya sendiri.
            AssetServiceResource::class => [
                'export',
                'viewAll',
                'updateAll',
                'deleteAll',
            ],
            ObChecksheetResource::class => [
                'export',
                'viewAll',
                'updateAll',
                'deleteAll',
            ],
            DivisionResource::class => [
                'restore',
                'restoreAny',
                'forceDelete',
                'forceDeleteAny',
            ],
            UserResource::class => [
                'import',
                'impersonate',
                // Username, email, password, role, dan login WhatsApp.
                'manageAccess',
                'manageBusinessEntityAccess',
            ],
            VehicleChecksheetResource::class => [
                'export',
                'import',
            ],
            RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
        ],
        'exclude' => [
            // Rekonsiliasi mewarisi izin import Asset agar tidak membuat
            // permission paralel yang dapat melewati kontrol import utama.
            AssetReconciliationResource::class,
        ],
    ],

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    // Horizon & Log Viewer access, managed from the Shield role form (Custom tab).
    'custom_permissions' => ObservabilityAccess::shieldPermissions(),

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    'register_role_policy' => false,
];
