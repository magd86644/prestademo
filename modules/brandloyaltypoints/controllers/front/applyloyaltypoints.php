<?php
require_once _PS_MODULE_DIR_ . 'brandloyaltypoints/helpers/LoyaltyPointsHelper.php';
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

        $brandsInCart = LoyaltyPointsHelper::getBrandsInCart($cart);
        $manufacturerIdToApply = (int) Tools::getValue('brand');
        $manufacturerId = $manufacturerIdToApply;
        $availablePoints = (int) Db::getInstance()->getValue(
            'SELECT points FROM `' . _DB_PREFIX_ . 'loyalty_points`
            WHERE id_customer = ' . $customerId . ' AND id_manufacturer = ' . $manufacturerId
        );
        if ($availablePoints > 0 && isset($brandsInCart[$manufacturerId])) {
            if (LoyaltyPointsHelper::isLoyaltyRuleApplied($cart, $manufacturerId)) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Loyalty points already applied for this brand.'
                ]));
            }

            $brandTotal = $brandsInCart[$manufacturerId]['total_price'];
            $conversionRate = LoyaltyPointsHelper::getConversionRateByManufacturer($manufacturerId);
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
            $cartRule = LoyaltyPointsHelper::createBrandLoyaltyCartRule(
                $customerId,
                $manufacturerId,
                $discountToApply
            );
            if ($cartRule && Validate::isLoadedObject($cartRule)) {
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
}
