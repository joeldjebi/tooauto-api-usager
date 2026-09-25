# Reprise IA - API TOOAUTO Usager

## Objectif

Ce document decrit les fonctionnalites ajoutees ou etendues autour des abonnements usager, cartes de reduction, campagnes de reduction, etablissements et stations de lavage. Il sert de point d'entree a un agent IA ou a un developpeur qui reprend le projet.

Projet: API Laravel `TOOAUTO-API-USAGER`.

Toutes les routes citees ci-dessous sont prefixees par `/api/v1`. Sauf `register`, elles sont placees dans le groupe protege par le middleware `auth.multiple`.

## Cartographie rapide

| Domaine | Point d'entree | Logique principale | Modeles |
| --- | --- | --- | --- |
| Inscription et abonnement automatique | `AuthController::register` | Configuration `.env`, creation transactionnelle de l'abonnement et attribution des cartes | `User`, `Forfait_usager`, `AbonnementUsager` |
| Achat ou attribution d'un forfait | `PaiementController` | Creation d'abonnement puis appel du service de cartes | `Paiement`, `AbonnementUsager` |
| Cartes de reduction usager | `ReductionCardController` | `ReductionCardService` | `ReductionCard`, `UserReductionCard`, `ReductionCardHistory` |
| Campagnes visibles par l'usager | `API/UserCampaignController` | Filtres, disponibilite, pagination, resolution de l'etablissement et des prestations | `ReductionCampaign`, `Etablissement`, `Station_service`, `StationDeLavage` |
| Etablissements et lavages | `EtablissementController` | Listes paginees et medias signes | `Etablissement`, `StationDeLavage`, `TypeLavage` |
| Medias prives | `WasabiService` | Upload, extraction du chemin et URL temporaire signee | Aucun |

## 1. Abonnement automatique a l'inscription

La route publique `POST /register` conserve son payload historique. Le choix d'attribuer automatiquement un forfait est entierement pilote par l'environnement:

```dotenv
REGISTER_AUTO_ABONNEMENT=false
REGISTER_AUTO_ABONNEMENT_FORFAIT=FREEMIUM
```

Les valeurs sont exposees dans `config/services.php` sous `services.register_auto_abonnement`.

Dans `AuthController::register`:

1. L'OTP et le payload sont valides.
2. L'usager est cree dans une transaction.
3. Si `REGISTER_AUTO_ABONNEMENT=true`, le forfait est cherche par son `libelle`, sans tenir compte de la casse ni des espaces.
4. Seuls `FREEMIUM`, `PERSONNEL` et `FAMILLE` sont acceptes.
5. Un `AbonnementUsager` actif et gratuit est cree avec `date_fin = now() + forfait.duree mois`.
6. `ReductionCardService::assignCardsToSubscription($abonnement)` attribue les cartes associees au forfait.
7. L'abonnement est ajoute a la reponse uniquement lorsqu'il a ete cree.

En production, apres modification des variables, vider le cache de configuration:

```bash
php artisan optimize:clear
```

## 2. Attribution automatique des cartes

La logique metier est centralisee dans `app/Services/ReductionCardService.php`.

### Attribution

`assignCardsToSubscription(AbonnementUsager $abonnement)`:

- charge les lignes actives de `reduction_cards` dont `forfait_usager_id = abonnement_usagers.forfait_id`;
- ignore une carte deja attribuee pour le couple `reduction_card_id + abonnement_usager_id`;
- cree `user_reduction_cards` avec l'usager, l'abonnement, le forfait et les dates de l'abonnement;
- genere un `card_code` de type `RC-ddmm-XXXXXXXX`;
- genere un `qr_code` de type `TOOAUTO-REDUCTION-XXXXXXXXXXXXXXXXXX`;
- initialise `statut` a `1`.

Le service est appele depuis `AuthController` et apres les creations d'abonnement dans `PaiementController`, y compris le flux gratuit. Toute future creation d'abonnement doit reproduire cet appel.

### Validite

Une carte est valide seulement si:

- elle appartient a l'usager authentifie;
- `user_reduction_cards.statut = 1`;
- la carte source `reduction_cards.statut = 1`;
- `date_fin` est nulle ou superieure ou egale a aujourd'hui.

La liste active impose actuellement une `date_fin >= aujourd'hui`; une date de fin nulle n'apparait donc pas dans la liste. La verification directe accepte une date nulle. Harmoniser seulement si le besoin metier le confirme.

