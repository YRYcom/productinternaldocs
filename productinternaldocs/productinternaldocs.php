<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/ProductInternalDocument.php';

class ProductInternalDocs extends Module
{
    public function __construct()
    {
        $this->name = 'productinternaldocs';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Votre Nom';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Documents internes produits');
        $this->description = $this->l('Gestion de documents internes pour les produits (back office uniquement)');
    }

    public function install()
    {
        // Définir le chemin de stockage par défaut (dans le dossier var de PrestaShop)
        $default_storage_path = _PS_ROOT_DIR_ . '/var/private_documents/products/';

        if (!parent::install() ||
            !$this->installSQL() ||
            !$this->installTab() ||
            !Configuration::updateValue('PRODUCT_INTERNAL_DOCS_STORAGE_PATH', $default_storage_path) ||
            !$this->registerHook('displayAdminProductsOptionsStepBottom') ||
            !$this->registerHook('actionProductUpdate') ||
            !$this->registerHook('actionProductDelete')
        ) {
            return false;
        }

        // Créer le dossier de stockage s'il n'existe pas
        if (!file_exists($default_storage_path)) {
            @mkdir($default_storage_path, 0755, true);
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !$this->uninstallTab() ||
            !Configuration::deleteByName('PRODUCT_INTERNAL_DOCS_STORAGE_PATH')
        ) {
            return false;
        }

        // On ne supprime PAS la table par défaut (conservation des données)
        // Pour supprimer : décommenter la ligne suivante
        // $this->uninstallSQL();

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitProductInternalDocsConfig')) {
            $storage_path = Tools::getValue('PRODUCT_INTERNAL_DOCS_STORAGE_PATH');

            // Valider le chemin
            if (empty($storage_path)) {
                $output .= $this->displayError($this->l('Le chemin de stockage ne peut pas être vide.'));
            } elseif (!is_dir($storage_path) && !@mkdir($storage_path, 0755, true)) {
                $output .= $this->displayError($this->l('Le chemin de stockage n\'existe pas et ne peut pas être créé. Vérifiez les permissions.'));
            } elseif (!is_writable($storage_path)) {
                $output .= $this->displayError($this->l('Le chemin de stockage n\'est pas accessible en écriture.'));
            } else {
                // S'assurer que le chemin se termine par /
                if (substr($storage_path, -1) !== '/') {
                    $storage_path .= '/';
                }
                Configuration::updateValue('PRODUCT_INTERNAL_DOCS_STORAGE_PATH', $storage_path);
                $output .= $this->displayConfirmation($this->l('Configuration sauvegardée.'));
            }
        }

        return $output . $this->renderConfigForm();
    }

    protected function renderConfigForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Chemin de stockage des documents'),
                        'name' => 'PRODUCT_INTERNAL_DOCS_STORAGE_PATH',
                        'desc' => $this->l('Chemin absolu vers le dossier de stockage. Ce dossier doit être hors du webroot et accessible en écriture.'),
                        'required' => true,
                        'size' => 100,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Sauvegarder'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProductInternalDocsConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                'PRODUCT_INTERNAL_DOCS_STORAGE_PATH' => Configuration::get('PRODUCT_INTERNAL_DOCS_STORAGE_PATH'),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public static function getStoragePath()
    {
        return Configuration::get('PRODUCT_INTERNAL_DOCS_STORAGE_PATH') ?: _PS_ROOT_DIR_ . '/var/private_documents/products/';
    }

    public function hookActionProductDelete($params)
    {
        $id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;

        if (!$id_product) {
            return;
        }

        // Soft delete tous les documents du produit
        $documents = ProductInternalDocument::getByProductId($id_product, false);

        if ($documents) {
            foreach ($documents as $doc) {
                $document = new ProductInternalDocument((int)$doc['id_document']);
                if (Validate::isLoadedObject($document)) {
                    $document->softDelete();

                    PrestaShopLogger::addLog(
                        'ProductInternalDocs: Auto-delete on product deletion - Document #' . $document->id_document,
                        1,
                        null,
                        'ProductInternalDocument',
                        $document->id_document,
                        true
                    );
                }
            }
        }
    }

    private function installSQL()
    {
        $sql = file_get_contents(dirname(__FILE__) . '/sql/install.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        return Db::getInstance()->execute($sql);
    }

    private function uninstallSQL()
    {
        $sql = file_get_contents(dirname(__FILE__) . '/sql/uninstall.sql');
        return Db::getInstance()->execute($sql);
    }

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

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminProductInternalDocs');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }

        return true;
    }

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
}
