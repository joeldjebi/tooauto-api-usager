<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie_service extends Model
{
    use HasFactory;

    protected $table = 'categorie_services';

    protected $casts = [
        'accessible_abonnement_expire' => 'boolean',
        'visible_par_defaut' => 'boolean',
        'accessible_en_fonction_de_mon_abonnement_actif' => 'boolean',
    ];

    public function sousCategorieService()
    {
        return $this->belongsTo(Sous_categorie_service::class, 'sous_categorie_service_id');
    }

    public function sousCategorieServices()
    {
        return $this->belongsToMany(
            Sous_categorie_service::class,
            'categorie_service_sous_categorie_service',
            'categorie_service_id',
            'sous_categorie_service_id'
        )->withTimestamps();
    }
}
