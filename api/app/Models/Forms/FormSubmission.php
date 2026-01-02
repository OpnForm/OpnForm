<?php

namespace App\Models\Forms;

use App\Events\Models\FormSubmissionDeleting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;

    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'data',
        'completion_time',
        'status',
        'meta',
        'public_id'
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'completion_time' => 'integer',
            'meta' => 'array',
            'public_id' => 'string',
        ];
    }

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'deleting' => FormSubmissionDeleting::class,
    ];

    /**
     * RelationShips
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
