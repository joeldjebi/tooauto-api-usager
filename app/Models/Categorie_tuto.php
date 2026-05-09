<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie_tuto extends Model
{
    protected $table = 'categorie_tutos';

    use HasFactory;
	
	public function tutos()
    {
        return $this->HasMany(Tuto::class);
    }
}