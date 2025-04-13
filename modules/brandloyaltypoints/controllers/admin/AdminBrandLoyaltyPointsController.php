<?php
require_once _PS_MODULE_DIR_ . 'brandloyaltypoints/classes/LoyaltyPoints.php';
class AdminBrandLoyaltyPointsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'loyalty_points';
        $this->className = 'LoyaltyPoints';
        $this->lang = false;
        $this->bootstrap = true;
        // Test if the LoyaltyPoints class can be instantiated
        try {
            $test = new LoyaltyPoints();  // No ID, just testing creation
            PrestaShopLogger::addLog('LoyaltyPoints class loaded successfully.', 1);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Failed to load LoyaltyPoints: ' . $e->getMessage(), 3);
        }
        parent::__construct();
    }

    public function renderList()
    {
        $this->fields_list = [
            'id_loyalty_points' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'id_customer' => [
                'title' => $this->l('Customer ID'),
            ],
            'customer_name' => [
                'title' => $this->l('Customer Name'),
                'orderby' => false,
                'search' => false
            ],
            'id_manufacturer' => [
                'title' => $this->l('Brand ID'),
            ],
            'manufacturer_name' => [
                'title' => $this->l('Brand Name'),
                'orderby' => false,
                'search' => false
            ],
            'points' => [
                'title' => $this->l('Points'),
                'align' => 'center',
            ],
            'last_updated' => [
                'title' => $this->l('Last Updated'),
            ]
        ];

        // Custom SQL join to display customer and brand names
        $this->_select = '
            c.firstname as customer_firstname, c.lastname as customer_lastname, m.name as manufacturer_name,
            CONCAT(c.firstname, " ", c.lastname) as customer_name
        ';
        $this->_join = '
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (a.id_customer = c.id_customer)
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (a.id_manufacturer = m.id_manufacturer)
        ';

        return parent::renderList();
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Edit Loyalty Points'),
                'icon' => 'icon-star',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Customer ID'),
                    'name' => 'id_customer',
                    'readonly' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Manufacturer ID'),
                    'name' => 'id_manufacturer',
                    'readonly' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Points'),
                    'name' => 'points',
                    'required' => true,
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        return parent::renderForm();
    }
    // Override processUpdate to prevent modification of customer or manufacturer ID
    public function processUpdate()
    {
        if ($id = Tools::getValue('id_loyalty_points')) {
            $object = new LoyaltyPoints((int)$id);

            // Force customer and manufacturer IDs to remain unchanged
            $_POST['id_customer'] = $object->id_customer;
            $_POST['id_manufacturer'] = $object->id_manufacturer;
        }

        // Call the parent processUpdate method to actually save the changes
        parent::processUpdate();
    }
}
