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

        // Group cart products by manufacturer
        $products = $cart->getProducts();
        $brandsInCart = [];

        foreach ($products as $product) {
            $id_manufacturer = (int) $product['id_manufacturer'];
            if (!isset($brandsInCart[$id_manufacturer])) {
                $brandsInCart[$id_manufacturer] = [
                    'total_price' => 0,
                    'products' => []
                ];
            }
            $brandsInCart[$id_manufacturer]['total_price'] += $product['total_wt'];  // price with tax
            $brandsInCart[$id_manufacturer]['products'][] = $product;
        }

        // Get loyalty points for this customer
        $loyaltyData = Db::getInstance()->executeS(
            '
            SELECT id_manufacturer, points 
            FROM `' . _DB_PREFIX_ . 'loyalty_points` 
            WHERE id_customer = ' . $customerId
        );

        $appliedAny = false;

        foreach ($loyaltyData as $entry) {
            $manufacturerId = (int) $entry['id_manufacturer'];
            $availablePoints = (int) $entry['points'];
        
            if (isset($brandsInCart[$manufacturerId]) && $availablePoints > 0) {
        
                // 💡 check if already applied
                $existingRules = $cart->getCartRules();
                $expectedRuleName = $this->getLoyaltyCartRuleName($manufacturerId);
                $alreadyApplied = false;
                foreach ($existingRules as $rule) {
                    if ($rule['name'] === $expectedRuleName) {
                        $alreadyApplied = true;
                        break;
                    }
                }
        
                if ($alreadyApplied) {
                    continue; // Skip: already applied
                }
        
                $brandTotal = $brandsInCart[$manufacturerId]['total_price'];
                $maxDiscount = $availablePoints * 0.1; // €0.1 per point
        
                $discountToApply = min($maxDiscount, $brandTotal);
                if ($discountToApply < 0.01) {
                    continue;
                }
        
                // Create new CartRule
                $cartRule = new CartRule();
                $cartRule->description = $cartRule->description = 'Loyalty points discount for brand #' . $manufacturerId;
                $cartRule->id_customer = $customerId;
                $cartRule->date_from = date('Y-m-d H:i:s');
                $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
                $cartRule->quantity = 1;
                $cartRule->quantity_per_user = 1;
                $cartRule->minimum_amount = 0;
                $cartRule->reduction_amount = $discountToApply;
                $cartRule->reduction_tax = true;
                $cartRule->active = 1;
                $cartRule->free_shipping = false;
        
                foreach (Language::getLanguages(true) as $lang) {
                    $cartRule->name[$lang['id_lang']] = $this->getLoyaltyCartRuleName($manufacturerId);
                }
        
                if ($cartRule->add()) {
                    $cart->addCartRule($cartRule->id);
                    $appliedAny = true;
        
                    // $pointsUsed = floor($discountToApply / 0.1); // this should be deducted when order is validated
                    // SET points = points - ' . $pointsUsed . ', last_updated = NOW() 
                    Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'loyalty_points` 
                        SET id_cart_rule = ' . (int)$cartRule->id . ', last_updated = NOW()
                        WHERE id_customer = ' . $customerId . ' 
                        AND id_manufacturer = ' . $manufacturerId . '
                    ');
                }
            }
        }


        if ($appliedAny) {
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => 'Loyalty points applied successfully!'
            ]));
        } else {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No applicable loyalty points found for the items in your cart.'
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
        $brand = new Manufacturer($manufacturerId, $this->context->language->id);
        $brandName = $brand->name ?: ('Brand #' . $manufacturerId);
        return sprintf('Loyalty Discount for %s', $brandName);
    }
}
