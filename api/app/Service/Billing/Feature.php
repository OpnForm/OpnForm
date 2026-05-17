<?php

namespace App\Service\Billing;

final class Feature
{
    public const BRANDING_REMOVAL = 'branding.removal';
    public const WORKSPACES_MULTIPLE = 'workspaces.multiple';
    public const INVITE_USER = 'invite_user';
    public const CUSTOM_DOMAIN = 'custom_domain';
    public const FORM_SUMMARY = 'form_summary';
    public const FORM_ANALYTICS = 'form_analytics';
    public const AI_FORM_GENERATION = 'ai.form_generation';
    public const CUSTOM_SMTP = 'custom_smtp';
    public const EMAIL_ADVANCED = 'integrations.email.advanced';
    public const FILE_UPLOAD_ALLOWED_TYPES = 'file_upload.allowed_types';
    public const EDITABLE_SUBMISSIONS = 'editable_submissions';
    public const BRANDING_ADVANCED = 'branding.advanced';
    public const FORM_VERSIONING = 'form_versioning';
    public const ENABLE_IP_TRACKING = 'enable_ip_tracking';
    public const PARTIAL_SUBMISSIONS = 'partial_submissions';
    public const SSO_OIDC = 'sso.oidc';
    public const ID_GENERATION = 'id_generation';

    public static function all(): array
    {
        return [
            self::BRANDING_REMOVAL,
            self::WORKSPACES_MULTIPLE,
            self::INVITE_USER,
            self::CUSTOM_DOMAIN,
            self::FORM_SUMMARY,
            self::FORM_ANALYTICS,
            self::AI_FORM_GENERATION,
            self::CUSTOM_SMTP,
            self::EMAIL_ADVANCED,
            self::FILE_UPLOAD_ALLOWED_TYPES,
            self::EDITABLE_SUBMISSIONS,
            self::BRANDING_ADVANCED,
            self::FORM_VERSIONING,
            self::ENABLE_IP_TRACKING,
            self::PARTIAL_SUBMISSIONS,
            self::SSO_OIDC,
            self::ID_GENERATION,
        ];
    }
}
