<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Courier extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'vehicle_type', 'license_plate',
                           'current_latitude', 'current_longitude', 'available', 'rating'];

    protected $casts = ['available' => 'boolean'];

    public function user()   { return $this->belongsTo(User::class); }
    public function orders() { return $this->hasMany(Order::class); }

    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->current_latitude  = $latitude;
        $this->current_longitude = $longitude;
        $this->save();

        app(\App\Support\Logger::class)->log(
            "Courier {$this->id} location updated: {$latitude},{$longitude}"
        );
    }
}
