<?php

namespace App\Service\Forms;

use App\Models\Forms\AgentFormDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentFormDraftService
{
    public const EXPIRY_DAYS = 7;

    public function __construct(
        private readonly AgentFormDefinition $formDefinition,
        private readonly AgentFormDraftPatcher $patcher,
    ) {
    }

    /**
     * @return array{draft: AgentFormDraft, token: string}
     */
    public function create(array $definition): array
    {
        $definition['visibility'] = 'draft';
        $definition = $this->formDefinition->normalizeAndValidate($definition);
        $token = $this->generateToken();

        $draft = AgentFormDraft::query()->create([
            'token_hash' => $this->hashToken($token),
            'definition' => $definition,
            'schema_version' => AgentFormDefinition::SCHEMA_VERSION,
            'version' => 1,
            'status' => AgentFormDraft::STATUS_ACTIVE,
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);

        return ['draft' => $draft, 'token' => $token];
    }

    public function get(string $token): AgentFormDraft
    {
        return $this->resolveActive($token);
    }

    public function patch(string $token, int $expectedVersion, array $operations): AgentFormDraft
    {
        return DB::transaction(function () use ($token, $expectedVersion, $operations) {
            $draft = $this->resolveActive($token, lock: true);

            if ($draft->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'expected_version' => ["Draft version conflict. Current version is {$draft->version}. Fetch the draft and retry."],
                ]);
            }

            $definition = $this->patcher->apply($draft->definition, $operations);
            $definition['visibility'] = 'draft';
            $definition = $this->formDefinition->normalizeAndValidate($definition);

            $draft->forceFill([
                'definition' => $definition,
                'schema_version' => AgentFormDefinition::SCHEMA_VERSION,
                'version' => $draft->version + 1,
            ])->save();

            return $draft->refresh();
        });
    }

    public function serialize(AgentFormDraft $draft): array
    {
        return [
            'version' => $draft->version,
            'schema_version' => $draft->schema_version,
            'status' => $draft->status,
            'expires_at' => $draft->expires_at->toIso8601String(),
            'definition' => $draft->definition,
        ];
    }

    private function resolveActive(string $token, bool $lock = false): AgentFormDraft
    {
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw $this->unavailable();
        }

        $query = AgentFormDraft::query()
            ->where('token_hash', $this->hashToken($token))
            ->active();

        if ($lock) {
            $query->lockForUpdate();
        }

        $draft = $query->first();

        if (! $draft) {
            throw $this->unavailable();
        }

        return $draft;
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages([
            'draft_token' => ['Draft not found, expired, or already claimed.'],
        ]);
    }
}
