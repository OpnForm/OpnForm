<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Spam Detection Settings
    |--------------------------------------------------------------------------
    |
    | This file contains settings for the spam detection feature.
    |
    */

    // Enable or disable the entire feature
    'enabled' => env('SPAM_DETECTION_ENABLED', true),

    // Definition of a "risky" user in days since registration
    'risky_user_days' => 7,

    // Percentage of non-risky users to check randomly
    'random_check_percentage' => 10,

    // Keywords that trigger a mandatory spam check
    'keywords' => [
        "password",
        "passphrase",
        "secret question",
        "security question",
        "mother's maiden name",
        "pin",
        "2fa code",
        "mfa code",
        "authentication code",
        "backup code",
        "recovery code",
        "credit card number",
        "card number",
        "cvv",
        "cvc",
        "csc",
        "card security code",
        "expiration date",
        "expiry date",
        "bank account number",
        "routing number",
        "iban",
        "swift code",
        "online banking password",
        "social security number",
        "ssn",
        "tax id",
        "username",
        "userid",
        "user id",
        "login",
        "signin",
        "sign in",
        "account number",
        "member id",
        "customer id",
        "email address",
        "phone number",
        "google",
        "microsoft",
        "apple",
        "amazon",
        "facebook",
        "meta",
        "instagram",
        "netflix",
        "paypal",
        "stripe",
        "dropbox",
        "linkedin",
        "yahoo",
        "adobe",
        "office 365",
        "icloud",
        "outlook",
        "gmail",
        "visa",
        "mastercard",
        "american express",
        "discover",
        "bank of america",
        "chase",
        "wells fargo",
        "citibank",
        "hsbc",
        "irs",
        "hmrc",
        "cra",
        "dhl",
        "fedex",
        "ups",
        "usps",
        "verify your account",
        "confirm your identity",
        "account suspended",
        "account locked",
        "unusual activity",
        "security alert",
        "action required",
        "update your details",
        "login required",
        "secure your account",
        "account validation",
        "service cancellation",
        "password reset",
        "claim your reward",
        "prize winner",
        "lottery winner",
        "free access"
    ],
];
