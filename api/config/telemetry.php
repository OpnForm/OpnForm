<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Instance Telemetry Enabled
    |--------------------------------------------------------------------------
    |
    | OpnForm collects instance metadata and usage data to improve the product
    | and investigate usage and potential Enterprise licensing issues.
    | Form content, submission values, and user emails are not included.
    |
    | It can be explicitly disabled by setting
    | OPNFORM_ANONYMOUS_TELEMETRY_DISABLED to true.
    |
    | What is collected:
    | - Basic usage metrics (form creation, submissions, workspace creation, user additions)
    | - Generated instance identifier (UUID), version, and license status
    | - Configured instance hostname, without URL paths, parameters, or credentials
    | - No form content, submission values, or user emails
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
