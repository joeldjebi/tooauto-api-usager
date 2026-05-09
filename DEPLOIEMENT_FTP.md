# Deploiement Plesk / FTP - USAGER-API

Ce projet est une API Laravel. Pour une mise en ligne via Plesk, prepare le projet en local, cree une archive `.zip`, puis envoie-la dans le gestionnaire de fichiers Plesk.

## 1. Preparation locale

```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 2. Fichiers a envoyer

Envoyer tout le projet sauf les dossiers/fichiers suivants :

```txt
.git
.env
.env.backup
.env.production
node_modules
storage/logs/*.log
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
tests
```

Comme l'hebergement Plesk ne permet pas toujours d'executer Composer, garder le dossier `vendor` dans l'archive.

## 3. Configuration serveur

Creer le fichier `.env` directement sur le serveur a partir de `.env.example`, puis renseigner :

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://votre-domaine-api`
- `JWT_SECRET`
- les acces base de donnees
- les cles Wasabi/S3, mail, Firebase et autres services externes

Le domaine doit pointer vers le dossier `public`. Dans Plesk : `Sites Web & Domaines > Parametres d'hebergement > Racine du document`, mettre le chemin vers `public`.

Si Plesk impose `httpdocs`, placer le projet dans un dossier hors `httpdocs`, puis faire pointer la racine du domaine vers le dossier `public` du projet.

## 4. Base de donnees

Importer la base ou lancer les migrations sur le serveur si l'acces SSH est disponible :

```bash
php artisan migrate --force
```

## 5. Permissions

Verifier que ces dossiers sont accessibles en ecriture par PHP :

```txt
storage
bootstrap/cache
```
