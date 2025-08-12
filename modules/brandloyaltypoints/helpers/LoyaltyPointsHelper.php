<?php

class LoyaltyPointsHelper
{


    /**
     * Get the total brands in the cart (by manufacturer).
     *
     * @param Cart $cart
     * @return array
     */
    public static function getBrandsInCart(Cart $cart)
    {
        $brandsInCart = [];
        foreach ($cart->getProducts() as $product) {
            $manufacturerId = $product['id_manufacturer'];
            if (!isset($brandsInCart[$manufacturerId])) {
                $brandsInCart[$manufacturerId] = [
                    'total_price_tax_incl' => 0,
                ];
            }
            $brandsInCart[$manufacturerId]['total_price_tax_incl'] += $product['total_wt']; // includes tax
        }

        return $brandsInCart;
    }

    /**
     * Generates the loyalty discount cart rule name for a given brand.
     *
     * @param int $manufacturerId
     * @return string
     */
    public static function getLoyaltyCartRuleName($manufacturerId)
    {
        $brandName = Manufacturer::getNameById((int)$manufacturerId);
        return 'Loyalty Discount - ' . $brandName;
    }

    public static function createBrandLoyaltyCartRule($customerId, $manufacturerId, $discount)
    {
        $cartRule = new CartRule();
        $cartRule->description = 'Loyalty points discount for brand #' . $manufacturerId;
        $cartRule->code = 'LOYALTY_BRAND_' . $manufacturerId . '_' . $customerId;
        $cartRule->id_customer = $customerId;
        $cartRule->date_from = date('Y-m-d H:i:s');
        $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->minimum_amount = 0;
        $cartRule->reduction_amount = $discount;
        $cartRule->reduction_tax = true;
        $cartRule->active = 1;
        $cartRule->free_shipping = false;
        $cartRule->product_restriction = 1;

        foreach (Language::getLanguages(true) as $lang) {
            $cartRule->name[$lang['id_lang']] = self::getLoyaltyCartRuleName($manufacturerId);
        }

        return $cartRule->add() ? $cartRule : null;
    }

