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
        $this->version = '1.0.7';
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
    // TODO add a cron jon to remove expired loyalty points (older than 6 months)
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
            `expiration_date` DATE NULL,
            CONSTRAINT `fk_customer_id` FOREIGN KEY (`id_customer`) REFERENCES `' . _DB_PREFIX_ . 'customer` (`id_customer`) ON DELETE CASCADE,
            CONSTRAINT `fk_manufacturer_id` FOREIGN KEY (`id_manufacturer`) REFERENCES `' . _DB_PREFIX_ . 'manufacturer` (`id_manufacturer`) ON DELETE CASCADE,
            UNIQUE KEY `customer_brand_unique` (`id_customer`, `id_manufacturer`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        // Add the expiration_date column if it doesn't exist
        $check = Db::getInstance()->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "loyalty_points` LIKE 'expiration_date'");
        if (empty($check)) {
            Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "loyalty_points` ADD COLUMN `expiration_date` DATE NULL");
        }

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
        // Create loyalty points history table, This table will track points granted per order
        $sqlLoyaltyHistory = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'loyalty_points_history` (
            `id_loyalty_points_history` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `id_order` INT UNSIGNED NOT NULL,
            `id_customer` INT UNSIGNED NOT NULL,
            `points_granted` INT NOT NULL DEFAULT 0,
            `date_added` DATETIME NOT NULL,
            UNIQUE KEY `order_unique` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        if (!Db::getInstance()->execute($sqlLoyaltyHistory)) {
            $error = Db::getInstance()->getMsgError();
            PrestaShopLogger::addLog('Error creating loyalty_points_history table: ' . $error, 3);
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

        $manufacturers = $this->getManufacturersWithConversionRates();
        $this->context->smarty->assign([
            'logo_path' => $this->_path . 'logo.png',
            'manufacturers' => $manufacturers,
            'form_action' => $_SERVER['REQUEST_URI'],
        ]);
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

        $routeName = Tools::getValue('controller');

        if ($routeName === 'accountloyaltypoints') {
            $this->context->controller->registerStylesheet(
                'brandloyaltypoints-account-css',
                'modules/' . $this->name . '/views/css/account_loyalty.css',
                [
                    'media' => 'all',
                    'priority' => 150,
                ]
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
                PrestaShopLogger::addLog(
                    "Loyalty program: No conversion rate set for brand ID: $brandId in order ID: {$order->id}",
                    3
                );
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


            Db::getInstance()->insert('loyalty_points_history', [
                'id_order' => (int) $order->id,
                'id_customer' => (int) $customerId,
                'points_granted' => (int) $earnedPoints,
                'date_added' => date('Y-m-d H:i:s')
            ]);
            // update expiration date to 6 months from now for all brands for this customer
            $expirationDate = (new DateTime())->modify('+6 months')->format('Y-m-d');
            Db::getInstance()->update('loyalty_points', [
                'expiration_date' => $expirationDate
            ], 'id_customer = ' . (int) $customerId . ' AND id_manufacturer = ' . (int) $brandId);
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
            (new DbQuery())
                ->select('m.id_manufacturer, m.name AS manufacturer_name, SUM(lp.points) AS total_points')
                ->from('loyalty_points', 'lp')
                ->leftJoin('manufacturer', 'm', 'm.id_manufacturer = lp.id_manufacturer')
                ->where('lp.id_customer = ' . (int) $customerId)
                ->where('(lp.expiration_date IS NULL OR lp.expiration_date >= CURDATE())')
                ->groupBy('lp.id_manufacturer')
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
        $newStatus = $params['newOrderStatus'];
        $orderId = (int) $params['id_order'];
        // Check if the new status is "Delivered"
        if ((int) $newStatus->id === (int) Configuration::get('PS_OS_DELIVERED')) {
            $alreadyGranted = Db::getInstance()->getValue(
                '
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'loyalty_points_history`
            WHERE id_order = ' . $orderId
            );

            if ($alreadyGranted) {
                return; // Points already granted for this order
            }
            $order = new Order($orderId);
            $customerId = (int) $order->id_customer;
            $usedCartRules = $order->getCartRules();

            $this->grantLoyaltyPointsBasedOnPaidAmount($order, $usedCartRules, $customerId);
        }
    }

    public function sendLoyaltyExpiryReminders()
    {
        PrestaShopLogger::addLog('Loyalty points expiration reminder cron job started', 1, null, 'LoyaltyPoints', 0, true);
        $now = date('Y-m-d');
        $threeMonthsLater = date('Y-m-d', strtotime('+3 months'));
        $oneMonthLater = date('Y-m-d', strtotime('+1 month'));

        $sql = 'SELECT lp.id_customer, lp.points, lp.expiration_date, m.name AS brand_name
            FROM ' . _DB_PREFIX_ . 'loyalty_points lp
            INNER JOIN ' . _DB_PREFIX_ . 'manufacturer m ON m.id_manufacturer = lp.id_manufacturer
            WHERE lp.expiration_date IN ("' . pSQL($threeMonthsLater) . '", "' . pSQL($oneMonthLater) . '") 
            AND lp.points > 0';
        // log the query
        PrestaShopLogger::addLog('Loyalty points expiration reminder query: ' . $sql, 1, null, 'LoyaltyPoints', 0, true);

        $expiringPoints = Db::getInstance()->executeS($sql);
        if (!$expiringPoints) {
            PrestaShopLogger::addLog('No loyalty points expiring soon found', 1, null, 'LoyaltyPoints', 0, true);
            return; // No points expiring soon
        }

        foreach ($expiringPoints as $row) {
            $customer = new Customer((int)$row['id_customer']);
            $templateVars = [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{points}' => (int)$row['points'],
                '{brand}' => $row['brand_name'],
                '{expiration_date}' => date('d/m/Y', strtotime($row['expiration_date'])),
            ];


            // check if mail not send add log
            if (!Mail::Send(
                (int)Language::getIdByIso('fr'),
                'loyalty_reminder',
                'Vos miles ' . $row['brand_name'] . ' expirent bientôt',
                $templateVars,
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . 'brandloyaltypoints/mails/',
                false,
                (int) $customer->id_shop
            )) {
                PrestaShopLogger::addLog('Failed to send loyalty points expiration reminder email to customer ID: ' . $row['id_customer'], 3, null, 'LoyaltyPoints', 0, true);
            } else {
                PrestaShopLogger::addLog('Loyalty points expiration reminder email sent to customer ID: ' . $row['id_customer'], 1, null, 'LoyaltyPoints', 0, true);
            }
        }
    }
}
