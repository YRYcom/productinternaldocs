<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductInternalDocument extends ObjectModel
{
    public $id_document;
    public $id_product;
    public $original_name;
    public $title;
    public $stored_name;
    public $storage_path;
    public $mime_type;
    public $size;
    public $uploaded_by;
    public $uploaded_at;
    public $deleted_at;
    public $is_active;

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

    public static function getByProductId($id_product, $include_deleted = false)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'product_internal_document`
                WHERE `id_product` = ' . (int)$id_product;

        if (!$include_deleted) {
            $sql .= ' AND `is_active` = 1
                     AND (`deleted_at` IS NULL OR `deleted_at` = \'0000-00-00 00:00:00\')';
        }

        $sql .= ' ORDER BY `uploaded_at` DESC';

        return Db::getInstance()->executeS($sql);
    }

    public function softDelete()
    {
        $this->deleted_at = date('Y-m-d H:i:s');
        $this->is_active = 0;
        return $this->update();
    }

    public static function uploadDocument($id_product, $file, $id_employee, $title = null)
    {
        // Vérifications de sécurité
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

        $max_file_size = 10 * 1024 * 1024; // 10 MB

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types)) {
            throw new Exception('Type de fichier non autorisé');
        }

        if ($file['size'] > $max_file_size) {
            throw new Exception('Fichier trop volumineux (max 10 MB)');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored_name = self::generateUUID() . '.' . $extension;

        // Récupérer le chemin de stockage depuis la configuration
        $base_path = Configuration::get('PRODUCT_INTERNAL_DOCS_STORAGE_PATH');
        if (!$base_path) {
            $base_path = _PS_ROOT_DIR_ . '/var/private_documents/products/';
        }

        $storage_base = $base_path . (int)$id_product . '/';

        if (!file_exists($storage_base)) {
            if (!@mkdir($storage_base, 0755, true)) {
                throw new Exception('Impossible de créer le dossier de stockage. Vérifiez les permissions.');
            }
        }

        $full_path = $storage_base . $stored_name;

        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            throw new Exception('Erreur lors du déplacement du fichier');
        }

        $doc = new ProductInternalDocument();
        $doc->id_product = (int)$id_product;
        $doc->original_name = pSQL($file['name']);
        $doc->title = $title ? pSQL($title) : null;
        $doc->stored_name = pSQL($stored_name);
        $doc->storage_path = pSQL($storage_base);
        $doc->mime_type = pSQL($mime_type);
        $doc->size = (int)$file['size'];
        $doc->uploaded_by = (int)$id_employee;
        $doc->uploaded_at = date('Y-m-d H:i:s');
        $doc->is_active = 1;

        if ($doc->add()) {
            return $doc;
        }

        throw new Exception('Erreur lors de l\'enregistrement en base de données');
    }

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

    public function getFullPath()
    {
        return $this->storage_path . $this->stored_name;
    }

    public function fileExists()
    {
        return file_exists($this->getFullPath());
    }
}
