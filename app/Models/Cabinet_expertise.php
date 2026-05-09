<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cabinet_expertise extends Model
{
    use HasFactory;

    public function ville()
    {
        return $this->belongsTo(Ville::class, 'ville_id', 'id');
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class, 'commune_id', 'id');
    }
}