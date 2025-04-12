<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class BrandLoyaltyPoints extends Module
{


    public function __construct()
    {
        $this->name = 'brandloyaltypoints';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Majd CHEIKH HANNA';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Brand Loyalty Points');
        $this->description = $this->l('Adds a loyalty points tab under Dashboard.');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'loyalty_points` (
            `id_loyalty_points` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `id_customer` INT UNSIGNED NOT NULL,
            `id_manufacturer` INT UNSIGNED NOT NULL,  
            `points` INT NOT NULL DEFAULT 0,
            `last_updated` DATETIME NOT NULL
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8';
    
        if (!Db::getInstance()->execute($sql)) {
            $error = Db::getInstance()->getMsgError();  // Capture the error message
            PrestaShopLogger::addLog('Error creating loyalty points table: ' . $error, 3);
            return false;
        }

        // Create tab manually for the admin controller
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminBrandLoyaltyPoints'; // Unique name for your tab
        $tab->module = $this->name;

        // Find parent tab ID (AdminCatalog)
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');

        // Set name for all available languages
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Loyalty Points');
        }

        return $tab->add();
    }


    public function uninstall()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminBrandLoyaltyPoints');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
        return parent::uninstall();
    }

    public function getContent()
    {
        $this->context->smarty->assign([
            'your_variable' => 'Hello from AdminBrandLoyaltyPoints!',
        ]);
        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
}
