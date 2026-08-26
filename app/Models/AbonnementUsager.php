<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbonnementUsager extends Model
{
    use HasFactory;

    protected $table = 'abonnement_usagers';

    protected $fillable = [
        'user_id',
        'forfait_id',
        'date_debut',
        'date_fin',
        'statut',
        'is_free'
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'statut' => 'integer',
        'is_free' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation avec le forfait
    public function forfait()
    {
        return $this->belongsTo(Forfait_usager::class, 'forfait_id');
    }

    public function reductionCards()
    {
        return $this->hasMany(UserReductionCard::class, 'abonnement_usager_id');
    }
}
