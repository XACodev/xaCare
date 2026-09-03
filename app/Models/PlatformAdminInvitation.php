<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlatformAdminInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
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
     * @return array{0: self, 1: string} [$invitation, $plainTextToken]
     */
    public static function generateFor(int $invitedBy, ?string $note = null, int $expiresInDays = 7): array
    {
        $plainTextToken = Str::random(64);

        $invitation = static::create([
            'token' => hash('sha256', $plainTextToken),
            'note' => $note,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return [$invitation, $plainTextToken];
    }

    public static function findValidByPlainTextToken(string $plainTextToken): ?self
    {
        return static::where('token', hash('sha256', $plainTextToken))
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
