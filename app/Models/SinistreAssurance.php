<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SinistreAssurance extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_assurance_id',
        'user_id',
        'immatriculation_vehicule',
        'numero_police_assurance',
        'photos',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    public function entrepriseAssurance()
    {
        return $this->belongsTo(EntrepriseAssurance::class, 'entreprise_assurance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
