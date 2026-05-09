<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'ville_id',
        'pays_id',
        'code_postal',
        'latitude',
        'longitude',
        'statut',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'statut' => 'boolean'
    ];

    /**
     * Relation avec la ville
     */
    public function ville()
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    /**
     * Relation avec le pays
     */
    public function pays()
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }

    /**
     * Relation avec les établissements
     */
    public function etablissements()
    {
        return $this->hasMany(Etablissement::class, 'commune_id');
    }

    /**
     * Scope pour les communes actives
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 1);
    }
}
