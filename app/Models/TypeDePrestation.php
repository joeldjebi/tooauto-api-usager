<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeDePrestation extends Model
{
    use HasFactory;

    protected $table = 'type_de_prestations';

    protected $fillable = [
        'libelle',
        'description',
        'statut',
    ];

    /**
     * Relation avec les établissements
     */
    public function etablissements()
    {
        return $this->belongsToMany(Etablissement::class, 'etablissement_type_prestations', 'type_prestation_id', 'etablissement_id');
    }
}
