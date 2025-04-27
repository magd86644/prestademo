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
    // TODO when removing the cart rule, remove the record rule id  from the loyalty points table
    // TODO add a cron job to remove old records from the loyalty points table (older than 1 year)
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
            `id_cart_rule` INT UNSIGNED DEFAULT NULL,
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
        PrestaShopLogger::addLog('Action Validate Order Hook Triggered', 1, null, 'Cart', 1, true);

        $order = $params['order'];
        $customerId = (int) $order->id_customer;

        if (!$customerId) {
            return;
        }

        $usedCartRules = $order->getCartRules();
        PrestaShopLogger::addLog('Used Cart Rules: ' . json_encode($usedCartRules), 1, null, 'Cart', 1, true);
        $this->handleUsedPointsDeduction($usedCartRules, $customerId);
        $this->grantLoyaltyPointsBasedOnPaidAmount($order, $usedCartRules, $customerId);
    }

    /**
     * Deducts loyalty points if customer used them in this order.
     */
    private function handleUsedPointsDeduction(array $usedCartRules, int $customerId): void
    {
        foreach ($usedCartRules as $cartRule) {
            $cartRuleId = (int) $cartRule['id_cart_rule'];

            // Check if cart rule is a loyalty points usage rule
            $pointsData = Db::getInstance()->getRow(
                '
            SELECT id_loyalty_points, points, id_manufacturer 
            FROM `' . _DB_PREFIX_ . 'loyalty_points`
            WHERE id_cart_rule = ' . $cartRuleId . '
            AND id_customer = ' . $customerId
            );

            if ($pointsData) {
                $discountAmount = (float) $cartRule['value'];
                $pointsUsed = floor($discountAmount / 0.1);

                if ($pointsUsed > $pointsData['points']) {
                    PrestaShopLogger::addLog(
                        "Loyalty program: Not enough points for deduction. Customer ID: $customerId, Manufacturer: {$pointsData['id_manufacturer']}",
                        3
                    );
                    return;
                }
                PrestaShopLogger::addLog(
                    "Loyalty program: Deducting points. Customer ID: $customerId, Points Used: $pointsUsed, Manufacturer: {$pointsData['id_manufacturer']}",
                    1);
                Db::getInstance()->execute(
                    '
                UPDATE `' . _DB_PREFIX_ . 'loyalty_points`
                SET points = GREATEST(0, points - ' . (int) $pointsUsed . '),
                    id_cart_rule = NULL,
                    last_updated = NOW()
                WHERE id_loyalty_points = ' . (int) $pointsData['id_loyalty_points']
                );
            }
        }
    }

    /**
     * Grants loyalty points based on the real paid price after applying discounts.
     */
    private function grantLoyaltyPointsBasedOnPaidAmount(Order $order, array $usedCartRules, int $customerId): void
    {
        $products = $order->getProducts();
        $totalOrderProductPrice = array_sum(array_column($products, 'total_price_tax_incl'));
        // Sum the total loyalty points discount used in the order
        $totalLoyaltyDiscount = $this->calculateTotalLoyaltyDiscount($usedCartRules);
        PrestaShopLogger::addLog(
            "Loyalty program: Total Loyalty Discount: $totalLoyaltyDiscount, Total Order Product Price: $totalOrderProductPrice",
            1
        );
        // Avoid division by zero
        if ($totalOrderProductPrice <= 0) {
            return;
        }
        foreach ($products as $product) {
            $brandId = (int) $product['id_manufacturer'];
            $productTotal = (float) $product['total_price_tax_incl'];
            if ($brandId <= 0 || $productTotal <= 0) {
                continue;
            }
            // Calculate the proportional paid amount after loyalty discount
            $productDiscountShare = ($productTotal / $totalOrderProductPrice) * $totalLoyaltyDiscount;
            $paidAmount = $productTotal - $productDiscountShare;
            if ($paidAmount <= 0) {
                continue;
            }

            $earnedPoints = (int) $paidAmount; // 1€ = 1 point

            Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'loyalty_points`
            (`id_customer`, `id_manufacturer`, `points`, `last_updated`) 
            VALUES (' . (int) $customerId . ', ' . (int) $brandId . ', ' . (int) $earnedPoints . ', NOW())
            ON DUPLICATE KEY UPDATE 
                points = points + ' . (int) $earnedPoints . ',
                last_updated = NOW()
        ');
        }
    }

    /**
     * Calculate the total loyalty-points-based discount used in the order.
     */
    private function calculateTotalLoyaltyDiscount(array $usedCartRules): float
    {
        $totalDiscount = 0.0;
        foreach ($usedCartRules as $cartRule) {
            if (strpos($cartRule['name'], 'Loyalty Discount') !== false) {
                $totalDiscount += (float) $cartRule['value'];
            }
        }

        return $totalDiscount;
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
