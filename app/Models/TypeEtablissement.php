<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeEtablissement extends Model
{
    use HasFactory;
    protected $table = 'type_etablissements';

    protected $fillable = [
        'nom',
        'description',
        'icone',
        'couleur',
        'statut',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'statut' => 'boolean'
    ];

    /**
     * Relation avec les établissements
     */
    public function etablissements()
    {
        return $this->hasMany(Etablissement::class, 'type_etablissement_id');
    }

    /**
     * Scope pour les types actifs
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 1);
    }
}