<?php

namespace App\Service\AdminApi;

use App\Service\Admin\AdminBilling;
use App\Service\Admin\AdminForms;
use App\Service\Admin\AdminOperations;

/** Explicit allowlist: never derive routes or abilities from controller methods. */
final class Operations
{
    public const TOKEN_MARKER = 'admin-api:v1';

    public const ACTIONS = [
        'block-user' => [AdminOperations::class, 'blockUser', 'admin:users:block'],
        'unblock-user' => [AdminOperations::class, 'unblockUser', 'admin:users:unblock'],
        'send-password-reset-email' => [AdminOperations::class, 'sendPasswordResetEmail', 'admin:users:password-reset'],
        'disable-two-factor-authentication' => [AdminOperations::class, 'disableTwoFactorAuthentication', 'admin:users:disable-2fa'],
        'clear-user-cache' => [AdminOperations::class, 'clearUserCache', 'admin:users:clear-cache'],
        'apply-discount' => [AdminOperations::class, 'applyDiscount', 'admin:billing:discount'],
        'extend-trial' => [AdminOperations::class, 'extendTrial', 'admin:billing:extend-trial'],
        'cancel-subscription' => [AdminOperations::class, 'cancelSubscription', 'admin:billing:cancel'],
        'refund-payment' => [AdminOperations::class, 'refundPayment', 'admin:billing:refund'],
        'update-customer' => [AdminBilling::class, 'updateCustomer', 'admin:billing:update'],
        'restore-form' => [AdminForms::class, 'restoreDeletedForm', 'admin:forms:restore'],
        'create-template' => [AdminOperations::class, 'createTemplate', 'admin:templates:create'],
    ];

    public static function scope(string $operation): string
    {
        abort_unless(isset(self::ACTIONS[$operation]), 404);
        return self::ACTIONS[$operation][2];
    }

    public static function abilities(): array
    {
        return array_merge(['admin:users:read', 'admin:workspaces:read', 'admin:billing:read', 'admin:forms:read'], array_column(self::ACTIONS, 2));
    }

    public static function canonical(mixed $value): string
    {
        $sort = function (mixed $item) use (&$sort): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (!array_is_list($item)) {
                ksort($item);
            }
            return array_map($sort, $item);
        };
        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
