<?php

namespace App\Models\Forms\AI;

use App\Jobs\Form\GenerateAiForm;
use App\Jobs\Form\GenerateAiFormFields;
use App\Jobs\Form\GenerateAiFormula;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFormCompletion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_FORM = 'form';
    public const TYPE_FIELDS = 'fields';
    public const TYPE_FORMULA = 'formula';

    protected $table = 'ai_form_completions';

    protected $fillable = [
        'access_token_hash',
        'form_prompt',
        'user_id',
        'status',
        'result',
        'ip',
        'error',
        'type',
        'context',
        'generation_params',
    ];

    protected $hidden = ['access_token_hash'];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'type' => self::TYPE_FORM,
    ];

    protected function casts()
    {
        return [
            'result' => 'array',
            'context' => 'array',
            'generation_params' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        // Dispatch completion job on creation
        static::created(function (self $completion) {
            if ($completion->type === self::TYPE_FORM) {
                GenerateAIForm::dispatch($completion);
            } elseif ($completion->type === self::TYPE_FIELDS) {
                GenerateAiFormFields::dispatch($completion);
            } elseif ($completion->type === self::TYPE_FORMULA) {
                GenerateAiFormula::dispatch($completion);
            }
        });
    }
}
