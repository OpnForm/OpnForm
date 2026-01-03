<?php

namespace App\Models\Forms;

use App\Events\Models\FormCreated;
use App\Models\Integration\FormIntegration;
use App\Models\Integration\FormZapierWebhook;
use App\Models\Traits\CachableAttributes;
use App\Models\Traits\CachesAttributes;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Stevebauman\Purify\Facades\Purify;
use Carbon\Carbon;
use App\Events\Forms\FormSaved;

class Form extends Model implements CachableAttributes
{
    use CachesAttributes;

    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    public const DARK_MODE_VALUES = ['auto', 'light', 'dark'];

    public const SIZES = ['sm', 'md', 'lg'];

    public const BORDER_RADIUS = ['none', 'small', 'full'];

    public const THEMES = ['default', 'simple', 'notion', 'minimal', 'transparent'];

    public const PRESENTATION_STYLES = ['classic', 'focused'];

    public const WIDTHS = ['centered', 'full'];

    public const VISIBILITY = ['public', 'draft', 'closed'];

    public const LANGUAGES = ['en', 'fr', 'hi', 'es', 'ar', 'zh', 'ja', 'bn', 'pt', 'ru', 'ur', 'pa', 'de', 'jv', 'ko', 'vi', 'te', 'mr', 'ta', 'tr', 'sk', 'cs', 'eu', 'gl', 'ca', 'sv', 'pl', 'nl', 'sr', 'uk'];

