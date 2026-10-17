# Journal des modifications

Toutes les évolutions notables du projet sont consignées ici, de la plus récente à la plus ancienne.
Le suivi global des problèmes et du plan d'action se trouve dans [AUDIT_ERP.md](AUDIT_ERP.md).

---

## 2026-10-17 — Pages expirées : retour à la connexion ou au formulaire, erreurs en français

- **Fini la page « 419 Page Expired »** : si la session est terminée, l'utilisateur arrive sur la connexion avec
  un message, puis revient sur la page où il était ; s'il est encore connecté (formulaire resté ouvert trop
  longtemps), il revient sur le formulaire **déjà rempli** avec le message « rien n'a été enregistré, validez à
  nouveau ». Une déconnexion expirée mène simplement à la connexion ; la page de connexion expirée garde l'e-mail.
- Ouvrir une page après la fin de la session : message d'explication sur la connexion (pas pour l'adresse `/`).
- **Pages d'erreur en français** (`resources/views/errors/` : 403, 404, 419, 429, 500, 503, 4xx, 5xx) avec les
  boutons « Page précédente » et « Retour à l'accueil », au lieu des pages anglaises de Laravel.
- Messages « warning » affichés aussi hors pages ERP (`layouts/flash`) et sur la page de connexion.
- Tests : `SessionExpiredTest` (6) ; **183 tests verts**.

---

## 2026-10-16 — Mise en ligne : dépôt GitHub pour cPanel

- Projet poussé sur `github.com/anwarbanner/facture-broadwayenergie` (branche `main`, remote `origin` ;
  l'ancien dépôt d'origine est renommé `upstream`).
- `.gitignore` : exclus `storage/backups` (dumps SQL et copies de `.env` = données réelles), `.claude`,
  `graphify-out`, `.phpunit.cache`, `phpunit.xml.bak`. Restent hors dépôt comme avant : `.env`, `vendor`,
  `node_modules`, la signature (`storage/app/branding`) — à envoyer à part sur le serveur.
- **177 tests verts** avant le push.

---

## 2026-10-09 (4) — Factures sans devis, facture fournisseur depuis un devis fournisseur

- **Facture sans devis** (page Factures) : formulaire simple (client, dates, articles) ; en mode simple, pour un
  utilisateur finance, la facture est **validée tout de suite** et s'ouvre prête à imprimer / encaisser.
- **Factures fournisseurs** au menu simple ; bouton **Facture fournisseur sans devis** (même principe), Imprimer,
  Excel, export de la liste (`list-export/supplier-invoices`).
- **Faire la facture fournisseur** sur un devis fournisseur (`POST purchase-quotes/{id}/invoice`,
  `PurchaseQuoteInvoiceService`) : facture fournisseur validée avec les lignes du devis, échéance J+30, liée au
  devis (`supplier_invoices.purchase_quote_id`, nouvelle migration). Une seule par devis ; devis verrouillé et
  marqué « Accepté ». Aucun mouvement de stock (pas de bon de réception).
- Devis fournisseur : cadre facture (reste à payer, Imprimer, Excel, **Payer la facture**) ; facture fournisseur :
  lien « Devis fournisseur », numéro du devis imprimé sur le PDF, cadre **Payer cette facture fournisseur** en un clic
  (décaissement), formulaire détaillé replié.
- Corrigé : la page devis fournisseur affichait « - » pour la date et la validité (pas de cast date sur le modèle).
- Sauvegarde avant migration : `storage/backups/laravel-before-supplier-invoice-quote-2026-10-09.sql`.
- **177 tests verts** ; vérifié dans Chrome (headless, `laravel_e2e`).

---

## 2026-10-09 (3) — Devis lié à la facture, « DEVIS FOURNISSEUR » sur le PDF

- PDF (et Excel) des devis fournisseurs titré **DEVIS FOURNISSEUR** en mode simple (« DEMANDE D'ACHAT » en ERP complet).
- **Faire la facture (payer plus tard)** sur le devis (`POST sales-quotes/{id}/invoice`, `QuoteCashInService::invoice`) :
  facture validée, non payée, échéance J+30, liée au devis (`sales_quote_id`) et à son BL ; le devis est verrouillé.
  **Encaisser** paie ensuite cette même facture (ou crée facture + paiement d'un coup si le devis n'est pas facturé).
  Une seule facture par devis.
- Liens dans les deux sens : cadre facture sur le devis (reste à payer, Imprimer, Excel, Voir la facture, Espèces /
  Chèque / Virement), « Devis DEV-… » sur la page et dans la liste des factures, **« Devis » imprimé sur le PDF de la facture**.
  Listes : état « Facturé » (à payer) ou « Encaissé ».
- Page facture (mode simple) : cadre **Encaisser cette facture** en un clic ; le formulaire détaillé est replié sous
  « Paiement partiel ou autre date ».
- **174 tests verts** ; vérifié dans Chrome (headless, `laravel_e2e`). Pas de migration.

---

## 2026-10-09 (2) — Devis fournisseurs, BL sans devis, modifier un BL

- **Devis fournisseurs** et **Fournisseurs** ajoutés au menu simple (écrans « Demandes d'achat » existants, appelés
  « Devis fournisseurs » en mode simple, à la demande de l'utilisateur ; le PDF garde le titre « DEMANDE D'ACHAT »).
  Formulaire sans numéro ni statut, Imprimer (PDF dans le navigateur), Excel, export de la liste.
  « Mes fournisseurs » : Nouveau fournisseur, **Faire un devis fournisseur** (fournisseur déjà choisi).
- **BL sans devis** (`SimpleDeliveryController`, `/erp/bons-de-livraison/creer`) : client (ou nouveau nom tapé), date,
  adresse facultative, articles + quantités, sans prix. Jamais de mouvement de stock. Ne se facture pas directement.
- **Modifier le BL** (BL sans commande) : sur la page du BL et dans le cadre BL du devis. Un BL fait depuis un devis
  et modifié à la main **ne suit plus le devis** (`deliveries.follows_quote = false`, nouvelle migration) ; tant
  qu'il n'est pas modifié à la main, modifier le devis met le BL à jour. Bouton **Modifier le devis** sur le BL.
  Supprimer le devis supprime son BL, sauf un BL modifié à la main qui reste (sans devis). BL facturé = non modifiable.
- Page, PDF et Excel d'un BL simple : une seule colonne « Quantité » (plus de « Qté commandée » / n° de suivi).
- Page Bons de livraison : boutons **BL sans devis** et **BL depuis un devis**.
- Sauvegarde avant migration : `storage/backups/laravel-before-bl-follows-quote-2026-10-09.sql`.
- Vérifié dans Chrome (headless, `laravel_e2e`) ; **171 tests verts**.

---

## 2026-10-09 — Mode simple (suite) : bons de livraison, Excel, clients et produits simplifiés

- **Bon de livraison en un clic** depuis le devis (`POST sales-quotes/{id}/delivery`, `QuoteDeliveryService`) :
  mêmes articles que le devis, sans prix. Décision du propriétaire : **le BL ne touche jamais au stock**
  (`deliveries.moves_stock = false`, respecté par `StockService::syncDelivery`). Le BL n'a pas de commande
  (`deliveries.sales_order_id` devient facultatif, nouveau `deliveries.sales_quote_id`). Il **suit le devis** :
  modifier le devis réécrit ses lignes ; supprimer le devis supprime son BL ; on ne le modifie pas et on ne le
  facture pas directement (on encaisse le devis, la facture est alors reliée au BL). Les livraisons issues
  d'une commande gardent leur comportement (sortie de stock).
- Page Bons de livraison : bouton **Faire un bon de livraison** → liste des devis sans BL (recherche) → **Faire le BL** en
  un clic, qui ouvre le BL créé (`erp.deliveries.choose-quote`).
- Menu : **Bons de livraison** ; page BL avec **Imprimer le BL** (PDF dans le navigateur), Excel, lien vers le devis.
- **Excel** : bouton Excel sur le devis, la facture et le BL ; **Exporter la liste en Excel** sur Mes devis,
  Factures et Bons de livraison (`/erp/list-export/{type}`, `ListExportController`, mêmes filtres que la page).
  Bouton **Imprimer** sur les factures.
- **Clients** : liste « Mes clients » (recherche, nom / téléphone / ville, bouton **Faire un devis** qui ouvre un
  devis avec le client déjà choisi) ; fiche réduite à nom, téléphone, adresse, ville, ICE, email (les autres
  champs gardent leur valeur).
- **Produits** : liste « Mes produits » (nom, prix de vente HT, unité, sans colonnes de stock) ; fiche réduite à
  nom, prix de vente HT, unité, description (stock, prix d'achat et catégorie gardent leur valeur).
- Mode sombre : les boutons verts en lien (Encaisser, Imprimer la facture) avaient un texte illisible ; corrigé.
- Vérifié dans Chrome (Selenium headless, base `laravel_e2e`) : client créé → « Faire un devis » → devis →
  BL → encaissement en espèces → impressions PDF (devis, BL, facture) → exports Excel ; ordinateur et téléphone,
  aucune erreur console ni débordement. Tests : `SimpleModeTest` (11 tests), **167 tests verts**.

---

## 2026-10-08 — Mode simple : créer un devis, l'imprimer, l'encaisser en un clic

Demande du propriétaire : l'utilisateur n'est pas habitué aux logiciels d'entreprise, il veut seulement créer
des devis et les imprimer. Le mode simple s'applique **à tout le monde** (`SIMPLE_MODE=true` dans `.env`,
`config('branding.simple_mode')`) ; `SIMPLE_MODE=false` rend le menu ERP complet. Aucun écran n'est supprimé.

- **Menu court** : Accueil, Nouveau devis, Mes devis, Factures, Clients, Produits (+ Administration pour l'Admin).
- **Accueil** (`simple_home.blade.php`) : trois grosses tuiles et les 10 derniers devis avec Imprimer / Encaisser.
- **Devis** : le numéro (automatique) et le statut ne sont plus demandés ; après l'enregistrement on arrive sur
  le devis (et non plus sur la liste). Grands boutons **Imprimer le devis** et **Modifier**.
- **Imprimer** (`sales-quotes/{id}/print`, `client-invoices/{id}/print`) : le PDF s'ouvre dans le navigateur
  (`LetterheadDocument::stream`) au lieu d'être téléchargé.
- **Encaisser en un clic** (`POST sales-quotes/{id}/cash-in`, `QuoteCashInService`, réservé à `manage-finance`) :
  un bouton par mode (Espèces / Chèque / Virement) crée la facture **validée** depuis le devis
  (`client_invoices.sales_quote_id`, nouvelle migration) et l'enregistre **payée en totalité** (référence =
  n° du devis). Pas de commande ni de livraison : **le stock ne bouge pas**. Le devis passe à « converti » et
  devient verrouillé (plus de modification, suppression ni conversion en commande) ; la page affiche
  « Devis encaissé » + **Imprimer la facture**. Refusé si le devis a déjà une commande, est refusé ou vide.
- Tests : `SimpleModeTest` (7 tests) ; les autres tests tournent en mode complet (`SIMPLE_MODE=false` dans
  `phpunit.xml`). **162 tests verts**.

---

## 2026-09-29 (2) — Page de connexion : champs noirs corrigés

- Quand le téléphone / l'ordinateur était en mode sombre (ou le mode sombre choisi dans l'ERP), les champs de la
  page de connexion devenaient noirs sur la carte blanche. Les pages de connexion, d'inscription et d'erreur
  (`layouts/master2`) restent maintenant **toujours en mode clair** (`forceLightTheme`), et une page claire garde
  des champs natifs clairs (`color-scheme: light`) même si l'appareil est en sombre.
