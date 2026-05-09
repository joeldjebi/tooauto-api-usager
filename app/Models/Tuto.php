<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tuto extends Model
{
    protected $table = 'tutos';

    use HasFactory;
	
	public function categorie_tuto()
    {
        return $this->belongsTo(Categorie_tuto::class, 'categorie_tuto_id');
    }
}