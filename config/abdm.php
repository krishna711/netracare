<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ABDM Client Credentials (Bridge ID & Secret)
    |--------------------------------------------------------------------------
    */
    'client_id' => env('ABDM_CLIENT_ID', ''),
    'client_secret' => env('ABDM_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment & Base URLs
    |--------------------------------------------------------------------------
    | Sandbox:
    |   Gateway: https://dev.abdm.gov.in/api/hiecm
    |   Bridge Gateway: https://dev.abdm.gov.in
    |   ABHA API: https://abhasbx.abdm.gov.in/abha/api
    | Production:
    |   Gateway: https://gateway.abdm.gov.in
    |   ABHA API: https://abha.abdm.gov.in/api
    */
    'env' => env('ABDM_ENV', 'sandbox'), // 'sandbox' or 'production'
    'cm_id' => env('ABDM_CM_ID', 'sbx'), // 'sbx' for sandbox, 'abdm' for production

    'gateway_base_url' => env('ABDM_GATEWAY_URL', 'https://dev.abdm.gov.in/api/hiecm'),
    'bridge_base_url' => env('ABDM_BRIDGE_URL', 'https://dev.abdm.gov.in'),
    'abha_base_url' => env('ABDM_ABHA_URL', 'https://abhasbx.abdm.gov.in/abha/api'),

    /*
    |--------------------------------------------------------------------------
    | Facility & HIP Configuration
    |--------------------------------------------------------------------------
    */
    'hip_id' => env('ABDM_HIP_ID', 'IN2310001444'), // Your registered Facility / HIP ID
    'facility_name' => env('ABDM_FACILITY_NAME', 'Netrika Netralaya'),
    'counter_id' => env('ABDM_COUNTER_ID', '1'),
    'public_callback_url' => env('ABDM_PUBLIC_URL', env('APP_URL', 'http://localhost')),
];
