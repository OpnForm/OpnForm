<?php

namespace App\Models;

use App\Jobs\Billing\WorkspaceUsersUpdated;
use App\Mail\UserInvitationEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserInvite extends Model
{
    use HasFactory;

    public const PENDING_STATUS = 'pending';
    public const ACCEPTED_STATUS = 'accepted';

    protected $fillable = [
        'email',
        'role',
        'workspace_id',
        'valid_until',
        'status',
        'token',
    ];

    public static function inviteUser(
        string $email,
        string $role,
        Workspace $workspace,
        Carbon $validUntil = null
    ): self {
        // Generate a token
        do {
            $token = Str::random(100);
        } while (UserInvite::where('token', $token)->exists());

        $invite = self::create([
            'email' => $email,
            'role' => $role,
            'workspace_id' => $workspace->id,
            'valid_until' => $validUntil ?? now()->addDays(7),
            'token' => $token,
        ]);
        $invite->sendEmail();
        return $invite;
    }

    public function getLink()
    {
        return front_url('/register?email=' . urlencode($this->email) . '&invite_token=' . urlencode($this->token));
    }

    public function hasExpired()
    {
        return Carbon::parse($this->valid_until)->isPast();
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function markAsAccepted()
    {
        // Use conditional atomic update as secondary guard against race conditions
        $updated = $this->where('id', $this->id)
            ->where('status', self::PENDING_STATUS)
            ->update(['status' => self::ACCEPTED_STATUS]);

        if ($updated === 0) {
            // Invite was already accepted or doesn't exist
            throw new \RuntimeException('Invite could not be marked as accepted - may already be processed.');
        }

        // Refresh the model to reflect the updated status
        $this->refresh();

        WorkspaceUsersUpdated::dispatch($this->workspace);
        return $this;
    }

    public function sendEmail()
    {
        Mail::to($this->email)->send(new UserInvitationEmail($this));
    }

    public function scopeNotExpired($query)
    {
        return $query->where('valid_until', '>', now());
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING_STATUS);
    }
}