### Calcul et historique

`applyDiscount()` applique:

```text
percentage: montant_initial * discount_value / 100
fixed:      discount_value
```

La reduction est plafonnee au montant initial. Le resultat et le contexte d'application sont enregistres dans `reduction_card_histories` avec `used_at = now()`.

L'implementation ne limite pas le nombre d'utilisations et ne passe pas automatiquement la carte a inactive apres usage.

### Routes cartes

`GET /mes-cartes-reduction`

Retourne les cartes actives et non expirees de l'usager connecte.

`POST /verifier-carte-reduction`

```json
{
  "card_code": "RC-2608-J2PS5BBJ"
}
```

`qr_code` peut remplacer `card_code`. La reponse contient la carte et `reduction_applicable`.

`POST /appliquer-carte-reduction`

```json
{
  "qr_code": "TOOAUTO-REDUCTION-QKYBNLNSPRVJDDHRQP",
  "montant_initial": 10000,
  "establishment_type": "lavage",
  "establishment_id": 1,
  "applied_by_id": 12,
  "notes": "Application en caisse"
}
```

Types d'etablissement acceptes: `etablissement`, `lavage`, `station`.

## 3. Campagnes de reduction usager

Controleur: `app/Http/Controllers/API/UserCampaignController.php`.

### Routes

- `GET /usager/campaigns`
- `GET /usager/campaigns/type/{establishment_type}`
- `GET /usager/campaigns/establishment/{establishment_type}/{establishment_id}`
- `GET /usager/campaigns/{campaign}`

Types acceptes: `lavage`, `station`, `etablissement`.

### Semantique importante

`index()` retourne toutes les campagnes par defaut, y compris celles qui ne sont pas actives ou hors periode. Ajouter `available_only=1` pour appliquer les regles suivantes:

- `statut = 1`;
- `date_debut <= aujourd'hui`;
- `date_fin >= aujourd'hui`;
- quantite illimitee, ou `quantity_used < quantity_available`.

Les routes `byType`, `byEstablishment` et `show` utilisent toujours ces regles de disponibilite.

Filtres disponibles sur `index`: `establishment_type`, `establishment_id`, `search`, `date_debut`, `date_fin`, `discount_type`, `min_price`, `max_price`, `available_only`, `page`, `per_page`. `per_page` est limite a 100.

La reponse de `index` ne duplique plus la collection:

```json
{
  "success": true,
  "message": "Liste des campagnes de reduction.",
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 0,
    "last_page": 1,
    "from": null,
    "to": null,
    "has_more_pages": false,
    "next_page_url": null,
    "prev_page_url": null
  }
}
```

### Enrichissement d'une campagne

`formatCampaign()` ajoute:

- l'etablissement complet dans `establishment`;
- l'image de campagne dans `image_url`;
- les IDs, libelles et objets de prestations;
- `montant_reduction`;
- `quantity_remaining`.

Resolution des etablissements:

| `establishment_type` | Table ou modele | Relations chargees |
| --- | --- | --- |
| `lavage` | `station_de_lavages` / `StationDeLavage` | `typeLavages` |
| `station` | `station_services` / `Station_service` | `ville`, `commune` |
| `etablissement` | `etablissements` / `Etablissement` | `type_etablissement`, `pays`, `ville`, `commune` |

`product_or_service` peut contenir une liste d'IDs separes par des virgules. Les tables consultees sont:

- lavage: `type_lavages`;
- station: `type_prestation_station_services`;
- etablissement: `type_de_prestations`.

Si la valeur n'est pas une liste numerique, elle est exposee comme liste de libelles texte.

### Diagnostic d'ecart avec l'API Lavage

Un ecart deja observe montrait trois campagnes cote usager et six cote lavage, avec des statuts differents pour un meme ID. Cela indique une source de donnees ou une synchronisation differente, pas necessairement une erreur dans le filtre Eloquent.

Avant de modifier la requete, comparer dans les deux environnements:

- la base et l'hote utilises;
- les IDs presents dans `reduction_campaigns`;
- `statut`, `date_debut`, `date_fin`;
- `quantity_available`, `quantity_used`;
- `establishment_type`, `establishment_id`.

Dans l'API Lavage, une methode qui appelle `first()` retourne volontairement une seule campagne. Pour une liste, elle doit utiliser `get()` ou `paginate()` et retourner une collection.

## 4. Images Wasabi