- « Se souvenir de moi » : la case ne recouvre plus le début du texte.
- Test ajouté (`ThemeAndLanguageTest`) ; **155 tests verts**.

---

## 2026-09-29 — Pages Utilisateurs et Rôles refaites

### Utilisateurs (Administration › Utilisateurs)
- Même présentation que le reste de l'ERP : chiffres (utilisateurs, actifs, administrateurs, comptes inactifs
  à activer), recherche nom / e-mail, filtres rôle et statut, tableau avec initiales, rôle et statut en
  pastilles, **dernière connexion**, date de création ; fiches compactes sur téléphone ; nouvelle ligne
  surlignée après une création.
- Formulaire en sections (Identité, Rôle et accès avec le rôle choisi sur une carte qui décrit ses droits,
  Mot de passe avec bouton afficher / masquer, 8 caractères minimum, messages en français).
- Fiche utilisateur : dernière connexion, nombre d'actions, ce que le compte peut faire, activité récente
  (hors connexions) avec lien vers tout son journal.
- **Sécurités** : on ne peut pas supprimer son propre compte ; le dernier administrateur actif ne peut être ni
  supprimé, ni désactivé, ni passé en Technicien.

### Rôles (Administration › Rôles)
- Page **« Rôles et droits »** : les deux rôles avec leurs utilisateurs, et un tableau « Qui peut faire quoi »
  (ventes, achats, stock, contacts, documents, validation des factures, paiements, comptabilité, administration).
- Les rôles sont **fixes** : la table des permissions était vide (les droits dépendent du rôle), un rôle créé
  ne pouvait être donné à personne et supprimer « Admin » bloquait l'application. Créer / modifier / supprimer
  un rôle ramène à cette page avec une explication ; les anciennes vues de rôles ont été supprimées.

Tests : nouveau `UsersAndRolesTest` (5 tests) ; **154 tests verts** ; pages vérifiées sur téléphone et
ordinateur, en mode clair et sombre.

---

## 2026-09-28 (9) — Mode sombre, français uniquement, écritures comptables (audit P7)

### Mode sombre
- Bouton **« Mode sombre / Mode clair »** dans l'en-tête (icône lune / soleil ; icône seule sur téléphone).
  Le choix est mémorisé sur l'appareil ; sans choix, l'ERP suit le réglage du téléphone / de l'ordinateur.
  Le thème est appliqué avant l'affichage (pas de flash blanc).
- `public/assets/css/erp-dark.css` : couleurs sombres de toutes les pages (tableaux, formulaires, select2,
  boutons, alertes, pastilles de statut, tableau de bord, comptabilité, journal d'activité, chargement).
  Une partie est générée depuis `erp-pro.css` / `erp-ui.css` : relancer `python scripts/build-dark-css.py`
  après modification de ces fichiers.
- Graphique du tableau de bord : couleurs sombres validées (daltonisme, contraste) et changement de thème
  sans recharger la page. La signature et le logo restent sur fond blanc (comme sur papier).

### Langue
- **Sélecteur de langue retiré** : l'ERP est uniquement en français (`SetLocale` force `fr`) ; les adresses
  `/fr`, `/en`, `/ar`, `/dashboard/{langue}` n'existent plus.

### Écritures comptables pour le comptable (audit P7, partie export)
- Nouvel onglet **Comptabilité › Écritures comptables** : factures, avoirs et règlements de la période
  transformés en écritures équilibrées (débit = crédit, contrôle affiché), export Excel pour le comptable.
  - Facture client : D 3421 Clients (TTC) / C 7111 Ventes (HT) / C 4455 TVA facturée ; avoir : l'inverse.
  - Facture fournisseur : D 6111 Achats (HT) / D 3455 TVA récupérable / C 4411 Fournisseurs (TTC).
  - Règlement : banque 5141 (journal BQ) ou caisse 5161 (journal CA, espèces) contre le compte client /
    fournisseur. Règlements annulés et brouillons exclus.
- Comptes et journaux modifiables dans `.env` (`ACCOUNTING_ACCOUNT_*`, `ACCOUNTING_JOURNAL_*`, voir
  `config/accounting.php` et `.env.example`).

Tests : `ThemeAndLanguageTest`, écritures dans `AccountingTest` ; **149 tests verts**.

---

## 2026-09-28 (8) — Quantités lisibles, tableau de bord réel, écrans téléphone et ordinateur

### Nombres
- **Quantités sans décimales inutiles** : « 1 », « 2,5 », « 1 250 » au lieu de « 1.000 » (`Format::qty()`), sur
  toutes les pages (documents, stock, avoirs, tableau de bord) et dans l'export Excel.
- **Montants au format français** partout : « 12 500,00 » au lieu de « 12 500.00 » (`Format::money()`).

### Tableau de bord (refait, données de l'ERP uniquement)
- Avant : graphiques de l'ancien module (tous à 0 %, camembert vide), deux échelles sur un même graphique.
- Maintenant : bonjour + date, **actions rapides** (nouveau devis, nouvelle facture, encaisser, nouveau contact),
  **chiffres du mois** cliquables (CA HT, encaissé, à encaisser / à payer avec la part en retard — Admin),
  **« À faire »** (factures en retard, brouillons à valider, devis sans réponse, commandes à livrer / à recevoir,
  stock bas — chaque ligne mène à la liste filtrée), **ventes / achats HT sur 6 mois** (une seule échelle,
  légende, valeurs au survol, tableau « Voir les chiffres »), **derniers documents** avec leur statut, **stock bas**.
  `App\Services\Dashboard\DashboardService`.

### Écrans
- **Statuts en pastilles de couleur** (texte toujours présent) dans toutes les listes et fiches (`Format::statusBadge()`).
- **Téléphone** : fiches de liste compactes (numéro en titre, informations sur 2 colonnes, 4 boutons sur une
  ligne) ~30 % moins hautes ; détails d'un document sur 2 colonnes ; filtres pleine largeur ; actions rapides
  du tableau de bord en grille.
- Vérifié à 390 px (téléphone) et 1440 px (ordinateur) : aucun débordement horizontal.

Tests : 146 verts (2 tests mis à jour pour le nouveau format des nombres).

---

## 2026-09-28 (7) — Excel (export des documents, imports) et signature sur les documents

### Export Excel des documents
- Bouton **« Excel »** à côté de « PDF » sur les devis, commandes, livraisons, factures / avoirs, demandes d'achat,
  commandes d'achat, bons de réception et factures fournisseur. Même contenu que le PDF (en-tête société et logo,
  client, lignes, totaux, montant en lettres, signature, mentions légales), prêt à imprimer sur une largeur A4.
- `App\Services\Export\DocumentSpreadsheet` (données : `LetterheadDocument`), routes `erp.{document}.excel`.

