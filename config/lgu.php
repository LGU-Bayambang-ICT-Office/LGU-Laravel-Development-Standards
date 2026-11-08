<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Philippine Local Government Unit Identification Baseline
    |--------------------------------------------------------------------------
    | Immutable organizational identification stored in .env and immutable configs.
    | Follows Department of the Interior and Local Government (DILG) standards.
    */
    'agency' => [
        'name' => env('AGENCY_NAME', 'Municipality of Bayambang'),
        'short_name' => env('AGENCY_SHORT_NAME', 'LGU-Bayambang'),
        'type' => env('LGU_TYPE', 'Municipality'), // Municipality, City, Province, Barangay
        'class' => env('LGU_CLASS', '1st Class'),
        'province' => env('AGENCY_PROVINCE', 'Pangasinan'),
        'region' => env('AGENCY_REGION', 'Region I - Ilocos Region'),
        'psgc_code' => env('AGENCY_PSGC_CODE', '015512000'),
        'address' => env('AGENCY_OFFICIAL_ADDRESS', 'Municipal Hall, Rizal Avenue, Bayambang, Pangasinan'),
        'contact_email' => env('AGENCY_CONTACT_EMAIL', 'info@bayambang.gov.ph'),
        'official_domain' => env('AGENCY_DOMAIN', 'bayambang.gov.ph'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-Red Tape Authority (ARTA RA 11032) Configuration
    |--------------------------------------------------------------------------
    | Standard SLA day windows and operating hours for statutory calculations.
    */
    'arta' => [
        'sla_days' => [
            'simple' => 3,
            'complex' => 7,
            'highly_technical' => 20,
        ],
        'working_hours' => [
            'start' => '08:00',
            'end' => '17:00',
        ],
        'workdays' => [
            Carbon\CarbonInterface::MONDAY,
            Carbon\CarbonInterface::TUESDAY,
            Carbon\CarbonInterface::WEDNESDAY,
            Carbon\CarbonInterface::THURSDAY,
            Carbon\CarbonInterface::FRIDAY,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Privacy Act of 2012 (RA 10173) & NPC Configuration
    |--------------------------------------------------------------------------
    */
    'dpa' => [
        'encryption_key' => env('PII_ENCRYPTION_KEY'),
        'blind_index_salt' => env('PII_BLIND_INDEX_SALT'),
        'idle_timeout_minutes' => (int) env('MUNICIPAL_SESSION_TIMEOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission on Audit (COA) Circulars & Immutable Ledger
    |--------------------------------------------------------------------------
    */
    'coa' => [
        'audit_table' => 'audit_activities',
        'hash_algorithm' => 'sha256',
        'genesis_hash' => '0000000000000000000000000000000000000000000000000000000000000000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Departmental Multi-Office Scoping & Teams
    |--------------------------------------------------------------------------
    */
    'office' => [
        'session_key' => 'active_office_id',
        'spatie_teams' => true,
        'oversight_offices' => ['MACCO', 'MTO', 'MO', 'IAS', 'ICTO'],
    ],

];
