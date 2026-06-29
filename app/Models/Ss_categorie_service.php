<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ss_categorie_service extends Model
{
    use HasFactory;

    protected $table = 'ss_categorie_services';

    public function sousCategorieServices()
    {
        return $this->belongsToMany(
            Sous_categorie_service::class,
            'sous_categorie_service_ss_categorie_service',
            'ss_categorie_service_id',
            'sous_categorie_service_id'
        )->withTimestamps();
    }

    public function sousCategorieServicesLegacy()
    {
        return $this->hasMany(Sous_categorie_service::class, 'ss_categorie_service_id');
    }
}
