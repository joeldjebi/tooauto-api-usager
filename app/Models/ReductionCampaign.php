<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReductionCampaign extends Model
{
    protected $table = 'reduction_campaigns';

    protected $fillable = [
        'establishment_type',
        'establishment_id',
        'name',
        'image',
        'description',
        'product_or_service',
        'discount_type',
        'discount_value',
        'normal_price',
        'promotional_price',
        'date_debut',
        'date_fin',
        'quantity_available',
        'quantity_used',
        'conditions',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'normal_price' => 'decimal:2',
        'promotional_price' => 'decimal:2',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'quantity_available' => 'integer',
        'quantity_used' => 'integer',
        'statut' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
