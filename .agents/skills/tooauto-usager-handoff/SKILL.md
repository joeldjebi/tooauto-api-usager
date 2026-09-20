---
name: tooauto-usager-handoff
description: Maintenir et prolonger l'API Laravel TOOAUTO Usager, notamment les campagnes, cartes de reduction, abonnements automatiques, etablissements, stations de lavage et medias Wasabi. Utiliser pour reprendre ces fonctionnalites sans redecouvrir leur architecture.
---

# TOOAUTO Usager Handoff

Travaille dans le projet Laravel `TOOAUTO-API-USAGER` en preservant les contrats API deja utilises par l'application mobile.

Avant toute modification liee aux campagnes, cartes de reduction, abonnements automatiques, etablissements, lavages ou Wasabi, lis [la documentation de reprise](../../../docs/AI_HANDOFF_TOOAUTO_USAGER.md). Ne charge que les sections utiles a la demande.

## Regles de travail

- Inspecte d'abord les routes, le controleur, le service et les modeles concernes. Certaines tables existent en production sans migration locale correspondante.
- Garde les routes sous `/api/v1` et le middleware `auth.multiple` lorsqu'elles concernent l'usager connecte.
- Preserve les cles JSON existantes. Pour une liste paginee, conserve la cle metier puis ajoute ou maintiens `pagination`.
- Utilise `ReductionCardService` comme point central des regles de cartes. N'ajoute pas la logique de generation, validation ou calcul dans plusieurs controleurs.
- Lors de toute nouvelle creation d'un `AbonnementUsager`, appelle `assignCardsToSubscription()` apres la sauvegarde de l'abonnement, idealement dans la meme transaction.
- Une carte n'est utilisable que si l'attribution et la carte source sont actives, appartiennent a l'usager connecte et ne sont pas expirees.
- Pour les campagnes, distingue la liste generale de `index` des routes actives `byType`, `byEstablishment` et `show`. Ne reinstaure pas involontairement un filtre actif sur `index` sans exigence explicite.
- Pour les medias prives, passe les chemins Wasabi par `WasabiService::temporaryUrl()`. Verifie le comportement pour les valeurs deja stockees sous forme d'URL avant de modifier la signature.
- N'invente pas les schemas. Verifie les modeles et, si disponible, la base cible. Les schemas campagne et reduction sont partages avec d'autres API TOOAUTO et peuvent etre desynchronises.

## Verification minimale

Apres une modification PHP:

1. Execute `php -l` sur chaque fichier touche.
2. Execute `php artisan route:list --path=api/v1` si les routes ou controleurs API changent.
3. Lance les tests cibles s'ils existent et indique clairement les tests non executables.
4. Pour un ecart entre API Lavage et API Usager, compare les IDs, statuts, dates, quantites et connexions de base avant de modifier les filtres.

## Points de vigilance

- `REGISTER_AUTO_ABONNEMENT` et `REGISTER_AUTO_ABONNEMENT_FORFAIT` sont lus via `config/services.php`; un changement `.env` peut necessiter `php artisan optimize:clear` en deploiement.
- `ReductionCardService::assignCardsToSubscription()` est idempotent au niveau applicatif par `reduction_card_id + abonnement_usager_id`; une contrainte unique en base reste souhaitable pour proteger les appels concurrents.
- L'application des reductions cree un historique, mais ne desactive pas la carte et ne limite pas son nombre d'utilisations.
- `UserCampaignController::index()` retourne toutes les campagnes par defaut; `available_only=1` active les regles de disponibilite.
- Une difference de nombre de campagnes entre deux API peut venir de bases distinctes ou non synchronisees, meme lorsque les requetes PHP sont correctes.

