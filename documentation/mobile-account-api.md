# Comptes mobiles SalaTime — contrat et configuration

L’API comptes Google/email a été activée de manière ciblée le 12 septembre 2026, après autorisation explicite. La migration additive mobile et la dépendance JWT ont été installées ; aucun autre paquet n’a été mis à jour ou retiré. La vérification/récupération par email est également activée après validation TLS et authentification SMTP du transport Mailgun dédié depuis PHP web OVH, sans envoi de message de test. Voir `mobile-account-activation-2026-09-12.md` pour la preuve d’activation et ses limites. Aucun push, Play, Shorebird ou publication des autres modifications n’a été effectué dans cette activation.

## Isolation et données

Les comptes de l’application utilisent `mobile_accounts`, `mobile_identities`, `mobile_account_actions` et `mobile_preferences`. Ils ne créent aucun utilisateur administrateur et n’ouvrent aucune session `web`. Les jetons Sanctum portent exclusivement `mobile:account`, expirent après 30 jours et sont limités aux 20 sessions les plus récentes. Le middleware mobile exige un jeton de type `MobileAccount`, refuse un jeton administrateur même avec `*`, et s’exécute avant les limiteurs propres au compte. La route administrateur existante `/api/user` refuse les jetons mobiles.

Les mots de passe utilisent le hash Laravel, au moins 12 caractères dont lettres et chiffres, au plus 72 octets pour éviter la troncature bcrypt. Les adresses sont normalisées en minuscules. Les identités Google/Apple sont identifiées par le couple fournisseur / `sub`, jamais associées automatiquement par adresse mail. Une association explicite requiert une session du compte existant et une adresse déjà vérifiée.

La suppression nécessite le mot de passe pour un compte local, ou une connexion sociale datant de moins de 10 minutes. Elle révoque l’autorisation Apple avant de supprimer le compte, ses sessions et ses préférences. Un échec de révocation Apple laisse le compte intact pour permettre une nouvelle tentative. Les refresh tokens Apple sont chiffrés par le cast Laravel `encrypted` ; conserver `APP_KEY` de façon durable.

## Endpoints JSON

Préfixe : `/api/mobile`. Envoyer `Accept: application/json`, `Content-Type: application/json`, et `Authorization: Bearer <token>` pour les endpoints authentifiés. Ne jamais journaliser le jeton.

| Méthode et chemin | Corps / résultat |
|---|---|
| `GET auth/config` | `{data:{enabled,email:{enabled,verification_enabled,password_reset_enabled},google:{enabled,server_client_id,ios_client_id},apple:{enabled,client_id,redirect_uri}}}` |
| `POST auth/register` | `name,email,password,password_confirmation,device_name?` → 201, `{data:{user,token}}` |
| `POST auth/login` | `email,password,device_name?` → `{data:{user,token}}` |
| `GET auth/me` | Authentifié, `{data:{user}}` |
| `POST auth/logout` | Authentifié, révoque uniquement cette session |
| `DELETE auth/account` | Authentifié, `password` si `user.has_password` |
| `POST auth/forgot-password` | `email` → message identique que le compte existe ou non |
| `POST auth/reset-password` | `email,token,password,password_confirmation` → révoque toutes les sessions |
| `POST auth/resend-verification` | Authentifié, renvoie un code si nécessaire |
| `POST auth/verify-email` | Authentifié, `token` → `{data:{user}}` |
| `POST auth/google` | `id_token,device_name?,name?` → session |
| `GET auth/challenge?provider=apple&platform=android` | `{data:{challenge_id,nonce,state}}` ; utiliser `ios` pour l’application iOS |
| `POST auth/apple` | `identity_token,authorization_code,challenge_id,nonce,state,device_name?,name?` → session |
| `POST auth/apple/callback` | Retour `form_post` Apple pour Android ; état validé avant redirection vers l’application |
| `POST auth/link/google` / `auth/link/apple` | Même corps que la connexion, session existante authentifiée et email vérifié obligatoires |
| `GET preferences` | Authentifié, `{data:{version:0,preferences:{},updated_at:null}}` initialement |
| `PUT preferences` | Authentifié, `{version,preferences}` → document remplacé et version incrémentée |

`user` contient seulement `id,name,email,email_verified,has_password,providers`. `providers` est une liste (`google`, `apple`). Les champs d’administration, mots de passe et tokens de fournisseurs ne sont pas exposés.

Les codes mail sont des chaînes de **6 chiffres**, copiables et saisissables dans l’application. Ils expirent après 15 minutes, sont stockés sous forme HMAC liée à `APP_KEY`, utilisables une fois, et bloqués après 5 erreurs par code. Les renvois et tentatives sont également limités par IP et adresse. Un code de vérification est lié au compte authentifié ; un code de reset est lié à l’adresse validée.

