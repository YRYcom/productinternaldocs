<?php
// Initialisation du contexte PrestaShop
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/classes/ProductInternalDocument.php';

$context = Context::getContext();

// Chercher le cookie admin parmi tous les cookies PrestaShop
$employee = null;
foreach ($_COOKIE as $cookie_name => $cookie_value) {
    if (strpos($cookie_name, 'PrestaShop-') === 0) {
        // Créer un objet Cookie pour lire les données chiffrées
        $test_cookie = new Cookie($cookie_name, '');
        if (!empty($test_cookie->id_employee)) {
            $employee = new Employee((int)$test_cookie->id_employee);
            if (Validate::isLoadedObject($employee)) {
                $context->employee = $employee;
                break;
            }
        }
    }
}

// Vérification d'authentification stricte
if (!$context->employee || !Validate::isLoadedObject($context->employee)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error' => 'Accès non autorisé. Veuillez vous connecter au back-office.'
    ]));
}

// Vérification du token CSRF pour les actions POST
$action = Tools::getValue('action');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = Tools::getValue('token');
    $expected_token = Tools::getAdminTokenLite('AdminProductInternalDocs');

    if (!$token || $token !== $expected_token) {
        header('HTTP/1.1 403 Forbidden');
        die(json_encode([
            'success' => false,
            'error' => 'Token de sécurité invalide. Veuillez rafraîchir la page.'
        ]));
    }
}

try {
    switch ($action) {
        case 'getDocuments':
            $id_product = (int)Tools::getValue('id_product');
            if (!$id_product) {
                throw new Exception('Produit invalide');
            }

            $documents = ProductInternalDocument::getByProductId($id_product, true);

            die(json_encode([
                'success' => true,
                'documents' => $documents
            ]));
            break;

        case 'upload':
            $id_product = (int)Tools::getValue('id_product');
            $title = Tools::getValue('title');

            if (!$id_product) {
                throw new Exception('Produit invalide');
            }

            if (!isset($_FILES['document'])) {
                throw new Exception('Aucun fichier reçu');
            }

            if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP (upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
                    UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléversé',
                    UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléversé',
                    UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                    UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
                    UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléversement',
                ];
                $error_code = $_FILES['document']['error'];
                $error_msg = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Erreur inconnue (code: ' . $error_code . ')';
                throw new Exception($error_msg);
            }

            $document = ProductInternalDocument::uploadDocument(
                $id_product,
                $_FILES['document'],
                Context::getContext()->employee->id,
                $title
            );

            PrestaShopLogger::addLog(
                'ProductInternalDocs: Upload - Document #' . $document->id_document,
                1,
                null,
                'ProductInternalDocument',
                $document->id_document,
                true,
                Context::getContext()->employee->id
            );

            die(json_encode([
                'success' => true,
                'document' => [
                    'id' => $document->id_document,
                    'name' => $document->original_name,
                    'size' => $document->size,
                    'uploaded_at' => $document->uploaded_at,
                ]
            ]));
            break;

        case 'delete':
            $id_document = (int)Tools::getValue('id_document');

            if (!$id_document) {
                throw new Exception('Document invalide');
            }

            $document = new ProductInternalDocument($id_document);

            if (!Validate::isLoadedObject($document)) {
                throw new Exception('Document introuvable');
            }

            if (!$document->softDelete()) {
                throw new Exception('Erreur lors de la suppression');
            }

            PrestaShopLogger::addLog(
                'ProductInternalDocs: Delete - Document #' . $document->id_document,
                1,
                null,
                'ProductInternalDocument',
                $document->id_document,
                true,
                Context::getContext()->employee->id
            );

            die(json_encode(['success' => true]));
            break;

        default:
            throw new Exception('Action inconnue');
    }
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]));
}