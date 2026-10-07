<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps a Telkomsel physical-voucher zone/region string to an administrative city.
 *
 * The Telkomsel /check response returns an opaque zone code (e.g. "SIKKA").
 * This table translates that zone into a `cities` row so vouchers and packages
 * can be surfaced only to outlets located in the matching kabupaten.
 *
 * @property int $id
 * @property string $region
 * @property int $city_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['region', 'city_id'])]
class TelkomselArea extends Model
{
    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
