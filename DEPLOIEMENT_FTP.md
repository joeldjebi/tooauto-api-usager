# Deploiement FTP - USAGER-API

Ce projet est une API Laravel. Pour une mise en ligne par FTP, prepare le projet en local, puis envoie les fichiers generes vers l'hebergement.

## 1. Preparation locale

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
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

Si l'hebergement ne permet pas d'executer Composer, envoyer aussi le dossier `vendor` genere localement avec la commande ci-dessus.

## 3. Configuration serveur

Creer le fichier `.env` directement sur le serveur a partir de `.env.example`, puis renseigner :

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://votre-domaine-api`
- `JWT_SECRET`
- les acces base de donnees
- les cles Wasabi/S3, mail, Firebase et autres services externes

Le domaine doit pointer vers le dossier `public`. Si ce n'est pas possible sur l'hebergement, placer le contenu de `public` dans le dossier web public et adapter les chemins de `index.php`.

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

