<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The provider-written part of the Recommendations tab, saved per forecast
 * cycle.
 *
 * Only what the AI contributed is stored: the brief and one action line per
 * item. Quantities, priorities and queue membership are never persisted here —
 * they are recomputed from the stored forecast on every read, so a saved row
 * can never make the tab disagree with the table beside it.
 */
class ForecastAiRecommendation extends Model
{
    protected $table = 'forecast_ai_recommendations';

    protected $fillable = [
        'source_type',
        'forecast_generated_at',
        'forecast_period',
        'brief',
        'queues',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'queues' => 'array',
            'forecast_generated_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public const SOURCE_LIVE = 'live';

    public const SOURCE_DEMO = 'demo';
}
