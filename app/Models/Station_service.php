<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station_service extends Model
{
    use HasFactory;

    /**
     * Les attributs pouvant être remplis via une affectation de masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'ville_id',
        'commune_id',
        'adresse',
        'adresse_map',
        'borne_electrique',
    ];

    /**
     * Les attributs protégés (non remplissables via une affectation de masse).
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * Relations : Une station-service appartient à une ville.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ville()
    {
        return $this->belongsTo(Ville::class);
    }

    /**
     * Relations : Une station-service peut appartenir à une commune.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }
}