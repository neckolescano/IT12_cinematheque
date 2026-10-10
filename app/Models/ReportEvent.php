<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The report's audit trail: who did what to it, when, and why (an unlock request carries a reason). */
class ReportEvent extends Model
{
    public const ACTIONS = ['generated', 'edited', 'submitted', 'unlock_requested', 'unlocked', 'resubmitted'];

    protected $primaryKey = 'event_id';

    public const UPDATED_AT = null;

    protected $fillable = ['report_id', 'user_id', 'action', 'reason'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id', 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
