<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Manila report of one program: free and paid screenings in one table.
 * draft → submitted (read-only for admins) → unlocked (by the Super Admin only) → submitted again.
 */
class Report extends Model
{
    public const STATUSES = ['draft', 'submitted', 'unlocked'];

    protected $primaryKey = 'report_id';

    protected $fillable = ['program_id', 'status', 'generated_by', 'submitted_by', 'submitted_at', 'unlocked_by', 'unlocked_at'];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    /** A submitted report can't be changed until the Super Admin unlocks it. */
    public function isLocked(): bool
    {
        return $this->status === 'submitted';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Submitted · locked',
            'unlocked' => 'Unlocked for changes',
            default => 'Draft',
        };
    }

    /** The open unlock request: asked for after the last submission and not yet answered. */
    public function pendingUnlockRequest(): ?ReportEvent
    {
        if (! $this->isLocked()) {
            return null;
        }
        $latest = $this->events->sortByDesc('event_id')
            ->first(fn (ReportEvent $e) => in_array($e->action, ['submitted', 'resubmitted', 'unlock_requested', 'unlocked'], true));

        return $latest?->action === 'unlock_requested' ? $latest : null;
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ReportRow::class, 'report_id', 'report_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReportEvent::class, 'report_id', 'report_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by', 'user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'user_id');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by', 'user_id');
    }
}
