<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ville extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
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
        return $this->hasMany(Etablissement::class, 'ville_id');
    }

    /**
     * Relation avec les communes
     */
    public function communes()
    {
        return $this->hasMany(Commune::class, 'ville_id');
    }

    /**
     * Scope pour les villes actives
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 1);
    }
}
