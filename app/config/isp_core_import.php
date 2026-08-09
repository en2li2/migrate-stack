<?php

return [
    // Hedef CRM (stage: panel.wexconnect.com.tr, prod: crm.wexconnect.com.tr)
    'base_url' => env('ISP_CORE_IMPORT_BASE_URL'),
    'token' => env('ISP_CORE_IMPORT_TOKEN'),
    'batch_size' => (int) env('ISP_CORE_IMPORT_BATCH', 100),
];
