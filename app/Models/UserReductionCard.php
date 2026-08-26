<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReductionCard extends Model
{
    protected $table = 'user_reduction_cards';

    protected $fillable = [
        'reduction_card_id',
        'user_id',
        'abonnement_usager_id',
        'forfait_usager_id',
        'card_code',
        'qr_code',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'statut' => 'integer',
    ];

    public function reductionCard()
    {
        return $this->belongsTo(ReductionCard::class, 'reduction_card_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function abonnementUsager()
    {
        return $this->belongsTo(AbonnementUsager::class, 'abonnement_usager_id');
    }

    public function forfaitUsager()
    {
        return $this->belongsTo(Forfait_usager::class, 'forfait_usager_id');
    }

    public function histories()
    {
        return $this->hasMany(ReductionCardHistory::class, 'user_reduction_card_id');
    }
}
