# Documentation technique — ELFATOORA

Plateforme de facturation électronique tunisienne basée sur Laravel, Vue 3, TEIF et XAdES-B.

## 1. Présentation

Le projet est composé de deux applications indépendantes :

- `facture-api` : API Laravel 13, authentification Sanctum, SQLite, génération XML TEIF, PDF et signature XAdES-B.
- `facture-front` : interface Vue 3 + Vite + TypeScript.

En développement, le frontend appelle l'API à l'adresse `http://127.0.0.1:8000/api`.

## 2. Prérequis

Installer les outils suivants et vérifier leurs versions :

```powershell
php -v
composer --version
node --version
npm --version
```

Versions attendues :

- PHP 8.3 ou supérieur, avec les extensions `openssl`, `dom`, `pdo_sqlite`, `mbstring`, `xml` et `fileinfo`.
- Composer 2.x.
- Node.js `22.18+` ou `24.12+`.
- npm fourni avec Node.js.

Aucun serveur MySQL n'est nécessaire pour le développement : la configuration par défaut utilise SQLite.

## 3. Installation initiale

Depuis la racine du workspace :

### 3.1 Installer et configurer l'API

```powershell
cd .\facture-api
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
New-Item -ItemType File -Path .\database\database.sqlite -Force
php artisan key:generate
php artisan migrate --seed
```

Si `.env` existe déjà, ne pas l'écraser. Vérifier au minimum les valeurs suivantes :

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
MAIL_MAILER=log
TEIF_SIGNATURE_CERT_PATH=certs/test-cert.pem
TEIF_SIGNATURE_KEY_PATH=certs/test-key.pem
```

Les certificats de démonstration suivants doivent être présents :

- `facture-api/certs/test-cert.pem`
- `facture-api/certs/test-key.pem`

La commande `migrate --seed` crée les tables, une entreprise de test et l'utilisateur administrateur.

### 3.2 Installer le frontend

Ouvrir un nouveau terminal PowerShell :

```powershell
cd .\facture-front
npm install
```

Le frontend utilise par défaut l'API locale. Pour définir une autre adresse, créer `facture-front/.env.local` :

```dotenv
VITE_API_URL=http://127.0.0.1:8000/api
```

Après toute modification de cette variable, redémarrer Vite.

## 4. Lancer l'application en développement

Deux terminaux sont nécessaires.

### Terminal 1 — API Laravel

```powershell
cd C:\Users\chahr\OneDrive\Desktop\stage\facture-api
php artisan serve --host=127.0.0.1 --port=8000
```

API : http://127.0.0.1:8000

### Terminal 2 — Frontend Vue

```powershell
cd C:\Users\chahr\OneDrive\Desktop\stage\facture-front
npm run dev
```

Interface : http://localhost:5173

Ouvrir ensuite `http://localhost:5173` dans le navigateur.

Le script Laravel `composer run dev` peut aussi démarrer simultanément le serveur Laravel, la file d'attente, les logs et Vite depuis `facture-api`, si les dépendances nécessaires sont installées :

```powershell
cd .\facture-api
composer run dev
```

## 5. Première connexion

Après le seed initial :

- Email : `admin@facture.com`
- Mot de passe : `admin123`

Ce compte est destiné au développement et à la démonstration. Il doit être remplacé ou supprimé dans un environnement réel.

## 6. Parcours fonctionnel

1. Se connecter depuis l'interface.
2. Créer une facture avec les informations du client et au moins une ligne.
3. Générer le XML TEIF.
4. Télécharger le PDF ou le XML.
5. Signer le XML avec le certificat de test.
6. Vérifier la signature et l'intégrité du document.
7. Envoyer la facture par e-mail.

Pour le scénario de démonstration détaillé et les valeurs de facture prêtes à saisir, consulter [DEMO_GUIDE.md](DEMO_GUIDE.md).

## 7. Fonctionnement technique

### Backend

