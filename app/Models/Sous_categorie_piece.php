<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sous_categorie_piece extends Model
{
    use HasFactory;

    public function categorie_piece()
    {
        return $this->belongsTo(Categorie_piece::class, 'categorie_piece_id', 'id');
    }
}