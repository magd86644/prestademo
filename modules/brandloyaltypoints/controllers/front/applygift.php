<?php

require_once _PS_MODULE_DIR_ . 'brandloyaltypoints/helpers/LoyaltyPointsHelper.php';

class BrandLoyaltyPointsApplyGiftModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customerId = (int) $this->context->customer->id;
        $giftProductId = (int) Tools::getValue('product');
        $manufacturerId = (int) Tools::getValue('brand');

        if (!$customerId || !$giftProductId || !$manufacturerId) {
            die(json_encode([
                'success' => false,
                'message' => 'Missing required parameters: customer, product or brand.',
            ]));
        }

        $cart = $this->context->cart;
        $giftPrice = (float) Product::getPriceStatic($giftProductId, true);

        // Step 1: Add the gift product to the cart
        if (!$cart->containsProduct($giftProductId)) {
            $cart->updateQty(1, $giftProductId);
            PrestaShopLogger::addLog('Gift product added to cart: ' . $giftProductId, 1, null, 'Cart', 0, true);
        }

        // Step 2: Create a discount cart rule equal to the gift product price
        $cartRule = LoyaltyPointsHelper::createBrandLoyaltyGiftCartRule($customerId, $manufacturerId, $giftProductId, $giftPrice);
        if (!$cartRule || !Validate::isLoadedObject($cartRule)) {
            PrestaShopLogger::addLog('Failed to create loyalty gift cart rule', 3, null, 'Cart', 0, true);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to create gift rule.',
            ]));
        } else {
            LoyaltyPointsHelper::attachManufacturerConditionToCartRule($cartRule->id, $manufacturerId);
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
        }
    }
}
