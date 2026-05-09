# Configuration du système de notifications automatiques avec Cron-job.org

## Configuration requise

### 1. Variables d'environnement

Ajoutez ces variables dans votre fichier `.env` :

```env
# Clé API de Cron-job.org (votre clé fournie - pour créer/gérer les jobs)
CRON_JOB_ORG_API_KEY=404DoEj/c73rsINqk6x8OJAX7uv46Dgt1a/yxrbmb4=

# Clé API de protection pour l'endpoint (celle-ci doit être utilisée dans le header X-API-Key)
# Exemple généré : 4237c656f4a072195b9d193d2d1906a9
CRON_API_KEY=4237c656f4a072195b9d193d2d1906a9

# URL de base de votre application (pour construire l'URL de l'endpoint)
APP_URL=https://votre-domaine.com
```

### 2. Générer une clé API de protection

Pour générer une clé API sécurisée, vous pouvez utiliser :

```bash
php artisan tinker
>>> Str::random(32)
```

Ou en ligne de commande :
```bash
php -r "echo bin2hex(random_bytes(16));"
```

## Installation

### Étape 1 : Configurer les variables d'environnement

1. Ouvrez votre fichier `.env`
2. Ajoutez les variables mentionnées ci-dessus
3. Remplacez `CRON_API_KEY` par une clé aléatoire sécurisée

### Étape 2 : Créer le job automatiquement

Exécutez la commande suivante pour créer automatiquement le job dans Cron-job.org :

```bash
php artisan cron:setup-alert-notifications
```

Cette commande va :
- Vérifier vos clés API
- Créer un job dans Cron-job.org
- Configurer l'exécution quotidienne à 9h00 (heure d'Abidjan)
- Afficher l'ID du job créé

**Options disponibles :**
- `--url=` : Spécifier l'URL complète de l'endpoint (optionnel)
- `--force` : Forcer la création même si un job existe déjà

**Exemple avec URL personnalisée :**
```bash
php artisan cron:setup-alert-notifications --url=https://votre-domaine.com/api/v1/cron/send-alert-notifications
```

### Étape 3 : Vérifier le job

Le job sera automatiquement créé et configuré pour s'exécuter tous les jours à 9h00 (heure d'Abidjan).

Vous pouvez vérifier le job dans votre console Cron-job.org : https://console.cron-job.org

## Fonctionnement

### Flux d'exécution

1. **Cron-job.org** appelle votre endpoint tous les jours à 9h00
2. L'endpoint vérifie la clé API via le middleware `ApiKeyMiddleware`
3. Si la clé est valide, la commande `alerts:send-expiration-notifications` est exécutée
4. Les notifications sont envoyées aux utilisateurs concernés

### Endpoint API

**URL :** `POST /api/v1/cron/send-alert-notifications`

**Headers requis :**
```
X-API-Key: votre_cle_secrete_aleatoire_ici
```

Ou via Authorization Bearer :
```
Authorization: Bearer votre_cle_secrete_aleatoire_ici
```

**Réponse en cas de succès :**
```json
{
  "success": true,
  "message": "Notifications d'expiration d'alertes envoyées avec succès.",
  "output": "...",
  "timestamp": "2025-01-08 09:00:00"
}
```

## Gestion du job

### Lister les jobs existants

Vous pouvez utiliser le service `CronJobOrgService` dans votre code :

```php
use App\Services\CronJobOrgService;

$service = new CronJobOrgService();
$jobs = $service->listJobs();
```

### Mettre à jour un job

```php
$service->updateJob($jobId, [
    'enabled' => true,
    'schedule' => [
        'hours' => [10], // Changer à 10h00
    ]
]);
```

### Supprimer un job

```php
$service->deleteJob($jobId);
```

## Dépannage

### Le job ne s'exécute pas

1. Vérifiez que le job est activé dans la console Cron-job.org
2. Vérifiez les logs dans la console pour voir les erreurs
3. Testez manuellement l'endpoint avec curl :

