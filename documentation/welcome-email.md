# Email de bienvenue SalaTime

Ajout local demandé le 12 septembre 2026. Aucun déploiement, envoi réel ou renvoi aux comptes existants n’a été effectué dans cette intervention.

Le message français « Bienvenue sur SalaTime » accueille personnellement le nouveau membre. Il reprend le vert `#2F5233`, le fond ivoire et les accents dorés de SalaTime, présente la sauvegarde des préférences et propose les liens HTTPS du site et de sa confidentialité. Les couleurs viennent de `config/brand.php` avec valeurs de repli. Le HTML utilise des tableaux et styles intégrés, sans image ni police distante ; une partie texte brut accompagne le message.

## Déclenchement

`WelcomeEmails::afterRegistration` est appelé après construction réussie de la session d’inscription par email ou de première connexion sociale. L’envoi est enregistré seulement pour un compte nouvellement créé et quand `mobile_auth.mail_enabled` est actif. Une transaction annulée ne programme aucun email.

Le callback de fin de réponse relit le compte par son identifiant et effectue une seule tentative. Les reconnexions et l’association d’un fournisseur à un compte existant ne déclenchent pas de bienvenue. Un compte supprimé avant le callback est ignoré. Les erreurs SMTP sont interceptées avec un diagnostic générique, sans adresse ou secret, et ne font pas échouer la création du compte. Aucun worker de queue ou changement de schéma n’est nécessaire ; aucune relance automatique n’est prévue après une panne SMTP ou un arrêt du processus.

`AccountWelcome` et les codes existants `AccountCode` partagent le choix du transport `mobile_accounts` et de son expéditeur via `UsesAccountMailTransport`. Le comportement de repli des codes existants est conservé si la configuration dédiée est incomplète. L’envoi du code de vérification conserve son fonctionnement antérieur ; seul le nouveau message de bienvenue est différé après la réponse.

## Contrôles locaux

Les tests utilisent exclusivement SQLite en mémoire, notifications simulées et transport email en mémoire. Ils couvrent les inscriptions, reconnexions et liaisons Google, les transactions annulées, les comptes supprimés, la désactivation des emails, les échecs SMTP, le rendu HTML/texte et le transport dédié. Le rendu navigateur a été inspecté aux largeurs 320, 375 et 900 px sans débordement. La réception et le rendu dans les clients de messagerie réels ne sont pas revendiqués.

Suite ciblée finale : **41 tests, 428 assertions**, tous réussis (`MobileAccountApiTest`, `MobileAccountMailTransportTest`, `MobileGoogleConfigurationTest`, `MobileAccountWelcomeMailTest`, `MobileWelcomeEmailLifecycleTest`). Pint, syntaxe PHP et contrôle des espaces Git valides. Aperçu avec prénom fictif : [welcome-email.html](previews/welcome-email.html).

Pour une future activation autorisée, installer uniquement le contrôleur mobile modifié, `WelcomeEmails`, `AccountWelcome`, `AccountCode`, `UsesAccountMailTransport` et les deux vues `mail/mobile/welcome*`. Aucune modification des identifiants SMTP ou des comptes existants n’est requise. Ne pas utiliser ce changement pour envoyer rétrospectivement le message aux membres déjà inscrits.
