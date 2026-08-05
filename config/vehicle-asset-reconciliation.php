<?php

return [
    'source_system' => 'VEHICLE_AUDIT',
    'default_sheet' => 'Monitoring Asset',
    'kir_sheet' => 'KIR',
    'plate_attribute_name' => 'Plat Nomor',
    'stnk_attribute_name' => 'STNK',
    'kir_attribute_name' => 'KIR',
    'tax_attribute_name' => 'Pajak',
    'insurance_attribute_name' => 'Asuransi',
    'bpkb_attribute_name' => 'BPKB',
    'holder_attribute_name' => 'Pemegang Inventaris',
    'document_reminder_offset_days' => 30,
    'category_names' => ['MOBIL', 'MOTOR'],
    /*
     * Presence / keberadaan labels mapped to AssetLocation.name (exact after normalize).
     * Values must match asset_locations.name exactly when possible.
     */
    'location_aliases' => [
        'HO' => 'HEAD OFFICE PIK',
        'PC' => 'GUDANG PRIMA CENTER',
        'PIK' => 'HEAD OFFICE PIK',
        'PURWOKERTO' => 'PURWOKERTO',
        'JATIWANGI' => 'AUTO EV JATIWANGI',
    ],
    /*
     * Explicit ACC/STNK aliases. Values must match business_entities.name exactly.
     */
    'business_entity_aliases' => [
        'MSI' => 'PT MEDIA SELULAR INDONESIA',
        'CS' => 'CV COMPLETE SELULAR',
        'TOP' => 'CV TOP SELULAR',
        'MKLI' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA',
        'RISM' => 'PT RETAIL INDONESIA SELALU MAJU',
        'PT. MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
        'PT MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
        'CV. COMPLETE SELULAR' => 'CV COMPLETE SELULAR',
        'CV COMPLETE SELULAR' => 'CV COMPLETE SELULAR',
        'CV. TOP SELULAR' => 'CV TOP SELULAR',
        'CV TOP SELULAR' => 'CV TOP SELULAR',
        'PT. MAJU KENDARAAN LISTRIK INDONESIA' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA',
        'PT MKLI' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA',
        'CV. BERSAMA CS' => 'CV BERSAMA CS',
        'CV BERSAMA CS' => 'CV BERSAMA CS',
        'CV MAJU TECNOLOGI' => 'CV MAJU TECNOLOGI',
        'PT RISM' => 'PT RETAIL INDONESIA SELALU MAJU',
    ],
];
