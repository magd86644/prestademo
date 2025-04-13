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
        parent::__construct();
    }

    public function renderList()
    {
        $this->fields_list = [
            'id_loyalty_points' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false
            ],
            'customer_name' => [
                'title' => $this->l('Customer Name'),
                'callback' => 'renderCustomerLink',
                'search' => false,
            ],
            'manufacturer_name' => [
                'title' => $this->l('Brand Name'),
                'filter_key' => 'm!name', 

            ],
            'points' => [
                'title' => $this->l('Points'),
                'type' => 'int',
                'align' => 'center',
                'filter_key' => 'a!points',
                'search' => true,
                'callback' => 'formatPoints',
            ],
            'last_updated' => [
                'title' => $this->l('Last Updated'),
                'type' => 'datetime',
                'align' => 'center',
                'search' => false
            ],
        ];

        $this->_select = '
        a.id_loyalty_points,
        a.points,
        c.firstname as customer_firstname, 
        c.lastname as customer_lastname, 
        m.name as manufacturer_name,
        CONCAT(c.firstname, " ", c.lastname) as customer_name';

        $this->_join = '
        LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (a.id_customer = c.id_customer)
        LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (a.id_manufacturer = m.id_manufacturer)
    ';

        $this->processFilter();
        return parent::renderList();
    }

    public function formatPoints($points, $tr)
    {
        return $points . ' pts';
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

    public function renderCustomerLink($customer_name, $row)
    {
        $id_customer = $row['id_customer'];
        $link = $this->context->link->getAdminLink('AdminCustomers', true, [], ['id_customer' => $id_customer, 'updatecustomer' => 1]);

        return '<a href="' . $link . '">' . htmlspecialchars($customer_name) . '</a>';
    }
    // public function renderManufacturerLink($manufacturer_name, $row)
    // {
    //     $id_manufacturer = $row['id_manufacturer'];
    //     $link = $this->context->link->getAdminLink('AdminManufacturers', true, [], ['id_manufacturer' => $id_manufacturer, 'updatemanufacturer' => 1]);

    //     return '<a href="' . $link . '">' . htmlspecialchars($manufacturer_name) . '</a>';
    // }
}
