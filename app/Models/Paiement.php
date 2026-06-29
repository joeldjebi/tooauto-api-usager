<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    use HasFactory;

    protected $fillable = [
        'referenceNumber',
        'amount',
        'description',
        'countryCurrencyCode',
        'customerEmail',
        'customerFirstName',
        'customerLastname',
        'customerPhoneNumber',
        'user_id',
        'forfait_id',
        'statut',
        'fineopay_reference',
        'checkout_link',
        'date_debut',
        'date_fin',
        'reponse_api',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'reponse_api' => 'array',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation avec le forfait
    public function forfait()
    {
        return $this->belongsTo(Forfait::class);
    }
}
