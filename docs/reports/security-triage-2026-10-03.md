# OpnForm — évaluation des rapports de sécurité

La branche **`codex/report-triage-fixes`** réunit désormais le triage initial, `origin/main` au commit `407e552e` et la branche PDF PSEC au commit `ae85e6f1`. Les constats et validations du 3 octobre ci-dessous restent un historique ; la validation intégrée du 25 octobre fait foi pour la version combinée.

## Validation intégrée — 25 octobre 2026

Les conflits ont été résolus en conservant la suppression des anciens liens de reset, les limites de mot de passe, les noms sûrs et les preuves DNS avec toutes les protections PSEC. Les revues ont également corrigé trois défauts OpnForm : réponse retardée enregistrée dans le mauvais cache workspace, confusion entre fournisseur OAuth et compte Stripe, et autorisations MCP survivant à la rotation du mot de passe. Les conditions de formulaires sont alignées avec le navigateur pour zéro, faux, les matrices et les groupes vides. Les dernières revues indépendantes en sous-agents ne trouvent plus de finding actionnable.

Sur le résultat final : **2 488 tests serveur / 19 662 assertions**, **1 095 tests frontend**, lint et build passent. Les **70 scénarios navigateur** ont été validés au cours de la campagne, dont quatre au rejeu après des incidents de chargement du serveur local. Les 25 parcours sensibles sur build production passent ; après la dernière intégration de main, sept parcours sont rejoués sur le nouveau build, dont les deux nouveaux cas de retry desktop/mobile, les soumissions, l'iframe, les domaines et l'upload. Les migrations et contrats applicatifs passent sur PostgreSQL 16.14 et MySQL 8.4.13 isolés. Deux skips et deux dépréciations serveur préexistants restent visibles dans les logs.

Composer ne remonte aucune advisory. Les politiques npm passent avec deux advisories racines déjà exemptées, node-forge high et Quill low, jusqu'au **6 novembre 2026**. Ce résultat ne constitue pas un audit sans vulnérabilité. Les migrations, le déploiement coordonné API/client, les parcours fournisseurs réels et la configuration infrastructure restent à valider avant production. Voir aussi `docs/security-remediation-2026-10.md`. Les branches restent locales, sans push ni déploiement.

## Revue de la PR #1346 — 25 octobre 2026

La branche a ensuite été poussée et la PR ouverte. Une nouvelle revue indépendante du diff complet ne trouve pas de finding fonctionnel ou de sécurité supplémentaire. Les 2 488 tests serveur passent à nouveau après correction du style PHP dans les fichiers concernés ; le contrôle Pint complet passe également.

Le job d'image Linux utilisait un environnement refusé par le seeder sécurisé. Il utilise maintenant `e2e` dans son environnement isolé, sans modifier la protection de production ; un test protège ce contrat. L'image Linux ARM64 a été construite et les migrations, le seed PostgreSQL, SSR, l'authentification, MCP, le formulaire public, les icônes et les CSS ont été vérifiés. La contre-revue du correctif CI ne trouve plus de finding. La CI GitHub AMD64 doit confirmer le résultat sur le nouveau commit. Aucun merge ni déploiement de production n'a été effectué.

## Recherche de non-régression supplémentaire — 25 octobre 2026

La recherche approfondie ajoute les anciens JWT sans version, les modifications refusées, les éditions ordinaires du profil, les pannes de stockage pendant la révocation et le parcours 2FA après reset. Un défaut supplémentaire est corrigé : le reset par email et les révocations sont désormais atomiques, pour qu'une erreur ne laisse pas un mot de passe changé malgré une réponse 500. Les parcours réussis de reset et de rotation sont aussi exercés via HTTP sur PostgreSQL et MySQL isolés.

Les domaines existants, leurs longueurs historiques, les limites du plan et les droits admin sont vérifiés. Une ligne vide dans la liste des domaines, convertie en null par Laravel, est de nouveau ignorée sans empêcher la sauvegarde. Les prefills configurés conservent zéro et faux.

