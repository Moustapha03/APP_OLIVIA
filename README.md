# Transport Tickets

Application de gestion des **tickets journaliers** et **abonnements mensuels** d'une compagnie de transport sur l'axe Abidjan – Anani – Grand-Bassam – Bonoua.

- Vente en ligne (Mobile Money) et au guichet (espèces)
- Titres avec QR code signé, contrôle à bord sur smartphone (fonctionne hors ligne)
- Administration : arrêts, tarifs, caisses, rapports, journal d'audit

Interface en français, montants en FCFA, fuseau `Africa/Abidjan`.

## Stack

| Couche | Outils |
| --- | --- |
| Back-end | Laravel 12, PHP 8.3+ (8.2 toléré en local), MySQL 8, Redis (via predis) |
| Front | Blade, Tailwind CSS v4 (Vite), Alpine.js, JS natif |
| Auth & droits | Laravel Breeze (adapté : téléphone + mot de passe, sans SMS ni 2FA), spatie/laravel-permission |
| Audit | spatie/laravel-activitylog |
| QR | simplesoftwareio/simple-qrcode (génération), html5-qrcode (scan) |
| Exports | barryvdh/laravel-dompdf, maatwebsite/excel |
| Qualité | Pest, Larastan (niveau 6), Laravel Pint |

## Prérequis

- PHP 8.3+ avec les extensions `pdo_mysql`, `mbstring`, `gd`, `zip`, `xml`, `fileinfo`, `bcmath`
- Composer 2, Node.js 20+ et npm
- MySQL 8 (ou MariaDB 10.4+ en local)
- Redis (production ; facultatif en local)

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate

# Clé de signature des QR codes : coller le résultat dans QR_SIGNING_KEY
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"

# Créer la base indiquée par DB_DATABASE, puis :
php artisan migrate --seed

npm install
npm run build
```

> **Windows** : si Composer échoue avec « Could not delete … », l'antivirus bloque l'extraction parallèle. Relancer avec `COMPOSER_MAX_PARALLEL_PROCESSES=1`.

## Comptes de test (local uniquement)

Connexion par **numéro de téléphone + mot de passe** (pas de code SMS, pas de 2FA). Le personnel peut aussi saisir son e-mail.

| Rôle | Téléphone | Mot de passe |
| --- | --- | --- |
| super-admin | 07 00 00 00 00 (ou `admin@gmail.com`) | `1234567890` |
| gestionnaire | 07 00 00 00 01 | `demo-password-2026` |
| direction | 07 00 00 00 02 | `demo-password-2026` |
| comptable | 07 00 00 00 03 | `demo-password-2026` |
| guichetier | 07 00 00 00 04 | `demo-password-2026` |
| contrôleur | 07 00 00 00 05 | `demo-password-2026` |
| client | 07 00 00 00 06 | `demo-password-2026` |

Ces seeders refusent de s'exécuter hors `local` / `testing`. Réinitialiser : `php artisan migrate:fresh --seed`.

## Lancer en développement

```bash
composer dev
```

Démarre en parallèle le serveur (`http://localhost:8000`), la file d'attente, les logs (Pail) et Vite.

**Paiement de test** : avec `PAYMENT_DRIVER=fake`, l'achat ouvre une page « Mobile Money » simulée (Valider / Refuser). Aucun argent n'est prélevé. Les notifications de paiement passent par la file d'attente (`queue:listen`, lancé par `composer dev`) ; au retour sur le site, le statut est aussi vérifié directement.

**Avant la production avec CinetPay** : renseigner `CINETPAY_SITE_ID`, `CINETPAY_API_KEY`, `CINETPAY_SECRET_KEY`, passer `PAYMENT_DRIVER=cinetpay`, déclarer l'URL de notification `https://<domaine>/webhooks/paiements/cinetpay` et faire un essai complet sur le sandbox CinetPay.

## Qualité

```bash
composer test       # tests Pest
composer lint       # formatage Pint
composer analyse    # Larastan niveau 6
composer quality    # tout : Pint (vérification), Larastan, tests, composer audit
npm audit
```

Les tests utilisent SQLite en mémoire : aucune base à préparer.

## Configuration

Tous les secrets et réglages passent par `.env` (voir `.env.example`, commenté section par section) :

- **Règles métier** (`config/transport.php`) : trajets par ticket, durée d'abonnement, rappel SMS, anti-rejeu, taille des photos.
- **QR codes** : `QR_SIGNING_KEY` (HMAC-SHA256) avec rotation via `QR_SIGNING_PREVIOUS_KEYS`.
- **Paiement** (`config/services.php`) : `PAYMENT_DRIVER=fake` en développement, `cinetpay` en production. Webhooks signés HMAC (`PAYMENT_WEBHOOK_SECRET`) et liste blanche d’IP.
- **SMS** (rappels d’expiration uniquement) : `SMS_DRIVER=log` en développement (les SMS sont écrits dans `storage/logs`).
- **Sécurité** : verrouillage après `MAX_LOGIN_ATTEMPTS` échecs pendant `LOCKOUT_MINUTES` ; session du personnel expirée après `SESSION_LIFETIME` minutes d’inactivité.

