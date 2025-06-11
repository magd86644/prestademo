<?php

class AdminLoyaltyReportController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->lang = false;
        $this->display = 'view'; // Show a custom report page
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $this->content .= $this->renderReportForm();
        $this->context->smarty->assign([
            'content' => $this->content
        ]);
    }

    public function renderView()
    {
        return $this->content;
    }

    protected function renderReportForm()
    {

        $months = [];
        // I started from the first day of the month to avoid issues with 30/31 days in months
        $baseDate = strtotime(date('Y-m-01'));


        for ($i = 0; $i < 12; $i++) {
            $monthTimestamp = strtotime("-$i months", $baseDate);
            $monthValue = date('Y-m', $monthTimestamp);
            $monthLabel = date('F Y', $monthTimestamp);

            // Avoid duplicates
            if (!isset($months[$monthValue])) {
                $months[$monthValue] = [
                    'id' => $monthValue,
                    'name' => $monthLabel,
                ];
            }
        }

        // Reindex the array numerically for the form helper
        $months = array_values($months);

        $brands = Manufacturer::getManufacturers(false, 0, true);
        $brandOptions = [];
        foreach ($brands as $brand) {
            $brandOptions[] = [
                'id_manufacturer' => $brand['id_manufacturer'],
                'name' => $brand['name']
            ];
        }

        // Build form structure
        $formFields = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Generate Monthly Report'),
                    'icon' => 'icon-bar-chart'
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Month'),
                        'name' => 'report_month',
                        'required' => true,
                        'options' => [
                            'query' => $months,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Brand'),
                        'name' => 'report_brand',
                        'required' => true, // Added required attribute
                        'options' => [
                            'query' => $brandOptions,
                            'id' => 'id_manufacturer',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Generate Report'),
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-download'
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->token = $this->token;
        $helper->currentIndex = self::$currentIndex . '&report=1';
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = $this->context->language->id;

        $helper->submit_action = 'submitMonthlyReport';
        $helper->fields_value = [
            'report_month' => Tools::getValue('report_month', date('Y-m')),
            'report_brand' => Tools::getValue('report_brand', ''),
        ];

        $html = $helper->generateForm([$formFields]);

        if (Tools::isSubmit('submitMonthlyReport')) {
            $html .= $this->generateReportTable(
                Tools::getValue('report_month'),
                (int)Tools::getValue('report_brand')
            );
        }

        return $html;
    }


    protected function generateReportTable($month, $brandId)
    {
        // Add time parts to include full days in date range
        $startDate = $month . '-01 00:00:00';
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));

        $sql = '
        SELECT m.name AS brand_name,
               COUNT(DISTINCT o.id_order) AS orders_count,
               SUM(ocr.value) AS total_discount
        FROM ' . _DB_PREFIX_ . 'orders o
        INNER JOIN ' . _DB_PREFIX_ . 'order_cart_rule ocr ON o.id_order = ocr.id_order
        INNER JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.id_cart_rule = ocr.id_cart_rule
        INNER JOIN ' . _DB_PREFIX_ . 'manufacturer m 
            ON m.id_manufacturer = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cr.code, "_", -2), "_", 1) AS UNSIGNED)
        WHERE cr.code LIKE "LOYALTY_BRAND\_%" 
        AND o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"';

        if (!empty($brandId)) {
            $sql .= ' AND m.id_manufacturer = ' . (int)$brandId;
        }

        $sql .= ' GROUP BY m.id_manufacturer ORDER BY total_discount DESC';

        $results = Db::getInstance()->executeS($sql);

        if (empty($results)) {
            return '<div class="alert alert-warning">' . $this->l('No data for selected month and brand.') . '</div>';
        }

        $html = '<table class="table">
        <thead>
            <tr>
                <th>' . $this->l('Brand') . '</th>
                <th>' . $this->l('Orders Used') . '</th>
                <th>' . $this->l('Total Discount Used') . '</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($results as $row) {
            $html .= '<tr>
            <td>' . htmlspecialchars($row['brand_name']) . '</td>
            <td>' . (int)$row['orders_count'] . '</td>
            <td>' . Tools::displayPrice((float)$row['total_discount'], $this->context->currency) . '</td>
        </tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
