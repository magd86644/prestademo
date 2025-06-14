<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class BrandLoyaltyPoints extends Module
{

    private $_html = '';

    public function __construct()
    {
        $this->name = 'brandloyaltypoints';
        $this->tab = 'administration';
        $this->version = '1.0.3';
        $this->author = 'Majd CHEIKH HANNA';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Brand Loyalty Points');
        $this->description = $this->l('Apply loyalty points for different brands in the cart.');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        require_once _PS_MODULE_DIR_ . 'brandloyaltypoints/helpers/LoyaltyPointsHelper.php';
    }
    // TODO add a cron job to remove old records from the loyalty points table (older than 1 year)
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $sqlLoyaltyPoints = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'loyalty_points` (
            `id_loyalty_points` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `id_customer` INT UNSIGNED NOT NULL,
            `id_manufacturer` INT UNSIGNED NOT NULL,
            `points` INT NOT NULL DEFAULT 0,
            `last_updated` DATETIME NOT NULL,
            `id_cart_rule` INT UNSIGNED DEFAULT NULL,
            CONSTRAINT `fk_customer_id` FOREIGN KEY (`id_customer`) REFERENCES `' . _DB_PREFIX_ . 'customer` (`id_customer`) ON DELETE CASCADE,
            CONSTRAINT `fk_manufacturer_id` FOREIGN KEY (`id_manufacturer`) REFERENCES `' . _DB_PREFIX_ . 'manufacturer` (`id_manufacturer`) ON DELETE CASCADE,
            UNIQUE KEY `customer_brand_unique` (`id_customer`, `id_manufacturer`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        if (!Db::getInstance()->execute($sqlLoyaltyPoints)) {
            $error = Db::getInstance()->getMsgError();
            PrestaShopLogger::addLog('Error creating loyalty_points table: ' . $error, 3);
            return false;
        }

        // Create brand_loyalty_config table
        $sqlBrandLoyaltyConfig = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'brand_loyalty_config` (
            `id_manufacturer` INT UNSIGNED NOT NULL,
            `points_conversion_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.10,
            PRIMARY KEY (`id_manufacturer`),
            CONSTRAINT `fk_brand_loyalty_manufacturer`
                FOREIGN KEY (`id_manufacturer`) REFERENCES `' . _DB_PREFIX_ . 'manufacturer`(`id_manufacturer`)
                ON DELETE CASCADE
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        if (!Db::getInstance()->execute($sqlBrandLoyaltyConfig)) {
            $error = Db::getInstance()->getMsgError();
            PrestaShopLogger::addLog('Error creating brand_loyalty_config table: ' . $error, 3);
            return false;
        }

        // Register hooks
        if (
            !$this->registerHook('actionValidateOrder') ||
            !$this->registerHook('displayCheckoutSubtotalDetails') ||
            !$this->registerHook('actionFrontControllerSetMedia') ||
            !$this->registerHook('displayShoppingCart') ||
            !$this->registerHook('actionCartSave') ||
            !$this->registerHook('displayCustomerAccount') ||
            !$this->registerHook('actionOrderStatusPostUpdate')
        ) {
            return false;
        }

        // Create admin tab
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminBrandLoyaltyPoints'; // Unique name for the tab
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');

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
        if (Tools::isSubmit('submit_loyalty_points_config')) {
            // Handle form submission (save new conversion rates)
            $this->updatePointsConversionRate();
        }

        // Fetch manufacturers and current conversion rates
        $manufacturers = $this->getManufacturersWithConversionRates();

        // Assign variables to smarty
        $this->context->smarty->assign([
            'logo_path' => $this->_path . 'logo.png',  // Assuming your logo is located at 'views/img/logo.png'
            'manufacturers' => $manufacturers,
            'form_action' => $_SERVER['REQUEST_URI'], // Current URL to handle form submission
        ]);

        // Render the template
        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Get all manufacturers with their points conversion rates.
     *
     * @return array
     */
    public function getManufacturersWithConversionRates()
    {
        $result = Db::getInstance()->executeS(
            'SELECT m.id_manufacturer, m.name, IFNULL(b.points_conversion_rate, 0.1) as points_conversion_rate
         FROM `' . _DB_PREFIX_ . 'manufacturer` m
         LEFT JOIN `' . _DB_PREFIX_ . 'brand_loyalty_config` b
         ON m.id_manufacturer = b.id_manufacturer'
        );

        return $result;
    }

    /**
     * Update points conversion rate for manufacturers.
     */
    public function updatePointsConversionRate()
    {
        foreach (Tools::getValue('points_conversion_rate') as $manufacturerId => $conversionRate) {
            if (is_numeric($conversionRate) && $conversionRate >= 0) {
                // Update the conversion rate for the manufacturer
                Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'brand_loyalty_config` 
                (`id_manufacturer`, `points_conversion_rate`) 
                VALUES (' . (int)$manufacturerId . ', ' . (float)$conversionRate . ') 
                ON DUPLICATE KEY UPDATE 
                `points_conversion_rate` = ' . (float)$conversionRate
                );
            }
        }

        // Set a success message
        $this->_html .= '<div class="conf confirm">' . $this->l('Points conversion rates updated successfully!') . '</div>';
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

        $order = $params['order'];
        $customerId = (int) $order->id_customer;

        if (!$customerId) {
            return;
        }

        $usedCartRules = $order->getCartRules();
        $this->handleUsedPointsDeduction($usedCartRules, $customerId);
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
                $pointsUsed = floor($discountAmount);
                if ($pointsUsed > $pointsData['points']) {
                    PrestaShopLogger::addLog(
                        "Loyalty program: Not enough points for deduction. Customer ID: $customerId, Manufacturer: {$pointsData['id_manufacturer']}",
                        3
                    );
                    return;
                }
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
            $conversionRate = LoyaltyPointsHelper::getConversionRateByManufacturer($brandId);
            if ($conversionRate <= 0) {
                continue;
            }
            // Calculate proportional discount
            $productDiscountShare = ($productTotal / $totalOrderProductPrice) * $totalLoyaltyDiscount;
            $paidAmount = $productTotal - $productDiscountShare;
            if ($paidAmount <= 0) {
                continue;
            }

            // Calculate points based on the brand's conversion rate
            $earnedPoints = (int) floor($paidAmount * $conversionRate);

            if ($earnedPoints <= 0) {
                continue; // no points to grant
            }

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
    private function renderLoyaltyPointsBlock($params)
    {
        $customerId = (int) $this->context->customer->id;
        $cart = $this->context->cart;
        if (!$customerId || !$cart->id) {
            return '';
        }

        // Query: get loyalty points grouped by brand
        $rawPointsData = Db::getInstance()->executeS(
            'SELECT m.id_manufacturer, m.name AS manufacturer_name, SUM(lp.points) AS total_points
         FROM `' . _DB_PREFIX_ . 'loyalty_points` lp
         LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.id_manufacturer = lp.id_manufacturer
         WHERE lp.id_customer = ' . $customerId . '
         GROUP BY lp.id_manufacturer'
        );

        // Get brand totals in the current cart
        $brandsInCart = [];
        foreach ($cart->getProducts() as $product) {
            $mid = (int) $product['id_manufacturer'];
            if (!isset($brandsInCart[$mid])) {
                $brandsInCart[$mid] = 0;
            }
            $brandsInCart[$mid] += $product['total_wt'];
        }

        // Detect which loyalty rules are already applied
        $appliedManufacturerIds = [];
        foreach ($cart->getCartRules() as $rule) {
            if (strpos($rule['name'], 'Loyalty Discount - ') === 0) {
                foreach ($rawPointsData as $entry) {
                    if (strpos($rule['name'], 'Loyalty Discount - ' . $entry['manufacturer_name']) === 0) {
                        $appliedManufacturerIds[] = (int) $entry['id_manufacturer'];
                    }
                }
            }
        }

        // Enhance points data
        foreach ($rawPointsData as &$entry) {
            $mid = (int) $entry['id_manufacturer'];
            $points = (int) $entry['total_points'];
            $canApply = false;
            $isApplied = in_array($mid, $appliedManufacturerIds);

            if (isset($brandsInCart[$mid]) && $points > 0 && !$isApplied) {
                $conversionRate = LoyaltyPointsHelper::getConversionRateByManufacturer($mid);
                $maxDiscount = $points * $conversionRate;
                $brandTotal = $brandsInCart[$mid];

                if ($conversionRate > 0 && $maxDiscount >= 0.01 && $brandTotal > 0) {
                    $canApply = true;
                }
            }

            $entry['can_apply'] = $canApply;
            $entry['is_applied'] = $isApplied;
        }
        unset($entry);

        // Show "Reset" button if any loyalty rule applied
        $hasAppliedLoyalty = !empty($appliedManufacturerIds);
        $brandsInCart = LoyaltyPointsHelper::getBrandsInCart($cart);

        $this->context->smarty->assign([
            'loyaltyPointsApplyUrl' => $this->context->link->getModuleLink($this->name, 'applyloyaltypoints'),
            'loyaltyPointsRemoveUrl' => $this->context->link->getModuleLink($this->name, 'removeloyaltypoints'),
            'pointsData' => $rawPointsData,
            'hasAppliedLoyalty' => $hasAppliedLoyalty,
        ]);

        return $this->display(
            _PS_MODULE_DIR_ . $this->name . '/' . $this->name . '.php',
            'views/templates/front/loyalty_points_display.tpl'
        );
    }

    public function hookDisplayCheckoutSubtotalDetails($params)
    {
        if ($this->context->controller->php_self != 'cart') {
            return '';
        }
        return $this->renderLoyaltyPointsBlock($params);
    }

    public function hookActionCartSave($params)
    {   
        if ((int) $this->context->cookie->id_cart) {

            if (!isset($cart)) {
                $cart = new Cart($this->context->cookie->id_cart);
            }

            if (Validate::isLoadedObject($cart) && $cart->orderExists()) {
                PrestaShopLogger::addLog('BeeCreative: - Cart cannot be loaded or an order has already been placed using this cart', 1, null, 'Cart', (int) $this->context->cookie->id_cart, true);
            } else {
                // Sync loyalty discounts with the cart
                LoyaltyPointsHelper::syncLoyaltyDiscountsWithCart($cart);
            }
        }
    }

    // Here we render the loyalty points block in the customer account page
    public function hookDisplayCustomerAccount($params)
    {
        $context = Context::getContext();
        $id_customer = $context->customer->id;
        $this->context->smarty->assign([
            'loyalty_url' => $this->context->link->getModuleLink($this->name, 'accountloyaltypoints'),
            'id_customer' => $id_customer,
        ]);

        return $this->display(__FILE__, 'views/templates/front/customerAccount.tpl');
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        if (!isset($params['id_order']) || !$params['newOrderStatus']) {
            return;
        }

        $order = new Order((int) $params['id_order']);
        $newStatus = $params['newOrderStatus'];

        // Check if the new status is "Delivered"
        if ((int) $newStatus->id === (int) Configuration::get('PS_OS_DELIVERED')) {
            $customerId = (int) $order->id_customer;
            $usedCartRules = $order->getCartRules();

            $this->grantLoyaltyPointsBasedOnPaidAmount($order, $usedCartRules, $customerId);
        }
    }
}
