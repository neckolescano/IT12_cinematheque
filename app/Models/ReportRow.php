<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One screening's line in a report, frozen when the report is generated (as on the Manila sheets:
 * viewers M/F, PWD, Senior, total audience, occupancy; paid rows also regular/discount tickets and sales).
 */
class ReportRow extends Model
{
    protected $primaryKey = 'report_row_id';

    public $timestamps = false;

    protected $fillable = [
        'report_id', 'screening_id', 'type', 'screening_date', 'start_time', 'film_title', 'films_count',
        'male', 'female', 'pwd', 'senior', 'total_audience', 'occupancy_rate',
        'regular_count', 'discount_count', 'total_sales', 'partner', 'agency_type', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'screening_date' => 'date',
            'occupancy_rate' => 'decimal:2',
            'total_sales' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id', 'report_id');
    }

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class, 'screening_id', 'screening_id');
    }
}
