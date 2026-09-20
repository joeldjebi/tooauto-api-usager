<?php

namespace App\Services\Assistant\Contracts;

interface AssistantTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema (format Anthropic "input_schema") décrivant les paramètres attendus.
     */
    public function inputSchema(): array;

    /**
     * Exécute l'outil pour l'utilisateur authentifié courant.
     *
     * @param array $input Paramètres fournis par le modèle, déjà décodés.
     * @param \App\Models\User|\App\Models\Chauffeur $authenticatable Utilisateur authentifié (jamais fourni par le modèle).
     * @param string $guard Guard actif ("api" ou "chauffeur").
     * @param array $context Données de contexte fournies par le client (ex: latitude/longitude),
     *   jamais manipulées par le modèle — voir AssistantController::chat().
     * @return array Résultat compact, sérialisable en JSON, renvoyé au modèle.
     */
    public function handle(array $input, $authenticatable, string $guard, array $context = []): array;
}
