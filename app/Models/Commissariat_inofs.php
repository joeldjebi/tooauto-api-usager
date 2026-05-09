<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commissariat_inofs extends Model
{
    use HasFactory;

    protected $table = 'commissariat_inofs';

    protected $fillable = [
        'categorie',
        'nom',
        'commune',
        'ville',
        'situation_geographique',
        'contacts',
        'statut',
    ];

    protected $casts = [
        'contacts' => 'array',
        'statut' => 'boolean',
    ];
}
