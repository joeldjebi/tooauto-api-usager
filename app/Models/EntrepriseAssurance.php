<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntrepriseAssurance extends Model
{
    use HasFactory;

    protected $table = 'entreprises_assurances';

    protected $casts = [
        'telephones' => 'array',
    ];
}
