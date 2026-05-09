<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sous_categorie_service extends Model
{
    use HasFactory;

    protected $table = 'sous_categorie_services';

    public function categorieServices()
    {
        return $this->hasMany(Categorie_service::class, 'sous_categorie_service_id');
    }

    public function ssCategorieService()
    {
        return $this->belongsTo(Ss_categorie_service::class, 'ss_categorie_service_id');
    }
}
