<?php

namespace App\Models\Integration;

use App\Events\Models\FormIntegrationCreated;
use App\Events\Models\FormIntegrationSaved;
use App\Models\Forms\Form;
use App\Models\OAuthProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FormIntegration extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'form_id',
        'status',
        'integration_id',
        'logic',
        'data',
        'oauth_id'
    ];

    protected $hidden = ['failure_streak'];

    protected function casts(): array
    {
        return [
            'data' => 'object',
            'logic' => 'object',
            'failure_streak' => 'array',
        ];
    }

    protected $dispatchesEvents = [
        'created' => FormIntegrationCreated::class,
        'saved' => FormIntegrationSaved::class,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $integration) {
            if ($integration->isDirty(['status', 'integration_id', 'data', 'logic'])
                && ($integration->pausesOnRepeatedFailures() || $integration->failure_streak !== null)) {
                $streak = $integration->getOriginal('failure_streak') ?? [];
                $settings = array_intersect_key($streak, ['resume_after' => true]);
                if ($integration->status === self::STATUS_ACTIVE
                    && $integration->isDirty('status')
                    && isset($streak['auto_disabled'])) {
                    $settings['resume_after'] = (float) now()->format('U.u');
                }
                if ($integration->status === self::STATUS_INACTIVE && isset($streak['auto_disabled'])) {
                    $settings['auto_disabled'] = true;
                }
                $integration->failure_streak = $settings + ['version' => (string) Str::uuid()];
            }
        });
    }

    public function pausesOnRepeatedFailures(): bool
    {
        return in_array($this->integration_id, ['webhook', 'zapier'], true);
    }

    public function updateSettings(array $attributes, bool $reactivate): void
    {
        if (($attributes['status'] ?? null) === self::STATUS_ACTIVE
            && isset($this->failure_streak['auto_disabled'])
            && !$reactivate) {
            $attributes['status'] = self::STATUS_INACTIVE;
        }

        $this->update($attributes);
    }

    /**
     * Relationships
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function events()
    {
        return $this->hasMany(FormIntegrationsEvent::class, 'integration_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(OAuthProvider::class, 'oauth_id');
    }

    public static function getAllIntegrations()
    {
        return json_decode(file_get_contents(resource_path('data/forms/integrations.json')), true);
    }

    public static function getIntegration($key)
    {
        return self::getAllIntegrations()[$key] ?? null;
    }
}
