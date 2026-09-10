# Guide Complet de Démonstration — Plateforme ELFATOORA
## Facturation Électronique TEIF v1.9.0 & Signature Électronique XAdES-B (TTN El Fatoora v3.0)

Ce guide contient **toutes les étapes pas-à-pas** pour réussir une démonstration complète et impressionnante du projet, y compris **les valeurs exactes à saisir** dans chaque champ.

---

## Sommaire
1. [Architecture & Contexte Métier](#1-architecture--contexte-métier)
2. [Lancement des Serveurs](#2-lancement-des-serveurs)
3. [Étape 1 : Connexion à la Plateforme](#étape-1--connexion-à-la-plateforme)
4. [Étape 2 : Découverte du Tableau de Bord](#étape-2--découverte-du-tableau-de-bord)
5. [Étape 3 : Création d'une Facture Conforme TEIF](#étape-3--création-dune-facture-conforme-teif)
6. [Étape 4 : Génération XML TEIF et Document PDF](#étape-4--génération-xml-teif-et-document-pdf)
7. [Étape 5 : Signature Électronique XAdES-B (Cœur TTN)](#étape-5--signature-électronique-xades-b-cœur-ttn)
8. [Étape 6 : Audit Cryptographique & Vérification d'Intégrité](#étape-6--audit-cryptographique--vérification-dintégrité)
9. [Étape 7 : Test de Détection d'Altération (Anti-Fraude)](#étape-7--test-de-détection-daltération-anti-fraude)
10. [Étape 8 : Envoi par Email](#étape-8--envoi-par-email)
11. [Commandes de Test & Validation Automatisée](#11-commandes-de-test--validation-automatisée)

---

## 1. Architecture & Contexte Métier

- **Backend** : Laravel 12 (PHP 8.4) avec Sanctum, OpenSSL, DOMDocument C14N et SQLite.
- **Frontend** : Vue 3 + Vite + TypeScript.
- **Normes respectées** :
  - **TEIF v1.9.0** (Tunisie E-Invoicing Format).
  - **TTN El Fatoora v3.0** / **ETSI TS 101 903 (XAdES-B)** enveloppé.
  - Politique de signature officielle TTN : OID `urn:2.16.788.1.2.1.3`.
  - Algorithmes : RSA-SHA256, Exclusive Canonicalization (C14N-EXC), Digest SHA-256, CertDigest SHA-1.

---

## 2. Lancement des Serveurs

Ouvrez **deux terminaux PowerShell** :

### Terminal 1 : Backend API (Laravel)
```powershell
cd c:\Users\chahr\OneDrive\Desktop\stage\facture-api
php artisan serve
```
> Le serveur écoute sur : `http://127.0.0.1:8000`

### Terminal 2 : Frontend (Vue 3 / Vite)
```powershell
cd c:\Users\chahr\OneDrive\Desktop\stage\facture-front
npm run dev
```
> L'interface web est disponible sur : `http://localhost:5173`

---

## Étape 1 : Connexion à la Plateforme

1. Ouvrez votre navigateur sur : **`http://localhost:5173`**
2. La page de connexion s'affiche avec la charte graphique ELFATOORA.
3. Renseignez les identifiants administrateur :

| Champ | Valeur exacte à saisir |
| :--- | :--- |
| **Adresse email** | `admin@facture.com` |
| **Mot de passe** | `admin123` |

4. Cliquez sur le bouton **"Se connecter"**.
5. **Résultat attendu** : Authentification réussie via API Sanctum et redirection immédiate vers l'accueil.

---

## Étape 2 : Découverte du Tableau de Bord

Sur la page d'accueil, observez :
- Le bandeau de conformité : `● Conforme TEIF v1.9.0 & XAdES-B v3.0`.
- Les cartes d'indicateurs temps réel :
  - **Total Factures**
  - **Factures Signées XAdES-B** (avec icône de cadenas de sécurité)
  - **Volume Total TTC** en Dinars Tunisiens (TND)
- Les boutons d'accès rapide : **"Nouvelle Facture"** et **"Liste des Factures & Signatures"**.

---

## Étape 3 : Création d'une Facture Conforme TEIF

1. Cliquez sur **"Générer facture"** dans le menu latéral (ou sur le bouton de l'accueil).
2. Remplissez le formulaire avec les **valeurs exactes suivantes** :

### A. Informations de la facture
| Champ | Valeur à saisir | Remarque |
| :--- | :--- | :--- |
| **Numéro de facture** | `FAC-2026-001` | Identifiant unique (ex: `FAC-2026-002` si déjà utilisé) |
| **Date de facture** | *(Laisser la date du jour)* | Format automatique YYYY-MM-DD |
| **Date limite** | `2026-10-05` | Optionnel (30 jours) |
| **Mode de paiement** | `Virement` | Ou `Espèces` / `Chèque` |
| **Devise** | `TND` | Dinar Tunisien (3 décimales) |

### B. Informations du Client (Conformes au schéma TEIF)
| Champ | Valeur à saisir | Remarque importante |
| :--- | :--- | :--- |
| **Nom du client** | `Société Maghrébine de Négoce` | Raison sociale |
| **Matricule fiscal** | `0000001B` | **Validé par l'algorithme modulo 23 de l'État tunisien** (la clé de contrôle pour `0000001` est `B`). *Vous pouvez aussi utiliser `1234567R` ou laisser vide pour un client particulier.* |
| **Adresse** | `12 Avenue Habib Bourguiba` | Adresse obligatoire TEIF |
| **Ville** | `Tunis` | Ville obligatoire TEIF |
| **Code postal** | `1000` | Obligatoire TEIF v1.9.0 |
| **Pays** | `TN` | Code ISO 2 lettres |
| **Téléphone** | `71234567` | Optionnel |
| **Email** | `contact@client-negoce.tn` | Utilisé pour l'envoi de facture |

### C. Lignes de Facture
| Code | Désignation | Qté | Unité | PU HT | TVA % | Remise |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `SRV-01` | `Prestation d'ingénierie et audit TEIF` | `2` | `UNIT` | `250.000` | `19` | `0` |

*(Optionnel : Cliquez sur **"+ Ajouter une ligne"** pour ajouter un second article si souhaité, ex: `MAT-01` / `Licence logicielle` / Qté `1` / `UNIT` / PU `100.000` / TVA `19`).*

### D. Vérification des Totaux Automatiques
- **Total HT** : `500.000 TND`
- **Total TVA (19%)** : `95.000 TND`
- **Droit de timbre légal** : `1.000 TND`
- **Total TTC** : `596.000 TND`

3. Cliquez sur le bouton **"Créer la facture"**.
4. **Résultat attendu** : Message de confirmation `"Facture enregistrée avec succès"` puis redirection automatique vers la **Liste des factures**.

---

## Étape 4 : Génération XML TEIF et Document PDF

Dans la liste des factures :
1. Repérez votre facture **`FAC-2026-001`**.
2. Remarquez le statut initial :
   - Statut Envoi : `Non envoyée`
   - Signature Électronique : **`Non signée`** (badge gris)
3. Cliquez sur **"Générer XML"** :
   - Notification bleue : `XML généré pour FAC-2026-001.`
   - Le bouton **`XML`** devient actif.
4. Cliquez sur le bouton **`PDF`** :
   - Le PDF professionnel officiel de la facture est généré à la volée et téléchargé sur votre ordinateur.
5. Cliquez sur le bouton **`XML`** :
   - Le fichier brut XML conforme TEIF v1.9.0 (`FAC-2026-001.xml`) est téléchargé.

---

## Étape 5 : Signature Électronique XAdES-B (Cœur TTN)

C'est l'étape maîtresse démontrant la conformité avec la réglementation TTN :

1. Sur la ligne de la facture, cliquez sur le bouton bleu **"Signer"**.
2. **Ce que réalise le système en arrière-plan (Staff Engineer Level)** :
   - Si le XML brut n'était pas généré, il le génère automatiquement en une seule opération atomique.
   - Charge le certificat X.509 (`certs/test-cert.pem`) et la clé privée RSA (`certs/test-key.pem`).
   - Calcule la signature XAdES-B enveloppée avec transform XPath `not(ancestor-or-self::ds:Signature)` et C14N-EXC.
   - Intègre les métadonnées officielles de la politique TTN (`urn:2.16.788.1.2.1.3`) et le rôle `Fournisseur`.
   - Persiste la date de signature (`signed_at`), l'identité du signataire (`signer_dn`), et enregistre le fichier `FAC-2026-001-signed.xml`.
3. **Changements visuels immédiats sur l'interface** :
   - Le badge devient : **`✓ Signée (XAdES-B)`** en bleu/vert avec la date et l'heure exacte.
   - Un bouton vert **"XML Signé"** apparaît.
   - Un bouton bleu ciel **"Vérifier"** apparaît.
   - Le libellé du bouton devient **"Re-signer"**.
4. Cliquez sur **"XML Signé"** :
   - Le fichier certifié `FAC-2026-001-signed.xml` est téléchargé. Si vous l'ouvrez, vous y trouverez le bloc `<ds:Signature Id="SigFrs">` complet !

---

## Étape 6 : Audit Cryptographique & Vérification d'Intégrité

1. Cliquez sur le bouton **"Vérifier"** situé à côté de la facture signée.
2. Une **fenêtre modale professionnelle d'audit** s'affiche :
   - **Bannière verte de succès** :
     > **✓ Signature Cryptographiquement Valide**
     > *Le document TEIF respecte l'intégrité des données, la signature RSA-SHA256 est authentifiée et conforme aux spécifications TTN.*
   - **Cartouche Signataire & Rôle** :
     - Nom (CN) : `1234567R`
     - Organisation : `Ma Societe Test`
     - Pays : `TN`
     - Rôle : `Fournisseur`
   - **Cartouche Certificat X.509** :
     - Émetteur : `/C=TN/O=Ma Societe Test/CN=1234567R`
     - N° de série : `0`
     - Période de validité officielle
   - **Cartouche Politique Officielle TTN** :
     - Date & Heure UTC de signature (`SigningTime`)
     - OID officiel : `urn:2.16.788.1.2.1.3`
     - Lien direct vers la documentation officielle TTN
   - **Liste des 5 Contrôles d'Intégrité (tous validés `✓`)** :
     - `✓` Signature RSA-SHA256 (`SignedInfo`)
     - `✓` Intégrité du document TEIF (Digest `r-id-frs`)
     - `✓` Intégrité des propriétés XAdES (Digest `#xades-SigFrs`)
     - `✓` Empreinte SHA-1 du certificat (`CertDigest` conforme TTN)
     - `✓` Validité temporelle du certificat X.509
3. Cliquez sur **"Fermer"** pour refermer la modale.

---

## Étape 7 : Test de Détection d'Altération (Anti-Fraude)

Pour démontrer au client ou à l'évaluateur la sécurité absolue du mécanisme contre les falsifications :

1. Ouvrez un terminal PowerShell.
2. Exécutez le script unitaire de test anti-altération :
```powershell
cd c:\Users\chahr\OneDrive\Desktop\stage\facture-api
php artisan test --filter=TeifSignatureVerifierServiceTest
```
3. **Résultat affiché** :
   ```
   PASS  Tests\Unit\TeifSignatureVerifierServiceTest
   ✓ verify valid signed xml
   ✓ verify detects document tampering (anti-tampering)
   ✓ verify detects signature tampering
   ✓ verify returns error for unsigned xml
   ✓ verify returns error for malformed xml
   ```
> **Explication pour la démo** : Si un tiers malveillant modifie ne serait-ce qu'un seul chiffre (par exemple le montant ou le numéro de compte dans le fichier XML), le contrôle `document_digest` échoue immédiatement et la facture est déclarée invalide avec le message : *"Altération détectée : le digest du document TEIF ne correspond pas."*

---

## Étape 8 : Envoi par Email

1. Dans la liste des factures, cliquez sur le bouton vert **"Email"** sur la ligne de votre facture.
2. La facture est envoyée au client (`contact@client-negoce.tn`) avec le PDF et le XML TEIF joints.
3. Le badge passe à **`✓ Envoyée`** en vert.

---

## 11. Commandes de Test & Validation Automatisée

Pour exécuter l'ensemble de la suite de tests automatisés couvrant 100% de la chaîne de valeur :

```powershell
cd c:\Users\chahr\OneDrive\Desktop\stage\facture-api
php artisan test
```

**Résultat garanti :**
```
Tests:    16 passed (71 assertions)
Duration: ~0.9s
```

Pour compiler le frontend à blanc :
```powershell
cd c:\Users\chahr\OneDrive\Desktop\stage\facture-front
npm run build
```

---

## Récapitulatif des Identifiants Utiles pour la Démo

- **URL Application** : `http://localhost:5173`
- **Email Admin** : `admin@facture.com`
- **Mot de passe Admin** : `admin123`
- **Matricule Fiscal Émetteur (Société)** : `1234567R`
- **Matricule Fiscal Client Valide (Modulo 23)** : `0000001B` (ou `1234567R`)
- **Code Postal Tunis** : `1000`
- **OID TTN** : `urn:2.16.788.1.2.1.3`
