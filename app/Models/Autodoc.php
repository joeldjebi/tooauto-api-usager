<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Autodoc extends Model
{
    use HasFactory;

    protected $casts = [
        'images' => 'array',
    ];

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id', 'id');
    }

    public function type_docauto()
    {
        return $this->belongsTo(Type_docauto::class, 'type_docauto_id', 'id');
    }
}
