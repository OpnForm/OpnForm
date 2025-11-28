<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormView extends Model
{
    use HasFactory;

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
