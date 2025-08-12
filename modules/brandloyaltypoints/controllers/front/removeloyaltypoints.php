<?php

class BrandLoyaltyPointsRemoveLoyaltyPointsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customerId = (int)$this->context->customer->id;
        $cart = $this->context->cart;

        if (!$customerId || !$cart->id) {
            return $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No active customer or cart found.'
            ]));
        }

        $result = $this->removeLoyaltyGiftsAndCartRules($cart, $customerId);

        $this->ajaxDie(json_encode([
            'success' => $result,
            'message' => $result
                ? 'Loyalty points discounts and gifts removed from your cart.'
                : 'No loyalty points discounts or gifts were applied to remove.'
        ]));
    }

    /**
     * Removes loyalty gift products and related cart rules
     */
    private function removeLoyaltyGiftsAndCartRules(Cart $cart, int $customerId): bool
    {
        $removedAny = false;

        PrestaShopLogger::addLog("Starting removal of loyalty items for cart ID: {$cart->id}", 1, null, 'Cart', $cart->id, true);

        $products = $cart->getProducts();
        PrestaShopLogger::addLog("Cart has " . count($products) . " products", 1, null, 'Cart', $cart->id, true);

        foreach ($products as $product) {
            $idProduct = (int)$product['id_product'];
            $idProductAttribute = !empty($product['id_product_attribute']) ? (int)$product['id_product_attribute'] : null;
            $idCustomization = !empty($product['id_customization']) ? (int)$product['id_customization'] : null;

            PrestaShopLogger::addLog("Checking product ID $idProduct for gift status", 1, null, 'Cart', $cart->id, true);

            if ( LoyaltyPointsHelper::isGiftProduct($idProduct)) {
                PrestaShopLogger::addLog("Product $idProduct is a gift. Attempting to remove.", 1, null, 'Cart', $cart->id, true);

                $removed = $cart->deleteProduct($idProduct, $idProductAttribute, $idCustomization);

                if ($removed) {
                    PrestaShopLogger::addLog("Successfully removed gift product: $idProduct", 1, null, 'Cart', $cart->id, true);
                    $removedAny = true;
                } else {
                    PrestaShopLogger::addLog("FAILED to remove gift product: $idProduct", 3, null, 'Cart', $cart->id, true);
                }
            } else {
                PrestaShopLogger::addLog("Product ID $idProduct is not a gift", 1, null, 'Cart', $cart->id, true);
            }
        }

        // Process cart rules
        $cartRules = $cart->getCartRules();
        PrestaShopLogger::addLog("Found " . count($cartRules) . " cart rules to process", 1, null, 'Cart', $cart->id, true);

        foreach ($cartRules as $ruleData) {
            $cartRule = new CartRule((int)$ruleData['id_cart_rule']);
            $code = $cartRule->code;

            PrestaShopLogger::addLog("Checking cart rule ID {$cartRule->id} with code '$code'", 1, null, 'Cart', $cart->id, true);

            $manufacturerId = LoyaltyPointsHelper::extractManufacturerIdFromLoyaltyCode($code);

            if ($manufacturerId !== null) {
                PrestaShopLogger::addLog("Cart rule is loyalty-related. Removing rule ID {$cartRule->id} for brand $manufacturerId", 1, null, 'Cart', $cart->id, true);

                $cart->removeCartRule($cartRule->id);
                $cartRule->delete();

                $removedAny = true;
            } else {
                PrestaShopLogger::addLog("Cart rule ID {$cartRule->id} is NOT a loyalty rule", 1, null, 'Cart', $cart->id, true);
            }
        }

        $cart->update();

        PrestaShopLogger::addLog("Finished processing. Any items removed: " . ($removedAny ? 'Yes' : 'No'), 1, null, 'Cart', $cart->id, true);

        return $removedAny;
    }

    
}
