<?php

namespace App\Models\Forms\AI;

use App\Jobs\Form\GenerateAiForm;
use App\Jobs\Form\GenerateAiFormFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiFormCompletion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_FORM = 'form';
    public const TYPE_FIELDS = 'fields';

    protected $table = 'ai_form_completions';

    protected $fillable = [
        'form_prompt',
        'status',
        'result',
        'ip',
        'error',
        'type',
        'context',
        'generation_params',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'type' => self::TYPE_FORM,
    ];

    protected function casts()
    {
        return [
            'context' => 'array',
            'generation_params' => 'array',
        ];
    }

    protected static function booted()
    {
        // Dispatch completion job on creation
        static::created(function (self $completion) {
            if ($completion->type === self::TYPE_FORM) {
                GenerateAIForm::dispatch($completion);
            } elseif ($completion->type === self::TYPE_FIELDS) {
                GenerateAiFormFields::dispatch($completion);
            }
        });
    }
}
