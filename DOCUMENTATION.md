# Documentation Technique - ProductInternalDocs

Documentation complète du module PrestaShop ProductInternalDocs pour la gestion sécurisée de documents internes.

## Table des matières

- [Architecture](#architecture)
- [Base de données](#base-de-données)
- [Sécurité](#sécurité)
- [Installation détaillée](#installation-détaillée)
- [Utilisation](#utilisation)
- [API et Endpoints](#api-et-endpoints)
- [Développement](#développement)
- [Troubleshooting](#troubleshooting)

---

## Architecture

### Vue d'ensemble

Le module suit l'architecture standard PrestaShop avec une séparation claire des responsabilités :

```
productinternaldocs/
├── classes/
│   └── ProductInternalDocument.php    # Modèle de données (ObjectModel)
├── controllers/
│   └── admin/
│       └── AdminProductInternalDocsController.php  # Contrôleur admin pour downloads
├── sql/
│   ├── install.sql                    # Création de la table
│   └── uninstall.sql                  # Suppression de la table
├── views/
│   └── templates/
│       └── admin/
│           └── product_documents.tpl  # Interface utilisateur (Smarty + jQuery)
├── ajax.php                           # Endpoint AJAX (upload, delete, getDocuments)
├── productinternaldocs.php           # Fichier principal du module
├── README.md                          # Documentation utilisateur
└── DOCUMENTATION.md                   # Documentation technique (ce fichier)
```

### Flux de données

#### 1. Téléversement d'un document

```
[Interface utilisateur (product_documents.tpl)]
            ↓ (AJAX POST)
      [ajax.php - action: upload]
            ↓
[ProductInternalDocument::uploadDocument()]
            ↓
    [Validation sécurité]
    - Vérification MIME type
    - Vérification taille fichier
    - Génération UUID
            ↓
    [Stockage fichier]
    /var/private_documents/products/{id_product}/{uuid}.ext
            ↓
    [Enregistrement BDD]
    ps_product_internal_document
            ↓
    [Log via PrestaShopLogger]
            ↓
    [Retour JSON success]
```

#### 2. Téléchargement d'un document

```
[Interface utilisateur - clic sur ⬇️]
            ↓ (GET request)
[AdminProductInternalDocsController::processDownload()]
            ↓
[Vérification authentification]
    - Employé connecté ?
            ↓
[Chargement document depuis BDD]
    - Document existe ?
    - Fichier existe physiquement ?
            ↓
[Log téléchargement]
            ↓
[Streaming fichier par chunks (8KB)]
    - Headers HTTP appropriés
    - Content-Type, Content-Disposition
    - Lecture par blocs pour éviter surcharge mémoire
```

#### 3. Suppression d'un document

```
[Interface utilisateur - clic sur 🗑️]
            ↓ (AJAX POST)
      [ajax.php - action: delete]
            ↓
[ProductInternalDocument::softDelete()]
            ↓
    [Soft delete]
    - deleted_at = NOW()
    - is_active = 0
    - Fichier physique conservé
            ↓
    [Log suppression]
            ↓
    [Retour JSON success]
```

---

## Base de données

### Table : `ps_product_internal_document`

```sql
CREATE TABLE IF NOT EXISTS `ps_product_internal_document` (
    `id_document` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `storage_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size` BIGINT UNSIGNED NOT NULL,
    `uploaded_by` INT(11) UNSIGNED NOT NULL,
    `uploaded_at` DATETIME NOT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_document`),
    KEY `idx_product` (`id_product`),
    KEY `idx_active` (`is_active`),
    KEY `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Description des champs

| Champ | Type | Nullable | Description |
|-------|------|----------|-------------|
| `id_document` | INT(11) UNSIGNED | Non | Clé primaire auto-incrémentée |
| `id_product` | INT(11) UNSIGNED | Non | Référence au produit PrestaShop |
| `original_name` | VARCHAR(255) | Non | Nom original du fichier téléversé |
| `title` | VARCHAR(255) | Oui | Titre personnalisé (optionnel, sinon = original_name) |
| `stored_name` | VARCHAR(255) | Non | Nom UUID du fichier sur disque (ex: `a3f2c9d8-...-.pdf`) |
| `storage_path` | VARCHAR(500) | Non | Chemin du répertoire de stockage |
| `mime_type` | VARCHAR(100) | Non | Type MIME réel du fichier (vérifié avec finfo) |
| `size` | BIGINT UNSIGNED | Non | Taille du fichier en octets |
| `uploaded_by` | INT(11) UNSIGNED | Non | ID de l'employé ayant téléversé le document |
| `uploaded_at` | DATETIME | Non | Date et heure du téléversement |
| `deleted_at` | DATETIME | Oui | Date de suppression (soft delete), NULL si actif |
| `is_active` | TINYINT(1) | Non | 1 = actif, 0 = supprimé (soft delete) |

### Index

- **PRIMARY** : `id_document` - Recherche par ID
- **idx_product** : `id_product` - Recherche rapide des documents d'un produit
- **idx_active** : `is_active` - Filtrage des documents actifs
- **idx_deleted** : `deleted_at` - Audit des suppressions

### Modèle de données (ObjectModel)

Le fichier `classes/ProductInternalDocument.php` définit le modèle :

```php
public static $definition = [
    'table' => 'product_internal_document',
    'primary' => 'id_document',
    'fields' => [
        'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
        'original_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
        'title' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
        'stored_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
        'storage_path' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 500],
        'mime_type' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 100],
        'size' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
        'uploaded_by' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
        'uploaded_at' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
        'deleted_at' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
        'is_active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
    ],
];
```

**Note importante** : Les champs `uploaded_at` et `deleted_at` utilisent `TYPE_STRING` et non `TYPE_DATE` car PrestaShop 1.7 a des problèmes de validation avec TYPE_DATE pour les champs DATETIME.

---

## Sécurité

### 1. Stockage sécurisé

#### Emplacement des fichiers

Les documents sont stockés dans `/var/private_documents/products/{id_product}/` qui est **en dehors du DocumentRoot** d'Apache/Nginx.

**Structure type** :
```
/var/private_documents/
└── products/
    ├── 1/
    │   ├── a3f2c9d8-1e4b-4a7c-9f2e-3d8c1b5a6e7f.pdf
    │   └── f8d3a1c9-7b2e-4f6a-8d1c-9e3b5a7c2f1d.xlsx
    ├── 2/
    │   └── b2e4f7c8-3a1d-4c5e-9f2b-7d8e1a3c6b9f.docx
    └── 3/
        └── ...
```

**Permissions** :
```bash
mkdir -p /var/private_documents/products
chmod 755 /var/private_documents
chown www-data:www-data /var/private_documents
```

#### Pourquoi ce n'est PAS accessible via HTTP

1. **Hors DocumentRoot** : Le serveur web ne peut pas servir de fichiers en dehors de `/var/www/html/`
2. **Aucune règle de routage** : Pas de `.htaccess` ou configuration Nginx pour exposer ce répertoire
3. **Nommage UUID** : Même si quelqu'un devine le chemin, le nom de fichier est un UUID v4 impossible à deviner

**Test de sécurité** :
```bash
# Tentative d'accès direct (ÉCHOUERA)
curl http://localhost:8000/../../var/private_documents/products/1/fichier.pdf
# → Erreur 404 ou 403

# Tentative avec path traversal (ÉCHOUERA)
curl http://localhost:8000/modules/productinternaldocs/../../../../../../../var/private_documents/products/1/fichier.pdf
# → Erreur 404 ou 403
```

### 2. Nommage UUID v4

Chaque fichier téléversé reçoit un nom unique généré via UUID v4 :

```php
private static function generateUUID()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}
```

**Exemple** : `facture-fournisseur.pdf` devient `a3f2c9d8-1e4b-4a7c-9f2e-3d8c1b5a6e7f.pdf`

**Nombre de possibilités** : 2^122 ≈ 5.3 × 10^36 combinaisons (impossible à brute-force)

### 3. Validation stricte des fichiers

#### Validation du type MIME

Utilisation de `finfo` (FileInfo PHP) pour vérifier le **contenu réel** du fichier, pas seulement l'extension :

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mime_types = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
];

if (!in_array($mime_type, $allowed_mime_types)) {
    throw new Exception('Type de fichier non autorisé');
}
```

**Protection** : Un fichier `.exe` renommé en `.pdf` sera rejeté car le MIME réel ne correspond pas.

#### Limitation de taille

```php
$max_file_size = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $max_file_size) {
    throw new Exception('Fichier trop volumineux (max 10 MB)');
}
```

### 4. Authentification et autorisation

#### Contrôleur admin

Le `AdminProductInternalDocsController` hérite de `ModuleAdminController`, ce qui impose :

```php
public function __construct()
{
    parent::__construct();

    if (!$this->context->employee || !$this->context->employee->id) {
        Tools::redirect('index.php?controller=AdminLogin');
    }
}
```

**Résultat** : Seuls les employés authentifiés au back-office peuvent télécharger des documents.

#### AJAX endpoint

Le fichier `ajax.php` vérifie également l'authentification :

```php
// TODO: Ajouter une vraie vérification d'authentification en production
// Pour l'instant, on utilise l'employé par défaut du contexte
if (!$context->employee || !$context->employee->id) {
    if (isset($context->cookie->id_employee) && $context->cookie->id_employee) {
        $context->employee = new Employee((int)$context->cookie->id_employee);
    } else {
        // En dev local, utiliser l'ID 1 (admin par défaut)
        $context->employee = new Employee(1);
    }
}
```

**⚠️ Note de production** : Le fallback sur Employee(1) doit être retiré en production et remplacé par un rejet strict.

### 5. Soft delete

Les documents supprimés ne sont jamais effacés physiquement :

```php
public function softDelete()
{
    $this->deleted_at = date('Y-m-d H:i:s');
    $this->is_active = 0;
    return $this->update();
}
```

**Avantages** :
- Traçabilité complète
- Récupération possible en cas d'erreur
- Conformité RGPD (historique des actions)

### 6. Audit trail

Toutes les actions sont loggées via `PrestaShopLogger` :

```php
PrestaShopLogger::addLog(
    'ProductInternalDocs: ' . $action . ' - Document #' . $id_document,
    1,                                    // Severity: Info
    null,                                 // Error code
    'ProductInternalDocument',            // Object type
    $id_document,                         // Object ID
    true,                                 // Allow duplicate
    $id_employee                          // Employee ID
);
```

**Actions loggées** :
- `upload` : Téléversement d'un document
- `download` : Téléchargement d'un document
- `delete` : Suppression (soft) d'un document

**Consultation** : Back-office > Paramètres avancés > Logs

---

## Installation détaillée

### Prérequis système

- **PrestaShop** : 1.7.0.0 minimum (testé sur 1.7.8.7)
- **PHP** : 7.1+ (recommandé 7.4 ou 8.0)
- **MySQL** : 5.6+ ou MariaDB 10.1+
- **Extensions PHP** :
  - `fileinfo` (pour validation MIME)
  - `pdo_mysql`
  - `gd` ou `imagick` (pour miniatures futures)

### Installation en production

Cette procédure est à suivre pour installer le module sur un serveur PrestaShop en production.

1. **Télécharger le module**

```bash
git clone https://github.com/[VOTRE_REPO]/productinternaldocs.git
cd productinternaldocs
```

2. **Copier dans PrestaShop**

```bash
cp -r productinternaldocs /var/www/html/modules/
chown -R www-data:www-data /var/www/html/modules/productinternaldocs
```

3. **Créer le répertoire de stockage sécurisé**

```bash
mkdir -p /var/private_documents/products
chmod 755 /var/private_documents
chown www-data:www-data /var/private_documents
```

**Important** : Ce répertoire doit être **hors du DocumentRoot** d'Apache/Nginx pour garantir la sécurité.

4. **Installer via le back-office**

- Connexion au back-office PrestaShop
- Menu `Modules` > `Module Manager`
- Rechercher "Documents internes produits"
- Cliquer sur `Installer`

### Test en local avec Docker (optionnel)

Pour tester le module en environnement local avant de le déployer en production, un environnement Docker complet est fourni :

**Fichier `docker-compose.yml`** :

```yaml
version: '3.8'

services:
  prestashop:
    image: prestashop/prestashop:1.7.8.7
    container_name: prestashop_dev
    restart: unless-stopped
    ports:
      - "8000:80"
    environment:
      DB_SERVER: mysql
      DB_NAME: prestashop
      DB_USER: prestashop
      DB_PASSWD: prestashop
      PS_INSTALL_AUTO: 1
      PS_DOMAIN: localhost:8000
      PS_FOLDER_INSTALL: install
      PS_LANGUAGE: fr
      PS_COUNTRY: FR
      PS_ENABLE_SSL: 0
      ADMIN_MAIL: admin@prestashop.local
      ADMIN_PASSWD: Prestashop123
    volumes:
      - prestashop_data:/var/www/html
      - ./modules/productinternaldocs:/var/www/html/modules/productinternaldocs
      - ./private_documents:/var/private_documents
    networks:
      - prestashop_network

  mysql:
    image: mysql:8.0
    container_name: prestashop_mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: prestashop
      MYSQL_USER: prestashop
      MYSQL_PASSWORD: prestashop
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - prestashop_network

  phpmyadmin:
    image: phpmyadmin:latest
    container_name: prestashop_phpmyadmin
    restart: unless-stopped
    ports:
      - "8081:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD: root
    networks:
      - prestashop_network

volumes:
  mysql_data:
  prestashop_data:

networks:
  prestashop_network:
    driver: bridge
```

**Démarrage** :

```bash
docker-compose up -d
```

**Accès** :
- PrestaShop : http://localhost:8000
- phpMyAdmin : http://localhost:8081
- Identifiants : admin@prestashop.local / Prestashop123

**⚠️ Note** : Cet environnement Docker est uniquement destiné au test et développement local. Pour la production, suivez la procédure d'installation ci-dessus.

### Processus d'installation du module

Lorsque vous cliquez sur "Installer" dans le Module Manager, PrestaShop exécute :

1. **`install()` dans `productinternaldocs.php`**

```php
public function install()
{
    if (!parent::install() ||
        !$this->installSQL() ||
        !$this->installTab() ||
        !$this->registerHook('displayAdminProductsOptionsStepBottom') ||
        !$this->registerHook('actionProductUpdate')
    ) {
        return false;
    }

    return true;
}
```

2. **`installSQL()`** : Création de la table

```php
private function installSQL()
{
    $sql = file_get_contents(dirname(__FILE__) . '/sql/install.sql');
    $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
    return Db::getInstance()->execute($sql);
}
```

**Important** : Le remplacement `PREFIX_` → `_DB_PREFIX_` permet d'utiliser le préfixe de tables PrestaShop (généralement `ps_`).

3. **`installTab()`** : Création du contrôleur admin

```php
private function installTab()
{
    $tab = new Tab();
    $tab->active = 1;
    $tab->class_name = 'AdminProductInternalDocs';
    $tab->name = [];
    foreach (Language::getLanguages(true) as $lang) {
        $tab->name[$lang['id_lang']] = 'Product Internal Docs';
    }
    $tab->id_parent = -1; // Caché du menu
    $tab->module = $this->name;

    return $tab->add();
}
```

**Note** : `id_parent = -1` cache le contrôleur du menu latéral (il n'est accessible que via URL directe).

4. **Enregistrement des hooks**

- `displayAdminProductsOptionsStepBottom` : Affiche l'interface dans l'onglet "Options" de la fiche produit
- `actionProductUpdate` : Enregistré pour évolutions futures (notifications, etc.)

### Désinstallation

La désinstallation **préserve les données** par défaut :

```php
public function uninstall()
{
    if (!parent::uninstall() ||
        !$this->uninstallTab()
    ) {
        return false;
    }

    // On ne supprime PAS la table par défaut (conservation des données)
    // Pour supprimer : décommenter la ligne suivante
    // $this->uninstallSQL();

    return true;
}
```

**Pour supprimer complètement les données** : Décommenter `$this->uninstallSQL();`

---

## Utilisation

### Interface utilisateur

L'interface est accessible depuis la fiche produit PrestaShop :

1. **Accéder à un produit**
   - Catalogue > Produits
   - Sélectionner un produit existant

2. **Onglet Options**
   - Cliquer sur l'onglet "Options"
   - Descendre jusqu'à la section "Documents internes"

3. **Téléverser un document**
   - (Optionnel) Saisir un titre personnalisé
   - Cliquer sur "Parcourir" et sélectionner un fichier
   - Cliquer sur "Téléverser"
   - Le document apparaît dans la liste

4. **Télécharger un document**
   - Cliquer sur l'icône ⬇️
   - Le fichier est téléchargé sur votre ordinateur

5. **Supprimer un document**
   - Cliquer sur l'icône 🗑️
   - Confirmer la suppression
   - Le document disparaît de la liste (soft delete)

### Titre personnalisé vs Nom de fichier

**Comportement** :
- Si un **titre** est saisi : Le titre s'affiche dans la liste
- Si **pas de titre** : Le nom original du fichier s'affiche

**Exemple** :
```
Fichier : facture_fournisseur_mars_2024.pdf
Titre : Facture Mars 2024 - Fournisseur XYZ

→ Affichage : "Facture Mars 2024 - Fournisseur XYZ"
→ Téléchargement : fichier téléchargé avec nom original
```

### Filtrage des documents supprimés

L'interface JavaScript filtre automatiquement les documents supprimés :

```javascript
function displayDocuments(documents) {
    var activeDocuments = documents.filter(function(doc) {
        var isDeleted = doc.deleted_at !== null && doc.deleted_at !== '0000-00-00 00:00:00';
        return !isDeleted;
    });

    if (activeDocuments.length === 0) {
        $('#internal-documents-list').html('<p class="text-muted">Aucun document</p>');
        return;
    }
    // ...
}
```

---

## API et Endpoints

### Endpoint AJAX : `ajax.php`

#### 1. Récupérer les documents d'un produit

**Request** :
```http
GET /modules/productinternaldocs/ajax.php?action=getDocuments&id_product=123
```

**Response** :
```json
{
  "success": true,
  "documents": [
    {
      "id_document": "1",
      "id_product": "123",
      "original_name": "facture.pdf",
      "title": "Facture Mars 2024",
      "stored_name": "a3f2c9d8-1e4b-4a7c-9f2e-3d8c1b5a6e7f.pdf",
      "storage_path": "/var/private_documents/products/123/",
      "mime_type": "application/pdf",
      "size": "245760",
      "uploaded_by": "1",
      "uploaded_at": "2024-03-15 14:30:00",
      "deleted_at": null,
      "is_active": "1"
    }
  ]
}
```

#### 2. Téléverser un document

**Request** :
```http
POST /modules/productinternaldocs/ajax.php
Content-Type: multipart/form-data

action=upload
id_product=123
title=Mon document
document=[FILE_DATA]
```

**Response (succès)** :
```json
{
  "success": true,
  "document": {
    "id": 42,
    "name": "fichier.pdf",
    "size": 245760,
    "uploaded_at": "2024-03-15 14:30:00"
  }
}
```

**Response (erreur)** :
```json
{
  "success": false,
  "error": "Type de fichier non autorisé"
}
```

#### 3. Supprimer un document

**Request** :
```http
POST /modules/productinternaldocs/ajax.php

action=delete
id_document=42
```

**Response** :
```json
{
  "success": true
}
```

### Contrôleur Admin : `AdminProductInternalDocsController`

#### Téléchargement sécurisé

**URL** :
```
/admin-dev/index.php?controller=AdminProductInternalDocs&action=download&id_document=42
```

**Processus** :
1. Vérification authentification employé
2. Chargement document depuis BDD
3. Vérification existence fichier physique
4. Log de l'action
5. Envoi du fichier par streaming (chunks de 8KB)

**Headers HTTP** :
```http
Content-Type: application/pdf
Content-Disposition: attachment; filename="facture.pdf"
Content-Length: 245760
Cache-Control: private
Pragma: private
Expires: 0
```

---

## Développement

### Stack technique

- **Backend** : PHP 7.4+ (POO, Exceptions, ObjectModel PrestaShop)
- **Base de données** : MySQL 8.0 / MariaDB 10.1+
- **Template engine** : Smarty 3
- **Frontend** : jQuery 3 (inclus dans PrestaShop)
- **Développement local** : Docker Compose (optionnel)

### Hooks PrestaShop

#### `displayAdminProductsOptionsStepBottom`

Hook appelé dans l'onglet "Options" de la fiche produit :

```php
public function hookDisplayAdminProductsOptionsStepBottom($params)
{
    $id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;

    if (!$id_product) {
        return '';
    }

    $documents = ProductInternalDocument::getByProductId($id_product, true);

    $this->context->smarty->assign([
        'id_product' => $id_product,
        'module_dir' => $this->_path,
        'documents' => $documents,
        'link' => $this->context->link,
    ]);

    return $this->display(__FILE__, 'views/templates/admin/product_documents.tpl');
}
```

**Paramètres reçus** :
- `$params['id_product']` : ID du produit en cours d'édition

**Important** : Utiliser `$params['id_product']` et NON `Tools::getValue('id_product')` car ce dernier retourne 0 dans ce contexte.

#### `actionProductUpdate`

Hook enregistré mais non utilisé actuellement (réservé pour futures évolutions).

### Structure CSS

Le template inclut du CSS inline pour l'autonomie du module :

```css
.internal-docs-main-heading {
    font-size: 20px;
    font-weight: bold;
    padding: 15px;
}

.internal-docs-sub-heading {
    font-weight: bold;
    font-size: 14px;
}

.doc-action-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px;
    font-size: 18px;
    color: #666;
    transition: all 0.2s;
    text-decoration: none !important;
}

.doc-action-btn:hover {
    transform: scale(1.2);
}
```

### AJAX avec jQuery

Le template utilise jQuery (disponible nativement dans PrestaShop) :

```javascript
$(document).ready(function() {
    var idProduct = {$id_product};
    var moduleDir = '{$module_dir}';

    function loadDocuments() {
        $.ajax({
            url: moduleDir + 'ajax.php',
            type: 'GET',
            data: {
                action: 'getDocuments',
                id_product: idProduct
            },
            dataType: 'json',
            success: function(response) {
                displayDocuments(response.documents || []);
            }
        });
    }

    loadDocuments();
});
```

### Logs de développement

Pour déboguer, ajouter des logs dans le code :

```php
PrestaShopLogger::addLog(
    '[DEBUG] Variable value: ' . print_r($variable, true),
    3, // Warning level
    null,
    'ProductInternalDocs',
    0,
    true
);
```

Consulter : Back-office > Paramètres avancés > Logs

---

## Troubleshooting

### Problème : Le module n'apparaît pas dans la fiche produit

**Causes possibles** :
1. Hook mal enregistré
2. Cache PrestaShop

**Solutions** :
```bash
# Vider le cache PrestaShop
rm -rf var/cache/*

# Régénérer les assets
php bin/console prestashop:cache:clear

# Réinstaller le module
```

### Problème : Erreur "Produit invalide" (ID = 0)

**Cause** : Utilisation de `Tools::getValue('id_product')` au lieu de `$params['id_product']`

**Solution** : Vérifier le code du hook :
```php
// ❌ MAUVAIS
$id_product = (int)Tools::getValue('id_product');

// ✅ BON
$id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;
```

### Problème : "Erreur lors de l'enregistrement en base de données"

**Causes possibles** :
1. Validation PrestaShop échoue
2. Champ manquant ou invalide

**Debug** :
```php
$doc = new ProductInternalDocument();
// ... assignation des propriétés ...

if (!$doc->add()) {
    // Afficher les erreurs de validation
    PrestaShopLogger::addLog(
        'Validation errors: ' . print_r($doc->getErrors(), true),
        3
    );
}
```

### Problème : Documents affichés comme supprimés par défaut

**Cause** : La date `deleted_at` est `'0000-00-00 00:00:00'` au lieu de `NULL`

**Solution** : Filtrer correctement dans JavaScript :
```javascript
var isDeleted = doc.deleted_at !== null && doc.deleted_at !== '0000-00-00 00:00:00';
```

### Problème : Permission denied sur `/var/private_documents/`

**Solution** :
```bash
sudo chown -R www-data:www-data /var/private_documents
sudo chmod -R 755 /var/private_documents
```

### Problème : Module installé mais table non créée

**Cause** : Erreur dans `installSQL()`, le préfixe n'est pas remplacé

**Solution** :
```php
private function installSQL()
{
    $sql = file_get_contents(dirname(__FILE__) . '/sql/install.sql');
    $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql); // ← IMPORTANT
    return Db::getInstance()->execute($sql);
}
```

### Problème : Téléchargement ne fonctionne pas (404)

**Cause** : Lien AdminProductInternalDocs mal généré

**Vérification** :
```javascript
var adminController = '{$link->getAdminLink("AdminProductInternalDocs", true)|escape:"javascript"}';
console.log(adminController);
// Doit afficher : http://localhost:8000/admin-dev/index.php?controller=AdminProductInternalDocs&token=...
```

### Problème : Fichiers non accessibles en production

**Cause** : SELinux ou permissions restrictives

**Solution** :
```bash
# Désactiver SELinux temporairement (test)
sudo setenforce 0

# Ou configurer SELinux correctement
sudo chcon -R -t httpd_sys_rw_content_t /var/private_documents/
```

---

## Glossaire

| Terme | Définition |
|-------|------------|
| **Soft delete** | Suppression logique : le fichier reste en base avec un flag `deleted_at` mais n'est plus visible |
| **UUID v4** | Universal Unique Identifier version 4 : identifiant unique de 128 bits généré aléatoirement |
| **MIME type** | Multipurpose Internet Mail Extensions : type de fichier standardisé (ex: `application/pdf`) |
| **ObjectModel** | Classe abstraite PrestaShop pour la gestion de modèles de données en BDD |
| **Hook** | Point d'extension dans PrestaShop permettant aux modules de s'intégrer à des emplacements spécifiques |
| **DocumentRoot** | Répertoire racine du serveur web (généralement `/var/www/html`) accessible publiquement |
| **Streaming** | Lecture et envoi d'un fichier par petits blocs pour éviter de charger tout le fichier en mémoire |
| **Audit trail** | Journal de toutes les actions effectuées (qui, quoi, quand) à des fins de traçabilité |

---

## Support

Pour toute question technique non couverte par cette documentation :

- 📧 Email : [VOTRE_EMAIL]
- 🐛 Issues GitHub : [LIEN_GITHUB_ISSUES]
- 📚 Documentation PrestaShop : https://devdocs.prestashop.com/

---

**Documentation maintenue par** : Alexis Ladam (YRYCOM)
**Dernière mise à jour** : 2024
**Version du module** : 1.0.0
