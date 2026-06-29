<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annonce_concessionnaire extends Model
{
    use HasFactory;

    // Définir les colonnes qui peuvent être assignées en masse
    protected $fillable = [
        'type_de_demande_id',
        'type_de_vehicule_id',
        'marque_id',
        'modele',
        'user_id',
        'statut',
        'concessionaire_id',
    ];

    // Si vous avez des relations avec d'autres modèles, vous pouvez les définir ici.
    // Par exemple, si vous avez des relations avec les modèles TypeDeDemande, TypeDeVehicule, etc.
    public function type_de_demande()
    {
        return $this->belongsTo(Type_de_demande::class);
    }

    public function type_de_vehicule()
    {
        return $this->belongsTo(Type_de_vehicule::class);
    }

    public function marque()
    {
        return $this->belongsTo(Marque::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function concessionnaire()
    {
        return $this->belongsTo(Concessionnaire::class, 'concessionaire_id');
    }
}
