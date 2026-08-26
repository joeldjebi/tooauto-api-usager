<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodePromoUtilisation extends Model
{
    protected $table = 'code_promo_utilisations';

    protected $fillable = [
        'code_promo_id',
        'user_id',
        'abonnement_usager_id',
        'forfait_usager_id',
        'paiement_id',
        'montant_initial',
        'montant_reduction',
        'montant_final',
    ];
}
