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
        return $this->hasMany(Sous_categorie_service::class, 'ss_categorie_service_id');
    }
}
