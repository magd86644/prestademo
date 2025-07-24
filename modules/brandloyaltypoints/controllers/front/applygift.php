<?php

require_once _PS_MODULE_DIR_ . 'brandloyaltypoints/helpers/LoyaltyPointsHelper.php';

class BrandLoyaltyPointsApplyGiftModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $idCustomer = (int) $this->context->customer->id;
        $cart = $this->context->cart;
        $idBrand = (int) Tools::getValue('brand');
        $idProduct = (int) Tools::getValue('product');

        if (!$idCustomer || !$cart || !$idBrand || !$idProduct) {
            die(json_encode([
                'success' => false,
                'message' => 'Missing required parameters.',
            ]));
        }

        // Check if the product is a valid gift for the brand
        $giftProduct = Db::getInstance()->getRow(
            '
            SELECT p.id_product, p.price
            FROM ' . _DB_PREFIX_ . 'brand_loyalty_gift blg
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON blg.id_product = p.id_product
            WHERE blg.id_manufacturer = ' . (int)$idBrand . '
              AND p.id_product = ' . (int)$idProduct
        );

        if (!$giftProduct) {
            die(json_encode([
                'success' => false,
                'message' => 'Invalid gift selection for the selected brand.',
            ]));
        }

        // Check if user has enough points
        $availablePoints = (int) Db::getInstance()->getValue(
            '
            SELECT points FROM ' . _DB_PREFIX_ . 'loyalty_points
            WHERE id_customer = ' . $idCustomer . ' AND id_manufacturer = ' . $idBrand
        );

        $giftPrice = (float) Product::getPriceStatic($idProduct, true); // tax incl
        if ($availablePoints < $giftPrice) {
            die(json_encode([
                'success' => false,
                'message' => 'Not enough loyalty points to claim this gift.',
            ]));
        }
        PrestaShopLogger::addLog('Available points: ' . $availablePoints, 1, null, null, true);
        PrestaShopLogger::addLog('Gift price: ' . $giftPrice, 1, null, 'Cart', 0, true);

        // Prevent duplicate gift or discount
        if (LoyaltyPointsHelper::isLoyaltyRuleApplied($this->context->cart, $idBrand)) {
            die(json_encode([
                'success' => false,
                'message' => 'You already used loyalty points for this brand.',
            ]));
        }
        // log here
        PrestaShopLogger::addLog('Checking if any gift is already applied', 1, null, null, true);

        if (LoyaltyPointsHelper::isAnyGiftApplied($this->context->cart)) {
            die(json_encode([
                'success' => false,
                'message' => 'You already claimed a gift.',
            ]));
        }
        // Create Cart Rule with gift product

        $cartRule = LoyaltyPointsHelper::createBrandLoyaltyGiftCartRule($idCustomer, $idBrand, $idProduct, $giftPrice);


        if (!$cartRule || !Validate::isLoadedObject($cartRule)) {
            PrestaShopLogger::addLog('Failed to create loyalty gift cart rule', 3, null, 'Cart', null, true);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to create gift rule.',
            ]));
        }

        PrestaShopLogger::addLog('Loyalty gift cart rule created: ' . $cartRule->id, 1, null, 'Cart', null, true);
        // Restrict to brand
        LoyaltyPointsHelper::attachManufacturerConditionToCartRule($cartRule->id, $idBrand);
        PrestaShopLogger::addLog('Attached brand restriction to cart rule ' . $cartRule->id, 1, null, 'Cart', null, true);
        // Apply to cart
        try {
            $cart->addCartRule($cartRule->id);

            Db::getInstance()->execute(
                '
                UPDATE ' . _DB_PREFIX_ . 'loyalty_points
                SET id_cart_rule = ' . (int)$cartRule->id . ', last_updated = NOW()
                WHERE id_customer = ' . $idCustomer . ' AND id_manufacturer = ' . $idBrand
            );

            die(json_encode([
                'success' => true,
                'message' => 'Gift successfully applied to your cart.',
            ]));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Exception while applying gift to cart: ' . $e->getMessage(), 3, null, 'Cart', null, true);
            die(json_encode([
                'success' => false,
                'message' => 'Error applying gift to cart: ' . $e->getMessage(),
            ]));
        }
    }
}
