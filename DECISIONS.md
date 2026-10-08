# Journal des décisions

Chaque point ambigu est tranché par l'option la plus simple et la plus sûre, puis noté ici.

## Étape 1 — Installation

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D1 | Version de PHP | Le projet tourne sur **PHP 8.2.12** (XAMPP local). `composer.json` exige `^8.2`. La production devra être en **PHP 8.3+** comme demandé. | Laravel 12 supporte 8.2 ; on évite de bloquer le développement local. À relever à `^8.3` dès que le poste local est mis à jour. |
| D2 | Base de données locale | **MariaDB 10.4** (XAMPP) en local, base `bd_appolivia`. **MySQL 8** en production. Tests sur **SQLite en mémoire**. | Seul moteur disponible localement. On s'interdit les fonctions propres à MySQL 8 (ou on les isole) pour rester compatible. |
| D3 | Redis | Client **predis** (paquet PHP) au lieu de l'extension phpredis. En local sans Redis : cache, sessions et files en driver `database`. En production : `redis` pour les trois. | L'extension phpredis n'est pas installée ; predis fonctionne partout sans compilation. |
| D4 | Tailwind | Breeze installe Tailwind v3 : migré vers **Tailwind v4** (plugin `@tailwindcss/vite`, configuration dans `resources/css/app.css` via `@theme`). `tailwind.config.js` et `postcss.config.js` supprimés. | Exigence de la stack. |
| D5 | PostCSS | `vite.config.js` déclare une config PostCSS vide en ligne. | Un `postcss.config.js` (Tailwind v3) présent dans `C:\Users\WMC` était récupéré par Vite et cassait le build. Le projet ne dépend ainsi plus de son dossier parent. |
| D6 | Police | **Inter** auto-hébergée (`@fontsource-variable/inter`), plus de Google Fonts / Bunny Fonts. | Compatible avec une CSP stricte (aucun domaine tiers) et fonctionne hors ligne (PWA contrôleur). |
| D7 | 2FA (TOTP) | `pragmarx/google2fa` (le QR de configuration est généré par `simple-qrcode`, déjà requis). | Bibliothèque légère et éprouvée, pas besoin de Fortify. |
| D8 | Traductions | `laravel-lang/common` (dev) pour générer `lang/fr`. Locale et repli : `fr`. | Messages de validation et d'auth en français sans les réécrire à la main. |
| D9 | Configuration métier | Règles par défaut (trajets par ticket, durée d'abonnement, anti-rejeu, OTP, clé QR) dans `config/transport.php`, alimentées par `.env`. Les valeurs modifiables à chaud (arrêts, tarifs) seront en base. | Aucun secret ni « nombre magique » dans le code. |
| D10 | Rotation de la clé QR | `QR_SIGNING_KEY_ID` + `QR_SIGNING_KEY` pour la clé active, `QR_SIGNING_PREVIOUS_KEYS` (`id:clé,id:clé`) pour les anciennes encore acceptées. Le QR portera l'identifiant de clé. | Permet de changer la clé sans invalider d'un coup les titres déjà émis. |
| D11 | Sessions | `SESSION_ENCRYPT=true`, cookie `secure` forcé en production, `http_only`, `same_site=lax`, durée 120 min. | Exigences §5.4. |
| D12 | Vérification d'e-mail Breeze | `User` implémente provisoirement `MustVerifyEmail` pour garder les tests Breeze verts. | Remplacé à l'étape 3 par la vérification du **numéro de téléphone** (middleware `verified`). |
| D13 | Antivirus Windows | Les installations Composer utilisent `COMPOSER_MAX_PARALLEL_PROCESSES=1`. | Windows Defender verrouille les fichiers pendant l'extraction parallèle (« Could not delete … »). |

## Étape 2 — Design system

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D14 | Couleur principale | **Bleu « lagune »** (pas vert). | Le vert est réservé au statut « valide » : une marque verte brouillerait le message au contrôle. |
| D15 | Mode sombre | **Non** pour client, guichet et admin. Le **contrôle** est sombre par défaut. | Facultatif dans le cahier des charges ; un seul thème clair = moins de cas à tester pour un public non technicien. Le sombre au contrôle réduit l'éblouissement dans le bus et économise la batterie. |
| D16 | Alpine.js | Build **`@alpinejs/csp`** (+ plugin `focus`). Logique dans `Alpine.data()` (`resources/js/alpine/`), expressions inline simples uniquement. | Permet la CSP stricte de l'étape 9 sans `unsafe-eval`. |
| D17 | Pas d'attribut `style` inline | Toutes les variations passent par des classes (ex. `grid-cols-3/4`, barre d'étapes en segments). | Compatible avec une CSP `style-src` sans `unsafe-inline`. |
| D18 | Icônes | **Lucide** via `mallardduck/blade-lucide-icons` (`<x-lucide-bus />`), SVG inline. | Lucide a les pictos métier (bus, ticket, qr-code, scan-line) absents d'Heroicons ; SVG inline = hors ligne, aucune requête. |
| D19 | Comportements de formulaire | JS natif (`resources/js/forms.js`) : spinner + anti double envoi sur tout `submit`, validation en direct en français (contraintes HTML5), bouton « Afficher » du mot de passe, `data-modal-open`. | Fonctionne sur toutes les pages sans code par écran ; le serveur revalide toujours (Form Requests). |
| D20 | Modale | Remplace celle de Breeze en gardant son API d'événements (`open-modal`, `close-modal`, `close`). Feuille du bas sur téléphone. | La page profil Breeze continue de fonctionner ; le pouce atteint les boutons. |
| D21 | Navigation | Définie dans `App\Support\Navigation` : un lien s'affiche si sa route existe **et** si l'utilisateur a la permission. | Les menus se remplissent tout seuls au fil des étapes ; aucune logique de droits dans les vues. |
| D22 | Super-admin | `Gate::before` dans `AppServiceProvider` accorde toutes les permissions au rôle `super-admin`. C'est le **seul** test de nom de rôle du code. | Méthode recommandée par Spatie ; le reste du code ne teste que des permissions. |
| D23 | Accès à `/design` | Middleware `can:design.view` + `Gate::authorize()` dans le contrôleur. Seul le super-admin l'obtient (via D22). | « Super-admin uniquement » tout en vérifiant une permission, pas un rôle. |
| D24 | Montants | `App\Support\Money::format()` et `<x-money>` : `1 500 FCFA` avec espaces **insécables**. | Le montant n'est jamais coupé en fin de ligne. Pas d'extension `intl` requise. |
| D25 | QR codes | `<x-qr>` produit un SVG inline (sans prologue XML, sans attribut `style`). | Net à tout zoom, fonctionne hors ligne, compatible CSP. |
| D26 | Vues Breeze | Les écrans de connexion/profil Breeze restent tels quels jusqu'à l'étape 3, qui les réécrit avec les nouveaux composants. | Éviter de styliser deux fois des écrans qui vont changer. |

