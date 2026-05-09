<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    use HasFactory;

    protected $casts = [
        'photos' => 'array',
    ];
	
	public function marque()
    {
        return $this->belongsTo(Marque::class, 'marque_id', 'id');
    }
}
