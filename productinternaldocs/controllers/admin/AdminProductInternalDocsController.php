<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'productinternaldocs/classes/ProductInternalDocument.php';

class AdminProductInternalDocsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function processDownload()
    {
        $id_document = (int)Tools::getValue('id_document');

        if (!$id_document) {
            die('Document invalide');
        }

        $document = new ProductInternalDocument($id_document);

        if (!Validate::isLoadedObject($document)) {
            die('Document introuvable');
        }

        // Vérifier les droits (TODO: implémenter les permissions personnalisées)
        if (!$this->context->employee->id) {
            die('Accès non autorisé');
        }

        // Vérifier que le fichier existe
        if (!$document->fileExists()) {
            die('Fichier introuvable sur le serveur');
        }

        $file_path = $document->getFullPath();

        // Logger le téléchargement (audit trail)
        $this->logAction('download', $document->id_document, $this->context->employee->id);

        // Échapper correctement le nom de fichier pour le header Content-Disposition
        $filename = $document->original_name;
        $filename_ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        $filename_encoded = rawurlencode($filename);

        header('Content-Type: ' . $document->mime_type);
        header('Content-Disposition: attachment; filename="' . $filename_ascii . '"; filename*=UTF-8\'\'' . $filename_encoded);
        header('Content-Length: ' . $document->size);
        header('Cache-Control: private');
        header('Pragma: private');
        header('Expires: 0');

        $file = fopen($file_path, 'rb');
        while (!feof($file)) {
            echo fread($file, 8192);
            flush();
        }
        fclose($file);
        exit;
    }

    public function processUpload()
    {
        $id_product = (int)Tools::getValue('id_product');
        $title = Tools::getValue('title');

        if (!$id_product) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Produit invalide']));
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']));
        }

        try {
            $document = ProductInternalDocument::uploadDocument(
                $id_product,
                $_FILES['document'],
                $this->context->employee->id,
                $title
            );

            $this->logAction('upload', $document->id_document, $this->context->employee->id);

            $this->ajaxDie(json_encode([
                'success' => true,
                'document' => [
                    'id' => $document->id_document,
                    'name' => $document->original_name,
                    'size' => $document->size,
                    'uploaded_at' => $document->uploaded_at,
                ]
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    public function processDelete()
    {
        $id_document = (int)Tools::getValue('id_document');

        if (!$id_document) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Document invalide']));
        }

        $document = new ProductInternalDocument($id_document);

        if (!Validate::isLoadedObject($document)) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Document introuvable']));
        }

        if ($document->softDelete()) {
            $this->logAction('delete', $document->id_document, $this->context->employee->id);

            $this->ajaxDie(json_encode(['success' => true]));
        }

        $this->ajaxDie(json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']));
    }

    private function logAction($action, $id_document, $id_employee, $extra_data = [])
    {
        PrestaShopLogger::addLog(
            'ProductInternalDocs: ' . $action . ' - Document #' . $id_document,
            1,
            null,
            'ProductInternalDocument',
            $id_document,
            true,
            $id_employee
        );
    }

    public function processGetDocuments()
    {
        $id_product = (int)Tools::getValue('id_product');

        if (!$id_product) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Produit invalide']));
        }

        $documents = ProductInternalDocument::getByProductId($id_product, true);

        $this->ajaxDie(json_encode([
            'success' => true,
            'documents' => $documents
        ]));
    }

    public function postProcess()
    {
        $action = Tools::getValue('action');

        switch ($action) {
            case 'download':
                $this->processDownload();
                break;
            case 'upload':
                $this->processUpload();
                break;
            case 'delete':
                $this->processDelete();
                break;
            case 'getDocuments':
                $this->processGetDocuments();
                break;
        }

        parent::postProcess();
    }
}