<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name', 'code', 'address', 'phone', 'has_service',
        'latitude', 'longitude', 'geofence_radius_m',
    ];

    protected $casts = ['has_service' => 'boolean'];

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    /** User yang menjadikan cabang ini sebagai cabang utama. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Semua user yang boleh absen di cabang ini, termasuk sebagai cabang kedua. */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withTimestamps();
    }

    /** Jarak titik (lat,lng) ke cabang, dalam meter (haversine). Null kalau koordinat cabang belum diset. */
    public function distanceToMeters(float $lat, float $lng): ?int
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $earthRadius = 6371000;
        $dLat = deg2rad($lat - (float) $this->latitude);
        $dLng = deg2rad($lng - (float) $this->longitude);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
