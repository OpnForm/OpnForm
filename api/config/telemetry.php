<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anonymous Telemetry Enabled
    |--------------------------------------------------------------------------
    |
    | OpnForm collects anonymous usage data to help improve the product. This
    | telemetry is completely anonymous and contains no personally identifiable
    | information (PII). None of your customer data, form content, submission
    | data, or user emails are ever transmitted.
    |
    | It can be explicitly disabled by setting
    | OPNFORM_ANONYMOUS_TELEMETRY_DISABLED to true.
    |
    | What is collected:
    | - Basic usage metrics (form creation, submissions, workspace creation, user additions)
    | - Anonymous instance identifier (UUID)
    | - No PII, form content, submission data, or user emails
    |
    | Note: The actual check for production/self-hosted is done in
    | TelemetryService::shouldSendTelemetry().
    |
    */

    'enabled' => !env('OPNFORM_ANONYMOUS_TELEMETRY_DISABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Telemetry Endpoint
    |--------------------------------------------------------------------------
    |
    | The OpenPanel endpoint URL where telemetry events will be sent.
    | This is hardcoded for OpnForm's telemetry collection.
    |
    */

    'endpoint' => 'https://telemetry.opnform.com/track',

    /*
    |--------------------------------------------------------------------------
    | OpenPanel Client ID
    |--------------------------------------------------------------------------
    |
    | OpenPanel client ID for OpnForm telemetry authentication.
    | Hardcoded - users do not need to configure this.
    |
    */

    'client_id' => 'd6d85ed5-723f-48de-894e-db2de1a0c7c1', // NOSONAR - This is a public client ID for telemetry

    /*
    |--------------------------------------------------------------------------
    | OpenPanel Client Secret
    |--------------------------------------------------------------------------
    |
    | OpenPanel client secret for OpnForm telemetry authentication.
    | Hardcoded - users do not need to configure this.
    |
    */

    'client_secret' => 'sec_236ae00cc86099409ee2', // NOSONAR - This is a public client secret for telemetry
];
