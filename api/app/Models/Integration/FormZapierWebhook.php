<?php

namespace App\Models\Integration;

use App\Integrations\Handlers\ZapierIntegration;
use App\Models\Forms\Form;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormZapierWebhook extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'form_zapier_webhooks';

    protected $fillable = [
        'form_id',
        'hook_url',
    ];

    /**
     * Relationships
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function triggerHook(array $data)
    {
        Http::throw()->post(
            $this->hook_url,
            ZapierIntegration::formatWebhookData($this->form, $data)
        );
    }
}
