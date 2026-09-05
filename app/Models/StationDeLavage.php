<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationDeLavage extends Model
{
    protected $table = 'station_de_lavages';

    protected $fillable = [
        'name',
        'contact',
        'longitude',
        'latitude',
        'adresse',
        'logo',
        'statut',
        'created_by',
        'call_center_deja_appele',
        'call_center_commentaire',
        'call_center_called_at',
    ];

    protected $casts = [
        'statut' => 'integer',
        'call_center_deja_appele' => 'boolean',
        'call_center_called_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeLavages()
    {
        return $this->hasMany(TypeLavage::class, 'lavage_id', 'id');
    }
}