Onze nouveaux scénarios exécutent les vrais composants de paiement avec HTTP et SDK Stripe simulés : facturation préremplie sans ressaisie, changement de compte, erreurs et retry, démontage, langue, réponses tardives et remontage d'un paiement déjà réussi. Les corrections empêchent l'ancien contexte de paiement de contaminer le suivant. Elles ne prouvent pas l'acceptation d'une carte réelle ou l'idempotence financière complète. La contre-revue indépendante des correctifs ne trouve plus de finding actionnable. Les résultats CI de la PR font foi pour son commit courant ; les sections précédentes conservent l'historique des campagnes.

## Périmètre et méthode

Évaluation au 3 octobre 2026 des signalements de sécurité retrouvés dans Crisp, complétée par les issues publiques OpnForm. La source des « rapports » n'était pas précisée dans la demande : ce périmètre a été annoncé pendant le travail. Les mails hors Crisp, documents privés non transmis et vulnérabilités seulement annoncées restent hors de la preuve disponible.

453 conversations candidates ont été récupérées : 216 OpnForm et 237 NoteForms. Les historiques ont été paginés, y compris les anciens messages des conversations longues. Ce nombre **n'est pas un nombre de vulnérabilités** : les recherches renvoient aussi newsletters, usages métier, questions support et chaînes de mails. Le corpus technique est présenté ci-dessous, avec les relances regroupées. Les plaintes de phishing, de réputation et les sollicitations sans PoC sont séparées des défauts applicatifs.

Les constats reposent sur les messages, le code actuel, des tests locaux et des lectures publiques DNS/HTTP. Aucun exploit destructif n'a été exécuté sur la production. Les conversations privées sont résumées sans identifiants Crisp, adresses personnelles, URLs clientes ou emails complets ; les dates et descriptions permettent de les retrouver dans Crisp.

## Évaluation des rapports techniques

