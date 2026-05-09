<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tv extends Model
{
    protected $table = 'tvs';

    use HasFactory;
	
	public function categorie_tv()
    {
        return $this->belongsTo(Categorie_tv::class, 'categorie_tv_id');
    }
}