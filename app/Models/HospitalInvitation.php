<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HospitalInvitation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hospital_id',
        'token',
        'note',
        'invited_by',
        'expires_at',
        'accepted_at',
        'accepted_by',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Create a new invitation and return both the model and the ONE-TIME
     * plaintext token. Only the SHA-256 hash of the token is persisted;
     * the plaintext value is never stored and cannot be recovered later.
     *
     * @return array{0: self, 1: string} [$invitation, $plainTextToken]
     */
    public static function generateFor(int $hospitalId, ?int $invitedBy = null, ?string $note = null, int $expiresInDays = 7): array
    {
        $plainTextToken = Str::random(64);

        $invitation = static::create([
            'hospital_id' => $hospitalId,
            'token' => hash('sha256', $plainTextToken),
            'note' => $note,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return [$invitation, $plainTextToken];
    }

    /**
     * Look up an invitation by its plaintext token, but ONLY return it when
     * it is still valid (exists, not expired, not already used). This is
     * the single entry point the public acceptance flow should use so that
     * "doesn't exist", "expired" and "already used" are indistinguishable
     * to a caller — all three simply return null.
     */
    public static function findValidByPlainTextToken(string $plainTextToken): ?self
    {
        return static::withoutGlobalScopes()
            ->where('token', hash('sha256', $plainTextToken))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function status(): string
    {
        return match (true) {
            $this->isAccepted() => 'accepted',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
