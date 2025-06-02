<?php

class BrandLoyaltyPointsAccountLoyaltyPointsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customerId = (int)$this->context->customer->id;
        $pointsByBrand = Db::getInstance()->executeS('
            SELECT m.name AS brand_name, lp.points
            FROM '._DB_PREFIX_.'loyalty_points lp
            INNER JOIN '._DB_PREFIX_.'manufacturer m ON m.id_manufacturer = lp.id_manufacturer
            WHERE lp.id_customer = '.$customerId
        );

        $this->context->smarty->assign([
            'pointsByBrand' => $pointsByBrand,
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
