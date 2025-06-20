<?php

class BrandLoyaltyPointsAccountLoyaltyPointsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customerId = (int)$this->context->customer->id;
        $pointsByBrand = Db::getInstance()->executeS(
            '
            SELECT m.name AS brand_name, lp.points
            FROM ' . _DB_PREFIX_ . 'loyalty_points lp
            INNER JOIN ' . _DB_PREFIX_ . 'manufacturer m ON m.id_manufacturer = lp.id_manufacturer
            INNER JOIN ' . _DB_PREFIX_ . 'brand_loyalty_config blc ON blc.id_manufacturer = lp.id_manufacturer
            WHERE lp.id_customer = ' . $customerId . '
            AND blc.points_conversion_rate > 0'
        );
        $ordersWithPoints = Db::getInstance()->executeS('
            SELECT o.reference, o.date_add AS order_date,o.total_paid_tax_incl,
            o.delivery_date, lph.points_granted
            FROM ' . _DB_PREFIX_ . 'loyalty_points_history lph
            INNER JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = lph.id_order
            WHERE lph.id_customer = ' . (int)$customerId . '
            ORDER BY o.date_add DESC
        ');
        foreach ($ordersWithPoints as &$order) {
            $order['formatted_total'] = Tools::displayPrice($order['total_paid_tax_incl'], $this->context->currency);
        }

        $this->context->smarty->assign([
            'pointsByBrand' => $pointsByBrand,
            'ordersWithPoints' => $ordersWithPoints,
        ]);

        $this->setTemplate('module:brandloyaltypoints/views/templates/front/customerAccountPoints.tpl');
    }
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = [
            'title' => $this->trans('Your account', [], 'Shop.Theme.CustomerAccount'),
            'url' => $this->context->link->getPageLink('my-account'),
        ];

        $breadcrumb['links'][] = [
            'title' => $this->trans('Loyalty Miles', [], 'Modules.Brandloyaltypoints.Shop'),
            'url' => '', // Current page, so no URL
        ];

        return $breadcrumb;
    }
}
