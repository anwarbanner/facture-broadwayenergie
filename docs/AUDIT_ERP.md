# Audit de l'ERP de facturation

Document vivant : il liste les problèmes connus, leur statut et les bonnes pratiques visées.
Mettre à jour le **statut** à chaque correction et consigner le détail dans [CHANGELOG.md](CHANGELOG.md).

- Audit initial : 2026-09-23 (lecture complète du code, du schéma et des conversions de documents).
- Légende : ✅ corrigé · ⏳ à faire · 🔴 critique · 🟠 important · 🟡 mineur

---

## 1. Architecture actuelle

- **Produit standard :** aucune marque d'entreprise dans le code. Identité, mentions légales, devise et TVA par défaut
  sont réglées dans `.env` (voir [PERSONNALISATION.md](PERSONNALISATION.md)).
- **Stack :** Laravel 10 / PHP 8.1+, MySQL 8.4 (Laragon), Blade + jQuery/Bootstrap, dompdf, Maatwebsite Excel,
  Spatie Permission, Fortify + Breeze.
- **Monolithe MVC** rendu côté serveur, pas d'API. La logique métier est dans les contrôleurs (300 à 430 lignes).
- **Deux systèmes cohabitent :**
  - ancien module `invoices` / `invoices_details` / `invoices_attachments` / `products` (routes à la racine) ;
  - nouveau module **ERP** (`app/Http/Controllers/ERP/*`, préfixe `/erp`).
- **Services :** `DocumentNumberService` (numérotation), 8 services PDF, `FinancialHelper` (HT/TVA/TTC, solde, statut),
  `InvoiceAttachmentStorage` (pièces jointes de l'ancien module).
- **Factures :** `InvoiceLifecycleService` (`app/Services/Invoicing`) + trait `HasInvoiceLifecycle` : brouillon → validée → annulée par avoir.
- **Paiements :** `PaymentService` (tables `payments` + `payment_allocations`), relevés calculés par `StatementService`, tâche `invoices:refresh-statuses`.
- **Stock :** `StockService` (`app/Services/Stock`) est le seul point d'entrée. Il déclenche `ProductStockChanged` → notification de stock bas.
- **Audit :** middleware `AuditActivity`, qui écrit chaque requête dans `activity_logs`.
- **Rôles :** `Admin`, `Technicien`. Middleware `admin` sur users / roles / activity-logs uniquement.

### Flux documentaires

```
Vente  : Devis (DEV) → Commande (CMD) → Livraison (BLV) → Facture client (FAC) → Relevé client
Achat  : Demande de prix (DPA) → Bon de commande (BCA) → Réception (BLA) → Facture fournisseur (FSA) → Relevé fournisseur
```
Numérotation : `PREFIXE-AAAA-0001`, remise à zéro chaque année (`config/numbering.php`).

---

## 2. Problèmes connus

### Flux vente

| # | Gravité | Problème | Où | Statut |
|---|---|---|---|---|
| V1 | 🔴 | Stock décrémenté 2 à 3 fois : à la création de la livraison (même `pending`), à chaque modification de la livraison (lignes supprimées puis recréées sans restitution), puis quand la facture passe `paid`. | `DeliveryItemObserver`, `DeliveryController::update`, `ClientInvoiceController::syncSalesInvoiceStockMovement` | ✅ 2026-09-23 |
| V2 | 🔴 | Statut de facture saisi à la main : `paid` possible avec 0 payé. `FinancialHelper::invoiceStatus()` n'est pas utilisé ici. | `ClientInvoiceController` store/update | ✅ 2026-09-23 |
| V3 | 🔴 | Facture payée modifiable et supprimable. Pas de verrouillage ni d'avoir. | `ClientInvoiceController` update/destroy | ✅ 2026-09-23 |
| V4 | 🟠 | Conversion livraison → facture : prix repris avec `firstWhere('product_id', null)`, donc une ligne de service prend le prix du premier service de la commande. Même problème pour un produit présent deux fois. | `DeliveryController::convertToInvoice` | ✅ 2026-09-23 |
| V5 | 🟠 | Pas de livraison partielle : une seule livraison par commande, `delivered_qty` jamais mis à jour, la commande n'atteint jamais « livrée ». | `SalesOrderController::convertToDelivery` | ✅ 2026-09-23 |
| V6 | 🟠 | Pas de transaction dans les conversions et mises à jour devis / commande / livraison. | contrôleurs ventes | ✅ 2026-09-23 |
| V7 | 🟡 | Commande et livraison modifiables après facturation. | tous | ✅ 2026-09-23 (commande verrouillée dès la 1re livraison ; livraison / réception verrouillée une fois facturée) |

### Flux achat

| # | Gravité | Problème | Statut |
|---|---|---|---|
| A1 | 🔴 | Stock incrémenté deux fois : `DeliveryNoteItemObserver` puis facture fournisseur payée. De plus, une réception à `received_qty = 0` ajoutait la quantité commandée. | ✅ 2026-09-23 |
| A2 | 🟠 | Réception créée directement en `partial`, une seule réception par commande. | ✅ 2026-09-23 |
| A3 | 🟠 | Pas de transactions (conversions, mises à jour). | ✅ 2026-09-23 |
| A4 | 🟠 | Conversion réception → facture : une ligne à `received_qty = 0` est facturée pour la quantité commandée (même repli pour `shipped_qty` côté vente). Le stock, lui, utilise maintenant la quantité réellement reçue : facture et stock peuvent diverger. | ✅ 2026-09-23 |

### Paiements et relevés

| # | Gravité | Problème | Statut |
|---|---|---|---|
| P1 | 🔴 | Pas de table de paiements : `amount_paid` est un champ écrasé, sans historique ni rapprochement. | ✅ 2026-09-23 |
| P2 | 🟠 | Relevé = photo figée de sommes, sans solde d'ouverture ni mouvements, faux si une facture change. | ✅ 2026-09-23 |
| P3 | 🟡 | `overdue` saisi à la main, aucune tâche planifiée. | ✅ 2026-09-23 (tâche quotidienne ; nécessite le cron `schedule:run` sur le serveur) |
| P4 | 🟠 | Avoirs uniquement **totaux** (annulation complète). Pas d'avoir partiel (remise, retour d'une partie). | ✅ 2026-09-28 (quantités par ligne, remise, motif ; remboursement du trop-perçu ; annulation au centime) |
| P5 | 🟡 | Le remboursement porté par un avoir ne pouvait pas être enregistré. | ✅ 2026-09-23 |
| P6 | 🟡 | Les montants stockés sur les relevés (`client_statements`, `supplier_statements`) sont une copie au moment de la génération ; la fiche du relevé, elle, est recalculée en direct. La liste des relevés peut donc afficher un solde ancien. | ✅ 2026-09-23 |
| P7 | 🟡 | Pas de rapprochement bancaire ni d'export comptable des règlements. | Partiel 2026-09-28 : export des écritures comptables (VT / HA / BQ / CA) ✅ ; rapprochement avec le relevé bancaire ⏳ |

