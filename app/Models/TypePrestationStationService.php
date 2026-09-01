<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypePrestationStationService extends Model
{
    protected $table = 'type_prestation_station_services';

    protected $fillable = [
        'libelle',
        'name',
        'montant',
        'prix',
        'station_id',
        'station_service_id',
        'establishment_id',
    ];
}
