<?php

class BrandLoyaltyPointsRemoveLoyaltyPointsModuleFrontController extends ModuleFrontController
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

        $removedAny = false;

        // Get all active Cart Rules for this cart
        $cartRules = $cart->getCartRules();

        foreach ($cartRules as $rule) {
            $cartRule = new CartRule((int)$rule['id_cart_rule']);
            
            // Check if the cart rule was created by your module
            if (strpos($cartRule->description, 'Loyalty points discount') !== false) {

                // Remove it from the cart
                if ($cart->removeCartRule($cartRule->id)) {
                    $removedAny = true;

                    // If you want, you could also restore points here.
                    // But usually points are only truly "spent" when the order is confirmed.
                }
            }
        }

        if ($removedAny) {
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => 'Loyalty points discount removed from your cart.'
            ]));
        } else {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No loyalty points discounts were applied to remove.'
            ]));
        }
    }
}
