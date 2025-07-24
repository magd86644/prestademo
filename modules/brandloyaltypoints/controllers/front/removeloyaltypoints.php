<?php

class BrandLoyaltyPointsRemoveLoyaltyPointsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customerId = (int) $this->context->customer->id;
        $cart = $this->context->cart;

        if (!$customerId || !$cart->id) {
            return $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No active customer or cart found.'
            ]));
        }

        $removedAny = false;
        $giftProductIds = [];

        // Get all active Cart Rules for this cart
        $cartRules = $cart->getCartRules();

        // First pass: Identify gift products and remove cart rules
        foreach ($cartRules as $rule) {
            $cartRule = new CartRule((int)$rule['id_cart_rule']);
            // Check if it's a loyalty rule
            $isLoyaltyRule = (
                strpos($cartRule->description, 'Loyalty points discount') !== false ||
                strpos($cartRule->description, 'Loyalty gift') !== false ||
                (int)$cartRule->gift_product > 0
            );

            if ($isLoyaltyRule) {
                // Handle gift products
                if ((int)$cartRule->gift_product > 0) {
                    $giftProductIds[] = (int)$cartRule->gift_product;
                    $cartRule->active = 0;
                    $cartRule->save();
                }
                if ($cart->removeCartRule($cartRule->id)) {
                    PrestaShopLogger::addLog('Cart rule removed from cart: ' . $cartRule->id, 1, null, 'Cart', $cart->id, true);
                    $cartRule->delete();
                    $removedAny = true;
                } else {
                    PrestaShopLogger::addLog('Failed to remove cart rule from cart: ' . $cartRule->id, 3, null, 'Cart', $cart->id, true);
                }
            }
        }

        $cart->update(); // ensures total is recalculated

        // Second pass: Handle gift product removal
        if (!empty($giftProductIds)) {
            PrestaShopLogger::addLog('Preparing to remove gift products: ' . implode(',', $giftProductIds), 1, null, 'Cart', $cart->id, true);
            $products = $cart->getProducts();
            PrestaShopLogger::addLog('Cart contains ' . count($products) . ' products', 1, null, 'Cart', $cart->id, true);

            foreach ($products as $product) {
                if (in_array((int)$product['id_product'], $giftProductIds) && $product['is_gift']) {

                    PrestaShopLogger::addLog('Removing gift product: ID ' . $product['id_product'] . ' Attribute: ' . $product['id_product_attribute'], 1, null, 'Cart', $cart->id, true);

                    // Use cart's deleteProduct method
                    $result = $cart->deleteProduct(
                        $product['id_product'],
                        $product['id_product_attribute'],
                        $product['id_customization']
                    );

                    if ($result) {
                        PrestaShopLogger::addLog('Successfully removed gift product: ' . $product['id_product'], 1, null, 'Cart', $cart->id, true);
                    } else {
                        PrestaShopLogger::addLog('Failed to remove gift product: ' . $product['id_product'], 3, null, 'Cart', $cart->id, true);
                    }
                }
            }
        }
        if ($removedAny) {
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => 'Loyalty points discounts and gifts removed from your cart.'
            ]));
        } else {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No loyalty points discounts or gifts were applied to remove.'
            ]));
        }
    }
}
