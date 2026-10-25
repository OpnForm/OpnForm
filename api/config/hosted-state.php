<?php

return [
    // Opt-in: existing DynamoDB deployments keep their historical configuration.
    // redis-fresh: cold cache/sessions after maintenance; no global DynamoDB import.
    // redis: optional retained-state recovery, requiring a signed receipt.
    'mode' => env('OPNFORM_STATE_MODE', 'historical-dynamodb'),
    'namespace' => env('OPNFORM_STATE_NAMESPACE'),
    'scheduler_store' => env('SCHEDULE_CACHE_DRIVER'),
    // Deployment-owned, read-only files. Never use APP_KEY to sign acceptance.
    'receipt_file' => env('OPNFORM_STATE_RECEIPT_FILE'),
    'public_key_file' => env('OPNFORM_STATE_RECEIPT_PUBLIC_KEY_FILE'),
];
