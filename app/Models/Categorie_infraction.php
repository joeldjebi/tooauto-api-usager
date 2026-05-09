<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie_infraction extends Model
{
    protected $table = 'categorie_infractions';

    use HasFactory;
	
	public function infractions()
    {
        return $this->HasMany(Infraction::class);
    }
}