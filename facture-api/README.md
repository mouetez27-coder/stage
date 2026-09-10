# facture-api

API Laravel de la plateforme ELFATOORA : authentification Sanctum, facturation, génération TEIF, PDF et signature XAdES-B.

La documentation complète d'installation et d'exécution se trouve dans [DOCUMENTATION_TECHNIQUE.md](../DOCUMENTATION_TECHNIQUE.md).

## Commandes rapides

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
New-Item -ItemType File -Path .\database\database.sqlite -Force
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

## Tests

```powershell
php artisan test
```