```bash
curl -X POST https://votre-domaine.com/api/v1/cron/send-alert-notifications \
  -H "X-API-Key: votre_cle_secrete_aleatoire_ici"
```

### Erreur 401 (Unauthorized)

- Vérifiez que `CRON_API_KEY` est bien configuré dans `.env`
- Vérifiez que la clé envoyée par Cron-job.org correspond à celle dans `.env`
- Vérifiez que le header `X-API-Key` est bien envoyé

### Erreur lors de la création du job

- Vérifiez que `CRON_JOB_ORG_API_KEY` est correct
- Vérifiez votre connexion internet
- Vérifiez que l'URL de votre endpoint est accessible publiquement

## Test manuel

Vous pouvez tester manuellement l'envoi des notifications :

```bash
php artisan alerts:send-expiration-notifications
```

## Sécurité

⚠️ **Important :**
- Ne commitez jamais votre fichier `.env` dans Git
- Gardez votre `CRON_API_KEY` secrète
- Utilisez HTTPS pour votre endpoint en production
- Considérez l'activation de la restriction IP dans Cron-job.org si possible

## Consultation des logs

### Méthode 1 : Commande Artisan (Recommandé)

Afficher les logs des notifications :

```bash
# Afficher les 100 dernières lignes (par défaut)
php artisan notifications:view-logs

# Afficher les 200 dernières lignes
php artisan notifications:view-logs --lines=200

# Afficher uniquement les logs d'aujourd'hui
php artisan notifications:view-logs --today

# Filtrer par date spécifique
php artisan notifications:view-logs --date=2025-01-08
```

### Méthode 2 : API REST

Consulter les logs via l'API :

```bash
GET /api/v1/cron/notification-logs?lines=100&date=2025-01-08
```

**Headers requis :**
```
X-API-Key: votre_CRON_API_KEY
```

**Paramètres :**
- `lines` : Nombre de lignes à retourner (défaut: 100, max: 1000)
- `date` : Filtrer par date (format: Y-m-d)

### Méthode 3 : Fichier de logs directement

Les logs sont stockés dans : `storage/logs/laravel.log`

```bash
# Voir les dernières lignes
tail -f storage/logs/laravel.log | grep -i "notification"

# Voir les logs d'une date spécifique
grep "2025-01-08" storage/logs/laravel.log | grep -i "notification"

# Compter les notifications envoyées aujourd'hui
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep -i "notification.*succès" | wc -l
```

### Format des logs

Les logs incluent les informations suivantes :

**Log de début d'exécution :**
```
[2025-01-08 09:00:00] local.INFO: === Début de l'envoi des notifications d'expiration d'alertes === {"timestamp":"2025-01-08 09:00:00","date":"2025-01-08"}
```

**Log de notification envoyée :**
```
[2025-01-08 09:00:01] local.INFO: Notification d'expiration d'alerte envoyée {"alert_id":84,"user_id":10,"type_alert":"Vidange","vehicule_id":66,"date_fin":"2025-11-30","days_remaining":3,"timestamp":"2025-01-08 09:00:01"}
```

**Log de succès Firebase :**
```
[2025-01-08 09:00:01] local.INFO: Notification FCM envoyée avec succès {"user_id":10,"title":"⏰ Vidange expire dans 3 jours","body":"...","data":{...},"timestamp":"2025-01-08 09:00:01"}
```

**Log de résumé :**
```
[2025-01-08 09:00:05] local.INFO: === Fin de l'envoi des notifications d'expiration d'alertes === {"total_sent":15,"total_errors":0,"timestamp":"2025-01-08 09:00:05","date":"2025-01-08"}
```

### Logs dans Cron-job.org

Vous pouvez également consulter les logs d'exécution directement dans la console Cron-job.org :
- Accédez à : https://console.cron-job.org
- Sélectionnez votre job
- Consultez l'onglet "History" pour voir les détails de chaque exécution

## Support

Pour plus d'informations sur l'API Cron-job.org :
- Documentation : https://docs.cron-job.org/rest-api
- Console : https://console.cron-job.org

