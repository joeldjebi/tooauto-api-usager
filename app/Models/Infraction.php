<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Infraction extends Model
{
    protected $table = 'infractions';

    use HasFactory;
	
	public function categorie_infraction()
    {
        return $this->belongsTo(Categorie_infraction::class, 'categorie_infraction_id');
    }
}