    protected $fillable = [
        'workspace_id',
        'creator_id',
        'properties',
        'removed_properties',

        'title',
        'tags',
        'visibility',

        // Customization
        'language',
        'font_family',
        'custom_domain',
        'size',
        'border_radius',
        'theme',
        'presentation_style',
        'width',
        'layout_rtl',
        'cover_picture',
        'cover_settings',
        'logo_picture',
        'dark_mode',
        'color',
        'uppercase_labels',
        'no_branding',
        'transparent_background',
        'translations',

        // Settings
        'settings',

        // Custom Code
        'custom_code',
        'custom_css',

        // Submission
        'submit_button_text',
        'database_fields_update',
        'clear_empty_fields_on_update',
        're_fillable',
        're_fill_button_text',
        'submitted_text',
        'redirect_url',
        'use_captcha',
        'captcha_provider',
        'closes_at',
        'closed_text',
        'max_submissions_count',
        'max_submissions_reached_text',
        'editable_submissions',
        'editable_submissions_button_text',
        'confetti_on_submission',
        'show_progress_bar',
        'auto_save',
        'auto_focus',
        'enable_partial_submissions',
        'enable_ip_tracking',

        // Security & Privacy
        'can_be_indexed',
        'password',

        // Custom SEO
        'seo_meta',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'database_fields_update' => 'array',
            'closes_at' => 'datetime',
            'tags' => 'array',
            'removed_properties' => 'array',
            'seo_meta' => 'object',
            'cover_settings' => 'array',
            'translations' => 'array',
            'enable_partial_submissions' => 'boolean',
            'enable_ip_tracking' => 'boolean',
            'auto_save' => 'boolean',
            'clear_empty_fields_on_update' => 'boolean',
            'presentation_style' => 'string',
            'settings' => 'array',
        ];
    }

    protected $appends = [
        'share_url',
    ];

    protected $hidden = [
        'workspace_id',
        'redirect_url',
        'database_fields_update',
        'password',
        'tags',
        'removed_properties',
    ];

    protected $cachableAttributes = [
        'is_pro',
        'views_count',
        'max_file_size',
        'submissions_count',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => FormCreated::class,
        'saved' => FormSaved::class,
    ];

    public function getIsProAttribute()
    {
        return $this->remember('is_pro', 15 * 60, function (): ?bool {
            return $this->workspace?->is_pro === true;
        });
    }

    public function getShareUrlAttribute()
    {
        if ($this->custom_domain) {
            return 'https://' . $this->custom_domain . '/forms/' . $this->slug;
        }

        return front_url('/forms/' . $this->slug);
    }

    public function getSubmissionsUrlAttribute()
    {
        if ($this->custom_domain) {
            return 'https://' . $this->custom_domain . '/forms/' . $this->slug . '/show/submissions';
        }

        return front_url('/forms/' . $this->slug . '/show/submissions');
    }

    public function getEditUrlAttribute()
    {
        return front_url('/forms/' . $this->slug . '/show');
    }

    public function getSubmissionsCountAttribute()
    {
        // If hydrated from withCount, use it directly (no cache needed)
        if (isset($this->attributes['submissions_count'])) {
            return $this->attributes['submissions_count'];
        }

        // Fallback to cached query for individual form access
        return $this->remember('submissions_count', 15 * 60, function (): int {
            return $this->submissions()->where('status', FormSubmission::STATUS_COMPLETED)->count();
        });
    }

    public function getViewsCountAttribute()
    {
        // If preloaded via scope, use the combined count
        if (isset($this->attributes['total_views_count'])) {
            return (int) $this->attributes['total_views_count'];
        }

        // Fallback to cached calculation for individual access
        return $this->remember('views_count', 15 * 60, fn () => $this->calculateTotalViews());
    }

    /**
     * Calculate total views from both views table and statistics table
     */
    private function calculateTotalViews(): int
    {
        $directViews = $this->views()->count();
        $statisticsViews = $this->getStatisticsViewsSum();
        return $directViews + $statisticsViews;
    }

    /**
     * Get views sum from statistics table (database agnostic)
     */
    private function getStatisticsViewsSum(): int
    {
        return (int) $this->statistics()->sum(
            config('database.default') === 'mysql'
                ? DB::raw("CAST(JSON_EXTRACT(data, '$.views') AS SIGNED)")
                : DB::raw("CAST(data->>'views' AS INTEGER)")
        );
    }

    /**
     * Scope to efficiently load total views count
     */
    public function scopeWithTotalViews($query)
    {
        $statisticsExpression = config('database.default') === 'mysql'
            ? 'SUM(CAST(JSON_EXTRACT(data, "$.views") AS SIGNED))'
            : 'SUM(CAST(data->>\'views\' AS INTEGER))';

        return $query->addSelect([
            'total_views_count' => DB::raw(
                'COALESCE((SELECT COUNT(*) FROM form_views WHERE form_views.form_id = forms.id), 0) + ' .
                    'COALESCE((SELECT ' . $statisticsExpression . ' FROM form_statistics WHERE form_statistics.form_id = forms.id), 0)'
            )
        ]);
    }



    public function setSubmittedTextAttribute($value)
    {
        // Strip out unwanted html
        $this->attributes['submitted_text'] = Purify::clean($value);
    }

    public function setTagsAttribute($value)
    {
        if ($value == '') {
            $value = null;
        }
        $this->attributes['tags'] = json_encode($value);
    }

    public function setClosesAtAttribute($value)
    {
        $this->attributes['closes_at'] = ($value) ? Carbon::parse($value)->setTimezone('UTC') : null;
    }

    public function getClosesAtAttribute($value)
    {
        if (!$value) {
            return $value;
        }
        // Retrieve the desired timezone from the request or default to 'UTC'
        $timezone = request()->get('timezone', 'UTC');
        return Carbon::parse($value)->setTimezone($timezone)->toIso8601String();
    }

    public function getIsClosedAttribute()
    {
        return $this->visibility === 'closed' || ($this->closes_at && now()->gt($this->closes_at));
    }

    public function getFormPendingSubmissionKeyAttribute()
    {
        if ($this->updated_at?->timestamp) {
            return 'openform-' . $this->id . '-pending-submission-' . substr($this->updated_at?->timestamp, -6);
        }

        return null;
    }

    public function getMaxNumberOfSubmissionsReachedAttribute()
    {
        return $this->max_submissions_count && $this->max_submissions_count <= $this->submissions_count;
    }

    public function setClosedTextAttribute($value)
    {
        $this->attributes['closed_text'] = Purify::clean($value);
    }

    public function setMaxSubmissionsReachedTextAttribute($value)
    {
        $this->attributes['max_submissions_reached_text'] = Purify::clean($value);
    }

    public function getHasPasswordAttribute()
    {
        return !empty($this->password);
    }

    public function getMaxFileSizeAttribute()
    {
        return $this->remember('max_file_size', 15 * 60, function (): int {
            return $this->workspace->max_file_size;
        });
    }

    protected function removedProperties(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $value ? json_decode($value, true) : [];
            }
        );
    }

    /**
     * Relationships
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function views()
    {
        return $this->hasMany(FormView::class);
    }

    public function statistics()
    {
        return $this->hasMany(FormStatistic::class);
    }

    public function zappierHooks()
    {
        return $this->hasMany(FormZapierWebhook::class);
    }

    public function integrations()
    {
        return $this->hasMany(FormIntegration::class);
    }

    /**
     * Config/options
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->doNotGenerateSlugsOnUpdate()
            ->generateSlugsFrom(function (Form $form) {
                return $form->title . ' ' . Str::random(6);
            })
            ->saveSlugsTo('slug');
    }

    public static function newFactory()
    {
        return FormFactory::new();
    }

    public static function booted(): void
    {
        static::deleting(function (Form $form) {
            $form->integrations()
                ->lazyById()
                ->each(function (FormIntegration $integration): void {
                    $integration->delete();
                });
        });
    }
}