`WasabiService::temporaryUrl()` accepte un chemin ou une URL, en extrait le chemin objet, puis genere une URL temporaire signee. La duree par defaut est de 10080 minutes.

Dans `UserCampaignController`, les chemins de campagne, logos et covers passent par `signedImageUrl()`. Etat actuel important: si la valeur stockee est deja une URL valide, elle est retournee telle quelle. Elle n'est donc pas re-signee par ce controleur.

Si la base contient des URL Wasabi non signees et qu'elles doivent etre renouvelees, adapter `signedImageUrl()` pour appeler `WasabiService::temporaryUrl($image)` meme lorsque `$image` est une URL Wasabi. Conserver un passage direct pour les URL externes si elles sont autorisees.

Ne jamais exposer les cles Wasabi dans une reponse ou dans cette documentation. La configuration reside dans `config/wasabi.php` et les variables d'environnement.

## 5. Pagination des annuaires

Trois API ont ete paginees pour l'application mobile:

- `POST /index-etablissement`;
- `POST /get-type-etablissement-type-de-prestation`;
- `GET|POST /get-station-de-lavage`.
- `GET /get-etablissement-electrique-list`.

Parametres communs: `page` et `per_page`, avec une valeur par defaut de 15 et un maximum de 100.

Les cles metier historiques sont conservees:

- `etablissements`;
- `etablissement_by_type_de_prestation`;
- `station_de_lavages`.

La cle `pagination` contient `current_page`, `per_page`, `total`, `last_page`, `from`, `to`, `has_more_pages`, `next_page_url` et `prev_page_url`.

La liste des stations de lavage:

- filtre `statut = 1`;
- accepte `search` sur le nom, le contact et l'adresse;
- charge les types de lavage dans `types_lavages`;
- signe le logo avec le mecanisme Wasabi du controleur.

La liste des etablissements electriques filtre `statut = 1` et `is_electrique = 1`, accepte une recherche sur le nom, l'adresse ou le mobile, charge les relations geographiques et signe les medias Wasabi.

## 6. Fichiers essentiels

- `routes/api.php`: declarations des routes et middleware.
- `app/Http/Controllers/AuthController.php`: inscription et forfait automatique.
- `app/Http/Controllers/PaiementController.php`: creation des abonnements achetes ou gratuits.
- `app/Services/ReductionCardService.php`: regles metier des cartes.
- `app/Http/Controllers/ReductionCardController.php`: validation HTTP des cartes.
- `app/Http/Controllers/API/UserCampaignController.php`: campagnes cote usager.
- `app/Http/Controllers/EtablissementController.php`: annuaires pagines.
- `app/Services/WasabiService.php`: stockage et signatures temporaires.
- `app/Models/ReductionCampaign.php`, `ReductionCard.php`, `UserReductionCard.php`, `ReductionCardHistory.php`, `StationDeLavage.php`, `TypeLavage.php`.
- `config/services.php` et `.env.example`: abonnement automatique.

## 7. Verification avant livraison

Pour une modification ciblee:

```bash
php -l app/Http/Controllers/API/UserCampaignController.php
php artisan route:list --path=api/v1/usager/campaigns
```

Adapter la commande `php -l` aux fichiers touches. Tester egalement:

- utilisateur authentifie et non authentifie;
- carte active, inactive, expiree et appartenant a un autre utilisateur;
- reduction fixe superieure au montant initial;
- pourcentage et arrondis;
- campagne active, future, expiree et quantite epuisee;
- pagination vide, premiere page et derniere page;
- image absente, chemin Wasabi et URL Wasabi complete.

L'environnement local peut ne pas avoir acces a la base de production. Dans ce cas, ne pas presenter une validation syntaxique comme un test fonctionnel et signaler explicitement la limite.

## 8. Dette technique connue

- Ajouter une contrainte unique DB sur `(reduction_card_id, abonnement_usager_id)` et des index sur les codes si elle n'existe pas deja.
- Decider si une carte a une utilisation unique ou multiple et appliquer la regle de maniere transactionnelle.
- Harmoniser le traitement des `date_fin` nulles entre liste et verification.
- Eviter les requetes N+1 dans la resolution des etablissements et prestations lorsque le volume de campagnes augmente.
- Centraliser le format de pagination si d'autres controleurs adoptent le meme contrat.
- Definir une strategie de synchronisation ou une base partagee pour `reduction_campaigns` entre les API Lavage et Usager.
- Clarifier si toute URL Wasabi deja stockee doit etre re-signee a chaque reponse.