- Les routes sont définies dans `facture-api/routes/api.php`.
- `POST /api/login` authentifie l'utilisateur et retourne un token Sanctum.
- Les routes de facturation sont protégées par `auth:sanctum`.
- Les factures, lignes et entreprises sont persistées dans SQLite.
- Le XML est généré selon le format TEIF.
- Le PDF est généré avec Dompdf.
- La signature utilise le certificat X.509 et la clé privée configurés dans `.env`.
- La vérification contrôle notamment la signature RSA-SHA256, les digests XML, le digest du certificat et la validité temporelle du certificat.

### Frontend

- Axios est configuré dans `facture-front/src/api.js`.
- Le token d'authentification est conservé dans `localStorage` sous la clé `auth_token`.
- Les appels API utilisent `VITE_API_URL`, ou `http://127.0.0.1:8000/api` par défaut.
- Le serveur de développement Vite utilise le port `5173` par défaut.

## 8. Commandes de validation

### Tests backend

```powershell
cd .\facture-api
php artisan test
```

Le jeu de tests actuel couvre l'authentification, les factures, la génération de signature, la vérification et la détection d'altération.

Pour cibler la vérification XAdES :

```powershell
php artisan test --filter=TeifSignatureVerifierServiceTest
```

### Vérification frontend

```powershell
cd .\facture-front
npm run build
```

Cette commande effectue le contrôle TypeScript puis génère le bundle de production dans `facture-front/dist`.

### Formatage Laravel

```powershell
cd .\facture-api
vendor\bin\pint
```

## 9. E-mails en développement

La configuration `.env.example` utilise `MAIL_MAILER=log`. Les e-mails ne sont donc pas délivrés à une boîte réelle : leur contenu est écrit dans les logs Laravel, généralement dans :

```text
facture-api/storage/logs/laravel.log
```

Pour un envoi réel, configurer un serveur SMTP dans `.env`, par exemple :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=utilisateur
MAIL_PASSWORD=mot-de-passe
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="ELFATOORA"
```

Ne jamais versionner de vrais mots de passe, clés privées ou certificats de production.

## 10. Dépannage

### `php artisan` ne fonctionne pas

Vérifier que le terminal est dans `facture-api` et que PHP est accessible dans le `PATH` :

```powershell
cd .\facture-api
php artisan about
```

### `npm run dev` ou `npm run build` ne fonctionne pas

Vérifier que le terminal est dans `facture-front`, puis réinstaller les dépendances :

```powershell
cd .\facture-front
npm install
```

### Erreur de base de données SQLite

Créer le fichier absent puis rejouer les migrations :

```powershell
cd .\facture-api
New-Item -ItemType File -Path .\database\database.sqlite -Force
php artisan migrate --seed
```

Pour repartir d'une base de développement vide, utiliser uniquement si la suppression des données est acceptable :

```powershell
php artisan migrate:fresh --seed
```

### Erreur CORS ou API inaccessible

Vérifier que Laravel tourne sur le port `8000`, que `VITE_API_URL` correspond à cette adresse et que le frontend a été redémarré après toute modification de `.env.local`.

### Erreur de signature

Vérifier la présence et les droits de lecture de `certs/test-cert.pem` et `certs/test-key.pem`, puis confirmer les chemins `TEIF_SIGNATURE_CERT_PATH` et `TEIF_SIGNATURE_KEY_PATH` dans `.env`.

## 11. Préparation production

Avant une mise en production :

- utiliser un certificat et une clé privée officiels, stockés hors du dépôt ;
- remplacer les identifiants de démonstration ;
- utiliser une base de données et un stockage adaptés ;
- configurer un SMTP réel et sécurisé ;
- définir `APP_ENV=production` et `APP_DEBUG=false` ;
- exécuter `php artisan config:cache`, `php artisan route:cache` et `php artisan view:cache` ;
- servir le dossier `facture-api/public` via un serveur web avec HTTPS ;
- protéger les tokens, sauvegardes, fichiers XML signés et journaux ;
- valider la conformité finale avec les exigences TTN applicables.
