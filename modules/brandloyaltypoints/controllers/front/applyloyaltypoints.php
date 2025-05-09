<?php

class BrandLoyaltyPointsApplyLoyaltyPointsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customerId = (int) $this->context->customer->id;
        $cart = $this->context->cart;

        if (!$customerId || !$cart->id) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No active customer or cart found.'
            ]));
        }

        $brandsInCart = $this->getBrandsInCart($cart);

        $manufacturerIdToApply = (int) Tools::getValue('brand');
        $manufacturerId = $manufacturerIdToApply;
        $availablePoints = (int) Db::getInstance()->getValue(
            'SELECT points FROM `' . _DB_PREFIX_ . 'loyalty_points`
            WHERE id_customer = ' . $customerId . ' AND id_manufacturer = ' . $manufacturerId
        );
        if ($availablePoints > 0 && isset($brandsInCart[$manufacturerId])) {
            if ($this->isLoyaltyRuleAlreadyApplied($cart, $manufacturerId)) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Loyalty points already applied for this brand.'
                ]));
            }

            $brandTotal = $brandsInCart[$manufacturerId]['total_price'];
            $conversionRate = BrandLoyaltyPoints::getConversionRateByManufacturer($manufacturerId);
            if ($conversionRate <= 0) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Invalid conversion rate.'
                ]));
            }

            $maxDiscount = $availablePoints * $conversionRate;
            $discountToApply = min($maxDiscount, $brandTotal);

            if ($discountToApply < 0.01) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Discount amount too small.'
                ]));
            }

            $cartRule = $this->createBrandLoyaltyCartRule($customerId, $manufacturerId, $discountToApply);
            if ($cartRule && Validate::isLoadedObject($cartRule)) {
                $this->attachManufacturerConditionToCartRule($cartRule->id, $manufacturerId);
                $cart->addCartRule($cartRule->id);

                Db::getInstance()->execute(
                    ' UPDATE `' . _DB_PREFIX_ . 'loyalty_points`
                        SET id_cart_rule = ' . (int)$cartRule->id . ', last_updated = NOW()
                        WHERE id_customer = ' . $customerId . ' AND id_manufacturer = ' . $manufacturerId
                );

                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Loyalty discount applied.'
                ]));
            } else {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Failed to create discount rule.'
                ]));
            }
        } else {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No loyalty points available for this brand.'
            ]));
        }
    }

    /**
     * Generates the loyalty discount cart rule name for a given brand.
     *
     * @param int $manufacturerId
     * @return string
     */
    private function getLoyaltyCartRuleName($manufacturerId)
    {
        $brandName = Manufacturer::getNameById((int)$manufacturerId);
        return 'Loyalty Discount - ' . $brandName;
    }

    private function getBrandsInCart($cart)
    {
        $brandsInCart = [];
        foreach ($cart->getProducts() as $product) {
            $manufacturerId = (int) $product['id_manufacturer'];
            if (!isset($brandsInCart[$manufacturerId])) {
                $brandsInCart[$manufacturerId] = ['total_price' => 0, 'products' => []];
            }
            $brandsInCart[$manufacturerId]['total_price'] += $product['total_wt'];
            $brandsInCart[$manufacturerId]['products'][] = $product;
        }
        return $brandsInCart;
    }


    private function isLoyaltyRuleAlreadyApplied($cart, $manufacturerId)
    {

        $expectedRuleName = $this->getLoyaltyCartRuleName($manufacturerId);
        foreach ($cart->getCartRules() as $rule) {
            if ($rule['name'] === $expectedRuleName) {
                return true;
            }
        }
        return false;
    }

    private function createBrandLoyaltyCartRule($customerId, $manufacturerId, $discount)
    {
        $cartRule = new CartRule();
        $cartRule->description = 'Loyalty points discount for brand #' . $manufacturerId;
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
            $cartRule->name[$lang['id_lang']] = $this->getLoyaltyCartRuleName($manufacturerId);
        }

        return $cartRule->add() ? $cartRule : null;
    }

    private function attachManufacturerConditionToCartRule($cartRuleId, $manufacturerId)
    {
        try {
            if (!$cartRuleId || !$manufacturerId) {
                PrestaShopLogger::addLog('attachManufacturerConditionToCartRule: Invalid CartRule ID or Manufacturer ID.', 3);
                return false;
            }

            $db = Db::getInstance();

            // 1️⃣ Create the Product Rule Group
            $insertGroup = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cart_rule_product_rule_group` 
             (id_cart_rule, quantity) 
             VALUES (' . (int)$cartRuleId . ', 1)'
            );

            if (!$insertGroup) {
                PrestaShopLogger::addLog("Failed to create product rule group for CartRule ID: $cartRuleId", 3);
                return false;
            }

            $idProductRuleGroup = $db->Insert_ID();

            // 2️⃣ Create the Product Rule with correct type `manufacturers`
            $insertRule = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cart_rule_product_rule` 
             (id_product_rule_group, type) 
             VALUES (' . (int)$idProductRuleGroup . ', "manufacturers")'
            );

            if (!$insertRule) {
                PrestaShopLogger::addLog("Failed to create product rule for CartRule ID: $cartRuleId", 3);
                return false;
            }

            $idProductRule = $db->Insert_ID();

            // 3️⃣ Link Manufacturer to the Product Rule
            $insertValue = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'cart_rule_product_rule_value` 
             (id_product_rule, id_item) 
             VALUES (' . (int)$idProductRule . ', ' . (int)$manufacturerId . ')'
            );

            if (!$insertValue) {
                PrestaShopLogger::addLog("Failed to insert manufacturer restriction for CartRule ID: $cartRuleId and Manufacturer ID: $manufacturerId", 3);
                return false;
            }

            PrestaShopLogger::addLog("Successfully attached manufacturer restriction: CartRule ID: $cartRuleId -> Manufacturer ID: $manufacturerId", 1);
            return true;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('attachManufacturerConditionToCartRule: Exception - ' . $e->getMessage(), 3);
            return false;
        }
    }
}
