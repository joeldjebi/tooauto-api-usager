<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodePromo extends Model
{
    protected $table = 'codes_promo';

    protected $fillable = [
        'partenaire_promo_id',
        'forfait_usager_id',
        'code',
        'pourcentage',
        'date_debut',
        'date_fin',
        'usage_limit',
        'usage_count',
        'is_unlimited',
        'one_use_per_user',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'pourcentage' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'is_unlimited' => 'boolean',
        'one_use_per_user' => 'boolean',
        'statut' => 'integer',
    ];

    public function partenaire()
    {
        return $this->belongsTo(PartenairePromo::class, 'partenaire_promo_id');
    }

    public function forfaitUsager()
    {
        return $this->belongsTo(Forfait_usager::class, 'forfait_usager_id');
    }

    public function utilisations()
    {
        return $this->hasMany(CodePromoUtilisation::class, 'code_promo_id');
    }
}
