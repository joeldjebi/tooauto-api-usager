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
        'statut'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'update_at' => 'datetime'
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