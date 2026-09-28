# Kit Firebase Push Laravel

Ce dossier contient les fichiers et snippets necessaires pour reproduire le systeme FCM de TOOAUTO API Usager dans un autre projet Laravel.

## Contenu

- `app/Services/FirebaseNotificationService.php`: envoi unitaire et multicast.
- `app/Http/Controllers/NotificationController.php`: quatre API d'envoi.
- `config/firebase.php`: chemin configurable du compte de service.
- `database/migrations/*_add_fcm_token_to_users_table.php`: colonne FCM.
- `snippets/AuthController-method.php`: methode de mise a jour du token.
- `snippets/routes-api.php`: routes a fusionner dans `routes/api.php`.
- `snippets/User-model-changes.php`: changement du modele `User`.
- `gitignore.snippet`: exclusion de la vraie cle Firebase.
- `firebase-credentials.example.json`: structure indicative sans secret.
- `PROMPT.md`: prompt complet pour l'agent IA du projet cible.

## Installation

Depuis le projet cible:

```bash
composer require kreait/firebase-php:^6.9
php artisan migrate
php artisan optimize:clear
```

Adapter la version du package si le projet cible n'utilise pas PHP 8.1/Laravel 10.

Fusionner les fichiers du kit dans le projet cible. Les snippets ne doivent pas remplacer integralement les fichiers existants.

Ajouter dans `.env`:

```dotenv
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
```

Ajouter dans `.gitignore`:

```gitignore
/storage/app/firebase-credentials.json
```

## Transfert de la vraie cle

Le fichier source se trouve ici:

```text
TOOAUTO-API-USAGER/storage/app/firebase-credentials.json
```

Le copier directement vers le meme emplacement du projet cible. Ne pas le placer dans ce kit, dans Git, dans une archive partagee ou dans un message. Les permissions recommandees sur le serveur sont `600` avec le proprietaire du processus PHP.

Utiliser le meme compte de service signifie que le projet cible enverra dans le meme projet Firebase. Les tokens des applications rattachees a un autre projet Firebase ne seront pas valides.

## Payloads

Mise a jour du token:

```bash
curl --location --request POST 'https://example.com/api/v1/update-fcm-token' \
  --header 'Authorization: Bearer TOKEN' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{"fcm_token":"TOKEN_FCM_DU_TELEPHONE"}'
```

Envoi a l'utilisateur connecte:

```bash
curl --location --request POST 'https://example.com/api/v1/send-notification-to-current-user' \
  --header 'Authorization: Bearer TOKEN' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "title":"Test notification",
    "body":"La configuration Firebase fonctionne.",
    "data":{"type":"test","resource_id":"123"}
  }'
```

Envoi a un utilisateur:

```json
{
  "user_id": 12,
  "title": "Nouvelle information",
  "body": "Votre demande a ete mise a jour.",
  "data": {
    "type": "request_updated",
    "request_id": "45"
  }
}
```

Envoi multiple:

```json
{
  "user_ids": [12, 18, 25],
  "title": "Information",
  "body": "Une nouvelle offre est disponible.",
  "data": {
    "type": "offer"
  }
}
```

Envoi global:

```json
{
  "title": "Maintenance",
  "body": "Une maintenance est prevue ce soir.",
  "data": {
    "type": "maintenance"
  }
}
```

## Securite

Les routes d'envoi a un utilisateur, a plusieurs utilisateurs et a tous les utilisateurs doivent etre reservees a un role autorise. Le middleware `auth` seul ne suffit pas pour une diffusion globale.

Le token FCM retourne par l'API de mise a jour peut etre masque ou omis selon les conventions du projet cible.