Les erreurs de validation sont HTTP 422 (`errors` Laravel). Jeton absent/expiré : 401. Association sociale par adresse déjà existante : 409 `account_link_required`. Association avant vérification : 403 `email_verification_required`. Suppression sociale nécessitant une nouvelle connexion : 403 `reauthentication_required`. Fonction non configurée : 503. Les limiteurs renvoient 429.

## Préférences portables

Le document accepté est une liste blanche de champs, tous optionnels sauf `schemaVersion:1` lorsque le document n’est pas vide : thème (`daylight/light/dark`), langue, pays, présentation (`modern/classic`), format 24 h, méthode de calcul, madhab, corrections des huit horaires, clés des trois sons, rappels avant/après, activation par prière, réglages widgets, plages de silence, correction hégirienne, réglages du lecteur et rappels complémentaires.

Les règles exactes et bornes figurent dans `PreferencesController`. Les sons proviennent de `config/mobile_sound_keys.php`, copie versionnée des 68 clés du catalogue mobile, plus `silent`. Une référence personnelle `custom_<SHA256>` est admise, mais aucun fichier ni URI de son ne l’est : le client doit gérer son indisponibilité sur un autre appareil.

Le schéma version 1 est étendu de manière additive avec `prayerNotificationSettings` : `{before|adhan|after:{fajr|sunrise|dhuhr|jumaa|asr|maghrib|isha:{enabled,sound,minutes}}}`. Ce sont des overrides ; un objet vide réinitialise les réglages. `adhan.minutes` vaut 0 ; les phases avant/après acceptent 0 à 120 minutes. Le document v2 mobile fait autorité ; l’ancien groupe `prayerNotifications` est exporté comme synthèse des cinq prières historiques (Dhuhr pour l’identifiant 2). Une ancienne sauvegarde sans groupe v2 est migrée après restauration de ses réglages globaux.

Les rappels complémentaires ajoutent `fajrAlarm`, `bedtime`, `middleNight`, `monday` et `thursday`, ainsi que `anchor` et `useDefaultSound`. `lastThird` inclut déjà son délai avant le dernier tiers ; aucun rappel séparé `beforeLastThird` n’est créé. Les offsets acceptent 0 à 120 minutes, les anciens modes `clock` acceptent 0 à 1439. Les ancres autorisées par rappel figurent dans `REMINDER_ANCHORS`. Les anciens `mondayThursday` et horaires fixes sont conservés à la lecture puis migrés en deux rappels sans doublon.

Les sons personnels des deux nouveaux groupes sont remplacés dans le document portable par le son dédié de leur type, y compris les exceptions du lever du soleil et de Jumaa. Les factories Flutter et `config/mobile_notification_defaults.php` fournissent les mêmes 21 sons par phase/prière et 12 sons complémentaires. Le fichier audio personnel reste local ; un changement d’un autre champ ne le remplace pas sur son appareil d’origine lorsque le fallback n’a pas changé. Les réinitialisations, les éditions simultanées et les sauvegardes locales par compte sont testées.

Aucune coordonnée GPS, permission système, mot de passe, jeton, cache ou URI locale n’est acceptée. Les booléens/nombres doivent conserver leur type JSON. Taille maximale d’une requête : 64 Kio. Le document est toujours renvoyé comme objet JSON, y compris vide.

Le propriétaire est toujours déduit du jeton ; aucun identifiant de compte reçu dans le corps ne peut le changer. La mise à jour verrouille le compte dans une transaction. Si la version envoyée est périmée, HTTP 409 :

```json
{"message":"Preferences changed on another device.","code":"preferences_conflict","data":{"version":2,"preferences":{"schemaVersion":1,"themeMode":"dark"},"updated_at":"..."}}
```

Le client doit fusionner ou demander le choix utilisateur avant un nouveau PUT avec la version courante. Un conflit ne remplace jamais le document serveur silencieusement.

## Google et Apple

Google : signature **RS256** vérifiée avec JWKS officiels, `iss`, `aud`, `exp`, `iat`, `sub` contrôlés. Les audiences proviennent exclusivement de la configuration serveur. Les JWKS sont mis en cache, avec rafraîchissement borné lors d’une rotation de clé. Les jetons signés incorrectement, expirés, destinés à une autre application ou d’un autre émetteur sont refusés.

Le SDK Google Flutter natif ne fournit pas de nonce par tentative de connexion (`initialize` ne peut être appelé qu’une fois). La reconnexion peut réutiliser un ID token encore valide ; le serveur l’accepte selon le flux Google documenté. Il ne prétend pas imposer un nonce Google par tentative. Utiliser HTTPS en dehors du serveur QA local.

Apple : le serveur crée un challenge aléatoire de 10 minutes. Le client transmet `sha256(nonce)` au SDK Apple, et retourne au serveur le nonce brut, `challenge_id` et `state`. Le JWT reçu doit porter ce hash ; le challenge est consommé sous verrou, une seule fois. L’`authorization_code` est échangé auprès d’Apple avec un client secret ES256 de 5 minutes, signé par la clé `.p8`. Le JWT retourné est revérifié ; audience et sujet doivent correspondre. Le refresh token est conservé chiffré pour la révocation lors de la suppression.

