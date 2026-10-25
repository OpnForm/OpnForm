<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Model;

class AgentFormDraftVersion extends Model
{
    protected $fillable = ['version', 'schema_version', 'definition'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'schema_version' => 'integer', 'definition' => 'array'];
    }
}