## Importer la liste des clients (Excel)

Administration → Clients → **Importer (Excel)** : envoyer le fichier, vérifier l'aperçu, confirmer. Un modèle Excel est téléchargeable sur la page. L'extension PHP **zip** doit être activée (`extension=zip` dans `php.ini`) pour lire les fichiers .xlsx.

## Reprendre l'historique des reçus

Le fichier CSV de l'agence (colonnes `N°;Date;Abonné;Téléphone;Point de montée;Mois;Formule;Paiement;Réf.;Montant FCFA`) se range dans `storage/app/private/imports/` (données personnelles : jamais dans `public/` ni dans Git).

```bash
php artisan receipts:import storage/app/private/imports/historique.csv --dry-run   # vérifier
php artisan receipts:import storage/app/private/imports/historique.csv             # importer
```

L'import est idempotent (un reçu déjà présent est ignoré) et la numérotation des reçus continue après le dernier numéro importé. L'export au même format se fait depuis **Administration → Abonnés et reçus**.

## Application installable (PWA)

- `public/manifest.webmanifest`, icônes dans `public/icons/` (source : `resources/pwa/icon.svg`), service worker `public/sw.js`, enregistré par `resources/js/pwa.js`.
- **https obligatoire en production** (le service worker, la caméra du scanner et l’installation ne marchent qu’en https ; `localhost` est toléré en développement).
- Le serveur web doit servir `sw.js` **sans cache longue durée** (`Cache-Control: no-cache`), sinon les téléphones gardent l’ancienne version.
- Après une modification de `public/sw.js`, augmenter `VERSION` en haut du fichier : les anciens caches sont supprimés.
- Hors ligne : titres du client (pages déjà ouvertes, avec QR code), page « hors ligne » pour le reste ; contrôle à bord hors ligne (liste du jour + scans envoyés au retour du réseau).
- À la déconnexion, les copies hors ligne sont effacées (`Clear-Site-Data: "storage"` + message au service worker).

## Performance

Mesuré en local (temps de réponse du serveur) : 0,7 à 1,5 s avant réglages, **60 à 150 ms** après.

- **OPcache** activé dans `php.ini` (`zend_extension=opcache`, `opcache.enable=1`, `opcache.memory_consumption=256`, `opcache.max_accelerated_files=20000`). En production : `opcache.validate_timestamps=0` (puis vider OPcache à chaque déploiement).
- **Au déploiement** : `composer install --no-dev --optimize-autoloader`, `npm run build`, puis `php artisan optimize` (config, routes, vues, événements, icônes). Après une modification du `.env` en production : `php artisan optimize` à nouveau.
- **En local**, ne pas garder `config:cache` ni `route:cache` (les tests liraient la configuration de la vraie base) : seuls `php artisan view:cache`, `event:cache` et `icons:cache` sont sans risque.
- Sessions et cache : `file` en local ; en production `database` (ou Redis si plusieurs serveurs).
- `php artisan serve --no-reload` avec `PHP_CLI_SERVER_WORKERS=4` dans `.env` : plusieurs requêtes en parallèle (le serveur intégré traite sinon une requête à la fois, fichiers CSS/JS compris).
- Navigation instantanée : les liens sont préchargés au survol / à l’appui (Speculation Rules, Chrome/Edge/Android), sauf téléchargements, exports, WhatsApp, impression et déconnexion.

## Organisation (cible)

```
app/
  Actions/            logique métier unitaire (vendre, scanner, clôturer…)
  Contracts/          PaymentGateway, SmsSender
  Enums/              statuts et types
  Http/
    Controllers/      Admin/, Guichet/, Controle/, Espace/, Webhooks/
    Middleware/       EnsureUserIsActive, RequireTwoFactor, SecurityHeaders…
    Requests/         Form Requests
  Models/
  Policies/
  Services/           Payment/, Qr/, Photos/, Receipts/
config/transport.php  règles métier par défaut
routes/
  web.php             public + espace client (/espace)
  admin.php           /admin
  guichet.php         /guichet
  controle.php        /controle
  api.php             synchronisation PWA contrôle
  webhooks.php        notifications Mobile Money
resources/views/
  components/         <x-button>, <x-card>, <x-input>…
  layouts/            un layout par espace
```

## Documentation

- [DECISIONS.md](DECISIONS.md) — choix techniques et arbitrages
- Guide utilisateur et guide de déploiement : étape 10
