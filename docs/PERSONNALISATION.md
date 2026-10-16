# Adapter l'ERP à votre entreprise

L'ERP ne contient le nom ni le logo d'aucune entreprise. Tout ce qui identifie la société qui l'utilise se règle
dans le fichier `.env`, sans toucher au code. Les valeurs vides ne sont simplement pas affichées.

## 1. Identité de l'entreprise

```dotenv
APP_NAME="ERP"                       # nom de l'application (onglet du navigateur, e-mails)
COMPANY_NAME="Acme Industrie"        # nom affiché dans le menu, la connexion, le tableau de bord et les PDF
COMPANY_TAGLINE="Gestion commerciale"
COMPANY_LOGO_PATH="assets/img/brand/logo.png"
COMPANY_PHONE="+33 1 23 45 67 89"
COMPANY_EMAIL="contact@acme.example"
COMPANY_ADDRESS="12 rue de la Paix"
COMPANY_CITY="Paris"
COMPANY_COUNTRY="France"
```

- **Logo :** copiez votre fichier (PNG, JPG ou SVG) dans `public/assets/img/brand/`, puis indiquez son chemin
  dans `COMPANY_LOGO_PATH`. Le chemin est relatif à `public/`.
- **Sans logo :** laissez `COMPANY_LOGO_PATH` vide. Un badge avec les initiales du nom (« AI » pour Acme Industrie)
  remplace le logo partout, et l'icône d'onglet générique `assets/img/brand/erp-icon.svg` est utilisée.

## 1 bis. Papier à en-tête des documents (factures, devis, commandes, livraisons…)

Tous les documents PDF utilisent le même modèle (`resources/views/erp/pdf/letterhead.blade.php`) :

- **Haut de page :** logo en haut à gauche, suivi de la forme juridique ; slogan sur deux lignes en haut à droite.
- **Cadre du client ou du fournisseur :** seuls les 4 coins sont dessinés.
- **Filigrane :** le logo en très pâle, au centre de la page.
- **Pied de page :** deux filets de couleur et 3 lignes (adresse, téléphones et e-mail, mentions légales), repris sur
  chaque page.

```dotenv
COMPANY_LEGAL_FORM="SARL"                                  # imprimé à droite du logo
COMPANY_SLOGAN="Froid, Climatisation, Traitement d’air,|Plomberie sanitaire, Electricité"   # | = nouvelle ligne
COMPANY_COLOR_PRIMARY="#0061A3"                            # titres, slogan, pied de page, filet du haut
COMPANY_COLOR_ACCENT="#E91E25"                             # filet du bas, « Reste à payer »
COMPANY_WATERMARK_PATH="assets/img/brand/logo-broadway-watermark.png"   # PNG transparent très pâle
COMPANY_FAVICON_PATH="assets/img/brand/favicon-broadway.png"            # icône carrée de l'onglet
```

Le pied de page est construit automatiquement :

- **1re ligne :** `COMPANY_ADDRESS - COMPANY_CITY` ;
- **2e ligne :** `GSM : COMPANY_PHONE / Email : COMPANY_EMAIL` ;
- **3e ligne :** `COMPANY_LEGAL_IDS`.

Les factures, avoirs, devis et commandes affichent le montant TTC en lettres (« Arrêtée la présente facture à la
somme de : … dirhams TTC »).

**Filigrane :** il doit être un PNG **à fond transparent**. Une image à fond blanc masquerait le contenu du document.

## 2. Mentions légales (bas des PDF)

Le champ libre convient à n'importe quel pays :

```dotenv
COMPANY_LEGAL_IDS="SIRET 123 456 789 00012 | TVA FR12345678901"
```

Pour le Maroc, des champs dédiés existent aussi. Ils ne sont imprimés que s'ils sont remplis :

```dotenv
COMPANY_ICE=""
COMPANY_RC=""
COMPANY_IF=""
COMPANY_PATENTE=""
```

## 3. Valeurs proposées par défaut

```dotenv
DEFAULT_CURRENCY="EUR"   # devise pré-remplie sur les nouveaux devis, commandes et factures
DEFAULT_VAT_RATE=20      # taux de TVA pré-rempli sur les nouvelles lignes et les nouveaux produits
```

Ces valeurs restent modifiables document par document.

## 4. Premiers comptes

Le seeder `CreateAdminUserSeeder` crée deux comptes génériques : `admin@example.com` (Admin) et
`technicien@example.com` (Technicien), avec le mot de passe `password`.
**Changez leurs e-mails et leurs mots de passe dès l'installation.**

## 5. Appliquer les changements

Après toute modification du `.env` :

```bash
php artisan config:clear
```

Le code qui lit ces réglages est centralisé dans `config/branding.php` et `app/Support/Branding.php`.
Le composant `<x-brand-logo />` affiche le logo ou les initiales.