    public static function isLoyaltyRuleApplied($cart, $manufacturerId)
    {

        $expectedRuleName = self::getLoyaltyCartRuleName($manufacturerId);
        foreach ($cart->getCartRules() as $rule) {
            if ($rule['name'] === $expectedRuleName) {
                return true;
            }
        }
        return false;
    }
    public static function attachManufacturerConditionToCartRule($cartRuleId, $manufacturerId)
    {
        try {
            if (!$cartRuleId || !$manufacturerId) {
                return false;
            }

            $db = Db::getInstance();

            $insertGroup = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cart_rule_product_rule_group` 
             (id_cart_rule, quantity) 
             VALUES (' . (int)$cartRuleId . ', 1)'
            );

            if (!$insertGroup) {
                return false;
            }

            $idProductRuleGroup = $db->Insert_ID();

            $insertRule = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cart_rule_product_rule` 
             (id_product_rule_group, type) 
             VALUES (' . (int)$idProductRuleGroup . ', "manufacturers")'
            );

            if (!$insertRule) {
                return false;
            }

            $idProductRule = $db->Insert_ID();

            // Link Manufacturer to the Product Rule
            $insertValue = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cart_rule_product_rule_value` 
             (id_product_rule, id_item) 
             VALUES (' . (int)$idProductRule . ', ' . (int)$manufacturerId . ')'
            );

            if (!$insertValue) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the points conversion rate for a given manufacturer
     *
     * @param int $manufacturerId
     * @return float
     */
    public static function getConversionRateByManufacturer($manufacturerId)
    {
        $sql = 'SELECT points_conversion_rate FROM ' . _DB_PREFIX_ . 'brand_loyalty_config WHERE id_manufacturer = ' . (int) $manufacturerId;
        return (float) Db::getInstance()->getValue($sql);
    }

    public static function canSyncLoyaltyDiscounts()
    {
        $context = Context::getContext();

        if (empty($context->cookie->id_cart)) {
            return false;
        }

        $cartId = (int)$context->cookie->id_cart;
        $cart = new Cart($cartId);
        if (Validate::isLoadedObject($cart) && $cart->orderExists()) {
            // Cart is locked due to an order, do NOT sync
            return false;
        }
        return true;
    }
    public static function syncLoyaltyDiscountsWithCart(Cart $cart)
    {
        if (!self::canSyncLoyaltyDiscounts()) {
            return;
        }
        $customerId = (int)$cart->id_customer;
        if (!$customerId) {
            return;
        }

        $brandsInCart = self::getBrandsInCart($cart);

        foreach ($brandsInCart as $manufacturerId => $brandData) {
            $brandTotal = $brandData['total_price_tax_incl'];

            // Fetch loyalty points and applied cart rule (if any)
            $row = Db::getInstance()->getRow(
                '
            SELECT points, id_cart_rule FROM `' . _DB_PREFIX_ . 'loyalty_points`
            WHERE id_customer = ' . (int)$customerId . '
            AND id_manufacturer = ' . (int)$manufacturerId
            );

            if (!$row || (int)$row['points'] <= 0) {
                continue;
            }

            $points = (int)$row['points'];
            $cartRuleId = (int)$row['id_cart_rule'];

            // Only sync if user already applied the rule
            if ($cartRuleId <= 0) {
                continue;
            }

            $expectedDiscount = min($points, $brandTotal);
            $expectedDiscount = round($expectedDiscount, 2); // Always round monetary values

            $cartRules = $cart->getCartRules();
            $existingCartRule = null;

            foreach ($cartRules as $rule) {
                if ((int)$rule['id_cart_rule'] === $cartRuleId) {
                    $existingCartRule = new CartRule($cartRuleId);
                    break;
                }
            }

            if ($existingCartRule) {
                $currentDiscount = round((float)$existingCartRule->reduction_amount, 2);

                if (abs($currentDiscount - $expectedDiscount) > 0.01) {
                    // 💡 Update the rule directly
                    $existingCartRule->reduction_amount = $expectedDiscount;
                    $existingCartRule->save();

                    // Reload the rule into the cart (PrestaShop caches them)
                    $cart->removeCartRule($cartRuleId);
                    $cart->addCartRule($cartRuleId);
                }
            }
        }
        self::removeObsoleteLoyaltyRulesFromCart($cart, $brandsInCart);
    }

    public static function removeObsoleteLoyaltyRulesFromCart(Cart $cart, array $brandsInCart)
    {
        $cartRules = $cart->getCartRules();

        foreach ($cartRules as $rule) {
            $ruleName = $rule['name'];
            $cartRuleId = (int)$rule['id_cart_rule'];
            $matchesActiveBrand = false;
            // Check if this rule matches any current brand in the cart
            foreach ($brandsInCart as $manufacturerId => $_) {
                if ($ruleName === self::getLoyaltyCartRuleName($manufacturerId)) {
                    $matchesActiveBrand = true;
                    break;
                }
            }

            if (!$matchesActiveBrand) {
                $cart->removeCartRule($cartRuleId);
                $cartRule = new CartRule($cartRuleId);
                $ruleId = null;
                if (is_object($rule) && isset($rule->id)) {
                    $ruleId = $rule->id;
                } elseif (is_array($rule) && isset($rule['id_cart_rule'])) {
                    $ruleId = $rule['id_cart_rule'];
                }
                if ($ruleId !== null && $cart->removeCartRule($ruleId)) {
                    // Delete the rule from the database
                    $cartRule->delete();
                }
            }
        }
    }

    public static function isAnyGiftApplied($cart)
    {
        $cartRules = Db::getInstance()->executeS(
            '
        SELECT cr.*
        FROM ' . _DB_PREFIX_ . 'cart_rule cr
        INNER JOIN ' . _DB_PREFIX_ . 'cart_cart_rule ccr ON cr.id_cart_rule = ccr.id_cart_rule
        WHERE ccr.id_cart = ' . (int)$cart->id
        );

        foreach ($cartRules as $rule) {
            if ((int)$rule['gift_product'] > 0) {
                return true;
            }
        }

        return false;
    }

    public static function createBrandLoyaltyGiftCartRule($customerId, $manufacturerId, $giftProductId, $giftPrice)
    {
        $cartRule = new CartRule();
        $cartRule->description = 'Loyalty gift for brand #' . $manufacturerId;
        $cartRule->code = 'LOYALTY_GIFT_BRAND_' . $manufacturerId . '_' . $customerId;
        $cartRule->id_customer = $customerId;
        $cartRule->date_from = date('Y-m-d H:i:s');
        $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->minimum_amount = 0;
        $cartRule->reduction_amount = $giftPrice;
        $cartRule->reduction_tax = true;
        $cartRule->active = 1;
        $cartRule->free_shipping = false;
        $cartRule->product_restriction = 1;

        $productName = Product::getProductName($giftProductId);
        if (!$productName) {
            PrestaShopLogger::addLog('Invalid product name for gift product ID ' . $giftProductId, 3);
            return null;
        }
        foreach (Language::getLanguages(true) as $lang) {
            $cartRule->name[$lang['id_lang']] = self::getLoyaltyCartRuleName($manufacturerId);
        }

        return $cartRule->add() ? $cartRule : null;
    }

    /**
     * Extract manufacturer ID from a loyalty cart rule code
     *
     * @param string $code
     * @return int|null
     */
    public static function extractManufacturerIdFromLoyaltyCode($code)
    {
        if (preg_match('/^LOYALTY_(?:GIFT_)?BRAND_(\d+)_\d+$/', $code, $matches)) {
            return (int)$matches[1]; // manufacturer ID
        }
        return null;
    }

    /**
     * Check if product is a loyalty gift (based on used_as_gift column in DB)
     */
    public static function isGiftProduct(int $idProduct): bool
    {
        $sql = 'SELECT used_as_gift FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int)$idProduct;
        $result = Db::getInstance()->getValue($sql);

        PrestaShopLogger::addLog("Gift check for product $idProduct: used_as_gift = " . (int)$result, 1);

        return (bool)$result;
    }

    public static function removeBrandGiftIfNoBrandProductsLeft(Cart $cart, int $removedProductId)
    {
        $product = new Product($removedProductId);
        $brandId = (int)$product->id_manufacturer;

        if (!$brandId) {
            PrestaShopLogger::addLog("Removed product $removedProductId has no brand", 1);
            return;
        }

        $remainingProducts = $cart->getProducts();
        $brandStillInCart = false;

        foreach ($remainingProducts as $p) {
            $prod = new Product((int)$p['id_product']);
            if ((int)$prod->id_manufacturer === $brandId && !self::isGiftProduct($prod->id)) {
                $brandStillInCart = true;
                break;
            }
        }

        if (!$brandStillInCart) {
            PrestaShopLogger::addLog("No more regular products for brand $brandId. Removing gifts for brand.", 1, null, 'Cart', $cart->id, true);

            foreach ($remainingProducts as $p) {
                $prod = new Product((int)$p['id_product']);
                if ((int)$prod->id_manufacturer === $brandId && self::isGiftProduct($prod->id)) {
                    $cart->deleteProduct((int)$p['id_product'], (int)$p['id_product_attribute'] ?? null, (int)$p['id_customization'] ?? null);
                    PrestaShopLogger::addLog("Removed gift product ID {$p['id_product']} for brand $brandId", 1, null, 'Cart', $cart->id, true);
                }
            }

            $cart->update();
        } else {
            PrestaShopLogger::addLog("Brand $brandId still exists in cart. No gift removal needed.", 1, null, 'Cart', $cart->id, true);
        }
    }
}
