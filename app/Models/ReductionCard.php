<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReductionCard extends Model
{
    protected $table = 'reduction_cards';

    protected $fillable = [
        'forfait_usager_id',
        'name',
        'nom',
        'discount_type',
        'discount_value',
        'description',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'statut' => 'integer',
    ];

    public function forfaitUsager()
    {
        return $this->belongsTo(Forfait_usager::class, 'forfait_usager_id');
    }

    public function userReductionCards()
    {
        return $this->hasMany(UserReductionCard::class, 'reduction_card_id');
    }
}
