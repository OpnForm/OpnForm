<?php

namespace App\Models\Integration;

use App\Events\Models\FormIntegrationCreated;
use App\Events\Models\FormIntegrationSaved;
use App\Models\Forms\Form;
use App\Models\OAuthProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'data' => 'object',
            'logic' => 'object'
        ];
    }

    protected $dispatchesEvents = [
        'created' => FormIntegrationCreated::class,
        'saved' => FormIntegrationSaved::class,
    ];

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

    public function provider()
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
