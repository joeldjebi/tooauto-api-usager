# Test et suivi des SMS MTarget

## Routes

Toutes les routes sont sous `/api/v1`.

- `POST /test-sms-mtarget`: envoi public d'un SMS de test, sans authentification. Limité à 3 appels par minute et par adresse IP.
- `GET /test-sms-mtarget/{ticket}`: consultation publique du dernier statut connu avec le ticket MTarget.
- `POST /webhooks/mtarget/dlr/{token}`: reçoit les accusés de réception DLR MTarget. L'URL inclut un token secret configuré sur le compte fournisseur.

## Envoi de test

```bash
curl --request POST 'https://api-usager.tooauto.com/api/v1/test-sms-mtarget' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "msisdn": "+2250700000000",
    "message": "Message de test TooAuto",
    "sender": "TOOAUTO"
  }'
```

La reponse expose le code HTTP fournisseur, son `code`, son `reason`, le nombre de SMS et le `ticket`. Une reponse `code=0`, `reason=ACCEPTED` signifie que MTarget a accepte la soumission. Elle ne confirme pas la livraison au telephone.

Le champ `remote_id` est egalement envoye a MTarget; il permet de rapprocher le callback DLR de l'envoi. `ticket` est l'identifiant MTarget de l'envoi. `status_url` est generee si un ticket est retourne.

## Consultation du statut

Utiliser le `status_url` retourne par l'envoi, ou:

```bash
curl --request GET 'https://api-usager.tooauto.com/api/v1/test-sms-mtarget/TICKET_MTARGET' \
  --header 'Accept: application/json'
```

Le ticket MTarget sert de clé de consultation; le conserver comme un identifiant privé.

Etats DLR MTarget:

| Code | Etat API | Signification |
| --- | --- | --- |
| 0 | `waiting` | En attente |
| 1 | `in_progress` | En cours d'envoi |
| 2 | `sent_to_operator` | Envoye a l'operateur |
| 3 | `delivered` | Delivre au telephone |
| 4 | `refused` | Refuse par l'operateur |
| 6 | `not_delivered` | Non delivre |

## Configuration DLR MTarget

Dans `.env`, definir un secret aleatoire d'au moins 32 caracteres alphanumeriques:

```dotenv
MTARGET_DLR_TOKEN=REMPLACER_PAR_UN_SECRET_ALEATOIRE_LONG
```

Vider le cache de configuration apres modification:

```bash
php artisan optimize:clear
```

La reponse de l'API d'envoi retourne `dlr_callback_url`. Fournir cette URL au support/compte MTarget pour configurer les accusés de réception. Le serveur doit etre joignable publiquement en HTTPS. La route valide le token secret et acquitte les callbacks inconnus par HTTP 200 pour eviter les retries inutiles.

La migration `2026_10_19_000001_create_sms_test_messages_table.php` doit etre executee pour stocker les envois de test et les accusés recus:

```bash
php artisan migrate --force
```

Ne pas exposer le token DLR ni les identifiants MTarget dans une application mobile ou des logs.
