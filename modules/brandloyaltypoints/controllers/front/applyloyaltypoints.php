<?php
class BrandLoyaltyPointsApplyLoyaltyPointsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Your points logic here
        $this->ajaxDie(json_encode([
            'success' => true,
            'message' => 'Loyalty points applied successfully!'
        ]));
    }
}