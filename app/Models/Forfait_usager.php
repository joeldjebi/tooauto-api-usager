<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Forfait_usager extends Model
{
    use HasFactory;

    protected $table = 'forfait_usagers';

    protected $casts = [
        'nombre_vehicule' => 'integer',
    ];

    public function avantageUsager()
    {
        return $this->belongsTo(ForfaitAvantageUsager::class, 'forfait_avantage_usager_id');
    }

    public function categorieServices()
    {
        return $this->belongsToMany(
            Categorie_service::class,
            'forfait_usager_categorie_service',
            'forfait_usager_id',
            'categorie_service_id'
        );
    }
}
