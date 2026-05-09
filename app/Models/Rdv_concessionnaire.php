<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rdv_concessionnaire extends Model
{
    use HasFactory;

    protected $table = 'rdv_concessionnaires';  // Spécifie explicitement le nom de la table

    protected $fillable = [
        'jour',
        'heure',
        'concessionnaire_id',
        'user_id',
    ];

    public function concessionnaire()
    {
        return $this->belongsTo(Concessionnaire::class, 'concessionnaire_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
