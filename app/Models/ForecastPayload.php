<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The most recent trained forecast, stored in the database rather than on the
 * container's local disk.
 *
 * A Render Cron Job and a Render web service are separate containers with
 * separate ephemeral filesystems, so a forecast written as a file by one is
 * invisible to the other and is destroyed by the next deploy. Keeping it here
 * lets any instance read it, including after a redeploy.
 */
class ForecastPayload extends Model
{
    protected $table = 'forecast_payloads';

    protected $fillable = [
        'source_type',
        'payload',
        'generated_at',
        'training_status',
        'training_status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            // The document is validated on read, so it is kept as the exact
            // string Python produced rather than decoded here.
            'payload' => 'string',
            'generated_at' => 'datetime',
            'training_status_updated_at' => 'datetime',
        ];
    }

    public const SOURCE_LIVE = 'live';

    public const SOURCE_DEMO = 'demo';
}
