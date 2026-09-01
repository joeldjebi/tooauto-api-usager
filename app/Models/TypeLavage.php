<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeLavage extends Model
{
    protected $table = 'type_lavages';

    protected $fillable = [
        'libelle',
        'montant',
        'montant_laveur',
        'lavage_id',
    ];
}
