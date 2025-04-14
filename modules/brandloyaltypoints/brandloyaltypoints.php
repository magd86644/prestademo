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
            `last_updated` DATETIME NOT NULL,
            CONSTRAINT `fk_customer_id` FOREIGN KEY (`id_customer`) REFERENCES `' . _DB_PREFIX_ . 'customer` (`id_customer`) ON DELETE CASCADE,
            CONSTRAINT `fk_manufacturer_id` FOREIGN KEY (`id_manufacturer`) REFERENCES `' . _DB_PREFIX_ . 'manufacturer` (`id_manufacturer`) ON DELETE CASCADE,
            UNIQUE KEY `customer_brand_unique` (`id_customer`, `id_manufacturer`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8';        

        if (!Db::getInstance()->execute($sql)) {
            $error = Db::getInstance()->getMsgError();  // Capture the error message
            PrestaShopLogger::addLog('Error creating loyalty points table: ' . $error, 3);
            return false;
        }

        if (
            !$this->registerHook('actionValidateOrder') ||
            !$this->registerHook('displayShoppingCartFooter') ||
            !$this->registerHook('actionFrontControllerSetMedia') ||
            !$this->registerHook('displayShoppingCart')
        ) {
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

        if (!$tab->add()) {
            PrestaShopLogger::addLog('Error creating AdminBrandLoyaltyPoints tab.', 3);
            return false;
        }
        return true;
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

    public function hookActionFrontControllerSetMedia($params)
    {
        if ($this->context->controller->php_self == 'cart') {
            $this->context->controller->registerStylesheet(
                'brandloyaltypoints-styles',
                'modules/' . $this->name . '/views/css/loyalty_points.css',
                [
                    'media' => 'all',
                    'priority' => 150,
                ]
            );
            $this->context->controller->registerJavascript(
                'brandloyaltypoints-apply',
                'modules/' . $this->name . '/views/js/loyalty_points_apply.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }
    }

    public function hookActionValidateOrder($params)
    {
        /** @var Order $order */
        PrestaShopLogger::addLog('action validate order', 1, null, 'Cart', 1, true);
        $order = $params['order'];
        $customerId = (int) $order->id_customer;

        if (!$customerId) {
            return;
        }

        $products = $order->getProducts();

        foreach ($products as $product) {
            $brandId = (int) $product['id_manufacturer'];
            $productTotal = (float) $product['total_price_tax_incl']; // points based on price incl tax

            if ($brandId > 0 && $productTotal > 0) {
                $points = (int) $productTotal; // 1€ = 1 point

                Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'loyalty_points` 
                (`id_customer`, `id_manufacturer`, `points`, `last_updated`) 
                VALUES (' . (int)$customerId . ', ' . (int)$brandId . ', ' . (int)$points . ', NOW())
                ON DUPLICATE KEY UPDATE points = points + ' . (int)$points . ', last_updated = NOW()'
                );
            }
        }
    }
    public function hookDisplayShoppingCartFooter($params)
    {
        $customerId = (int) $this->context->customer->id;

        if (!$customerId) {
            return '';
        }

        // Query: get loyalty points grouped by brand
        $pointsData = Db::getInstance()->executeS(
            'SELECT m.name AS manufacturer_name, SUM(lp.points) AS total_points
        FROM `' . _DB_PREFIX_ . 'loyalty_points` lp
        LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.id_manufacturer = lp.id_manufacturer
        WHERE lp.id_customer = ' . $customerId . '
        GROUP BY lp.id_manufacturer'
        );

        // Assign points to Smarty
        $this->context->smarty->assign([
            'loyaltyPointsApplyUrl' => $this->context->link->getModuleLink($this->name, 'applyloyaltypoints'),
            'loyaltyPointsRemoveUrl' => $this->context->link->getModuleLink($this->name, 'removeloyaltypoints'),
            'pointsData' => $pointsData,
        ]);


        $output = $this->display(
            _PS_MODULE_DIR_ . $this->name . '/' . $this->name . '.php',
            'views/templates/front/loyalty_points_display.tpl'
        );

        $output .= $this->display(
            _PS_MODULE_DIR_ . $this->name . '/' . $this->name . '.php',
            'views/templates/front/loyalty_points_apply_button.tpl'
        );

        return $output;
    }

    public function hookDisplayShoppingCart($params)
    {
        $this->context->smarty->assign([
            'my_custom_text' => 'Check my payment website'
        ]);

        // return $this->display(__FILE__, 'views/templates/hook/cart_inside.tpl');
        return $this->display(
            _PS_MODULE_DIR_ . $this->name . '/' . $this->name . '.php',
            'views/templates/hook/cart_inside.tpl'
        );
    }
}