### Import Excel (Administration › Import Excel, Admin)
- **Produits, services, catégories, contacts** : télécharger le modèle (avec une feuille d'aide) → le remplir →
  l'envoyer → **aperçu** ligne par ligne (création / mise à jour / erreur avec la raison) → confirmer.
  Rien n'est écrit avant la confirmation ; les lignes en erreur sont ignorées.
- Pas de doublons : produit retrouvé par sa référence (sinon son nom), service et catégorie par leur nom,
  contact par type + société → **mis à jour**. Catégorie d'un produit créée si besoin. Le stock initial des
  nouveaux produits passe par un mouvement de stock.
- Formats : .xlsx, .xls, .ods, CSV (séparateur `;` ou `,`) ; nombres « 1 250,50 » acceptés ; 5 000 lignes max.
- Bouton « Importer (Excel) » sur les listes produits, services, catégories et contacts.

### Remplir un document depuis Excel
- Dans chaque formulaire de devis / commande / facture / livraison / réception : **« Importer des lignes
  (Excel) »** (colonnes Article, Désignation, Quantité, Prix unitaire HT ; lien « modèle »). Les lignes
  s'ajoutent au formulaire sans recharger la page : article du catalogue retrouvé par référence ou nom,
  sinon ligne « Manuel » ; prix vide = prix du catalogue (vente ou achat). Rien n'est enregistré avant
  « Enregistrer ».

### Signature et cachet (Administration › Signature et cachet, Admin)
- Image PNG / JPG (2 Mo max) + ligne « signataire » facultative, avec aperçu.
- Imprimée en bas de chaque document PDF et Excel : bloc « Cachet et signature » sous les totaux ; sur les
  bons de livraison / réception, dans la case de la société. **Jamais sur un brouillon de facture.**
- Fichier gardé hors du dossier public (`storage/app/branding/`, non versionné) : à téléverser aussi sur le
  serveur de production.

Tests : nouveau `ExcelAndSignatureTest` (8 tests) ; **146 tests verts** ; parcours vérifiés dans Chrome
(PDF rendu avec la signature, fichier Excel relu).

---

## 2026-09-28 (6) — Journal d'activité lisible, TVA retirée des produits, route de test supprimée

### Journal d'activité (`/activity-logs`, Admin)
- **Plus aucun code technique** (POST, routes, codes HTTP) : chaque ligne dit en français **qui**, **quoi**
  (Création, Modification, Suppression, Transformation, Finance, Téléchargement, Connexion…), **sur quel
  document** (lien vers « Devis client DEV-2026-0003 », « Contact Hôtel Atlas »…), le **message vu par
  l'utilisateur** (« Devis client créé. », « Ce devis ne peut pas être converti. »), le **résultat**
  (Réussi / Refusé), l'**appareil** (« Chrome · Windows », « Téléphone Android ») et le **lieu**
  (« Ce poste », « Réseau local (192.168.1.30) »).
- Lignes **regroupées par jour** (« Aujourd'hui », « Hier »…), heure + « il y a 5 minutes ».
- **Chiffres de la période** : actions, créations, modifications, suppressions, finance, connexions,
  refus et échecs (cliquable), utilisateurs actifs.
- **Filtres simples** : période (aujourd'hui, hier, 7 / 30 jours, tout, dates), utilisateur, type d'action,
  « Refus et échecs », recherche libre (numéro de document, nom, e-mail…) ; les anciennes consultations de
  pages sont masquées (case pour les afficher).
- Enregistrement plus riche (`AuditActivity`) : document créé ou produit (ex. devis → commande), numéro / nom au
  moment de l'action, message affiché, réussite ou refus (une erreur de saisie était enregistrée comme un succès).
  Les envois du formulaire de connexion ne doublent plus les lignes « Connexion ».
- Base : colonnes `category` et `outcome` sur `activity_logs` (anciennes lignes classées automatiquement).
  Traduction : `App\Support\ActivityLogPresenter`.

### Produits
- **TVA retirée des produits** (formulaire, fiche, export Excel, validation) et colonne `vat_rate` supprimée :
  la TVA est un taux par document. Sauvegarde : `storage/backups/laravel-before-product-vat-drop-2026-09-28.sql`.

### Nettoyage
- Route de test `/hhhhhhhhhhhhhhhhh` (et `HomeController::home`) supprimée.

Tests : `ActivityLogTest` complété ; **138 tests verts** ; page vérifiée dans Chrome.

---

## 2026-09-28 (5) — Avoirs partiels (audit P4)

Sur une facture client ou fournisseur **validée**, bouton **« Faire un avoir »** (Admin) :
- **quantités à créditer par ligne** (retour de marchandise, erreur sur une ligne) — le formulaire montre pour
  chaque ligne ce qui a été facturé, ce qui est déjà crédité et le maximum restant ;
- et / ou une **remise** (ligne libre, montant HT) ;
- un **motif** (retour, erreur de facturation, remise, geste commercial…) ; total HT / TVA / TTC en direct.

Règles (`InvoiceLifecycleService::createCreditNote`, `PaymentService::refresh`) :
- La TVA de l'avoir est le taux de la facture, sur le total de l'avoir.
- On ne peut pas créditer plus que ce qui reste (par ligne et en montant).
- La facture reste **validée** ; son reste à payer = total − avoirs − paiements.
- Si le client / fournisseur avait déjà payé plus que le nouveau total, l'excédent est **à rembourser** sur les
  avoirs (le plus ancien d'abord), avec le formulaire « Enregistrer le remboursement » existant.
- « Annuler par un avoir » crédite **tout ce qui reste** : la facture est alors annulée, au centime près
  (le dernier avoir prend exactement le reste, sans écart d'arrondi).
- Un avoir ne touche pas au stock : le retour physique se saisit sur la livraison / la réception.
- Journal des ventes / achats, TVA, relevés et échéanciers tiennent compte des avoirs partiels.

Base de données : `2026_09_28_120000_partial_credit_notes` — `credited_item_id` sur les lignes de factures
(ligne créditée), `credit_reason` sur les factures ; l'avoir fournisseur existant a été relié ligne à ligne.
Sauvegarde préalable : `storage/backups/laravel-before-partial-credit-notes-2026-09-28.sql`.

Le PDF d'un avoir indique « Avoir sur la facture » et le motif. Tests : nouveau `CreditNoteTest` (7 tests),
**137 tests verts** ; parcours vérifié dans Chrome.

---

## 2026-09-28 (4) — Vitesse : pages plus légères et plus rapides

Mesures dans Chrome (mêmes pages, même machine, serveur `php artisan serve`) :

| Mesure | Avant | Après |
|---|---|---|
| Temps serveur par page | ~250 ms | **~55 ms** |
| Page prête, visite suivante | ~340 ms | **~100 ms** |
| Données transférées, visite suivante | ~100 Ko | **~8 Ko** |
| `style.css` transféré | 1,3 Mo | **165 Ko** (gzip) |

- **OPcache activé** dans `D:\Dev\Tools\php\php.ini` (copie : `php.ini.bak-2026-09-28`) : PHP ne recompile
  plus Laravel à chaque page ; les modifications du code restent prises en compte immédiatement.
- **Barre de debug désactivée** (`DEBUGBAR_ENABLED=false` dans `.env`, copie :
  `storage/backups/.env.before-perf-2026-09-28`). La remettre à `true` pour déboguer.
- **Cache navigateur** : les CSS / JS portent leur version (`App\Support\Asset::url()`, `?v=` = date du fichier) et
  sont gardés un an ; un fichier modifié change d'adresse. `server.php` (utilisé par `php artisan serve`) envoie
  maintenant les en-têtes de cache et répond « 304 » quand rien n'a changé ; `public/.htaccess` fait de même
  sous Apache / Laragon.
- **Compression gzip** des CSS / JS / SVG et des pages (copie compressée gardée dans `storage/framework/cache`).
- **6 scripts inutiles retirés** de toutes les pages : moment, 2 plugins de notation, sparkline, eva-icons
  (232 Ko), et l'horloge de la barre latérale du thème qui tournait **chaque seconde** sur des éléments absents.
- **Icônes** : `icons-erp.css` charge 4 familles au lieu de 11 (Feather utilisée par l'ERP ; Line Awesome ;
  Font Awesome et Material Design pour l'ancien module).
- **Chargement plus léger** : plus de voile flou à l'ouverture d'une page ; une fine barre bleue en haut dès le
  clic, et le voile avec le logo seulement si la page met plus de 1,2 s. Les exports Excel ne l'affichent plus.

À faire côté serveur : **redémarrer `php artisan serve`** pour que OPcache s'applique.

---

## 2026-09-28 (3) — Section Comptabilité, « Contacts », « Demandes d'achat »

### Section « Comptabilité » (menu, réservée à l'Admin : `can:manage-finance`)
Une page `/erp/accounting` à onglets, avec filtre de période (aujourd'hui, hier, semaine, mois, mois dernier,
trimestre, année, année dernière, dates libres) et regroupement (jour, semaine, mois, trimestre, année).
Chaque onglet s'exporte en CSV lisible par Excel (séparateur `;`, accents conservés) et s'imprime.

| Onglet | Contenu |
|---|---|
| Synthèse | CA HT, achats HT, marge brute, TVA à payer, encaissements / décaissements, trésorerie nette, créances et dettes du jour (dont en retard) + tableau par période |
| Journal des ventes / des achats | relevé des factures par jour / mois… + liste des pièces (date, numéro, contact, HT, TVA, TTC, reste à payer) |
| TVA | TVA collectée − TVA déductible = TVA due (ou crédit), par période |
| Trésorerie | encaissements, décaissements, solde par période + répartition par mode de paiement |
| Échéancier clients / fournisseurs | reste à payer par contact : non échu, 1-30 j, 31-60 j, 61-90 j, +90 j |
| Classements | meilleurs clients et principaux fournisseurs de la période, avec leur part du total |

Règles de calcul (`App\Services\Accounting\AccountingService`) : seules les factures **validées** comptent
(les brouillons jamais) ; une facture annulée reste dans le journal et son **avoir** vient en négatif ;
la trésorerie ignore les règlements annulés ; le retard se compte depuis la date d'échéance.

### Libellés
- **« Tiers » devient « Contacts »** (menu, messages, règlements).
- **Contacts : seulement Client et Fournisseur** (création, modification, filtre de la liste ; les types
  « Piste » / « Prospect » ne sont plus proposés ni acceptés — aucun contact de ce type n'existait).
- **Achats : « Devis d'achat » devient « Demande d'achat »** (menu, pages, messages, PDF « DEMANDE D'ACHAT »,
  référence sur le bon de commande fournisseur).

### Corrections
- Erreur JavaScript sur la page de connexion (`sticky.js` sur un élément absent).
- Le menu masque un groupe dont aucun lien n'est autorisé (Comptabilité pour un Technicien).

### Tests
- Nouveau `tests/Feature/ERP/AccountingTest.php` (journal, avoirs, TVA, trésorerie, échéancier, export,
  droits). **130 tests verts.** Pages vérifiées dans Chrome (Selenium) : aucune erreur, aucun débordement.

---

## 2026-09-28 (suite) — Nouvelle ligne surlignée dans la liste, journal d'activité allégé

### Demandé
- **Après toute création** (devis, commandes, livraisons, réceptions, factures, contacts, produits, services,
  catégories, relevés, règlements), l'application **revient à la liste** : la ligne créée s'affiche **en bleu**
  (avec une barre bleue à gauche), puis redevient blanche comme les autres en ~4 secondes. Si elle est plus bas
  dans la page, la liste défile jusqu'à elle.
  - Contrôleurs : trait `RedirectsToList::createdRedirect()` (message + adresse de la fiche créée).
  - Affichage : `layouts/flash` transmet l'adresse, `public/assets/js/erp-ui.js` trouve la ligne dont un lien
    (Voir, Modifier, PDF…) ou un formulaire pointe vers elle ; animation dans `erp-ui.css`.
  - Vérifié dans Chrome (Selenium) sur les devis, les contacts et les produits : bleu à l'arrivée, blanc après 5 s.
- Correction au passage : la création d'un produit plantait l'appel de redirection (variable créée dans la
  transaction).

### Journal d'activité (audit Q6)
- Les simples consultations de pages (GET) ne sont plus enregistrées : elles faisaient ~85 % du journal
  (310 sur 366 lignes) et noyaient les actions utiles. Restent enregistrés : créations, modifications,
  suppressions, connexions, **téléchargements** (PDF, exports, impressions).
- Nouvelle commande manuelle `php artisan activity-logs:prune --days=365 [--reads-only]` (demande confirmation,
  **non planifiée** : la durée de conservation est à décider par le propriétaire).

### Tests
- `DocumentFormsTest` et `PurchaseQuoteItemModeValidationTest` adaptés, nouveau `ActivityLogTest`.
  **124 tests verts.**

---

## 2026-09-28 — Formulaires devis / commandes / factures simplifiés et analyse Selenium

### Demandé
- **Nouveau client / fournisseur depuis le document :** dans les devis, commandes et factures (ventes et achats),
  le champ client / fournisseur accepte un nom qui n'existe pas encore (« Créer le client « … » »). Le contact est
  ajouté à la base **Contacts** à l'enregistrement (type client ou fournisseur, actif). Un même nom retapé
  (majuscules comprises) réutilise le contact existant. — `App\Services\Contacts\ContactResolver`,
  règle `App\Rules\ContactReference`, vue `erp/partials/contact_select`.
- **Taux de change supprimé** de tous les formulaires ; il vaut toujours 1 (colonnes conservées pour l'historique).
- **TVA unique par document**, calculée sur le **total HT** (plus de TVA par ligne). Nouvelle colonne `tva_rate`
  sur devis, commandes et factures (ventes + achats), reprise par les conversions ; chaque ligne garde le même taux.
  Les documents existants **ne sont pas recalculés** (montants inchangés), leur taux a été repris de leurs lignes.
- **Articles manuels dans le devis client** (et commandes, livraisons, factures clients) : type de ligne « Manuel »
  avec une désignation libre. La ligne suit tout le cycle devis → commande → livraison → facture, n'a aucun effet
  sur le stock et apparaît sur les fiches et PDF.

### Migration
- `2026_09_28_100000_document_vat_rate_and_manual_sales_lines` : `tva_rate` sur 6 tables de documents,
  `item_name` sur `sales_quote_items`, `sales_order_items`, `delivery_items`, `client_invoice_items`.
  Sauvegarde préalable : `storage/backups/laravel-before-tva-contacts-2026-09-28.sql`.

### Formulaires
- Les 6 formulaires chiffrés et les 2 formulaires de livraison / réception partagent désormais
  `erp/partials/document_lines` (+ `document_line_row`) et `public/assets/js/erp-documents.js`
  (≈ 1 900 lignes de copies supprimées) : type de ligne Produit / Service / Manuel, prix repris du catalogue
  (prix de vente en vente, prix de revient en achat), total par ligne et totaux HT / TVA / TTC en direct,
  « Entrée » dans la dernière ligne = nouvelle ligne, affichage en fiches sur téléphone.
- Côté serveur, le **type de ligne décide** : une valeur restée dans un champ masqué est ignorée
  (`HandlesDocumentLines::pricedLines`, règles communes `App\Support\DocumentRules`).

### Ergonomie (suite de l'analyse Selenium, détail dans AUDIT_ERP.md § Ergonomie)
- Zone de messages unique (`layouts/flash`) : les confirmations et les **refus** (ex. « Ce devis ne peut pas être
  converti ») s'affichent enfin sur les fiches documents.
- « Enregistrer » sur une **modification** ouvre la fiche du document (boutons Convertir, PDF, Valider) ;
  une **création** revient à la liste (voir l'entrée suivante).
- Recherche par numéro **ou** nom du client / fournisseur + filtre de statut sur les 8 listes de documents
  (trait `FiltersDocuments`, vue `erp/partials/list_filter`).
- Erreur JavaScript `PerfectScrollbar` corrigée sur toutes les pages ; libellés accessibles sur les filtres.

### Tests
- Nouveau `tests/Feature/ERP/DocumentFormsTest.php` (9 tests) ; tests existants adaptés
  (`PurchaseQuoteItemModeValidationTest`, `FrenchInterfaceTest`). Suite complète verte.
- Parcours Selenium (Chrome) : cycles vente et achat complets OK, aucune erreur serveur ni JavaScript,
  aucun débordement horizontal à 390 px.

---

## 2026-09-23 — Broadway Energie : logo, identité et papier à en-tête des documents

### Identité (dans `.env`, le code reste générique)
- **Nom :** `COMPANY_NAME="Broadway Energie"` et `COMPANY_LEGAL_FORM="SARL"`.
- **Coordonnées :** adresse, GSM, e-mail.
- **Mentions légales :** « RC : 577179 - TP : 34104832 - IF : 53701023 - CNSS : 5014370 ICE : 003261782000029 ».
- **Slogan :** « Froid, Climatisation, Traitement d’air, / Plomberie sanitaire, Electricité ».
- **Couleurs :** #0061A3 (bleu) et #E91E25 (rouge).
- **Nouveaux réglages :** `COMPANY_LEGAL_FORM`, `COMPANY_SLOGAN`, `COMPANY_COLOR_PRIMARY`, `COMPANY_COLOR_ACCENT`,
  `COMPANY_WATERMARK_PATH`, `COMPANY_FAVICON_PATH` (documentés dans `.env.example` et
  [PERSONNALISATION.md](PERSONNALISATION.md)).

### Logo
Images générées à partir de `storage/logo/logo_broadwayEnergie.png` (2000×2000, fond transparent) :

| Fichier (`public/assets/img/brand/`) | Usage |
|---|---|
| `logo-broadway.png` | logo recadré (marges blanches retirées), 1200 px, fond transparent |
| `logo-broadway-watermark.png` | logo + « SARL » à ~7 % d'opacité, fond **transparent** |
| `favicon-broadway.png` | thermomètre et « B », carré 256 px |

- **Dans l'application :** menu latéral (bandeau blanc ; en menu réduit, seule la partie thermomètre + « B »),
  en-tête mobile, pages de connexion et d'inscription, écran de chargement, onglet du navigateur, ancienne impression.

### Papier à en-tête des documents (PDF)
- **Un seul modèle** `resources/views/erp/pdf/letterhead.blade.php` pour les 8 documents : facture, avoir, devis,
  bon de commande, bon de livraison, demande de prix, bon de commande fournisseur, bon de réception, facture
  fournisseur.
- **Mise en page :** reproduit `BE_260922_221134.pdf`, positions en pt sur A4 :

  | Élément | Position |
  |---|---|
  | Logo | x 7-205 / y 7-109 |
  | « SARL » | sur la ligne de base de « Energie » |
  | Slogan bleu | x ≈ 320-545 / y 70-93 |
  | Cadre client à 4 coins | x 267-543 / y 160-263 (première page seulement) |
  | Filigrane | x -24-632 / y 274-606 |
  | Filets bleu et rouge | x 91-543 / y 800-802 |
  | 3 lignes de pied | lignes de base 815 / 824 / 834 |

- **Répétition :** l'en-tête, le filigrane et le pied de page se répètent sur chaque page ; l'en-tête du tableau
  aussi. Le bloc des totaux ne se coupe jamais entre deux pages.
- **Contenu :**
  - titre et numéro, dates et références ;
  - tableau Désignation / Qté / P.U. HT / Total HT ;
  - totaux HT, TVA (taux affiché s'il est unique), TTC, déjà réglé et reste à payer ;
  - **montant TTC en lettres** en dirhams.
  - Livraisons et réceptions : quantités commandées et livrées ou reçues, avec cadres de signature.
- **Nouveau `App\Support\AmountInWords` :** nombres en lettres selon l'orthographe française (vingt et un,
  soixante et onze, quatre-vingts, deux cents, mille invariable, millions) et « dirhams et … centimes ».
- **Nouveau `App\Services\Pdf\LetterheadDocument` :** prépare les données de chaque type de document. Les 8
  services PDF y délèguent.
- **Bug corrigé :** l'ancien PDF de facture lisait `total_ht` et `total_tva`, qui n'existent pas sur les factures.
  Ses totaux s'imprimaient à 0.
- **Accents :** textes des documents imprimés avec accents (Échéance, Arrêtée la présente facture…).
- **Limite :** police DejaVu Sans (livrée avec dompdf) au lieu de Montserrat et Trebuchet MS, qui ne sont pas
  installées sur le projet.

### Tests
- `tests/Unit/AmountInWordsTest.php` (27 cas).
- `tests/Feature/LetterheadPdfTest.php` : en-tête complet et mentions exactes, PDF téléchargeables, facture longue
  sur plusieurs pages.
- **Résultat :** 112 tests passent.

---

## 2026-09-23 — Interface entierement en francais

Les **codes enregistres en base** (`draft`, `sent`, `supplier`, `pending`...) ne changent pas : ce sont des valeurs
techniques utilisees par la logique et les donnees existantes. Ce qui change, c'est ce que l'utilisateur **voit**.

- **Statuts et types :** listes deroulantes des devis et commandes, filtre des tiers, listes, fiches et PDF passent
  par `App\Support\Format::label()` (Brouillon, Envoye, Accepte, Refuse, Converti, Confirme, Partiel, Livre,
  Annule, En attente, Expedie, Retourne, Complet, Fournisseur, Actif, Entree, Sortie...).
- **Ancien module :** son statut etait stocke en arabe. Il s'affiche maintenant en francais a partir du code
  numerique (`Format::legacyStatus`) : Payee, Non payee, Partiellement payee.
- **Messages de confirmation :** 29 messages anglais (« Product created successfully. »...) et 10 messages arabes
  (« تم اضافة الفاتورة بنجاح »...) sont traduits.
- **Messages de validation :** `resources/lang/fr/validation.php` est complet (80+ regles ; avant, 12 seulement,
  donc la plupart des erreurs s'affichaient en anglais). Les noms de champs sont lisibles : « Le champ client est
  obligatoire. » au lieu de « The client id field is required. ».
- **Textes du framework :** nouveau `resources/lang/fr.json` (verification e-mail, reinitialisation du mot de
  passe, pagination, pages d'erreur).
- **Pagination :** style Bootstrap 4, celui du modele. L'ancienne pagination Tailwind affichait « Showing ...
  results » avec des fleches demesurees.
- **E-mails :** alerte de stock bas et notification de facture en francais. L'expediteur fictif du modele
  (« Barrett Blair ») est retire ; l'adresse `MAIL_FROM_*` du `.env` est utilisee.
- **Autres :** pages d'erreur 404 / 500, titre des pages Roles, « Facture a » sur l'ancienne impression, message
  « Acces refuse : reserve aux administrateurs ».
- **Volontairement conserves :**
  - le nom « العربية » dans le choix de langue (chaque langue est affichee dans sa propre ecriture) ;
  - les valeurs de statut deja enregistrees en arabe dans l'ancien module (donnees).
- **Tests :** nouveau `tests/Feature/FrenchInterfaceTest.php`. **82 tests passent (479 assertions).**

---

## 2026-09-23 — Interface : PC et mobile, boutons harmonisés, écran de chargement

### Mise en page
- **Sens de lecture :** l'application chargeait toujours les styles arabes (droite à gauche) : menu à droite,
  tableaux inversés. Les styles LTR sont maintenant utilisés en français et en anglais ; le RTL seulement en arabe
  (`<html lang dir>` suit la langue).
- **Langue mémorisée :** la langue choisie dans l'en-tête est retenue en session et appliquée à toutes les pages
  (middleware `SetLocale`). Avant, elle ne valait que pour le tableau de bord.
- **Retraits :**
  - panneau latéral de démonstration du modèle (`layouts.sidebar`, 23 liens vides) et fenêtres de démonstration
    (`layouts.models`) ;
  - rechargement complet de la page toutes les 10 s ;
  - écran de chargement bloquant du modèle ;
  - méta-données du vendeur du modèle ;
  - blocage du zoom sur mobile (`user-scalable=0`).
- **En-tête :** bouton de menu, choix de langue (FR / EN / AR) et menu compte (initiales, e-mail, rôle,
  déconnexion). Les 5 liens vides (Profil, Messages, Boîte de réception…) et les photos de démonstration sont
  supprimés.
- **Menu latéral :** construit côté serveur, avec icônes, lien « Tableau de bord », groupe actif déplié et page en
  cours surlignée. Les liens financiers restent réservés à l'Admin.
- **Correctif :** le script du modèle `sidemenu.js` « cliquait » sur la zone d'ouverture du menu à chaque ouverture
  du tableau de bord (le menu se réduisait sur PC et s'ouvrait par-dessus la page sur mobile). Il est désactivé
  pour le menu ERP.

### Boutons et affichage
- **Système commun `public/assets/css/erp-ui.css`** : une seule hiérarchie de boutons, avec icône et texte.

  | Rôle | Style |
  |---|---|
  | Créer, convertir, enregistrer | bouton plein bleu |
  | Voir, PDF, document lié | bouton clair |
  | Modifier | bouton contour |
  | Supprimer, annuler un règlement | bouton rouge, toujours en dernier |

- **Harmonisation :** appliquée automatiquement à toutes les pages ERP (listes, fiches, formulaires). En-têtes de
  page cohérents : titre à gauche, actions à droite.
- **Mobile :**
  - bouton principal en pleine largeur ;
  - listes affichées en cartes, avec libellés et boutons en grille 2×2 (`public/assets/js/erp-ui.js`) ;
  - barre « Enregistrer / Annuler » collée en bas des formulaires ;
  - zones tactiles d'au moins 40 px.
- **Formats** (`App\Support\Format`) : dates `13/09/2026`, montants `18 480,00`, statuts et types en français
  (Brouillon, Envoyé, Livré, Non payé…) dans les listes, fiches et PDF.
- **Tableau de bord :** les graphiques étaient écrits pour Chart.js v3 alors que la librairie est la v2.7. Seul le
  premier s'affichait. Les 3 graphiques fonctionnent désormais.
- **Ancien module (lecture seule) :** boutons morts supprimés (Ajouter, Modifier, Supprimer, Changer l'état,
  Archiver). Seuls « Imprimer » et « Exporter Excel » restent.
- **Connexion :** bouton et fond alignés sur le bleu de l'ERP.

### Écran de chargement
- **Nouveau `layouts/page-loader.blade.php`** : fond flouté avec le logo (ou les initiales), indicateur et
  « Chargement... ». Inclus dans tous les layouts, y compris connexion et pages d'erreur.
- **Affiché** au chargement ou au rafraîchissement, au clic sur un lien interne et à l'envoi d'un formulaire.
- **Jamais affiché** pour :
  - les téléchargements (PDF, exports) ;
  - les confirmations annulées ;
  - les clics Ctrl ou molette (nouvel onglet) ;
  - les menus déroulants ;
  - les liens marqués `data-no-loader`.
- **Protections :** retiré au retour arrière du navigateur, sécurité de 10 s, respect de « réduire les
  animations », masqué sans JavaScript.

### Tests
- **Nouveau `tests/Feature/LayoutTest.php`** : l'écran de chargement est présent ; la page est en LTR en français et
  en RTL en arabe, avec la langue mémorisée ; plus de panneaux de démonstration ni de liens morts.
- **Résultat :** 79 tests passent (459 assertions).
- **Vérification visuelle :** Chrome, sur PC (1440 px) et mobile (390 px), avec une base de prévisualisation séparée
  `laravel_preview` (données fictives, compte `admin@example.com`). Ta vraie base n'a pas été modifiée.

---

## 2026-09-23 — ERP standard : suppression de la marque LWEM Equipements

À la demande du propriétaire, l'ERP ne contient plus aucune marque d'entreprise. Il s'adapte à n'importe quelle
société par le `.env` (voir [PERSONNALISATION.md](PERSONNALISATION.md)).

### Supprimé

- **Logos LWEM Equipements :** `public/assets/img/brand/lwem-equipements-logo.jpg` et
  `public/assets/img/logoinvoice.PNG` sont supprimés. ⚠️ `logoinvoice.PNG` reste dans l'historique git des anciens
  commits.
- **Nom LWEM :** retiré du menu, de l'en-tête mobile, de l'icône d'onglet, des pages de connexion et d'inscription,
  du tableau de bord, des 8 modèles PDF, des fichiers de langue fr, et des `.env` / `.env.example`. Les coordonnées
  factices (contact@lwem.ma, Casablanca, ICE…) sont vidées.
- **Marques héritées du modèle d'origine :** « ZED Invoicing » (langues en et ar), mention de l'auteur dans le pied
  de page arabe, « Invoice soft, Inc. » et ses coordonnées d'exemple sur l'ancienne impression de facture.
- **Seeder :** comptes au nom de l'auteur du modèle (@abdocoder.com) remplacés par `admin@example.com` et
  `technicien@example.com`.

### Ajouté

- **`App\Support\Branding`** : source unique pour le nom, le logo, le slogan, les coordonnées, les mentions légales,
  la devise et la TVA par défaut. Il remplace les 8 copies du bloc d'identité des PDF, qui avaient « LWEM » en valeur
  de secours.
- **Composant `<x-brand-logo />`** : affiche le logo configuré, sinon un badge avec les initiales et le nom.
- **Icône générique** `public/assets/img/brand/erp-icon.svg` : icône d'onglet quand aucun logo n'est configuré.
- **Nouveaux réglages `.env` :**
  - `COMPANY_COUNTRY` ;
  - `COMPANY_LEGAL_IDS` : mentions légales en texte libre, pour n'importe quel pays (SIRET, TVA intracom…). Les
    champs marocains ICE / RC / IF / Patente restent disponibles, mais optionnels ;
  - `DEFAULT_CURRENCY` / `DEFAULT_VAT_RATE` : devise et TVA pré-remplies. Avant, `MAD` et `20` étaient codés en dur
    dans 8 formulaires et 3 calculs.
- **Valeurs par défaut :** `APP_NAME=ERP`, `COMPANY_NAME="Mon Entreprise"`, pas de logo.

### Tests

- Nouveau `tests/Feature/BrandingTest.php` (5 tests) :
  - pas de « LWEM » sur la connexion, initiales à la place du logo ;
  - logo configuré utilisé dans les écrans et comme icône d'onglet ;
  - un chemin hors de `public/` est refusé comme logo ;
  - le PDF reprend le nom et les mentions légales configurés ;
  - devise et TVA par défaut issues de la configuration.
- Résultat : **76 tests passent (443 assertions)**.

---

## 2026-09-23 — Étape 7 : permissions, inscription, ancien module, refactorisation

Décisions du propriétaire : ancien module en **lecture seule**, inscription en **comptes inactifs**, Technicien
**opérationnel sans finance**.

### Sécurité et accès (S3, S4 partiel)

- **Règle `manage-finance`** (Admin uniquement) : validation des factures, avoirs, règlements, relevés client et
  fournisseur. Ces routes renvoient 403 à un Technicien, et les boutons et menus correspondants sont masqués
  (« Validation par un administrateur » sur la fiche d'un brouillon).
- **Inscription publique :** le compte est créé inactif, sans connexion automatique, avec un message « un
  administrateur doit l'activer ». Le message d'un compte inactif à la connexion est traduit de l'arabe au français.
  Changement dans `RegisteredUserController` et dans `CreateNewUser` (Fortify).

### Ancien module en lecture seule (Q4, Q8)

- **Nouveau middleware `LegacyReadOnly`** (alias `legacy.readonly`) sur les anciennes routes : factures, produits,
  modification, changement de statut, corbeille, pièces jointes. Consultation, PDF, export et rapports restent
  ouverts. Toute écriture ou tout formulaire renvoie vers les factures ERP avec un message.
- Conséquence : l'ancien écran produit ne peut plus modifier le stock sans passer par `StockService`. L'e-mail avec
  URL Heroku codée en dur n'est plus envoyé (création bloquée).

### Verrouillages et affichage (V7, Q10, Q11, Q12, P6)

- **V7 :** une livraison ou une réception déjà facturée (facture brouillon ou validée) ne peut plus être modifiée ni
  supprimée. Elle se débloque si la facture est annulée par avoir.
- **Q11 :** « la facture de cette livraison » ignore désormais les avoirs et les factures annulées.
- **Q10 :** changer seulement le seuil d'alerte d'un produit relance la vérification de stock bas.
- **Q12 :** les fiches commande client et fournisseur affichent, par ligne, la quantité livrée ou reçue et le reste.
- **P6 :** la liste des relevés affiche les montants recalculés en direct.

### Refactorisation (Q1)

- **Nouveau trait `App\Http\Controllers\ERP\Concerns\HandlesDocumentLines`** (`pricedLines`, `checkLineChoice`,
  `checkLineChoiceWithManual`) : il remplace les copies de `normalizeItems` et `validateLineChoice` de 8
  contrôleurs. Avant suppression, chaque copie a été comparée automatiquement au code du trait.
- **Taille :** environ 60 lignes de moins par contrôleur de devis, commande et facture.

### Tests

- Nouveaux `PermissionsTest` (Technicien, Admin, ancien module en lecture seule) et `LockingAndDisplayTest`
  (livraison facturée verrouillée puis débloquée par avoir, reste à livrer, alerte de seuil, liste des relevés en
  direct).
- `RegistrationTest` : compte inactif, non connecté, qui ne peut pas entrer même avec le bon mot de passe.
- `InvoiceAttachmentSecurityTest` : réécrit, car l'upload et la suppression de l'ancien module sont maintenant
  bloqués. Le stockage des pièces jointes est testé directement.
- Utilitaires `actingAsAdmin()` / `userWithRole()` dans `tests/TestCase.php`.
- Résultat : **71 tests passent (426 assertions)**.

### Reste ouvert

- Q6 : le journal d'activité enregistre chaque consultation (GET) sans purge. C'est à décider, car cela touche l'audit.
- Q7 / Q9 : `AdminController.php` et les deux observers de stock ne sont plus utilisés, mais leur suppression n'a pas
  été autorisée dans cette session.
- S4 : les policies Produit / Catégorie / Contact autorisent encore tout (rôles déjà filtrés par `manage-finance`).
- Étape 1 : **rien n'est commité**.

---

## 2026-09-23 — Étape 6 : numérotation sûre, lignes liées, livraisons et réceptions partielles

### Numérotation (D2)

- **Nouveau compteur `document_sequences`** (migration `2026_09_23_130000_add_sequences_and_source_lines`,
  réversible, testée en rollback). `DocumentNumberService::next()` lit le compteur avec `SELECT … FOR UPDATE` : plus de
  doublon de numéro, donc plus d'erreur 500 quand deux utilisateurs valident en même temps.
- **Pas de trou :** si la création du document échoue, la transaction annule aussi l'incrément du compteur.
- **Reprise :** au premier usage d'une série (table, préfixe, année), le compteur démarre après le plus grand numéro
  existant. Même signature qu'avant, donc aucun appelant n'a changé.
- Sauvegarde avant migration : `storage/backups/laravel_before_2026_09_23_sequences.sql`.

### Lignes liées (V4, A4)

- **Nouvelles colonnes** `delivery_items.sales_order_item_id`, `delivery_note_items.purchase_order_item_id`,
  `client_invoice_items.delivery_item_id`, `supplier_invoice_items.delivery_note_item_id`.
- **Prix :** livraison → facture et réception → facture prennent le prix de la **ligne de commande liée**. Corrige
  deux bugs :
  - une ligne de service prenait le prix du premier service de la commande (`firstWhere('product_id', null)`) ;
  - un article commandé deux fois à deux prix différents était facturé deux fois au premier prix.
- **Quantités (A4) :** on facture la quantité réellement expédiée ou reçue. Une ligne reçue à 0 n'est plus facturée
  pour la quantité commandée.
- **Formulaires** livraison et réception : champ caché `item_source_id[]` par ligne. Le JavaScript d'ajout de ligne
  ne recopie plus ce lien (avant, il mettait `1` dans tous les champs de la ligne dupliquée).
- **Lignes saisies à la main :** rattachées automatiquement à la première ligne de commande du même article
  (produit, service ou libellé) pas encore utilisée.

### Livraisons et réceptions partielles (V5, A2)

- **Nouveau `App\Services\Fulfillment\OrderFulfillmentService`** :
  - calcule le reste à livrer ou à recevoir par ligne ;
  - tient `delivered_qty` à jour ;
  - fait passer la commande de `confirmed` à `partial` puis `delivered`.
- **« Convertir en livraison »** crée une nouvelle livraison pour le reste seulement. Plusieurs livraisons par
  commande sont possibles. Si tout est déjà livré ou planifié, un message l'indique.
- **« Convertir en réception »** fait de même pour les commandes d'achat, et réutilise un bon de réception encore vide
  au lieu d'en créer un deuxième.
- **Recalcul :** chaque création, modification, suppression ou conversion d'une livraison ou d'une réception met à
  jour la commande (et l'ancienne commande si on change de commande).
- **Verrouillage (V7 partiel) :** une commande client ou fournisseur qui a déjà une livraison ou une réception ne
  peut plus être modifiée ni supprimée (avant, sa modification recréait les lignes et cassait les liens).

### Tests

- Nouveau `tests/Feature/ERP/FulfillmentTest.php` (6 tests) :
  - numérotation qui reprend après les numéros existants, sans trou en cas d'échec ;
  - deux livraisons partielles avec prix exacts sur un article commandé à deux prix ;
  - prix des services ;
  - commande verrouillée après livraison ;
  - réceptions partielles avec facturation des seules quantités reçues ;
  - rendu des pages.
- Résultat : **64 tests passent (371 assertions)**.

### Limites connues

- Q12 : les fiches commande n'affichent pas encore le « reste à livrer » par ligne.
- V7 : une livraison déjà facturée reste modifiable (à bloquer comme les commandes).
- Les livraisons créées avant l'étape 6 n'ont pas de lien : leurs prix passent par l'ancien rapprochement produit /
  service (corrigé pour les services).

---

## 2026-09-23 — Étape 5 : paiements, relevés en direct, retards automatiques

### Paiements (P1, P5)

- **Nouvelles tables** `payments` et `payment_allocations` (migration `2026_09_23_120000_create_payments_tables`,
  réversible, testée en rollback). Les montants déjà saisis dans `amount_paid` sont repris en un règlement « Reprise
  historique » par facture. Sauvegarde avant migration : `storage/backups/laravel_before_2026_09_23_payments.sql`.
- **Nouveau `App\Services\Invoicing\PaymentService` :**
  - enregistre un règlement réparti sur une ou plusieurs factures, en tout ou rien (transaction + verrou sur chaque
    facture) ;
  - contrôle que chaque facture est validée, appartient au tiers, et que le montant ne dépasse pas son solde ;
  - détermine le sens tout seul (encaissement / décaissement) ;
  - recalcule `amount_paid`, `balance_remaining` et `status` à partir des règlements actifs ;
  - annule un règlement (avec motif) sans jamais le supprimer.
- **Remboursement des avoirs :** la fiche d'un avoir propose « Enregistrer le remboursement ». Le solde de l'avoir
  baisse, et il passe « Soldé » à 0.
- **Fiche facture :** le formulaire de paiement demande maintenant la date, le mode et la référence. L'historique des
  paiements est affiché, avec les annulés en grisé.
- **Nouvel écran « Règlements »** (`ErpPaymentController`, menus « Encaissements » et « Décaissements ») : liste
  filtrable, annulation avec motif, et « Encaisser un client » / « Payer un fournisseur » pour régler plusieurs
  factures d'un coup.
- `InvoiceLifecycleService` délègue désormais tous les calculs de paiement à `PaymentService` (validation, avoir).

### Relevés (P2)

- **Nouveau `App\Services\Invoicing\StatementService` :** grand livre du tiers calculé en direct (solde d'ouverture,
  factures, avoirs, règlements, remboursements, solde courant, solde de clôture). Les brouillons et les règlements
  annulés sont exclus.
- **Fiches de relevé client et fournisseur :** elles affichent ce grand livre (vue partielle
  `erp/partials/statement_ledger.blade.php`), avec des liens vers les factures. Les montants enregistrés à la
  génération reprennent le nombre de factures, les règlements et le solde de clôture de la période.
- Remplace le calcul de l'étape 4 (`statementTotals`, supprimé).

### Retards (P3)

- **Nouvelle commande** `php artisan invoices:refresh-statuses`, planifiée chaque jour à 01:00 dans
  `app/Console/Kernel.php` : une facture non soldée dont l'échéance est passée devient « En retard ».
  ⚠️ Il faut configurer le cron du serveur (`* * * * * php artisan schedule:run`).

### Tests

- Nouveau `tests/Feature/ERP/PaymentFlowTest.php` (9 tests) : détails du règlement et annulation, un règlement sur
  plusieurs factures, tout ou rien, remboursement d'avoir qui solde le compte, annulation d'un paiement d'une facture
  annulée, tâche des retards (voyage dans le temps), relevé avec solde d'ouverture et soldes courants, règlement
  fournisseur, rendu des écrans.
- Tests existants adaptés (un paiement exige maintenant une date et un mode). Le relevé compte désormais la facture
  annulée et son avoir, qui s'annulent.
- Résultat : **58 tests passent (327 assertions)**.

### Limites connues

- P6 : la **liste** des relevés affiche les montants enregistrés à la génération ; seule la fiche est en direct.
- P7 : pas encore de rapprochement bancaire ni d'export comptable.
- Pas d'acompte ni de trop-perçu : un règlement ne peut pas dépasser le solde des factures choisies.

---

## 2026-09-23 — Étape 4 : cycle de vie des factures, statut calculé, avoirs

### Factures client et fournisseur (V2, V3, D3)

- **Brouillon → Validée → Annulée par avoir.** Nouvelle migration `2026_09_23_110000_add_lifecycle_to_invoices`
  (colonnes `document_type`, `state`, `credited_invoice_id`, `validated_at`, `validated_by` sur `client_invoices` et
  `supplier_invoices`, réversible, testée en rollback). Les factures déjà en base sont passées « validées ».
  Sauvegarde avant migration : `storage/backups/laravel_before_2026_09_23_invoice_lifecycle.sql`.
- **Numéro définitif à la validation :** un brouillon a un numéro provisoire. `FAC-AAAA-0001` / `FSA-AAAA-0001` est
  attribué seulement à la validation, donc la séquence reste continue même si on supprime des brouillons.
- **Verrouillage :** une facture validée ne peut plus être modifiée ni supprimée. `edit`, `update` et `destroy`
  renvoient vers la fiche avec « créez un avoir ». Seuls les brouillons se suppriment.
- **Statut de paiement calculé :** le choix « Statut » est retiré des formulaires, et le champ « Montant payé » est en
  lecture seule. Toute valeur `status` ou `amount_paid` envoyée est ignorée (avant : `paid` possible avec 0 payé).
- **Paiements :** nouveau formulaire « Enregistrer un paiement » sur la fiche d'une facture validée
  (`POST …/payments`), plafonné au solde restant. Le statut (`partial`, `paid`, `overdue`) est recalculé.
- **Avoirs :** « Annuler par un avoir » (`POST …/credit-note`) crée un avoir total `AV-AAAA-0001` (client) ou
  `AVF-AAAA-0001` (fournisseur) avec les mêmes lignes. La facture passe « Annulée » avec un solde à 0, et l'avoir porte
  ce qui avait déjà été payé (à rembourser).
- **Conversions livraison / réception → facture :** elles créent un brouillon. Une facture annulée n'empêche plus de
  refacturer la livraison.
- **Nouveaux fichiers :** `app/Services/Invoicing/InvoiceLifecycleService.php`, `app/Models/Concerns/HasInvoiceLifecycle.php`,
  `app/Support/InvoiceLifecycle.php`, `resources/views/erp/partials/invoice_lifecycle.blade.php`.
  Préfixes ajoutés dans `config/numbering.php` : `client_credit_note` (AV), `supplier_credit_note` (AVF).

### Écrans et documents

- **Listes et fiches :** elles affichent l'état (Brouillon / Validée / Annulée) et le statut de paiement en français.
  « Modifier » et « Supprimer » n'apparaissent que sur les brouillons.
- **Fiche :** boutons Valider / Modifier / Supprimer (brouillon), paiement et « Annuler par un avoir » (validée), liens
  croisés facture ↔ avoir.
- **PDF :** titre « FACTURE - BROUILLON » + mention « Document provisoire » pour un brouillon, et « AVOIR » + facture
  annulée pour un avoir. Le numéro provisoire n'apparaît jamais.

### Tableau de bord et relevés

- **Tableau de bord :** chiffre d'affaires, coût des achats, nombre de factures, top clients, graphiques mensuels et
  retards ne comptent plus que les factures **validées** (ni brouillons, ni factures annulées, ni avoirs).
- **Relevés client / fournisseur :** factures validées uniquement, et le solde **déduit** les sommes à rembourser
  portées par les avoirs.

### Tests

- Nouveau `tests/Feature/ERP/InvoiceLifecycleTest.php` (10 tests) : brouillon non payé quoi qu'envoie le formulaire,
  numérotation sans trou, refus de valider une facture vide, verrouillage, paiements et statut calculé, pas de paiement
  sur brouillon, avoir (annulation + remboursement, une seule fois), relevé net des avoirs, cycle fournisseur, rendu de
  toutes les pages et des PDF.
- `StockFlowTest` : la facture est maintenant validée puis payée via les nouveaux écrans.
- Résultat : **49 tests passent (240 assertions)**.

### Limites connues

- P4 : seuls les avoirs **totaux** existent (pas de remise ni de retour partiel).
- P5 / P3 : le remboursement porté par un avoir et le passage automatique en retard dépendent de l'étape 5 (paiements).
- D2 : la numérotation n'est pas encore verrouillée contre deux validations simultanées (étape 6).

---

## 2026-09-23 — Étape 3 : une seule source de vérité pour le stock

### Stock (V1, A1)

- **Nouveau `App\Services\Stock\StockService`**, seul point d'entrée pour modifier le stock : transaction, verrou
  `FOR UPDATE` sur le produit (dans un ordre fixe pour éviter les interblocages), un `StockMovement` à chaque changement,
  alerte de stock bas envoyée seulement après validation de la transaction.
- **Seules les livraisons et les réceptions bougent le stock :**
  - livraison client en `shipped` / `delivered` → sortie de `shipped_qty` ;
  - réception fournisseur en `partial` / `complete` → entrée de `received_qty`.
- **Plus de double comptage :** chaque enregistrement recalcule l'effet attendu du document et n'applique que la
  **différence** avec ce qui a déjà été mouvementé. Avant, chaque modification d'une livraison redécrémentait le stock.
- **Annulation et suppression :** repasser une livraison en `pending` / `returned`, ou supprimer une livraison ou une
  réception, crée un mouvement inverse. Les mouvements ne sont plus jamais supprimés.
- **Stock négatif refusé :** une sortie supérieure au stock renvoie « Stock insuffisant… » et annule toute l'opération.
- **Factures :** la synchronisation du stock sur facture payée est retirée de `ClientInvoiceController` et
  `SupplierInvoiceController`. Payer une facture ne touche plus au stock.
- **Réceptions :** une ligne reçue à 0 n'ajoute plus rien (avant : la quantité commandée était ajoutée dès la conversion
  bon de commande → réception).
- **Observers désactivés :** `DeliveryItemObserver` et `DeliveryNoteItemObserver` ne sont plus enregistrés dans
  `AppServiceProvider` (fichiers encore présents, à supprimer).
- **Fiche produit :** le stock initial et les corrections passent par le service (`INITIAL_STOCK`,
  `MANUAL_ADJUSTMENT`) avec un mouvement `in` / `out` signé. Avant : un mouvement `adjustment` sans sens.

### Transactions (V6, A3)

`DB::transaction` ajouté autour de toutes les créations, mises à jour, suppressions et conversions de :
`SalesQuoteController`, `SalesOrderController`, `DeliveryController`, `PurchaseQuoteController`,
`PurchaseOrderController`, `DeliveryNoteController`, `ProductController`. Une erreur en cours de route n'écrit plus de
document à moitié.

### Tests

- Nouveau `tests/Feature/ERP/StockFlowTest.php` (7 tests) : livraison `pending` sans effet, modification = différence
  seulement, `returned` et suppression restituent le stock, stock insuffisant → rollback complet, facture payée sans
  effet sur le stock, réception = quantités reçues uniquement, correction manuelle signée.
- Résultat : **39 tests passent (120 assertions)**.

### Reste à faire, repéré pendant l'étape

- A4 : la conversion réception → facture facture encore la quantité commandée si `received_qty = 0` (étape 6).
- V2 (étape 4) : une facture peut toujours être passée `paid` avec 0 payé. Le test le montre, mais sans effet sur le stock désormais.
- Q9 / Q10 : voir [AUDIT_ERP.md](AUDIT_ERP.md).

---

## 2026-09-23 — Étape 2 : correctifs de sécurité urgents

### Sécurité

- **S1 — Suppression de fichiers arbitraires (corrigé).**
  `POST /delete_file` supprimait le chemin `invoice_number/file_name` reçu dans la requête : avec `../` on pouvait
  effacer n'importe quel fichier accessible au serveur. Le chemin est désormais reconstruit depuis l'enregistrement
  `invoices_attachments` (`id_file`), jamais depuis le formulaire.
  Fichier : `app/Http/Controllers/InvoicesDetailsController.php`.

- **S1b — Upload de pièces jointes non sûr (corrigé, découvert pendant l'étape 2).**
  L'upload gardait le nom de fichier du client et prenait le dossier dans un champ caché : un fichier pouvait être écrit
  hors de `public/Attachments`, et l'upload à la création de facture (`pic`) n'avait **aucune validation**.
  Désormais :
  - le dossier vient de la facture en base (`invoice_id`), pas du formulaire ;
  - le nom est régénéré (`slug-du-nom-XXXXXX.ext`) avec une extension déduite du **contenu** ;
  - seuls `pdf, jpg, jpeg, png` ≤ 10 Mo sont acceptés, sur les deux points d'upload ;
  - `invoice_number` (ancien module) refuse `/`, `\` et `..`, car il sert de nom de dossier.
  Nouveau helper : `app/Support/InvoiceAttachmentStorage.php`.
  Fichiers : `InvoicesAttachmentsController.php`, `InvoicesController.php` (store).

- **S5 — Affichage / téléchargement des pièces jointes (corrigé).**
  `/View_file/...` et `/download/...` utilisaient `applyPathPrefix()`, absent de Flysystem 3 (Laravel 10) : ces routes
  plantaient. Elles ne servent maintenant que les fichiers enregistrés dans `invoices_attachments` (sinon 404).

- **S2 — Route fourre-tout `/{page}` supprimée.**
  `Route::get('/{page}')` → `AdminController` affichait n'importe quelle vue Blade par son nom (`/index`, `/welcome`…).
  La route est retirée de `routes/web.php` (aucun lien de l'application ne l'utilisait). Ces URL renvoient maintenant 404.
  `app/Http/Controllers/AdminController.php` n'est plus référencé et peut être supprimé.

- **S6 — Route `/{lang}` restreinte.**
  `/{lang}` et `/dashboard/{lang}` n'acceptent plus que `ar|en|fr` (avant : n'importe quel segment). Effet de bord
  positif : `/dashboard` atteint enfin sa propre route nommée `dashboard`.
  Doublon de la route `GET /InvoicesDetails/{id}` supprimé.

### Base de données

- **D1 — Suppressions en cascade sur les documents financiers → `RESTRICT`.**
  Migration `2026_09_23_100000_restrict_deletes_on_financial_foreign_keys` (réversible, testée en rollback).
  Une suppression physique d'un client, d'un fournisseur, d'une commande ou d'un produit n'efface plus en cascade
  ses devis, commandes, livraisons, factures, relevés ou mouvements de stock : elle est refusée par MySQL.

  | Table | Colonne | Avant | Après |
  |---|---|---|---|
  | sales_quotes, sales_orders, deliveries, client_invoices, client_statements | client_id | CASCADE | RESTRICT |
  | purchase_quotes, purchase_orders, delivery_notes, supplier_invoices, supplier_statements | supplier_id | CASCADE | RESTRICT |
  | deliveries | sales_order_id | CASCADE | RESTRICT |
  | delivery_notes | purchase_order_id | CASCADE | RESTRICT |
  | stock_movements | product_id | CASCADE | RESTRICT |

  Inchangé volontairement : lignes → document (`*_items.*_id`) et `contact_addresses.contact_id` restent en CASCADE.
  Sauvegarde prise avant migration : `storage/backups/laravel_before_2026_09_23_fk_restrict.sql` (ignorée par git).

### Gestion des erreurs

- **Messages de validation perdus sur tous les formulaires (corrigé, découvert pendant l'étape 2).**
  Dans `app/Exceptions/Handler.php`, le `renderable(Throwable)` interceptait aussi les `ValidationException` et les
  erreurs HTTP (403/404/419) sur toute requête POST/PUT/DELETE : l'utilisateur voyait « Operation echouee… » au lieu du
  message du champ en erreur. Ces exceptions sont maintenant rendues nativement par Laravel.

### Tests

- **Protection de la base réelle (critique).** Les tests utilisent `RefreshDatabase` et tournaient sur la base
  `laravel` : lancer `php artisan test` **effaçait toutes les données**.
  - `phpunit.xml` force `DB_DATABASE=laravel_testing` (base créée sur le MySQL Laragon).
  - `tests/TestCase.php` refuse de démarrer si la base est `laravel`.
- Nouveaux tests : `tests/Feature/Security/InvoiceAttachmentSecurityTest.php` (upload `.php` refusé, dossier et nom non
  contrôlables, suppression qui ignore le chemin de la requête, fichiers non enregistrés → 404, `/index` → 404).
  Les uploads de test sont écrits dans `storage/framework/testing`, jamais dans `public/`.
- `tests/Feature/ExampleTest.php` : attend désormais la redirection de `/` vers `/login` (comportement réel).
- Résultat : **32 tests passent (66 assertions)**. Avant : 2 échecs.

### Environnement

- La base à jour est celle de **Laragon (MySQL 8.4)**, `D:\laragon\data\mysql-8.4\laravel`. La base `laravel` de XAMPP
  est une ancienne copie (mars 2026) sans les tables ERP : ne pas démarrer les deux MySQL en même temps (port 3306).
- Données : le compte `admin@gmail.com` a été repassé du rôle `Technicien` au rôle `Admin`.

---

## 2026-09-08 → 2026-09-23 — Migration vers l'ERP (non commitée)

- Ajout du module ERP (`app/Http/Controllers/ERP/*`, préfixe `/erp`) : contacts, produits, services, catégories,
  devis/commandes/livraisons/factures clients, demandes de prix/commandes/réceptions/factures fournisseurs,
  relevés, mouvements de stock, PDF, numérotation, journal d'activité.
- L'inscription publique force le rôle `Technicien` (la faille « s'inscrire en Admin » est fermée).
- L'ancien module `invoices` / `products` coexiste toujours ; `sections` a été supprimé.
