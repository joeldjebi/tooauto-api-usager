<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notation extends Model
{
    use HasFactory;

    protected $fillable = [
        'etablissement_id',
        'user_id',
        'note',
        'commentaire',
    ];

    protected $casts = [
        'note' => 'double',
    ];

    public function etablissement()
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
