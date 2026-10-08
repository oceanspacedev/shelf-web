<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Nama',
    'column.guard_name' => 'Nama Penjaga',
    'column.roles' => 'Peran',
    'column.permissions' => 'Izin',
    'column.updated_at' => 'Dirubah',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Nama',
    'field.guard_name' => 'Nama Penjaga',
    'field.permissions' => 'Izin',
    'field.select_all.name' => 'Pilih Semua',
    'field.select_all.message' => 'Aktifkan semua izin yang <span class="text-primary font-medium">Tersedia</span> untuk Peran ini.',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Pengaturan',
    'nav.role.label' => 'Peran',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Peran',
    'resource.label.roles' => 'Peran',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Entitas',
    'resources' => 'Sumber Daya',
    'widgets' => 'Widget',
    'pages' => 'Halaman',
    'custom' => 'Izin Kustom',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'Kamu tidak punya izin akses',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Lihat',
        'view_any' => 'Lihat Semua',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'delete_any' => 'Hapus Semua',
        'force_delete' => 'Hapus Permanen',
        'force_delete_any' => 'Hapus Permanen Semua',
        'restore' => 'Kembalikan',
        'replicate' => 'Duplikat',
        'reorder' => 'Urutkan',
        'restore_any' => 'Kembalikan Semua',
        'export' => 'Ekspor',
        'import' => 'Impor',
        'impersonate' => 'Login Sebagai',
        'manage_stock' => 'Kelola BA Stok',
        'manage_condition' => 'Kelola Kondisi (Jual/Rusak/Hilang/Perbaikan)',
        'repair_validation' => 'Perbaiki Validasi Penerima',
        'regenerate_qr' => 'Regenerate QR',
        'update_recipient' => 'Ubah Penerima',
        'merge' => 'Gabungkan Duplikat',
        'update_core' => 'Ubah Data Inti BA',
        'delete_detail' => 'Lepas Riwayat dari Aset',
        'view_all' => 'Lihat Data Semua User',
        'update_all' => 'Ubah Data Semua User',
        'delete_all' => 'Hapus Data Semua User',
        'manage_access' => 'Kelola Akses & Login',
        'manage_business_entity_access' => 'Kelola Akses Badan Usaha',

        // Custom permissions
        'view_horizon' => 'Lihat Horizon',
        'view_log_viewer' => 'Lihat Log Viewer',
        'download_log_viewer' => 'Unduh File Log',
        'delete_log_viewer' => 'Hapus File Log',
        'view_pulse' => 'Lihat Pulse',
    ],
];
