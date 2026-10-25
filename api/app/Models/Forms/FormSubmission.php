<?php

namespace App\Models\Forms;

use App\Events\Models\FormSubmissionDeleting;
use App\Events\Models\FormSubmissionSaved;
use App\Events\Models\FormSubmissionUpdating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Version;
use Mpociot\Versionable\VersionableTrait;
use App\Contracts\VersionableNestedDiff;

class FormSubmission extends Model implements VersionableNestedDiff
{
    use HasFactory;
    use VersionableTrait;

    // Configure versioning
    protected $versionClass = Version::class;
    protected $keepOldVersions = 5;
    protected $dontVersionFields = [
        'created_at',
        'updated_at',
    ];

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
        'saved' => FormSubmissionSaved::class,
        'updating' => FormSubmissionUpdating::class,
    ];

    /**
     * RelationShips
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function storedFiles()
    {
        return $this->hasMany(FormSubmissionFile::class);
    }

    public function getVersionNestedDiffFields(): array
    {
        return ['data'];
    }
}
