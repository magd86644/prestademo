<?php
// modules/brandloyaltypoints/controllers/admin/AdminBrandLoyaltyPointsController.php
class AdminBrandLoyaltyPointsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
      
        // die('Controller is being loaded!'); // Remove after testing
        $this->bootstrap = true;
    }

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign([
            'your_variable' => 'Hello from AdminBrandLoyaltyPoints!',
        ]);
        $this->setTemplate('configure.tpl');
    }
}