| Rapport / source | Évaluation | Décision et raison |
| --- | --- | --- |
| Crisp, 18/12/2023 : « reflected XSS », avec capture ; [issue SVG #281](https://github.com/OpnForm/OpnForm/issues/281), janvier 2024 | La capture montre un SVG exécuté sur l'origine S3. Le vol du stockage ou des cookies OpnForm n'est pas démontré ; il reste un risque de phishing via fichier actif. Le terme « reflected XSS » est imprécis. | **Déjà traité dans le code actuel** : inspection des uploads, sanitisation SVG et livraison protégée des assets. Tests d'uploads et d'assets exécutés. La capture et #281 montrent le même asset : regroupés. |
| Même conversation, décembre 2023 : clickjacking login/signup | Ancien rapport sans PoC détaillé. Les en-têtes actuels de `/login` imposent `frame-ancestors 'self'` avec les origines de développement explicitement permises. L'encapsulation par un site arbitraire est bloquée. | **Pas de nouvelle modification** : protection actuelle constatée en lecture HTTP. Ne pas appliquer cette restriction aux formulaires publics, dont l'intégration en iframe est une fonction voulue. |
| [Issue #1050](https://github.com/OpnForm/OpnForm/issues/1050) : upload SVG/HTML et XSS sur origine locale | **Vrai problème historique**, plus sérieux que le simple SVG sur S3 : une livraison locale active peut partager l'origine de l'application. La liberté de déposer des fichiers fait cependant partie du produit. | **Déjà corrigé** : limites, inspection/sanitisation et protection de livraison vérifiées. Aucun bannissement global des fichiers arbitraires ou des uploads de répondants, qui casserait les usages légitimes. |
| [Issue #888](https://github.com/OpnForm/OpnForm/issues/888) et [#1259](https://github.com/OpnForm/OpnForm/issues/1259) : lecture/écriture anonyme par Hashids prévisibles | **Vraie faille importante**. Le commentaire démontrant l'énumération dans #888 invalide l'ancienne réponse selon laquelle les liens étaient seulement visibles pour leur propriétaire. #1259 souligne aussi les anciennes lignes sans UUID. | **Déjà corrigé dans la base** : backfill des UUID, suppression du fallback Hashids et attribution paresseuse d'un UUID aux anciennes réponses. Tests de lecture/écriture Hashids et UUID exécutés. La présence du code ne prouve pas la migration de tous les serveurs self-hosted ; ils doivent être à jour. |
| [Issue #899](https://github.com/OpnForm/OpnForm/issues/899) : plusieurs vulnérabilités / account takeover annoncés | Aucun scénario ou write-up disponible dans l'issue. | **Non évaluables en l'état**, pas « faux positifs » prouvés. Aucune correction spéculative. |
| Signalement NoteForms : lien de reset conservé après changement de mot de passe | Même défaut présent dans le contrôleur OpnForm, reproduit par un test API. | **Corrigé ici**, avec le cas du changement d'email. La possession d'un ancien lien doit cesser de permettre la réinitialisation après une rotation volontaire. |
| Signalement NoteForms : mot de passe énorme à l'inscription | Même absence de borne dans OpnForm. La qualification de DoS « critique » n'est pas démontrée, mais les entrées surdimensionnées et la troncature bcrypt sont évitables. | **Corrigé ici**, limite en octets sur les nouveaux mots de passe, avant le hachage. |
| Signalement NoteForms : lien injecté dans le nom du compte | Les noms alimentent aussi l'automatisation marketing OpnForm. Le mail de reset natif ne reprend pas le nom. | **Durcissement retenu ici** sur inscription et profil, sans imposer une nouvelle barrière de vérification d'email à toute l'application. |
| Signalement NoteForms : appropriation d'un domaine pointant vers la plateforme | Le chemin OpnForm ne vérifiait que syntaxe, disponibilité et limites du plan. Une disponibilité en base n'est pas une preuve de propriété DNS. | **Corrigé ici** par TXT ; droits admin et limites du plan conservés. |

## Ce qui est implémenté dans la branche locale

1. **Liens de réinitialisation invalidés après un changement de mot de passe.** Le changement authentifié et la suppression des jetons sont réalisés dans la même transaction. Un changement refusé conserve le jeton. Lors d'un changement d'email, les jetons de l'ancienne adresse sont également supprimés avant de modifier l'identité.
2. **Mots de passe bornés avant le hachage.** Inscription, réinitialisation et changement refusent les nouveaux mots de passe de plus de 72 **octets**, y compris en UTF-8. Cette limite correspond au bcrypt utilisé ici ; il ne s'agit pas de 72 caractères. La vérification du mot de passe existant garde une limite plus large pour préserver les anciens comptes. Les exigences de complexité existantes sont conservées. [Référence PHP](https://www.php.net/manual/en/function.password-hash.php).
3. **Noms de compte sans liens ni HTML.** La validation s'applique à l'inscription et à la modification du profil, avec une limite de 255 caractères. Les noms internationaux courants, apostrophes et traits d’union restent possibles. C'est un durcissement anti-phishing à faible coût ; aucune exécution JavaScript dans les emails n'est démontrée par ce rapport. Les noms contenant déjà un lien devront être corrigés lors de leur prochaine modification.
4. **Preuve de propriété pour tout nouveau domaine personnalisé.** Un TXT lie le domaine, le produit et l'espace de travail. Un CNAME vers la plateforme ne suffit plus. Une preuve d'un autre domaine ou espace est refusée. L'interface affiche le nom et la valeur du TXT et l'erreur de vérification ; l'API ne persiste aucun changement si une preuve manque. Les autorisations existantes restent appliquées.

Les domaines déjà enregistrés restent actifs sans vérification rétroactive. Leur suppression ne demande pas de TXT ; un remplacement ou une nouvelle inscription après suppression nécessite la preuve. Cela évite une interruption de service, mais **ne certifie pas les anciennes revendications**. Un audit opérationnel des domaines existants reste utile. La résolution DNS réelle et sa propagation dépendent du résolveur du serveur ; les tests de succès utilisent des réponses DNS contrôlées, sans publication de TXT externe.

5. **Signalement d'abus plus facile à trouver.** Un lien **Report abuse** est ajouté au footer du site vers le formulaire existant. Les plaintes de phishing hébergé, dont celle du 07/03/2025, sont des incidents d'abus réels, pas des contournements de l'authentification. Cette amélioration réduit la friction sans lancer un nouveau système de modération ni modifier le branding des formulaires clients. NoteForms possédait déjà ce lien.

## Ce qui n'a pas été implémenté, regroupé

- **Corrections déjà présentes** : Hashids, SVG/HTML, changement d'email sans mot de passe, protections anti-framing des pages de connexion. Réimplémenter ces mécanismes augmenterait le risque de régression.
- **Annonces sans preuve** : quatre conversations de sollicitation / bounty (voir annexe) et #899. Il manque une cible précise, un scénario et un résultat reproductible. L'absence de preuve ne prouve pas l'absence de bug.
- **Durcissement DNS** : CAA absent lors de la lecture du 03/10/2026 ; pas de preuve d'émission frauduleuse de certificat. DMARC OpnForm est déjà en `p=quarantine`. Les autorités réellement utilisées et les flux d'envoi doivent être inventoriés avant de durcir davantage la zone DNS.
- **Suppression de compte avec session déjà ouverte** : pas de bypass d'authentification établi. Une étape de réauthentification peut réduire ce risque, mais doit gérer les comptes OAuth/SSO sans mot de passe connu. Le bénéfice ciblé est moindre que celui des défauts reproduits ; pas de barrière incomplète ajoutée dans cette branche.
- **Incidents d'abus et de réputation** : les plaintes de formulaires de phishing, de spam et les blocages antivirus ont été regroupés. Ils ne prouvent pas une faille applicative. Un incident de réputation du 04/06/2026 est déclaré résolu dans sa conversation ; le certificat du site répond aujourd'hui via HTTPS. Le retrait de formulaires, les demandes de reclassification fournisseur et les faux positifs de modération exigent un traitement opérationnel, pas une modification générale des routes d'authentification.
- **Autres résultats de recherche** : newsletters contenant `xss=removed`, problèmes support de performance/fichiers, noms de formulaires métier et questions d'intégration. Aucun de ces simples mots-clés ne suffit à constituer un rapport de vulnérabilité.

## Validation locale

- Tests API : `AuthSecurityRegressionTest`, `CustomDomainOwnershipTest`, `SettingsTest`, `RegisterTest`, `ForgotPasswordTest`, `Workspaces/WorkspaceTest`, `Forms/SubmissionIdentifierTest`, `Forms/PublicAssetSecurityTest`, `Forms/UploadFileSecurityTest` ; exécution via Pest sous PHP 8.4, SQLite en mémoire. **107 tests réussis, 475 assertions** après la relecture.
- Les tests de régression d'authentification ont d'abord échoué sur le code initial : 11 échecs sur 13 cas dans chaque produit, puis réussite après correction. Cela couvre les anciens liens, les entrées ASCII/UTF-8 volumineuses et les noms contenant liens/HTML.
- `npm run lint` dans `client/` ; suite `npm run test -- --run` : **75 fichiers, 873 tests réussis**.
- App gérée du checkout : **http://127.0.0.1:34399**, API **http://127.0.0.1:28399**, PostgreSQL isolé **22399**. Le navigateur a montré le TXT et le refus explicite d'enregistrer un domaine sans preuve ; aucun TXT de production n'a été publié.

## Limites et mise en service

La reprise de ce travail dans le checkout `ab6c/OpnForm` a confirmé la présence des 216 historiques OpnForm et des 237 historiques NoteForms récupérés dans le passage précédent. Le code applicatif est identique au commit initial de triage. Les 107 tests API OpnForm, les 44 tests API NoteForms, les 14 tests frontend NoteForms et le lint des deux produits ont été relancés avec succès dans les worktrees `2dea`, qui disposent déjà des dépendances. Les 873 tests frontend OpnForm et les vérifications visuelles ci-dessus proviennent du passage précédent du même jour ; ils n'ont pas été relancés lors de cette reprise. Dans NoteForms, `php artisan test` n'est pas disponible dans cet environnement : les tests ont été exécutés directement avec `vendor/bin/pest`.

Les modifications restent sur une branche locale : aucun push, déploiement, changement DNS, réponse aux chercheurs ou traitement d'un compte client. Aucune migration de données n'est nécessaire. La clé d'application existante doit rester configurée et stable ; sa rotation change les preuves TXT en attente. Les anciennes soumissions ou fichiers déjà en stockage externe ne sont pas audités par cette branche.

La relecture a porté sur les droits, la séparation des espaces, les transactions d'identité, les formats Unicode, les domaines existants et les erreurs visibles. Les points corrigés pendant cette relecture incluent la longueur totale du nom DNS de vérification, les anciennes réservations de domaine avec casse différente dans NoteForms, et la capture de l'espace cible pour les requêtes OpnForm en cours. Aucun défaut bloquant identifié ne reste dans le diff ; la validation production et l'inventaire infrastructure restent distincts.

## Revue en sous-agents — 25 octobre 2026

Le skill `jhumanj-skills:code-review` a été exécuté par un sous-agent dédié à chaque produit. La revue porte sur les changements de ce chat et leurs correctifs, sur leur base du 3 octobre ; elle ne couvre pas la branche d’audit PDF PSEC séparée. Trois passages OpnForm ont abouti à **zéro finding actionnable restant**.

- **Impersonation support corrigée** : la nouvelle route de preuve DNS est autorisée au même titre que la sauvegarde existante. Le test utilise un JWT d’impersonation et conserve le refus pour un utilisateur extérieur au workspace.
- **Réservation de domaine rendue explicitement globale** : la recherche d’un domaine déjà associé ignore le scope installé par `x-custom-domain`. Cette faiblesse était antérieure au patch initial OpnForm ; le nouveau parcours est maintenant protégé également dans ce contexte.
- **Test de scope corrigé après la deuxième revue** : le middleware des domaines est explicitement activé dans le test, qui vérifie aussi que l’autre workspace est invisible à une requête ordinaire. Les régressions rejouées avant les correctifs donnent **2 échecs sur 3 cas** ; après correction, **3 réussites, 15 assertions**.

Validation finale : **110 tests API réussis, 490 assertions** (suite locale précédente plus `CustomDomainRegressionTest`) ; **873 tests frontend réussis dans 75 fichiers** ; `npm run lint`, Pint du nouveau fichier de tests et `git diff --check` passent. Pest utilise PHP 8.4 et SQLite en mémoire ; le frontend utilise Node 20. Les succès DNS utilisent des réponses contrôlées, sans publication de TXT externe.

Cette boucle ne rébase pas les branches sur le `main` récent et ne fusionne pas les changements PSEC. Leur réconciliation et la validation sur la base intégrée restent nécessaires avant fusion. Les modifications restent locales, sans push ni déploiement.

## Annexe — sollicitations sans rapport exploitable

Chaque ligne correspond à une conversation distincte. Ces sollicitations ne sont pas assimilées à des vulnérabilités confirmées.

| Date Crisp | Contenu et décision |
| --- | --- |
| 2024-02-22 | Demande de contact, politique de disclosure ou rémunération ; pas de PoC exploitable transmis. |
| 2024-03-15 | Demande de contact, politique de disclosure ou rémunération ; pas de PoC exploitable transmis. |
| 2025-08-25 | Demande de contact, politique de disclosure ou rémunération ; pas de PoC exploitable transmis. |
| 2025-11-28 | Demande de contact, politique de disclosure ou rémunération ; pas de PoC exploitable transmis. |