## Étape 3 — Authentification, rôles, permissions, middlewares

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D27 | **Pas de code OTP, pas de 2FA** (choix du client) | Tout le monde se connecte par **téléphone + mot de passe**. Le middleware `RequireTwoFactor` et le paquet `google2fa` sont retirés. | Décision du client (coût et complexité des SMS, public peu technicien). **Risque accepté** : un mot de passe volé suffit. Compensations : D28 à D31. |
| D28 | Verrouillage | 5 mauvais mots de passe → compte bloqué 15 min (`MAX_LOGIN_ATTEMPTS`, `LOCKOUT_MINUTES`), journalisé. En plus : `throttle:login` 5/min par numéro+IP et 20/min par IP. Le message indique les essais restants. | Freine le vol de mot de passe par essais successifs. Afficher les essais restants aide un public peu technicien ; l'existence d'un numéro est de toute façon visible à l'inscription. |
| D29 | Compte désactivé | Refusé seulement **après** un mot de passe correct ; déconnecté à la requête suivante s'il est désactivé en cours de session (`EnsureUserIsActive` sur tout le groupe web). | Ne rien révéler à quelqu'un qui ne connaît pas le mot de passe. |
| D30 | « Rester connecté » | Pris en compte pour les **clients** seulement. Le personnel n'a jamais de cookie « remember » : session expirée après 120 min d'inactivité (`SESSION_LIFETIME`). | Exigence « 2 h pour le personnel » sans gêner les clients. |
| D31 | Journal des connexions | Chaque connexion du personnel, chaque verrouillage, changement ou réinitialisation de mot de passe est écrit dans `activity_log`. | Traçabilité des accès sensibles. |
| D32 | Mot de passe oublié | Lien par e-mail **uniquement vers une adresse confirmée**. Même réponse dans tous les cas (aucune énumération). Sans e-mail confirmé : le guichet donne un **mot de passe provisoire** (`must_change_password`, écran forcé par `EnsurePasswordIsChanged`). L'écran guichet/admin qui génère ce mot de passe arrive aux étapes 4 et 6 ; la policy `resetPassword` existe déjà. | Choix du client (e-mail facultatif). Une adresse non confirmée (faute de frappe) ne doit pas permettre de prendre le compte. |
| D33 | Confirmation de l'e-mail | Par **lien signé** (pas un code), envoyé à l'inscription ou au changement d'adresse. Changer d'adresse annule la confirmation. | Prouve que l'adresse appartient bien au client avant de lui confier la récupération du compte. |
| D34 | Middleware `verified` | **Retiré** des espaces : sans SMS, le numéro n'est pas « vérifié ». Le numéro sert d'identifiant ; il ne peut être changé qu'au guichet (contrôle d'identité). | Le prompt prévoyait `verified` = téléphone vérifié par OTP, abandonné (D27). |
| D35 | Format du téléphone | Stocké en E.164 (`+2250700000000`). On accepte « 07 00 00 00 00 », « +225… », « 00225… ». Préfixes 01, 05, 07, 21, 25, 27 (`App\Support\PhoneNumber`). | Un seul format en base, saisie libre pour l'utilisateur. |
| D36 | Connexion par e-mail | Le champ de connexion accepte aussi l'e-mail. | Garde le compte `admin@gmail.com` demandé ; pratique pour le personnel. |
| D37 | Accès aux espaces | Par **permissions** `space.admin`, `space.guichet`, `space.controle`, `space.espace` (middleware `permission:` sur chaque groupe), jamais par nom de rôle. Après connexion, l'espace d'arrivée est choisi par ces permissions (`App\Support\HomeRoute`). | Règle « on vérifie la permission, jamais le rôle ». |
| D38 | Catalogue des permissions | `App\Support\PermissionCatalog` : liste unique, libellés français, permissions par rôle. `RolesAndPermissionsSeeder` est idempotent. `direction` n'a que des permissions de lecture ; `tickets.cancel` (annulation superviseur) n'est pas donné au guichetier. | Une seule source de vérité, testée. |
| D39 | Policies | `UserPolicy` et `CashSessionPolicy` maintenant. Les policies `Ticket`, `Subscription`, `Fare`, `Stop`, `Vehicle`, `Trip`… arrivent **avec leurs modèles** (étapes 4, 5 et 7). | Pas de policy sans modèle. |
| D40 | Super-admin et `Gate::before` | Le super-admin passe toutes les policies, y compris sur son propre compte. Les garde-fous « ne pas supprimer le dernier super-admin / soi-même » seront dans l'Action de gestion des utilisateurs (étape 4). | `Gate::before` court-circuite les policies. |
| D41 | `cash_sessions` | Table et modèle créés dès maintenant (ouverture/clôture à l'étape 6). | Nécessaire au middleware `EnsureCashSessionOpen`. Pas d'identifiant de caisse dans l'URL du guichet : l'agent ne voit que la sienne. |
| D42 | CSP | Active dès maintenant : nonce par requête (balises Vite), pas de `unsafe-eval`/`unsafe-inline`. En `npm run dev`, le serveur Vite est autorisé automatiquement. Pas de CSP sur la page de debug 500 (locale). Caméra autorisée sur `/controle/*` seulement. HSTS en production HTTPS. | Exigence §5.3, sans attendre l'étape 9. |
| D43 | Webhooks | `VerifyWebhookSignature` : liste blanche d'IP + HMAC-SHA256 du corps brut (`PAYMENT_WEBHOOK_SECRET`, en-tête `X-Signature`). **Refus par défaut** si aucun secret. Tout rejet est journalisé. Groupe `api` : pas de session ni de CSRF. | Le contrôle propre à CinetPay (jeton `x-token`) et la vérification serveur à serveur s'ajoutent à l'étape 5. |
| D44 | `LogSensitiveAction` | Middleware `audit:<description>` : journalise les requêtes non GET **réussies**, avec route, IP et paramètres. Mots de passe, jeton CSRF et photos ne sont jamais enregistrés. | Exigence §5.4. |
| D45 | API JSON | `routes/api.php` est dans le groupe **web** (cookie de session + CSRF, même origine), pas Sanctum. | Seules nos propres pages (PWA contrôleur) l'appellent. Rien à exposer à des tiers. |
| D46 | Pages d'erreur | 403, 404, 419, 429, 500 et 503 en français simple, sans style inline, dès l'étape 3. | La page 403 par défaut de Laravel violait la CSP. |
| D47 | Comptes de démonstration | `DemoUsersSeeder` (local/test uniquement) : un compte par rôle, 07 00 00 00 01 à 06, mot de passe `demo-password-2026`. | Tester les accès dès maintenant. Le jeu de données complet reste à l'étape 10. |

## Étape 4 — Administration du référentiel

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D48 | Modèle de ligne | Une ligne a une **ville de départ** (`origin_name`, Abidjan) et des **arrêts de destination** ordonnés (`position` 1 = le plus proche). Abidjan n'est pas un arrêt vendable. | Le prix dépend de l'arrêt d'arrivée ; l'ordre sert à la règle 3 (`Stop::isCoveredBy()`). |
| D49 | Ordre des arrêts | Modifié par des flèches ↑/↓ (échange avec le voisin, en transaction), jamais en tapant un numéro. Un nouvel arrêt est ajouté en fin de ligne. | Impossible de créer des trous ou des doublons d'ordre ; simple pour un gestionnaire. |
| D50 | Tarifs immuables | Un tarif n'est **jamais modifié**. On en programme un nouveau (`ScheduleFare`) : date d'effet aujourd'hui ou future, jamais passée ; l'ancien est clos la veille ; un seul tarif futur à la fois par arrêt et formule. Seul un tarif **pas encore en vigueur** peut être annulé (l'ancien redevient ouvert). | Règles 1 et 6 ; l'historique des prix reste exact et auditable. Verrou `lockForUpdate` contre deux saisies simultanées. |
| D51 | Prix gelé à l'achat | Le prix sera **copié** sur chaque ticket/abonnement à l'achat (étape 5). | Un changement de tarif ne touche jamais un titre vendu ni un abonnement en cours (règle 6). |
| D52 | Arrêt sans tarif | Non affiché sur l'accueil et signalé « Pas de tarif » dans l'admin ; il ne pourra pas être vendu. | Évite la vente à un prix inexistant. |
| D53 | Pas de suppression | Lignes, arrêts et véhicules ne se suppriment pas : on les **désactive**. | Titres, voyages et historique y font référence. |
| D54 | Historique | `LogsActivity` sur `Line`, `Stop`, `Fare`, `Vehicle` (journal `reference`, anciennes et nouvelles valeurs). Comptes : journal `users` (création, rôle, statut, mot de passe provisoire). | « Modification de tarif » et « changement de rôle » tracés (§5.4). |
| D55 | Un rôle par personne | Le formulaire attribue **un** rôle (`syncRoles`). | Plus simple à comprendre et à contrôler ; suffisant pour les 7 rôles prévus. |
| D56 | Mot de passe provisoire | Généré par le système (3 groupes de 4 caractères sans 0/O/1/l), affiché **une seule fois** à l'écran (session chiffrée), jamais journalisé ; l'utilisateur doit le changer à la connexion. Il débloque aussi un compte verrouillé. | Pas de SMS (D27) ; lisible à voix haute au guichet. |
| D57 | Dernier super-admin | Garde-fou dans `UpdateUser` : impossible de rétrograder ou désactiver le dernier super-admin actif ; personne ne modifie son propre rôle/statut. | Ne jamais perdre l'accès à l'administration (complète D40). |
| D58 | Rôles en lecture seule | L'écran « Rôles » montre la matrice des permissions mais ne la modifie pas : les permissions se changent dans `PermissionCatalog` (revue de code) puis `db:seed --class=RolesAndPermissionsSeeder`. | Un clic ne doit pas pouvoir ouvrir un accès sensible par erreur. |
| D59 | Seeder du réseau | `ReferenceDataSeeder` (toutes les installations, idempotent) crée la ligne Abidjan – Bonoua, ses 3 arrêts et les **tarifs d'exemple** du cahier des charges. À remplacer par les vrais prix depuis l'admin. 3 véhicules de démo en local uniquement. | Démarrage immédiat sans écraser les données saisies ensuite. |
| D60 | Seeding et cache Spatie | `DatabaseSeeder` n'utilise plus `WithoutModelEvents`. | Avec les événements coupés, Spatie gardait un cache de permissions vide : `migrate:fresh --seed` échouait (`PermissionDoesNotExist`). |

## Étape 5 — Achat en ligne, paiement, QR signés

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D61 | Paiement d'abord, titre ensuite | Un **paiement** (`payments`) fige ce qui est acheté : arrêt, formule, montant du tarif du jour, référence unique. Le **titre** n'est créé que par `ConfirmPayment` → `IssuePass`. | Règle 5. Le prix vient du tarif en vigueur, jamais du formulaire (testé). |
| D62 | Confirmation serveur à serveur | Webhook signé **et** retour du client déclenchent la même Action : elle **interroge l'API de l'agrégateur**, contrôle montant et devise, puis verrouille le paiement (`lockForUpdate`). Le contenu d'une notification n'est jamais cru tel quel. | Un webhook « payé » signé mais sans paiement réel n'émet rien (testé). Le retour client accélère sans affaiblir la sécurité. |
| D63 | Idempotence | Référence = clé d'idempotence ; job `ShouldBeUnique` ; un paiement final n'est plus jamais modifié ; index **unique** `payment_id` sur `tickets` et `subscriptions`. | Webhook rejoué 3 fois + retour client = **un seul** titre (critère d'acceptation, testé). |
| D64 | Montant différent | Montant ou devise différents → paiement **échoué** (`amount_mismatch`), log `critical`, aucun titre ; le client est invité à passer au guichet avec la référence. | Rapprochement manuel par le comptable (étape 8). |
| D65 | Signature des webhooks | Vérifiée par le **driver** de l'agrégateur nommé dans l'URL (`/webhooks/paiements/{fake|cinetpay}`) : HMAC du corps pour `fake`, `x-token` CinetPay. Liste blanche d'IP, refus par défaut et journalisation restent dans `VerifyWebhookSignature`. | Chaque agrégateur a son format. |
| D66 | CinetPay | Implémenté d'après la documentation API v2 (`/payment`, `/payment/check`, notification + `x-token`), testé avec `Http::fake`. **À valider sur le sandbox CinetPay avant la production** (identifiants réels, montants multiples de 5). L'origine de la page de paiement est ajoutée à la CSP `form-action`. | Aucun accès au sandbox depuis ce poste. |
| D67 | Driver `fake` | Simulateur fidèle (page « Mobile Money » locale, statut côté « agrégateur », notification). Routes **non enregistrées en production** et driver refusé en production. | Développement et tests sans argent réel. |
| D68 | Date d'un ticket | Valable le jour où le **paiement est confirmé** (jusqu'à 23 h 59, Africa/Abidjan). | Un paiement commencé à 23 h 58 et confirmé à 0 h 02 donne un ticket utilisable. |
| D69 | Renouvellement | Un abonnement acheté alors qu'un autre du **même arrêt** court commence le lendemain de sa fin ; sinon aujourd'hui. Durée : `TRANSPORT_SUBSCRIPTION_DAYS` (30 j glissants, fin incluse). | « Renouveler en un clic » sans perdre de jours. |
| D70 | Contenu du QR | `TT1.<t|s>.<uuid>.<id de clé>.<HMAC-SHA256 base64url>` (~90 caractères). Aucune donnée personnelle. Rotation : nouvelle clé + ancienne dans `QR_SIGNING_PREVIOUS_KEYS`. Vérifié lisible depuis l'écran d'un téléphone. | §5.4. La vérification hors ligne (étape 7) devra protéger la clé côté appareil. |
| D71 | Photo d'abonné | Obligatoire avant d'acheter un abonnement. Stockée sur le disque **privé**, ré-encodée en JPEG par GD (EXIF/GPS supprimés), redressée, 800 px max, nom aléatoire. Servie par `/photos/{user}` aux seules personnes autorisées (titulaire, contrôleurs, guichetiers, gestion). Le service public `/storage` du disque local est **désactivé**. | Vie privée (loi 2013-450) et contrôle visuel au bord. |
| D72 | Suppression de compte | La photo est effacée ; titres et paiements sont conservés pour la comptabilité mais **détachés** de la personne (`user_id` → null). | Droit à l'effacement sans fausser la comptabilité. |
| D73 | Page « paiement en cours » | Se recharge toute seule toutes les 5 s (`<meta http-equiv="refresh">`). | Aucun script inline : compatible avec la CSP. |
| D74 | PDF du titre | Généré par dompdf, avec QR SVG embarqué. Le mode hors ligne PWA des titres arrive avec la PWA (étape 7). | « Téléchargeable » dès maintenant. |

## Étape 6 — Guichet

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D75 | Vente au comptant | Paiement `gateway = cash` confirmé immédiatement (l'argent est en main) et titre émis **dans la même transaction**, avec verrou sur la caisse (elle doit être ouverte et à l'agent). Prix = tarif en vigueur (jamais saisi). | Règles 1 et 5 ; la vente ne peut pas « glisser » dans une caisse en cours de clôture. |
| D76 | Choix en un clic | Une carte par couple arrêt × formule, prix affiché (`offer = <arrêt>:<formule>`). | Vente rapide au guichet, sans JavaScript. |
| D77 | Client | Facultatif pour un ticket du jour (« de passage »), obligatoire pour un abonnement, avec photo (prise au guichet si besoin). Vente impossible à un compte du personnel. Création express : consentement (loi 2013-450) coché par l'agent + mot de passe provisoire affiché une fois. | Règle 3 (abonnement nominatif) ; le client pourra ensuite acheter en ligne. |
| D78 | Annulation (règle 9) | Uniquement sur une vente de **la caisse ouverte de l'agent**, d'un titre non utilisé, avec téléphone + mot de passe d'un **superviseur** (`tickets.cancel`, actif, différent de l'agent) saisis sur place, et un motif. 5 essais faux / 10 min puis blocage. Paiement `cancelled`, titre `cancelled`, journal (causé par le superviseur). | Pas de « vente fantôme » : l'argent rendu sort automatiquement du total attendu. Après clôture, ce sera un remboursement (`tickets.refund`, règle 7). |
| D79 | Clôture (règle 8) | Attendu = fond + ventes non annulées. Écart ≠ 0 → justification d'au moins 10 caractères obligatoire. Verrou sur la caisse : pas de double clôture ni de vente pendant la clôture. Rapport imprimable signé agent / responsable. | Traçabilité des écarts. |
| D80 | Impression thermique | Page dédiée 58 ou 80 mm (`TRANSPORT_RECEIPT_WIDTH`, `?largeur=58`), QR inclus, impression lancée par un module JS (`data-auto-print`). La taille `@page` passe par un `<style nonce>` : la CSP `style-src` accepte désormais le nonce de la requête (toujours pas d'`unsafe-inline`). | Imprimante thermique standard (impression navigateur) ; compatible CSP. |
| D81 | Sacs d'erreurs | Les erreurs d'annulation s'affichent dans la modale de **la** vente concernée (sac `cancel_<référence>`) ; téléphone et motif sont conservés, jamais le mot de passe. | Plusieurs modales sur la même page. |
| D82 | Remboursements | Hors guichet : le remboursement d'un titre jamais scanné (règle 7, `tickets.refund`) sera traité côté administration avec les scans (étapes 7–8). | Il faut l'historique des scans pour l'appliquer. |

## Étape 6 bis — Alignement sur les données réelles de l'agence

Source : grille tarifaire et historique des reçus (`BA-2026-0001` à `0017`, total 695 000 FCFA) remis par le client. Plusieurs décisions précédentes sont **remplacées** (D69, D71, D76, D77 en partie).

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D83 | Réseau | Ligne (destination « Angré ») → **zones** (position 1 = la plus proche) → **points de montée**. Le prix dépend de la **zone**, pas du point. Une zone couvre les zones plus proches (`Zone::isCoveredBy`). | Grille du client : 3 zones (Koumassi/Marcory, Anani/Gonzague/Port-Bouët, Bonoua/Bassam). |
| D84 | Formules | Ticket du jour, « Matin & soir », « Matin simple », « Soir simple » (`FareType`, `Period`). | Colonne « Formule » de l'historique. |
| D85 | Période d'abonnement | **Mois calendaire** (« octobre 2026 »), mois en cours et mois suivant en vente. Remplace les 30 jours glissants (D69). | Colonne « Mois » ; l'agence encaisse en fin de mois pour le mois suivant. |
| D86 | Abonné ≠ compte | Table `subscribers` distincte des comptes ; téléphone **non unique** (un parent paie pour plusieurs). Lien vers un compte client seulement si l'agent coche « lier au compte » et jamais vers un compte du personnel. Photo sur l'abonné (D71 transposée, route `/photos/abonnes/{id}`). | L'historique contient deux abonnées sur le même numéro, sans compte en ligne. |
| D87 | Wave / Mobile Money | « Transfert sur le numéro de l'agence » : la **référence du transfert est obligatoire** et unique par moyen de paiement (pas de double saisie). Hors tiroir-caisse : la caisse attendue = fond + espèces ; les transferts sont totalisés à part. | Réponse du client : transfert, pas d'API. |
| D88 | Reçus numérotés | `BA-AAAA-NNNN` (abonnements) et `TJ-AAAA-NNNNN` (tickets), sans trou, compteur verrouillé (`receipt_sequences`). | Format des reçus existants ; la numérotation continue après l'historique. |
| D89 | Prix négocié | Possible au guichet avec `sales.discount`, montant ≤ grille, motif ≥ 5 caractères ; prix de grille conservé (`list_amount`) et action journalisée. | 2 reçus de l'historique sont sous la grille. **À confirmer avec le client** : règle exacte. |
| D90 | Historique | Commande `receipts:import {fichier} [--dry-run]` : idempotente, refuse tout le fichier si un point de montée est inconnu, lit l'UTF-8 et le Windows-1252 (Excel). Le fichier client reste dans `storage/app/private` (données personnelles). | Reprise de l'existant sans ressaisie. |
| D91 | Export | « Abonnés et reçus » (gestion, direction, comptable) : liste du mois, totaux par moyen de paiement, export CSV **au format exact du client** avec ligne TOTAL. | L'agence garde son tableau habituel. |
| D92 | Photo d'abonné | Visible par le titulaire, le guichet, les contrôleurs et la gestion des comptes ; **pas** par le comptable, qui lit les reçus sans avoir besoin du visage. | Moindre privilège. |

### Points à confirmer avec le client

- Prix « Matin simple » / « Soir simple » pour Koumassi/Marcory (non saisi : formule non vendue tant qu'il manque).
- Prix « Soir simple » de la zone Anani/Gonzague/Port-Bouët (supposé 30 000).
- Raison des montants sous la grille (Marcory 25 000 au lieu de 30 000 ; Gravier 8 Kilo Yaou 30 000 au lieu de 50 000).
- Rattachement de ZOUGLOU et BRIQUE à la zone Bonoua/Bassam (déduit du prix payé).

## Étape 7 (début) — Gestion complète, historique, contrôle des QR

Demande : créer / modifier / supprimer clients, tarifs, trajets, zones, lignes, véhicules ; réseau extensible ; paiement avec reçu unique et QR vérifiable par le convoyeur ; historique filtrable avec recherche.

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D93 | Scanner caméra | Classe bas niveau `Html5Qrcode` (QR uniquement), chargée à la demande (bundle séparé) ; styles posés via `element.style`, autorisés par la CSP actuelle (vérifié dans Chrome, aucune violation). Le champ « N° de reçu » marche toujours, même sans caméra ni JavaScript. | Pas d’`unsafe-inline` ; repli si la caméra est refusée. |
| D94 | Matin / soir | Un scan avant `TRANSPORT_EVENING_STARTS_AT` (12:00 par défaut) compte pour le matin, sinon le soir. « Matin simple » refusé le soir, « Soir simple » le matin. | Règle simple, réglable sans code. **À confirmer avec le client.** |
| D95 | Anti-rejeu | Titre déjà accepté depuis moins de `TRANSPORT_ANTI_REPLAY_MINUTES` (tout contrôleur) → « ATTENTION — Déjà scanné à HH:MM par X », sans décompter de trajet. | Détecte le partage d’un QR (capture d’écran). |
| D96 | Journal des scans | Chaque scan est enregistré (`scans` : résultat, code motif, motif en français), y compris les codes inconnus. | Anti-rejeu, remboursement « jamais scanné » (règle 7), rapports. |
| D97 | Photo manquante | Abonnement valide sans photo → ATTENTION (le passager monte), « vérifiez une pièce d’identité ». | Les abonnés importés n’ont pas encore de photo. |
| D98 | Ticket consommé | Un trajet décompté par scan accepté, sous `lockForUpdate`. | Deux contrôleurs simultanés ne décomptent qu’un trajet. |
| D99 | Saisie du reçu | Insensible à la casse et aux espaces, zéros complétés (« ba 2026 17 » → `BA-2026-0017`). | QR d’un reçu papier abîmé. |
| D100 | « Supprimer » (réseau) | Une ligne, zone ou un point **jamais utilisé** est vraiment supprimé (avec ses zones, points et prix). **Utilisé** par un reçu, un titre ou un abonné : archivé (inactif, plus vendu), message explicite. Les véhicules sont toujours supprimés (aucune vente n’y renvoie). Remplace « jamais supprimé » des étapes 4 et 6. | Réseau modifiable librement sans casser les reçus et l’historique. |
| D101 | « Trajet » | Compris comme **point de montée → destination** (ex. Zouglou → Angré), géré dans « Lignes, zones et trajets ». | Vocabulaire de la grille du client. **À confirmer.** |
| D102 | Modifier / supprimer un prix | « Modifier » change le prix en vigueur tout de suite (prochaines ventes ; les reçus gardent leur montant ; l’ancien montant reste au journal). « Supprimer » : prix programmé ou jamais utilisé → supprimé (le précédent est rétabli) ; prix en vigueur déjà utilisé → arrêté à hier (la formule n’est plus vendue dans la zone). Les anciens prix restent en lecture seule. Programmer un prix à une date reste possible. Remplace D-« tarif jamais modifié ». | Demande du client : tarifs modifiables, sans fausser l’historique. |
| D103 | Supprimer un client | Refusé tant qu’un abonnement est valable. La photo est toujours effacée (loi 2013-450). Jamais payé → supprimé ; déjà payé → archivé (*soft delete*), le nom reste sur les reçus ; filtre « Supprimés (archives) ». Permission `customers.delete` (gestionnaire). | Droit à l’effacement sans fausser la comptabilité. |
| D104 | Clients côté administration | Écran « Clients » : recherche (nom, téléphone, N° de reçu), filtres (zone, abonnement en cours / sans, photo manquante, supprimés), fiche avec abonnements et historique des paiements, bouton « Encaisser un abonnement » (guichet, caisse ouverte). | Tout au même endroit pour l’agence. |
| D105 | Encaisser depuis l’administration | Le paiement passe toujours par le guichet (caisse ouverte = traçabilité des espèces). Lien « Encaisser un paiement » dans le menu admin pour qui a `tickets.sell` ; lien « Administration » dans le guichet pour qui a `space.admin`. | Pas de deuxième circuit d’encaissement hors caisse. |
| D106 | Historique des transactions | « Paiements et reçus » : toutes les transactions (payées, annulées, en attente/échouées), filtres mois / statut / type / moyen / zone, recherche ; page détail (client, formule, moyen, référence, vendeur, annulation, historique, **QR code**) et réimpression du reçu (ventes guichet et historique importé). L’export CSV ne contient que les reçus payés (format du client). `payments.view` ajouté au gestionnaire et à la direction. | Retrouver et justifier chaque paiement ; redonner un QR aux abonnés importés. |
| D107 | Recherche | Barre de recherche sur lignes (ligne, zone ou point), tarifs (zone ou point), véhicules (nom, immatriculation + statut), clients, paiements, utilisateurs. | Demande du client. |

### À confirmer avec le client (ajouts)

- Heure de bascule matin / soir (12:00 par défaut).
- Sens de « trajet » (D101).

## Reçu : contenu, PDF et envoi WhatsApp

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D108 | Contenu du reçu | Une ligne par information : client, téléphone, type (abonnement mensuel + formule, ou ticket du jour), mois couvert et dates de validité, montée, zone, **montant payé**, **date de paiement** (date seule pour l’historique importé), moyen, réf. du transfert, agent, QR code. Même contenu sur le ticket thermique et le PDF. | Demande du client. |
| D109 | Reçu PDF | `GET /recus/{référence}/pdf` (dompdf, A5, une page, polices réduites au strict nécessaire : ~27 Ko). Accès : l’agent pour ses ventes, `payments.view`, ou le client pour son propre paiement (PaymentPolicy). Seulement un reçu payé. | Léger à envoyer par WhatsApp ; pas d’IDOR. |
| D110 | Envoi WhatsApp | Un lien `wa.me` ne peut pas joindre de fichier : le bouton ouvre WhatsApp **sur le numéro du client** avec un message prêt (n° de reçu, formule, mois, montant) et un **lien signé** vers le PDF, valable sans compte jusqu’à 7 jours après la fin du titre, refusé si la vente est annulée (410). Chaque envoi est journalisé (`receipt.whatsapp`). | Pas d’API WhatsApp payante ; le lien contient le titre (QR) : signé et à durée limitée. |
| D111 | Partager le fichier | Sur téléphone, bouton « Partager le PDF » (API Web Share avec fichier) : envoie le PDF lui-même dans WhatsApp. Affiché seulement si le navigateur le permet ; PDF préchargé (Safari exige l’appel juste après le toucher). | Le client reçoit le fichier, pas seulement un lien. **Non vérifié sur un vrai téléphone.** |
| D112 | `APP_URL` | Le lien envoyé au client est construit avec `APP_URL` : en production, il doit être l’adresse publique en **https**. | Sinon le client reçoit un lien inaccessible. |

## Tableau de bord

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D113 | Chiffre d’affaires | Reçus **payés** (annulations exclues) comptés à la **date du paiement** (un abonnement de novembre payé le 28 octobre compte en octobre). Comparaison avec le mois précédent. | Correspond à l’argent réellement encaissé, comme le cahier de caisse. |
| D114 | Indicateurs | Mois au choix (24 derniers) : CA, abonnés actifs, reçus et panier moyen, tickets du jour, espèces / Mobile Money, remises accordées, **abonnés à relancer** (abonnés le mois précédent, pas encore ce mois), contrôles à bord du jour. | Pilotage quotidien de l’agence. |
| D115 | Graphiques | Chart.js (npm), chargé seulement sur le tableau de bord ; configuration en JSON dans la page (aucun script en ligne : CSP). CA par jour, sur 12 mois (abonnements / tickets), moyens de paiement, abonnés par formule, CA par zone ; tableaux des points de montée les plus actifs et des derniers paiements. Chaque graphique a ses chiffres en tableau pour les lecteurs d’écran ; animation coupée si « réduire les animations ». | Lisible, accessible, compatible CSP. |
| D116 | Calcul | Agrégation en PHP sur les paiements du mois (et 12 mois pour l’évolution) : même résultat sur SQLite (tests) et MariaDB. À remplacer par des agrégats SQL si le volume dépasse quelques dizaines de milliers de reçus par an. | Simple et sûr pour le volume d’une agence. |

## Nouvelle charte visuelle

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D117 | Style | Inspiré de la planche fournie par le client : fond gris clair, cartes blanches très arrondies (`rounded-card` 28 px, `rounded-tile` 20 px), encre noire, **un seul accent corail**, boutons en pilule, icônes dans des cercles, police **Geist** à graisses légères. Appliqué par les jetons (`resources/css/app.css`) et les composants Blade : aucune page ni logique modifiée. | Demande du client ; changement centralisé, réversible. |
| D118 | Couleurs | Corail (`primary`) : bouton principal en 600 (texte blanc, contraste AA), texte coloré en 700. Gris neutres à la place des gris bleutés. Rouge « refusé » décalé vers le cramoisi et orange « attention » vers l'ambre pour ne pas se confondre avec le corail. Menu actif : pilule noire. | Lisibilité et accessibilité conservées. |
| D119 | Tableau de bord | En tête : pastille ronde du jour, carte du chiffre d'affaires (grand chiffre, répartition espèces / Mobile Money), **anneau corail du taux de renouvellement** sur fond noir, abonnés actifs et à relancer. Graphiques aux couleurs de la charte (corail, pêche, noir, gris). | L'élément mémorable de la planche, appliqué à l'indicateur clé d'une agence d'abonnements. |

## Import de la liste des clients (Excel)

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D120 | Import Excel des clients | Clients → « Importer (Excel) » : .xlsx, .xls, .ods ou .csv (5 Mo, 2 000 lignes). Colonnes trouvées par leur titre (Nom / Noms et prénoms / Client…, Téléphone / Contact / Numéro…, Point de montée facultatif), sinon A = nom, B = téléphone. En deux temps : **aperçu** (à ajouter, déjà enregistrés, à corriger avec n° de ligne et motif) puis **confirmation** ; seules les lignes nouvelles sont créées, rien n’est écrit à l’aperçu, le fichier n’est jamais conservé. Modèle Excel téléchargeable (colonne téléphone en texte). | Reprendre la liste existante de l’agence sans ressaisie, sans doublons ni surprise. |
| D121 | Doublons | Doublon = même nom (sans tenir compte des accents, majuscules, espaces) **et** même numéro, déjà en base ou plus haut dans le fichier. Un même numéro avec un autre nom est accepté (un parent paie pour plusieurs). Réimporter le même fichier n’ajoute rien. | Règle de l’agence (D86). |
| D122 | Numéros | Normalisés en +225 ; un numéro qu’Excel a transformé en nombre (708264421) retrouve son 0. Numéro invalide = ligne à corriger ; numéro vide accepté. | Fichiers Excel réels. |

## Application installable et hors ligne (PWA)

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D123 | Installation | Manifeste `public/manifest.webmanifest` (plein écran, couleurs de la charte, raccourcis « Mes titres » et « Scanner »), icônes générées depuis `resources/pwa/icon.svg` (bus blanc, roue corail, version « maskable »). Carte « Installer l’application » dans l’espace client (bouton natif ; explication sur iPhone). | Accès direct depuis l’écran d’accueil. |
| D124 | Service worker | `public/sw.js` : fichiers Vite, icônes et manifeste en cache d’abord ; pages toujours demandées au réseau, copie gardée seulement pour les titres du client et les pages du contrôleur (scanner, historique) ; ailleurs, page « hors ligne ». Jamais en cache : formulaires (non-GET), `/api`, PDF, administration, guichet. Les titres valables de la liste du client sont mis en cache dès l’affichage de la liste. `VERSION` à augmenter à chaque modification. | Titres et contrôle utilisables sans réseau, sans garder de données inutiles. |
| D125 | Déconnexion | Avertit s’il reste des scans hors ligne non envoyés, efface la liste du jour du contrôleur (IndexedDB `controle-hors-ligne`) et les pages gardées, puis le serveur envoie `Clear-Site-Data: "storage"`. | Téléphones partagés : aucune donnée de passager ne reste. |
| D126 | Contrôle hors ligne : liste du jour | `GET /api/controle/manifeste` (contrôleur) : titres valables aujourd’hui (formule, périodes, zone, montée, validité, trajets, « photo au dossier », dernier scan accepté). Ni photo, ni téléphone, ni clé de signature. `no-store`, téléchargement journalisé. Rafraîchie toutes les 15 min en ligne. | Contrôler sans réseau avec le minimum de données personnelles sur le téléphone. |
| D127 | Vérification hors ligne | Recherche du QR (uuid aléatoire) ou du n° de reçu dans la liste : inconnu → REFUSÉ, mauvaise période → REFUSÉ, ticket épuisé → REFUSÉ, déjà accepté depuis moins de 30 min → ATTENTION ; abonnement toujours ATTENTION (photo non affichable hors ligne). Hors ligne = pas de réseau ou `/api/me` sans réponse en 4 s. | La clé HMAC n’est jamais envoyée aux téléphones ; la signature est vérifiée au serveur à l’envoi. |
| D128 | Envoi des scans | File IndexedDB envoyée au retour du réseau depuis le scanner (`POST /api/controle/scans/sync`, 200 max, CSRF). Chaque scan repasse par VerifyPass à son heure ; trajet décompté seulement si accepté à bord et s’il en reste ; idempotent (`client_id` unique) ; scan de plus de 7 jours ou dans le futur refusé. | Historique et trajets justes ; renvoyer un lot ne double rien. |

## Vitesse et sécurité (audit du 8 octobre 2026)

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D129 | Garde-fou des tests | `tests/TestCase.php` refuse de démarrer si la configuration est en cache ou si la base n’est pas SQLite en mémoire. Ne jamais garder `config:cache` / `route:cache` en local (`php artisan optimize` réservé à la production). | Incident du 8 octobre : avec la configuration en cache, les tests ont ignoré `phpunit.xml` et vidé la base MySQL locale. |
| D130 | Prix négocié au guichet (remplace D89) | Le guichetier n’a plus `sales.discount`. Un prix inférieur à la grille est validé sur place par un responsable (`sales.discount`, compte actif, autre que l’agent) avec téléphone + mot de passe : même message pour tout échec, 5 essais / 10 min par agent, refus journalisés (`sale.discount.refused`). `discounted_by` = le responsable qui a validé ; motif toujours obligatoire ; montant entre 1 et la grille. | L’agent qui encaisse ne peut plus baisser seul le prix et garder la différence (audit F1). |
| D131 | Export CSV | Toute cellule commençant par = + - @ (ou tabulation) est précédée d’une apostrophe. Export réservé à `reports.export` (ajouté au gestionnaire), 10 / min. | Injection de formules Excel via un nom d’abonné (F6) ; F23. |
| D132 | Fiche abonné côté client | Tant qu’un abonnement est valable (ou payé pour un mois à venir), le client ne peut plus changer en ligne le nom ni la photo : à l’agence, avec une pièce d’identité. Les changements de photo en ligne sont journalisés. | Un abonnement personnel ne doit pas pouvoir changer de titulaire (F5). |
| D133 | Liens internes | Le paramètre `retour` n’accepte qu’une page du parcours d’achat (`/espace/acheter/…`). | Pas de lien vers un site externe (F17). |
| D134 | Photos | Entre 200 × 200 et 6 000 × 6 000 pixels. | Une image géante ferait tomber le serveur au ré-encodage (F19). |
| D135 | Import Excel | Taille décompressée limitée à 50 Mo, seules les 2 020 premières lignes et les colonnes A à J sont lues, les formules ne sont jamais calculées. | Fichiers piégés (« bombe zip », F18). |
| D136 | Conservation des données (loi 2013-450) | Tâches planifiées : journal d’activité nettoyé après 1 an, scans après 1 an, réponses brutes des passerelles de paiement vidées après 13 mois. Un client supprimé (jamais payé) disparaît aussi de l’historique d’activité. Nécessite la tâche cron `php artisan schedule:run` chaque minute en production. | F20. |
| D137 | Limites de débit | Ajoutées sur : PDF des reçus, ajout d’abonné en ligne, page de paiement en ligne, création de compte client et réinitialisation de mot de passe au guichet et à l’administration, export. | Abus et surcharge (F23). |
| D138 | Dépendances | `composer audit` : rien. `npm audit` : `shell-quote` (outil de développement) forcé en 1.12 via `overrides`. | 0 vulnérabilité connue. |
| D139 | Vitesse | OPcache activé, autoload optimisé, cache des icônes, sessions et cache en fichiers en local, 4 processus pour `php artisan serve --no-reload`, préchargement des liens au survol (Speculation Rules, sans les liens à effet). Temps serveur : 0,7–1,5 s → 60–150 ms. | Demande du client : application rapide. |
| D140 | En-tête Host | Seul l’hôte exact d’`APP_URL` est accepté (`trustHosts`, sans sous-domaines, motif ancré). En production, tous les liens absolus (réinitialisation, vérification d’e-mail, reçus) sont construits depuis `APP_URL` en https. | Un faux en-tête Host ne peut plus envoyer les liens de réinitialisation vers un site pirate (F2). |
| D141 | Verrouillage | Un verrou expiré repart d’un compteur à zéro (5 erreurs → LOCKOUT_MINUTES). Commande `php artisan users:unlock <téléphone ou e-mail>` (journalisée) pour débloquer un compte, notamment le seul super-administrateur. | Pas de blocage permanent entretenu par un tiers (F7). |
| D142 | Fichiers d’environnement | `.env.example` = développement local (plus de `SESSION_SECURE_COOKIE=false`). Nouveau `.env.production.example` : debug coupé, logs « warning », cookie sécurisé, session chiffrée, IP CinetPay à renseigner, aucun secret. Sessions chiffrées par défaut. | Copier le fichier local sur le serveur ne doit pas ouvrir de faille (F8). |
| D143 | Pas d’élévation de privilèges | Un gestionnaire ne donne qu’un rôle dont il détient toutes les permissions (le rôle client reste ouvert). Mot de passe provisoire d’un membre du personnel et changement de son téléphone ou e-mail : super-administrateur seulement. Changements de téléphone, e-mail et rôle journalisés (ancienne et nouvelle valeur). **Conséquence : créer un compte guichetier, contrôleur ou comptable est réservé au super-administrateur** (à confirmer avec le client). | Un gestionnaire ne peut ni se créer un compte plus puissant ni prendre la main sur celui d’un collègue (F4). |
| D144 | Proxys de confiance | Liste explicite `TRUSTED_PROXIES` (IP ou CIDR), vide = aucun, jamais « * ». | L’IP réelle sert au blocage des essais et au journal (F9). |
| D145 | Sessions et mot de passe | `AuthenticateSession` : quand le mot de passe change (par la personne, par e-mail ou mot de passe provisoire), toutes les autres sessions sont fermées ; la session de la personne qui change le sien reste ouverte. | Un téléphone volé perd l’accès dès le changement de mot de passe (F11). |
| D146 | Mots de passe jamais renvoyés | `current_password`, `password`, `password_confirmation`, `supervisor_password`, `approver_password` ne sont jamais remis en session après une erreur. | F12. |
| D147 | Validation du superviseur | Réponse unique « Identifiants du superviseur incorrects ou non autorisés. », même temps de calcul ; un mauvais mot de passe compte comme un échec de connexion sur le compte du superviseur ; refus journalisés. | Le formulaire d’annulation ne permet plus de tester des mots de passe (F13). |
| D150 | Saisie du n° de reçu (remplace D99 en partie) | Saisie manuelle acceptée pour les abonnements seulement (BA-…), verdict ATTENTION « N° saisi à la main : vérifiez le nom et la photo ». Ticket du jour (TJ-…) saisi : REFUSÉ, code `manual_entry`, aucun trajet décompté. En ligne et hors ligne. | Un n° de reçu se lit sur le reçu d’un autre ; le QR signé est la preuve (F3). |
| D151 | Traçabilité des saisies manuelles | Chaque n° saisi à la main est écrit dans le journal `scans` (`scan.manual_entry`). | Contrôle a posteriori. |
| D152 | Photo au contrôle | Servie au contrôleur seulement par un lien signé valable 5 min, créé pour le titre qui vient d’être scanné. `viewPhoto` = compte propriétaire ou `customers.view`. | Impossible de parcourir toutes les photos en changeant l’identifiant (F10). |
| D153 | Ancienneté des scans hors ligne (remplace « 7 jours » de D128) | Au plus 24 h et 10 min d’avance ; au-delà refusé et retiré de la file du téléphone. | Scans antidatés (F14). |
| D154 | Ticket synchronisé après son jour | Enregistré (ATTENTION, `offline_late`), aucun trajet décompté. | Un scan antidaté ne peut pas consommer un ticket après coup. |
| D155 | File hors ligne trop ancienne | Les scans de plus de 24 h sont supprimés avant l’envoi et le nombre est affiché au contrôleur. | Transparence. |
| D156 | Changement de compte sur le téléphone | `<body data-user-id>` comparé au dernier compte vu : copies de pages et liste du jour effacées ; la file de scans non envoyés est gardée pour le même contrôleur si sa session a expiré, effacée si un autre compte se connecte. | Pas de données d’un compte montrées à un autre (F15). |
| D157 | Service worker v2 | `VERSION = 'v2'` : anciens caches supprimés. | Purge des copies antérieures. |
| D158 | Compte désactivé | Sa déconnexion forcée envoie aussi `Clear-Site-Data: "storage"`. | Même effacement qu’à la déconnexion volontaire. |
| D159 | Liste du jour (complète D126) | Le nom du titulaire est gardé (comparé à une pièce d’identité hors ligne) ; le n° de reçu des tickets n’est plus envoyé. | Minimisation (F16). |

## Dépenses

| # | Sujet | Décision | Raison |
| --- | --- | --- | --- |
| D160 | Module Dépenses | Administration → Finances → Dépenses : date, type, libellé, montant, moyen de paiement (espèces, Mobile Money, virement, chèque), référence, véhicule facultatif, justificatif facultatif (photo ou PDF, 5 Mo, disque privé, servi seulement aux personnes autorisées). Liste par mois avec filtres (type, véhicule, moyen, recherche), **total**, **total par type** (barres et graphique), comparaison avec le mois précédent, export CSV (formules neutralisées, ligne TOTAL). | Demande du client. |
| D161 | Types de dépenses | Types modifiables à l’écran (9 types fournis : carburant, entretien, pièces et pneus, salaires, péage, assurance, loyer, fournitures, autres). Un type déjà utilisé n’est jamais effacé : il est archivé (plus proposé) et reste dans les totaux passés. | Pas de liste figée ; historique juste. |
| D162 | Supprimer une dépense | Archivage (*soft delete*) : elle ne compte plus dans les totaux ; création, modification (anciennes et nouvelles valeurs) et suppression sont au journal d’activité, avec l’auteur. | Traçabilité comptable. |
| D163 | Droits | Voir : gestionnaire, direction, comptable. Saisir / modifier / supprimer : gestionnaire et comptable. Export : `reports.export`. Guichet, contrôle et clients : aucun accès. | Moindre privilège. |
| D164 | Tableau de bord | Ajout de « Dépenses du mois », « Résultat (chiffre d’affaires − dépenses) », dépenses par type et dépenses dans le graphique sur 12 mois (barres à côté des recettes). Dépenses comptées à leur date. | Vision recettes / dépenses / résultat. |
| D165 | Liste des clients | Boutons « Modifier » et « Supprimer » (avec confirmation) directement sur chaque ligne, selon les droits ; rien pour un client archivé ; message clair si un abonnement est en cours. | Demande du client. |
