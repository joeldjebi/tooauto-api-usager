<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Concessionnaire extends Model
{
    protected $table = 'concessionnaires';

    use HasFactory;

    public function rdvs()
    {
        return $this->hasMany(Rdv_concessionnaire::class, 'concessionnaire_id');
    }
	
	public function userconcessionnaire()
    {
        return $this->belongsTo(Userconcessionnaire::class, 'userconcessionnaire_id');
    }
}