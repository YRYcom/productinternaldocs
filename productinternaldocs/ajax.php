<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/classes/ProductInternalDocument.php';

$context = Context::getContext();

// Vérification d'authentification stricte
if (!$context->employee || !$context->employee->id) {
    if (isset($context->cookie->id_employee) && $context->cookie->id_employee) {
        $context->employee = new Employee((int)$context->cookie->id_employee);
    }

    // Si toujours pas d'employé authentifié, refuser l'accès
    if (!$context->employee || !$context->employee->id) {
        header('HTTP/1.1 403 Forbidden');
        die(json_encode([
            'success' => false,
            'error' => 'Accès non autorisé. Veuillez vous connecter au back-office.'
        ]));
    }
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

            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors de l\'upload');
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