### Base de données

| # | Gravité | Problème | Statut |
|---|---|---|---|
| D1 | 🔴 | `client_id` / `supplier_id` / documents parents / `stock_movements.product_id` en `CASCADE` : une suppression physique effaçait les factures. | ✅ 2026-09-23 |
| D2 | 🟠 | Numérotation `SELECT max` sans verrou : doublon en concurrence, donc erreur 500 sur l'index unique. | ✅ 2026-09-23 |
| D3 | 🟠 | Soft-delete sur les factures : une facture ne doit jamais disparaître (avoir à la place). Seuls les brouillons sont supprimables. | ✅ 2026-09-23 |
| D4 | 🟡 | Devise et taux de change stockés mais jamais utilisés dans les totaux et rapports. | ✅ 2026-09-28 (taux de change retiré des formulaires, toujours 1 ; la devise reste une étiquette) |

### Sécurité

| # | Gravité | Problème | Statut |
|---|---|---|---|
| S1 | 🔴 | Suppression de fichiers arbitraires via le chemin reçu dans la requête (`/delete_file`). | ✅ 2026-09-23 |
| S1b | 🔴 | Upload : nom de fichier client conservé, dossier pris dans un champ caché, upload `pic` sans validation. | ✅ 2026-09-23 |
| S2 | 🔴 | Route `/{page}` : n'importe quelle vue Blade accessible par son nom. | ✅ 2026-09-23 |
| S3 | 🟠 | Inscription publique crée un compte actif avec accès à toute la facturation. Pas de permissions par module. | ✅ 2026-09-23 (inscription → compte inactif ; finance réservée à l'Admin) |
| S4 | 🟠 | Policies sur 3 modèles seulement, vérifiées à un seul endroit. | Partiel : règle `manage-finance` sur toutes les routes financières (2026-09-23). Les policies Produit / Catégorie / Contact autorisent encore tout ⏳ |
| S5 | 🟡 | `/View_file` et `/download` plantaient (`applyPathPrefix` absent en Laravel 10). | ✅ 2026-09-23 |
| S6 | 🟡 | `/{lang}` capturait n'importe quelle URL à un segment. | ✅ 2026-09-23 |
| S7 | 🔴 | Les tests (`RefreshDatabase`) tournaient sur la base réelle `laravel`. | ✅ 2026-09-23 |
| S8 | 🟠 | Handler d'exceptions : erreurs de validation remplacées par un message générique sur tous les formulaires. | ✅ 2026-09-23 |

### Qualité du code

| # | Problème | Statut |
|---|---|---|
| Q1 | `normalizeItems` / `validateLineChoice` copiés dans environ 8 contrôleurs. | ✅ 2026-09-23 (trait `HandlesDocumentLines`) |
| Q2 | Contrôleurs trop gros, logique métier mélangée à l'affichage. | Partiel : la logique métier est passée dans des services (stock, factures, paiements, relevés, livraisons, numérotation) ; les contrôleurs restent longs ⏳ |
| Q3 | Pas de tests sur les conversions, le stock ou les paiements. | ✅ 71 tests (stock, factures, paiements, relevés, livraisons partielles, permissions, sécurité) |
| Q4 | Ancien module et ERP non réconciliés. | ✅ 2026-09-23 : ancien module en lecture seule (décision du propriétaire) |
| Q5 | Environ 175 fichiers non commités. | ⏳ |
| Q6 | `AuditActivity` écrit chaque GET, sans purge. | ✅ 2026-09-28 (GET non journalisés sauf téléchargements ; commande `activity-logs:prune` manuelle, durée de conservation à décider) |
| Q7 | `AdminController.php` n'est plus utilisé (à supprimer). | ⏳ |
| Q8 | `InvoicesController::store` (ancien module) envoie un e-mail avec une URL Heroku codée en dur. | ✅ 2026-09-23 (création bloquée par la lecture seule) |
| Q9 | `app/Observers/DeliveryItemObserver.php` et `DeliveryNoteItemObserver.php` ne sont plus enregistrés (à supprimer). | ⏳ |
| Q10 | Modifier seulement le seuil `min_stock_alert_level` d'un produit ne relance plus la vérification de stock bas (seul un mouvement la déclenche). | ✅ 2026-09-23 |
| Q12 | Les fiches commande n'affichent pas encore le « reste à livrer / recevoir » par ligne (la donnée existe : `delivered_qty`). | ✅ 2026-09-23 |
| Q11 | `Delivery::clientInvoice` / `DeliveryNote::supplierInvoice` (`latestOfMany`) peuvent afficher l'avoir au lieu de la facture dans les listes de livraisons. | ✅ 2026-09-23 |

---

### Ergonomie — analyse Selenium du 2026-09-28

Parcours rejoués dans Chrome (Selenium, base isolée `laravel_e2e`) : cycle vente complet (devis → commande →
livraison → facture → validation → paiement → PDF), cycle achat complet, erreurs de saisie, et visite de toutes
les pages ERP en largeur bureau (1440 px) et téléphone (390 px).

| # | Gravité | Problème | Statut |
|---|---|---|---|
| U1 | 🟠 | Client / fournisseur inconnu : il fallait quitter le devis, créer le contact, puis revenir. | ✅ 2026-09-28 (nom tapé dans le document → contact créé) |
| U2 | 🟠 | TVA à saisir sur chaque ligne ; arrondi ligne par ligne. | ✅ 2026-09-28 (un taux par document, appliqué au total HT) |
| U3 | 🟡 | Champ « Taux de change » obligatoire et inutile. | ✅ 2026-09-28 (supprimé) |
| U4 | 🟠 | Devis client limité aux produits / services du catalogue. | ✅ 2026-09-28 (lignes « Manuel », reprises jusqu'à la facture, sans effet sur le stock) |
| U5 | 🟠 | Messages de confirmation et **refus de conversion invisibles** sur les fiches documents (le message était enregistré mais jamais affiché). | ✅ 2026-09-28 (zone de messages unique dans la mise en page) |
| U6 | 🟡 | Après « Enregistrer », retour à la liste sans repère : il fallait rechercher le document créé. | ✅ 2026-09-28 (création → liste avec la nouvelle ligne surlignée en bleu puis blanche ; modification → fiche du document) |
| U7 | 🟡 | Listes des ventes sans recherche ; achats : recherche par numéro seulement. | ✅ 2026-09-28 (recherche numéro **ou** client / fournisseur + filtre statut sur les 8 listes) |
| U8 | 🟡 | Erreur JavaScript sur toutes les pages (`PerfectScrollbar` initialisé sur des éléments absents). | ✅ 2026-09-28 |
| U9 | 🟡 | Totaux absents pendant la saisie ; pas de prix repris du catalogue côté achat. | ✅ 2026-09-28 (totaux en direct ; prix de revient repris sur les achats) |
| U10 | 🟡 | Champs de filtre sans libellé (lecteurs d'écran) ; en-têtes de lignes cachés sur téléphone. | ✅ 2026-09-28 |
| U11 | 🟡 | La fiche produit garde un « TVA % » propre au produit, qui n'est plus utilisé par les documents. | ✅ 2026-09-28 (retiré, colonne supprimée — décision du propriétaire) |
| U13 | 🟡 | `graphify update` échoue dans ce dossier (apostrophe dans `anwar's desk`) : le graphe du code n'est plus à jour depuis le 2026-09-24. | ⏳ (outil externe) |
| U14 | 🟠 | Pas d'analyse comptable (journaux, TVA due, trésorerie, échéancier, classements). | ✅ 2026-09-28 (section Comptabilité) |
| U15 | 🟡 | Libellés : « Tiers », types Piste / Prospect inutilisés, « Devis d'achat ». | ✅ 2026-09-28 (Contacts ; Client / Fournisseur ; Demande d'achat) |
| U16 | 🟠 | Pages lentes : sans OPcache, barre de debug active, aucun cache navigateur ni compression, 6 scripts et 7 familles d'icônes inutiles, voile flou à chaque chargement. | ✅ 2026-09-28 (serveur ~250 → ~55 ms ; visite suivante ~340 → ~100 ms) |
| U18 | 🟠 | Pas d'export Excel des documents, pas d'import en masse, pas de signature sur les documents. | ✅ 2026-09-28 |
| U19 | 🟠 | Quantités affichées « 1.000 », montants « 250.00 », tableau de bord alimenté par l'ancien module (tout à 0), listes trop hautes sur téléphone, statuts en texte brut. | ✅ 2026-09-28 |
| U20 | 🟡 | Pas de mode sombre ; sélecteur de langue inutile (l'ERP est en français). | ✅ 2026-09-28 |
| U21 | 🟠 | Pages Utilisateurs / Rôles de l'ancien modèle ; possibilité de supprimer son compte, le dernier administrateur ou le rôle Admin ; permissions sans effet. | ✅ 2026-09-29 |
| U12 | 🟡 | Route de test `hhhhhhhhhhhhhhhhh` → `HomeController@home` toujours déclarée. | ✅ 2026-09-28 (supprimée) |
| U17 | 🟠 | Journal d'activité illisible (méthodes HTTP, routes, codes), refus enregistrés comme succès, document créé inconnu. | ✅ 2026-09-28 (journal en français, groupé par jour, filtres simples, résultat réel) |

## 3. Bonnes pratiques visées

1. **Cycle de vie strict :** Brouillon (modifiable, sans numéro définitif) → Validé (numéroté, immuable) → Annulé par
   **avoir**. Jamais d'update ou de delete sur une facture validée.
2. **Statuts calculés, jamais saisis :** paiement dérivé des paiements et de l'échéance ; commande dérivée de
   `delivered_qty` / `invoiced_qty`. Tâche planifiée quotidienne pour `overdue`.
3. **Paiements dans des tables dédiées :** `payments` + `payment_allocations` (un paiement peut couvrir plusieurs
   factures). Relevés générés à la volée avec solde d'ouverture.
4. **Stock : une seule source de vérité.** Seule la **validation** d'une livraison ou d'une réception bouge le stock.
   Tout passe par un `StockService` (transaction, `lockForUpdate`, toujours un `StockMovement`). Une annulation crée un
   mouvement inverse. La facture ne touche jamais au stock.
5. **Copie des données sur chaque ligne + lien vers la ligne source :** désignation, prix et TVA copiés ;
   `source_line_id` d'une ligne de facture vers la livraison puis la commande (fin du `firstWhere`).
6. **Numérotation légale :** table `sequences` verrouillée, séquence continue et sans trou, attribuée à la validation.
7. **Montants :** arrondi par ligne puis total, `decimal(15,2)`, ventilation de la TVA par taux, mentions légales
   Maroc (ICE, IF, RC, patente), montants aussi stockés en devise de base.
8. **Architecture :** contrôleurs minces → FormRequest → Actions / Services → Models. `DB::transaction` pour toute
   écriture multi-tables. `restrictOnDelete` vers les tiers et les produits.
9. **Sécurité :** permissions Spatie par module et par action ; inscription publique fermée ou comptes inactifs ;
   fichiers servis uniquement depuis la base ; audit des changements (avant / après) sur les documents financiers.
10. **Tests :** Feature sur chaque conversion, le stock, les paiements, les avoirs et la numérotation concurrente ;
    toujours sur `laravel_testing`.

---

## 4. Plan d'action

| Étape | Contenu | Problèmes couverts | Statut |
|---|---|---|---|
| 1 | Commiter l'existant par lots | Q5 | ⏳ toujours à faire : rien n'est commité |
| 2 | Correctifs de sécurité urgents | S1, S1b, S2, S5, S6, S7, S8, D1 | ✅ 2026-09-23 |
| 3 | `StockService` unique + suppression des observers et de la synchro par facture + transactions | V1, V6, A1, A3 | ✅ 2026-09-23 |
| 4 | Statut calculé, verrouillage des factures validées, avoirs | V2, V3, D3 | ✅ 2026-09-23 |
| 5 | Tables de paiements, relevés dynamiques, tâche `overdue` | P1, P2, P3, P5 | ✅ 2026-09-23 |
| 6 | Numérotation verrouillée, `source_line_id`, livraisons et réceptions partielles | D2, V4, V5, A2, A4 | ✅ 2026-09-23 |
| 7 | Refactorisation (Actions/Services), permissions, tests, sort de l'ancien module | Q1–Q8, S3, S4 | ✅ 2026-09-23 (sauf Q6, Q7, Q9, S4 complet : voir ci-dessus) |

---

## 5. Règles de stock en vigueur (depuis l'étape 3)

| Document | Statuts qui bougent le stock | Quantité | Sens |
|---|---|---|---|
| Livraison client | `shipped`, `delivered` | `shipped_qty` | sortie |
| Réception fournisseur | `partial`, `complete` | `received_qty` (0 = rien reçu) | entrée |
| Fiche produit | création / modification | écart avec la valeur saisie | entrée ou sortie |
| Factures client / fournisseur | aucun | — | — |

- Toute écriture passe par `App\Services\Stock\StockService`, dans une transaction, avec verrou `FOR UPDATE` sur le produit.
- **Mouvements en ajout seul :** modifier, repasser en `pending`/`returned` ou supprimer un document ajoute un mouvement correctif
  égal à la différence. Aucun mouvement n'est supprimé. Référence : `DELIVERY:{id}`, `RECEIPT:{id}`, `INITIAL_STOCK`, `MANUAL_ADJUSTMENT`.
- **Stock négatif interdit :** la sortie est refusée (« Stock insuffisant… ») et toute l'opération est annulée.
- Le type `adjustment` n'est plus produit : les corrections manuelles sont des `in` / `out` signés.

## 6. Cycle de vie des factures (depuis l'étape 4)

Valable pour les factures **client et fournisseur**.

| État | Numéro | Modifiable / supprimable | Paiements | Compté dans le tableau de bord et les relevés |
|---|---|---|---|---|
| Brouillon | provisoire (`BROUILLON-…`, affiché « Brouillon #id ») | oui | non | non |
| Validée | définitif, sans trou : `FAC-AAAA-0001` / `FSA-AAAA-0001` | **non** | oui, sur la fiche | oui |
| Annulée par avoir | inchangé | non | non | non (solde ramené à 0) |
| Avoir | `AV-AAAA-0001` / `AVF-AAAA-0001` | non | — | son solde = somme à rembourser, déduite du relevé |

- **Statut de paiement** (`unpaid` / `partial` / `paid` / `overdue`) : toujours calculé à partir des paiements et de
  l'échéance. Le formulaire ne le propose plus, et une valeur envoyée est ignorée.
- **Valider :** refusé pour une facture sans ligne ou à 0. Le numéro est pris à ce moment-là, donc supprimer un brouillon
  ne crée pas de trou dans la séquence.
- **Payer :** bouton « Enregistrer un paiement » sur une facture validée. Le montant est plafonné au solde restant.
- **Annuler :** « Annuler par un avoir » crée un avoir total (mêmes lignes, mêmes montants) et passe la facture en
  « Annulée ». Si le client avait déjà payé X, l'avoir porte X à rembourser. Une seule fois par facture.
- **Livraison / réception → facture :** la conversion crée un **brouillon**. Une facture annulée par avoir ne bloque pas
  une nouvelle facturation de la même livraison.
- Les factures créées avant l'étape 4 ont été marquées « validées » par la migration.

## 7. Paiements et relevés (depuis l'étape 5)

- **Un règlement = un mouvement d'argent** (`payments`) pour un tiers, réparti sur une ou plusieurs factures
  (`payment_allocations`). Il porte une date, un mode (virement, chèque, espèces, carte, effet, autre), une référence
  et des notes.
- **Sens :** encaissement (`in`) = client qui paie, ou fournisseur qui rembourse un avoir. Décaissement (`out`) = paiement
  d'un fournisseur, ou remboursement d'un avoir client. Un même règlement ne mélange pas factures et avoirs.
- **Montants des factures :** `amount_paid`, `balance_remaining` et `status` sont **recalculés** à partir des règlements
  actifs. On ne les saisit jamais.
- **Jamais supprimé :** un règlement erroné est **annulé** (motif obligatoire). Il reste dans l'historique et les
  factures concernées sont recalculées.
- **Avoir :** son solde = ce qui avait été payé sur la facture annulée, moins les remboursements déjà faits.
- **Écrans :** fiche facture (paiement ou remboursement + historique), menu « Encaissements » / « Décaissements »
  (liste, filtre, annulation), « Encaisser un client » / « Payer un fournisseur » (un règlement sur plusieurs
  factures, tout ou rien).
- **Relevés :** grand livre calculé en direct : solde d'ouverture, puis chaque facture (+), avoir (−), règlement (−),
  remboursement (+), avec le solde courant, puis le solde de clôture. Une facture annulée et son avoir s'annulent.
- **Retards :** `php artisan invoices:refresh-statuses` recalcule chaque nuit à 01:00 les factures ouvertes. Il faut
  le cron du serveur : `* * * * * php /chemin/vers/artisan schedule:run`.

## 8. Livraisons partielles et numérotation (depuis l'étape 6)

- **Lien ligne à ligne :** ligne de commande → ligne de livraison / réception (`sales_order_item_id`,
  `purchase_order_item_id`) → ligne de facture (`delivery_item_id`, `delivery_note_item_id`). Les formulaires gardent
  ce lien dans un champ caché. Une ligne ajoutée à la main est rattachée à la première ligne de commande du même
  article pas encore utilisée.
- **Livraisons partielles :** « Convertir en livraison » ne prend que le **reste à livrer** (commandé − déjà livré ou
  planifié dans une livraison en attente). On peut en faire plusieurs par commande. Réceptions : même principe
  (commandé − déjà reçu). Un bon de réception encore vide est réutilisé au lieu d'être dupliqué.
- **Suivi :** `delivered_qty` de chaque ligne de commande = quantités réellement expédiées / reçues. Statut de la
  commande : `confirmed` → `partial` → `delivered`, calculé automatiquement (`OrderFulfillmentService`).
- **Verrouillage :** une commande qui a déjà une livraison (ou une réception) n'est plus modifiable ni supprimable.
- **Facturation :** la conversion en facture facture la quantité **réellement** expédiée ou reçue (0 = non
  facturée), au prix de la **ligne de commande liée**.
- **Numérotation :** compteur `document_sequences` par (table, préfixe, année), lu avec `SELECT … FOR UPDATE`. Deux
  utilisateurs ne peuvent plus obtenir le même numéro. Un document annulé par une erreur rend son numéro. Au premier
  usage, le compteur reprend après le plus grand numéro déjà présent.

## 9. Rôles, inscription et ancien module (depuis l'étape 7)

| Action | Admin | Technicien |
|---|---|---|
| Produits, services, stock, contacts | ✅ | ✅ |
| Devis, commandes, livraisons, réceptions | ✅ | ✅ |
| Factures : créer / modifier / supprimer un **brouillon** | ✅ | ✅ |
| Valider une facture, créer un avoir | ✅ | ❌ |
| Règlements (encaissements, décaissements, annulation) | ✅ | ❌ |
| Relevés clients et fournisseurs | ✅ | ❌ |
| Utilisateurs, rôles, journal d'activité | ✅ | ❌ |

- **Règle unique :** `Gate::define('manage-finance')` (Admin uniquement) dans `AuthServiceProvider`. Les routes
  financières sont regroupées sous `can:manage-finance` (403 sinon). Boutons et menus correspondants masqués pour
  le Technicien.
- **Inscription publique :** le compte est créé **inactif** (`status = unactive`), sans connexion automatique. Un Admin
  l'active dans Utilisateurs. Tant qu'il est inactif, la connexion est refusée avec un message.
- **Ancien module de facturation** (`/invoices`, anciens produits, pièces jointes, statuts, corbeille) : **lecture
  seule** via le middleware `legacy.readonly`. Liste, détails, impression, export et rapports restent accessibles.
  Toute création, modification ou suppression renvoie vers les factures ERP avec un message.

## 10. Lancer le projet en local

1. Démarrer **MySQL de Laragon** (pas celui de XAMPP : même port 3306, ancienne base).
2. `php artisan serve` puis ouvrir http://127.0.0.1:8000
3. Tests : `php artisan test`. Ils utilisent la base `laravel_testing` et refusent de tourner sur `laravel`.
   Créer la base si besoin : `CREATE DATABASE laravel_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
4. Avant toute migration sensible : `mysqldump -uroot laravel > storage/backups/<nom>.sql` (dossier ignoré par git).

## 11. Mode simple (depuis le 2026-10-08)

- `SIMPLE_MODE=true` (défaut) : menu court, accueil simplifié, devis sans numéro/statut à saisir, impression
  dans le navigateur. `false` = ERP complet. Les routes avancées restent accessibles par URL.
- « Encaisser » sur un devis = facture validée + paiement total via `QuoteCashInService` (cycle de vie et
  `PaymentService` habituels, donc comptabilité, relevés et avoirs voient la facture normalement). Aucun
  mouvement de stock (règle de l'étape 3 inchangée). Réservé à `manage-finance` (Admin).
- Un devis encaissé est verrouillé ; un devis déjà passé en commande s'encaisse par la facture de la commande.
- Bon de livraison depuis un devis (2026-10-09) : document imprimé seulement, **aucun mouvement de stock**
  (`moves_stock = false`), pas de commande, lignes = celles du devis. Facturé uniquement via « Encaisser ».
- Exports Excel : par document (devis, facture, BL) et listes filtrées (`list-export`).
- BL sans commande (2026-10-09) : depuis un devis ou tapé à la main ; `follows_quote` décide si le devis réécrit
  le BL. Aucun ne bouge le stock ni ne se facture directement. Devis fournisseurs (= demandes d'achat) au menu simple.
- Devis → facture (2026-10-09) : « Faire la facture » (à payer) ou « Encaisser » ; une facture par devis, devis verrouillé
  dès qu'il est facturé. À noter : un devis dont la facture est annulée par avoir reste verrouillé (pas de refacturation).
- Factures saisies à la main en mode simple : validées à l'enregistrement (finance). Devis fournisseur → facture
  fournisseur en un clic (une par devis, sans stock). Les devis fournisseurs restent les tables purchase_quotes.

## 12. Pages expirées et erreurs (depuis le 2026-10-17)
- 419 « Page Expired » jamais affiché (`App\Exceptions\Handler`) : session perdue → connexion avec message, puis
  retour sur la page d'origine (`url.intended`) ; encore connecté → retour au formulaire, saisies conservées
  (sauf mots de passe), message « rien n'a été enregistré ». Déconnexion expirée → connexion. AJAX → JSON 419.
- Accès sans session (`unauthenticated`) : message sur la page de connexion, sauf pour l'adresse `/`.
- Pages d'erreur en français dans `resources/views/errors/` (403, 404, 419, 429, 500, 503, 4xx, 5xx), avec
  « Page précédente » et « Retour à l'accueil ». Visibles seulement avec `APP_DEBUG=false` pour les 500.
