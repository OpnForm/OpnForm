<?php

namespace App\Service\License;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

class SelfHostedSeatLimitService
{
    private const FREE_SEAT_LIMIT = 2;

    public function canInviteEmail(string $email): bool
    {
        if (!$this->shouldEnforceLimit()) {
            return true;
        }

        if ($this->hasValidLicense()) {
            return true;
        }

        $normalizedEmail = $this->normalizeEmail($email);
        if ($this->activeUserEmails()->contains($normalizedEmail)) {
            return true;
        }

        if ($this->pendingInviteEmails()->contains($normalizedEmail)) {
            return true;
        }

        return $this->usedSeats() < self::FREE_SEAT_LIMIT;
    }

    public function canAcceptInvite(UserInvite $invite): bool
    {
        if (!$this->shouldEnforceLimit()) {
            return true;
        }

        if ($this->hasValidLicense()) {
            return true;
        }

        return $this->usedSeats($invite) < self::FREE_SEAT_LIMIT;
    }

    public function assertCanInviteEmail(string $email): void
    {
        if ($this->canInviteEmail($email)) {
            return;
        }

        throw new HttpResponseException(
            response()->json(['message' => $this->limitMessage()], 403)
        );
    }

    public function assertCanAcceptInvite(UserInvite $invite): void
    {
        if ($this->canAcceptInvite($invite)) {
            return;
        }

        throw new HttpResponseException(
            response()->json(['message' => $this->limitMessage()], 403)
        );
    }

    public function limitMessage(): string
    {
        return 'Enterprise license is required to add more than 2 users to a self-hosted instance.';
    }

    private function usedSeats(?UserInvite $excludeInvite = null): int
    {
        $activeUserEmails = $this->activeUserEmails();

        $pendingInvites = $this->pendingInviteEmails($excludeInvite)
            ->reject(fn (string $email) => $activeUserEmails->contains($email))
            ->unique()
            ->count();

        return $activeUserEmails->count() + $pendingInvites;
    }

    private function activeUserEmails(): Collection
    {
        return User::query()
            ->pluck('email')
            ->map(fn (string $email) => $this->normalizeEmail($email))
            ->unique()
            ->values();
    }

    private function pendingInviteEmails(?UserInvite $excludeInvite = null): Collection
    {
        return UserInvite::query()
            ->pending()
            ->notExpired()
            ->when($excludeInvite, fn ($query) => $query->where('id', '!=', $excludeInvite->id))
            ->pluck('email')
            ->map(fn (string $email) => $this->normalizeEmail($email))
            ->values();
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower($email);
    }

    private function shouldEnforceLimit(): bool
    {
        return (bool) config('app.self_hosted');
    }

    private function hasValidLicense(): bool
    {
        return app(LicenseService::class)->checkLicense()->isActive();
    }
}
