<?php

namespace App\Http\Controllers;

use App\Models\Notation;
use Illuminate\Http\Request;
use Validator;

class NotationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'etablissement_id' => 'required|exists:etablissements,id',
            'user_id' => 'required|exists:users,id',
            'note' => 'required|numeric|min:0|max:5',
            'commentaire' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $notation = Notation::updateOrCreate(
                [
                    'etablissement_id' => $request->etablissement_id,
                    'user_id' => $request->user_id,
                ],
                [
                    'note' => (double) $request->note,
                    'commentaire' => $request->commentaire,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Notation enregistrée avec succès.',
                'notation' => $notation->load('user', 'etablissement'),
            ], $notation->wasRecentlyCreated ? 201 : 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'enregistrement de la notation.",
                'dev' => $e->getMessage(),
            ], 500);
        }
    }

    public function getByEtablissement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'etablissement_id' => 'required|exists:etablissements,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notations = Notation::where('etablissement_id', $request->etablissement_id)
            ->with('user')
            ->orderBy('id', 'desc')
            ->get();

        if ($notations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune notation enregistrée pour cet établissement.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Liste des notations de l'établissement.",
            'moyenne' => round($notations->avg('note'), 2),
            'total' => $notations->count(),
            'notations' => $notations,
        ], 200);
    }

    public function getByUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notations = Notation::where('user_id', $request->user_id)
            ->with('etablissement')
            ->orderBy('id', 'desc')
            ->get();

        if ($notations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune notation enregistrée pour cet utilisateur.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Liste des notations de l'utilisateur.",
            'notations' => $notations,
        ], 200);
    }
}
