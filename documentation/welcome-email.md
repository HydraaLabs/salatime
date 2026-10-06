# Email de bienvenue SalaTime

Le message de bienvenue suit désormais la langue choisie dans l’application. La modification du 6 octobre 2026 est préparée localement : aucun déploiement, migration sur la base réelle ou envoi SMTP n’a été effectué pour cette modification.

## Langue enregistrée

L’application envoie sa langue effective dans `locale` lors d’une inscription par email ou d’une première connexion Google/Apple. Le choix manuel prime sur la langue du système ; une langue système non prise en charge utilise l’anglais. Le serveur normalise les codes régionaux, accepte les dix langues de l’application (`en`, `fr`, `ar`, `tr`, `ur`, `id`, `ms`, `es`, `bn`, `fa`) et enregistre `mobile_accounts.locale` avant de programmer le message. Les anciens clients peuvent utiliser leur en-tête `Accept-Language` ; une valeur absente ou non reconnue utilise `en`.

Pour un compte existant, la connexion et les en-têtes de lecture ne remplacent pas sa langue. Une mise à jour acceptée de `preferences.language` met à jour le document cloud et la langue du compte dans la même transaction, après le contrôle de version. Un conflit 409 ne modifie aucune des deux valeurs. Une mise à jour qui omet la langue conserve le choix connu.

Dans l’application, un choix manuel est signalé avant les écritures asynchrones pour empêcher une restauration cloud ancienne de le remplacer. À la connexion, seul le choix de langue/pays de l’invité peut remplacer ces champs du compte ; les autres réglages restent ceux du compte. Un choix hors ligne est sauvegardé dans la copie locale de son propriétaire et n’est pas transféré à un autre compte. Si le document cloud n’a pas de langue, la langue du profil sert de repli, même avec un cache existant ; une langue déjà acceptée par le serveur actualise ce repli.

La migration additive `2026_10_06_010000_add_locale_to_mobile_accounts.php` ajoute la colonne nullable et reprend les langues reconnues des préférences historiques sans modifier leurs documents, versions ou timestamps. Un compte historique dont la colonne est vide peut encore lire sa langue dans ses préférences ; sinon le repli est l’anglais. Voir [le contrat de l’API](mobile-account-api.md).

## Message et déclenchement

Le sujet, le préheader, les paragraphes, le bouton et le pied de page sont traduits dans les dix langues, dans les parties HTML et texte brut. `AccountWelcome` résout explicitement la langue du compte pour chaque message ; la langue globale du site ne décide pas du rendu. L’arabe, l’ourdou et le persan utilisent `dir="rtl"` et des paragraphes alignés à droite. Le prénom reste échappé dans le HTML et isolé avec `bdi` pour conserver son ordre de lecture au milieu d’un texte de droite à gauche. La marque SalaTime, ses couleurs et les liens HTTPS du site et de sa confidentialité sont conservés. Aucun asset distant n’est requis.

`WelcomeEmails::afterRegistration` programme une seule tentative pour un nouveau compte, après commit et à la fin de la réponse HTTP, si `mobile_auth.mail_enabled` est actif. Le callback recharge le compte par son identifiant. Reconnexion, association à un compte existant, transaction annulée, compte supprimé ou email désactivé ne produisent pas de bienvenue. Une erreur SMTP est interceptée sans exposer d’adresse ou de secret et ne fait pas échouer l’inscription. Aucun renvoi automatique ou rétroactif aux membres existants n’est ajouté.

`AccountWelcome` conserve le transport dédié `mobile_accounts` et son expéditeur via `UsesAccountMailTransport`. Les autres messages, notamment les codes de vérification/réinitialisation, conservent leur comportement actuel ; cette modification traduit le message de bienvenue.

## Validation locale

Les tests utilisent SQLite en mémoire, un transport email en mémoire et des réponses HTTP simulées. Ils couvrent les dix traductions complètes, le sujet et les alternatives HTML/texte, les codes régionaux, le repli, les directions de lecture, l’échappement du prénom, le transport dédié, la persistance avant bienvenue, la migration historique et les conflits de préférence.

Une inscription HTTP complète en arabe traverse le vrai canal de notification et produit en mémoire un message de bienvenue arabe, alors que le site est en français et l’en-tête client en anglais, sans attendre une sauvegarde cloud. Le callback répété ne produit aucun doublon. Les autres tests de cycle de vie couvrent commit, rollback, suppression, désactivation et échec SMTP.

Suite comptes/email ciblée : **73 tests, 1341 assertions**, tous réussis (`MobileAccountApiTest`, `MobileAccountMailTransportTest`, `MobileGoogleConfigurationTest`, `MobileAccountWelcomeMailTest`, `MobileWelcomeEmailLifecycleTest`). Syntaxe, Pint et espaces Git vérifiés. Trente rendus navigateur (10 langues × 320/375/900 px), puis sept rendus de contrôle après l’isolation du prénom, ont été vérifiés sans débordement ; les messages de code, réinitialisation et invitation conservent leur rendu. Côté Flutter, **750 tests passent** dans la suite complète ; les tests ciblés couvrent transmission de langue, changements rapides, restauration cloud, propriétaire des sauvegardes et profil historique. L’analyse des neuf fichiers modifiés ne signale aucune anomalie. Les aperçus multilingues utilisent exclusivement un prénom fictif dans `/tmp/salatime-account-locale-previews` ; aucun email réel n’a été envoyé. La réception et le rendu dans les clients de messagerie réels restent distincts des tests locaux.

## Installation future

Appliquer la migration additive avant de mettre en service le backend qui écrit la colonne. Installer uniquement `AccountLocale`, les modifications des contrôleurs `AuthController`/`PreferencesController`, du modèle `MobileAccount`, de `AccountWelcome`, des vues de bienvenue/layout et les dix fichiers `lang/*/mobile_welcome.php`. Distribuer aussi les changements Flutter de transmission et de synchronisation de langue pour garantir la conservation d’un choix manuel lors de la connexion et des restaurations cloud. Les identifiants SMTP restent inchangés. Ne pas envoyer rétrospectivement ce message aux comptes existants.
