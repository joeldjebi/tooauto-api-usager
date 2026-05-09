<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicule_concessionnaire extends Model
{
    use HasFactory;
	
	public function concessionnaire()
    {
        return $this->belongsTo(Concessionnaire::class, 'concessionnaire_id');
    }
		
	public function marque()
    {
        return $this->belongsTo(Marque::class, 'marque_id');
    }
}
