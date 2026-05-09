<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

	public function vehicule()
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }

	public function type_alert()
    {
        return $this->belongsTo(Type_alert::class, 'type_alert_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}