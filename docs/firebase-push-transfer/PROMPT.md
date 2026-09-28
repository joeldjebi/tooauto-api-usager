# Prompt d'integration Firebase Push

Copier le prompt ci-dessous dans l'agent IA ouvert sur le projet Laravel cible.

```text
Nous sommes dans un projet Laravel API. Je veux implementer le meme systeme de notifications push Firebase Cloud Messaging que dans TOOAUTO API Usager.

Un kit de reference est disponible dans le dossier firebase-push-transfer. Analyse d'abord le projet cible avant toute modification, puis adapte les namespaces, le modele authentifiable, le middleware d'authentification et la convention de reponse JSON du projet.

Objectifs:

1. Installer ou confirmer la dependance kreait/firebase-php compatible avec la version de PHP et Laravel du projet. Le projet source utilise kreait/firebase-php:^6.9 avec PHP 8.1 et Laravel 10.
2. Ajouter une configuration Firebase qui lit FIREBASE_CREDENTIALS et utilise par defaut storage/app/firebase-credentials.json.
3. Ajouter fcm_token nullable de type text dans la table users avec une migration non destructive. Si la colonne existe deja, ne pas la recreer.
4. Ajouter fcm_token dans $fillable du modele User sans modifier les autres champs.
5. Ajouter une API authentifiee POST /api/v1/update-fcm-token pour enregistrer ou remplacer le token FCM de l'utilisateur connecte.
6. Ajouter un service FirebaseNotificationService capable d'envoyer:
   - a un utilisateur;
   - a plusieurs utilisateurs par IDs;
   - a tous les utilisateurs possedant un token FCM.
7. Ajouter les API authentifiees:
   - POST /api/v1/send-notification-to-user
   - POST /api/v1/send-notification-to-multiple-users
   - POST /api/v1/send-notification-to-all-users
   - POST /api/v1/send-notification-to-current-user
8. Proteger les routes d'envoi global, multiple et par user avec le controle d'autorisation administrateur approprie au projet. Un utilisateur mobile ordinaire ne doit pas pouvoir envoyer une notification a tous les comptes.
9. Conserver les payloads title, body et data. Les valeurs de data envoyees a FCM doivent etre converties en chaines; encoder en JSON les tableaux ou objets.
10. Traiter les envois multicast par lots de 500 tokens maximum et retourner un bilan succes/echecs.
11. Journaliser les erreurs sans exposer les tokens FCM ni le contenu du fichier de credentials.
12. Ajouter storage/app/firebase-credentials.json au .gitignore. Ne jamais committer la cle privee.
13. Utiliser le meme fichier de compte de service Firebase que le projet source, copie directement dans storage/app/firebase-credentials.json sur le serveur cible. Ne pas afficher son contenu dans les logs, le terminal ou une reponse API.
14. Verifier php -l sur les fichiers PHP, php artisan migrate --pretend si possible, et php artisan route:list sur les nouvelles routes.
15. Fournir a la fin les fichiers modifies, les commandes de deploiement et des curls de test.

Contraintes:

- Ne remplace pas les mecanismes d'authentification existants.
- Ne modifie pas le payload d'inscription ou de connexion.
- Travaille avec les changements deja presents dans le projet.
- Si le modele utilisateur ou la table ne s'appelle pas User/users, adapte proprement toutes les references.
- Si la version de kreait/firebase-php doit changer a cause de la version PHP, utilise une version compatible sans modifier inutilement le reste du projet.

Les fichiers du kit sont des references transferables. Integre-les dans la structure reelle du projet au lieu d'ecraser aveuglement des fichiers existants.
```

