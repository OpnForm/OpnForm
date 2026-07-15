<?php

namespace App\Models;

use App\Events\Models\UserWorkspaceCreated;
use App\Traits\EnsureUserHasWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWorkspace extends Model
{
    use HasFactory;
    use EnsureUserHasWorkspace;

    protected $table = 'user_workspace';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'role',
    ];

    protected $dispatchesEvents = [
        'created' => UserWorkspaceCreated::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function (UserWorkspace $userWorkspace) {
            $userWorkspace->ensureUserHasWorkspace($userWorkspace->user);
        });
    }
}
