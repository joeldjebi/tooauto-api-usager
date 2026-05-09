<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    use HasFactory;

    protected $table = 'annonces';  // Assurez-vous que le nom de la table est correct
    protected $fillable = [
        'libelle', 'image', 'description', 'usager_id', 
        'type_etablissement_id', 'type_de_piece_id', 'marque_id', 'statut'
    ];
    
    // Relations (si besoin, à adapter selon les relations de la table)
    public function usager()
    {
        return $this->belongsTo(User::class, 'usager_id');
    }

    public function typeEtablissement()
    {
        return $this->belongsTo(TypeEtablissement::class, 'type_etablissement_id');
    }

    public function typeDePiece()
    {
        return $this->belongsTo(TypeDePiece::class, 'type_de_piece_id');
    }

    public function marque()
    {
        return $this->belongsTo(Marque::class, 'marque_id');
    }
	
	public function annonces()
	{
		return $this->belongsToMany(Annonce::class, 'annonce_etablissements')
			->withPivot('is_visible', 'created_at', 'updated_at'); // Ajouter les colonnes nécessaires
	}
}