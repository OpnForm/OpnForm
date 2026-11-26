<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormView extends Model
{
    use HasFactory;

    public const COUNTRY_ENRICHMENT_WINDOW_SECONDS = 300;

    protected $fillable = ['meta'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * RelationShips
     */
    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
