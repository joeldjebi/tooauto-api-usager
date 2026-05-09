<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pays extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'code_iso',
        'code_telephone',
        'devise',
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
        return $this->hasMany(Etablissement::class, 'pays_id');
    }

    /**
     * Relation avec les villes
     */
    public function villes()
    {
        return $this->hasMany(Ville::class, 'pays_id');
    }

    /**
     * Scope pour les pays actifs
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 1);
    }
}
