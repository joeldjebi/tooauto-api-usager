<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etablissement extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'adresse',
        'telephone',
        'email',
        'latitude',
        'longitude',
        'type_etablissement_id',
        'pays_id',
        'ville_id',
        'commune_id',
        'statut',
        'description',
        'horaires',
        'site_web',
        'logo',
        'images',
        'services',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'statut' => 'boolean',
        'images' => 'array',
        'services' => 'array',
        'horaires' => 'array'
    ];

    /**
     * Relation avec le type d'établissement
     */
    public function type_etablissement()
    {
        return $this->belongsTo(TypeEtablissement::class, 'type_etablissement_id');
    }

    /**
     * Relation avec le pays
     */
    public function pays()
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }
	
	public function notations()
    {
        return $this->hasMany(Notation::class, 'etablissement_id');
    }

    /**
     * Relation avec la ville
     */
    public function ville()
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    /**
     * Relation avec la commune
     */
    public function commune()
    {
        return $this->belongsTo(Commune::class, 'commune_id');
    }

    /**
     * Relation avec les articles
     */
    public function articles()
    {
        return $this->hasMany(Article::class, 'etablissement_id');
    }

    /**
     * Scope pour les établissements actifs
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 1);
    }

    /**
     * Scope pour filtrer par type d'établissement
     */
    public function scopeByType($query, $typeId)
    {
        return $query->where('type_etablissement_id', $typeId);
    }

    /**
     * Scope pour les établissements dans un rayon donné
     */
    public function scopeWithinRadius($query, $latitude, $longitude, $radius = 50)
    {
        return $query->selectRaw("
            *,
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(latitude))
            )) AS distance
        ", [$latitude, $longitude, $latitude])
        ->having('distance', '<=', $radius)
        ->orderBy('distance');
    }
}
