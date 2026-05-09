<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sapeur_pompier extends Model
{
    use HasFactory;

    public function ville()
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class, 'commune_id');
    }

}