Pour Android, le retour Apple vérifie l’état avant la redirection `intent://callback...`, dont le package est fixé côté serveur. Le paramètre client ne peut pas choisir un autre package. La configuration QA peut utiliser `net.salatime.app.preview`, la configuration de distribution doit utiliser `net.salatime.app`.

## Paramètres à configurer avant une future publication

Voir `.env.example` (aucun secret réel ajouté) :

- `MOBILE_AUTH_ENABLED` : activation générale.
- Le transport dédié `mail.mailers.mobile_accounts` utilise `MOBILE_MAIL_HOST`, `MOBILE_MAIL_PORT`, `MOBILE_MAIL_ENCRYPTION`, `MOBILE_MAIL_USERNAME`, `MOBILE_MAIL_PASSWORD`, `MOBILE_MAIL_FROM_ADDRESS` et `MOBILE_MAIL_FROM_NAME`. Une configuration complète sélectionne ce transport et son expéditeur pour les codes, indépendamment du SMTP général géré en base. `MOBILE_AUTH_MAIL_ENABLED=true` a été activé après validation TLS/certificat et SMTP AUTH 235 depuis PHP web OVH. Aucun test de livraison n’a été effectué ; réception et classement antispam restent des vérifications distinctes. Sans configuration dédiée complète, le code conserve le transport historique ; le flag est désactivé par défaut dans l’exemple.
- Google OAuth : `MOBILE_GOOGLE_CLIENT_IDS` (audiences autorisées), `MOBILE_GOOGLE_SERVER_CLIENT_ID`, éventuellement `MOBILE_GOOGLE_IOS_CLIENT_ID`. Configurer également les clients Android et leurs empreintes de signature dans Google Cloud.
- Apple : App ID avec capacité Sign in with Apple, Service ID pour Android, domaine et URL de retour HTTPS autorisés, `MOBILE_APPLE_CLIENT_IDS`, `MOBILE_APPLE_SERVICE_ID`, `MOBILE_APPLE_TEAM_ID`, `MOBILE_APPLE_KEY_ID`, `MOBILE_APPLE_PRIVATE_KEY_PATH`, `MOBILE_APPLE_REDIRECT_URI`, `MOBILE_APPLE_ANDROID_PACKAGE`. La clé doit rester hors du répertoire public, lisible uniquement par PHP.
- Migration additive `2026_09_12_190000_create_mobile_accounts_tables.php` appliquée lors de l’activation ciblée du 12 septembre 2026. La table Sanctum existante avait déjà sa colonne `expires_at` et n’a pas été modifiée. La base locale du projet n’a pas été migrée.
- En cas de plusieurs serveurs PHP, utiliser un cache partagé supportant les verrous pour les challenges et limiteurs. Planifier la purge des jetons Sanctum expirés.

## Validation et limites

Les tests `MobileAccountApiTest` forcent SQLite `:memory:` **avant le démarrage des providers**, créent uniquement les tables nécessaires, simulent notifications et HTTP, et interdisent les requêtes réseau inattendues. Ils couvrent l’isolation administrateur/mobile, inscription/connexion/déconnexion/suppression, expiration des tokens, vérification et reset, tentatives limitées, signature/audience/émetteur Google, reconnexion avec ID token valide, association explicite, nonce/état/code/révocation Apple, confidentialité et concurrence des préférences.

Le serveur QA temporaire utilise sa propre base SQLite, sa propre `APP_KEY`, ses propres logs et son propre cache. Les emails y sont écrits dans un log local, sans SMTP.

L’activation publique Google/email, l’accès HTTPS sortant aux clés Google et la connexion/authentification Mailgun depuis PHP web OVH ont été vérifiés. Après la connexion utilisateur, des compteurs agrégés ont confirmé un compte Google et une sauvegarde cloud non vide. La délivrance d’un code email et Apple restent des validations distinctes ; aucun résultat de test simulé ne constitue une connexion réelle à ces services.

La seule dépendance ajoutée est `firebase/php-jwt` 7.1.0. L’audit Composer ne signale aucune vulnérabilité pour celle-ci, mais révèle 38 avis concernant 10 dépendances préexistantes (dont Laravel 10, Guzzle et Symfony) ; aucune mise à jour globale de ces dépendances n’a été effectuée dans cette tranche.

Sources primaires :
- Google : https://developers.google.com/identity/sign-in/web/backend-auth
- Apple nonce : https://developer.apple.com/documentation/signinwithapple/authenticating-users-with-sign-in-with-apple
- Apple révocation : https://developer.apple.com/documentation/signinwithapplerestapi/revoke-tokens
- JWT : https://github.com/googleapis/php-jwt
- Sanctum : https://laravel.com/docs/10.x/sanctum
