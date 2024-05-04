<?php

/**
 * | Relative path and name of Document Uploads
 */
return [
    // Live Url
    "BASE_URL"        => env('BASE_URL'),                          // ( Authorization Service)
    "ULB_LOGO_URL"    => env('ULB_LOGO_URL'),                  // ( property )
    "PAYMENT_URL"     => env('PAYMENT_URL'),                    // ( Payment Engine )
    "ID_GENERATE_URL" => env('ID_GENERATE_URL'),            // ( Property )

    // Local Url
    // "BASE_URL" => 'http://192.168.0.21:8005/',
    // "ULB_LOGO_URL" => 'http://192.168.0.202:8001/',
    // "PAYMENT_URL" => "http://192.168.0.202:8006/",
    // "ID_GENERATE_URL" => 'http://192.168.0.21:8000/',


    "PARAM_ID" => '35',
    "PARAM_ST_ID" => '36',
    "WATER_TANKER_MODULE_ID"=>"11",
    "API_KEY" => "eff41ef6-d430-4887-aa55-9fcf46c72c99",
    "DOC_URL"                 => env('DOC_URL'),
    "DMS_URL"                 => env('DMS_URL'),
    "PARAM_IDS" => [
        "WAPP"  => 15,
        "WCON"  => 16,
        "TRN"   => 37,
        "WCD"   => 39,
        "WFC"   => 42,
        "WPS"   => 43
    ],
    "OFFLINE_PAYMENT_MODS"=>[
        'CASH',
        // 'CHEQUE',
        // 'DD',
        // 'NEFT'
    ],
    'PAYMENT_MODE' => [
        '1' => 'ONLINE',
        '2' => 'CASH',
        // '3' => 'CHEQUE',
        // '4' => 'DD',
        // '5' => 'NEFT'
    ],
];
