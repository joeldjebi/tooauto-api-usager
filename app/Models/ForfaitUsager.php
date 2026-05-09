<?php

// app/Models/ForfaitUsager.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForfaitUsager extends Model
{
    protected $table = 'forfait_usagers';

    public function avantageUsager()
    {
        return $this->belongsTo(ForfaitAvantageUsager::class, 'forfait_avantage_usager_id');
    }

    public function avantages()
    {
        return $this->belongsToMany(
            ForfaitAvantageUsager::class,
            'forfait_et_avantage_usagers',
            'forfait_usager_id',
            'forfait_avantage_usager_id'
        )->withPivot('is_active');
    }
}
