<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartenairePromo extends Model
{
    protected $table = 'partenaires_promo';

    protected $fillable = [
        'nom',
        'email',
        'telephone',
        'adresse',
        'statut',
        'created_by',
    ];

    public function codePromo()
    {
        return $this->hasOne(CodePromo::class, 'partenaire_promo_id');
    }
}
