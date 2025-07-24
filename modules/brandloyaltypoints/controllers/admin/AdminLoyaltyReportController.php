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
        if (Tools::isSubmit('export_csv')) {
            $this->exportReportCsv(
                Tools::getValue('report_month'),
                (int)Tools::getValue('report_brand')
            );
            exit;
        }
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
                        'required' => true,
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
                'buttons' => [
                    [
                        'title' => $this->l('Export CSV'),
                        'name' => 'export_csv',
                        'type' => 'submit',
                        'class' => 'btn btn-default',
                        'icon' => 'process-icon-export'
                    ],
                ]
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
        // if no month or no brand is selected, return an error message
        if (empty($month) || empty($brandId)) {
            return '<div class="alert alert-danger">' . $this->l('Please select a month and a brand.') . '</div>';
        }
        $html = '';

        $html .= $this->generateSummaryTable($month, $brandId);
        $html .= '<br><hr>';
        $html .= $this->generateEarnedPointsTable($month, $brandId);
        $html .= '<br><hr>';
        $html .= $this->generateSpentPointsTable($month, $brandId);

        return $html;
    }


    private function generateSummaryTable($month, $brandId)
    {
        // todo update where cr.code LIKE "LOYALTY_BRAND\_%" to 
        // WHERE (cr.code LIKE "LOYALTY_BRAND\_%" OR cr.code LIKE "LOYALTY_GIFT_BRAND\_%")
        // also we can differentiate the type in the report if needed:
        //   CASE 
        //   WHEN cr.code LIKE "LOYALTY_GIFT_BRAND\_%" THEN "Gift"
        //   ELSE "Discount"
        // END AS loyalty_type
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
        AND o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"
        AND m.id_manufacturer = ' . (int)$brandId . '
        GROUP BY m.id_manufacturer
        ORDER BY m.name ASC';
        $results = Db::getInstance()->executeS($sql);

        if (empty($results)) {
            return '<div class="alert alert-info">' . $this->l('No data for selected month and brand.') . '</div>';
        }

        $html = '<h4>' . $this->l('Summary') . '</h4>
        <table class="table">
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

    private function generateEarnedPointsTable($month, $brandId)
    {
        $startDate = $month . '-01 00:00:00';
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));

        $sql = '
        SELECT DISTINCT o.reference, o.date_add, lpob.points_granted
        FROM ' . _DB_PREFIX_ . 'loyalty_points_order_brand lpob     
        INNER JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = lpob.id_order
            WHERE o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"
            AND lpob.id_manufacturer = ' . (int)$brandId . '
            ORDER BY o.date_add DESC
            ';

        $results = Db::getInstance()->executeS($sql);

        if (empty($results)) {
            return '<div class="alert alert-info">' . $this->l('No points earned for this brand in this month.') . '</div>';
        }

        $html = '<h4>' . $this->l('Orders That Earned Points') . '</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>' . $this->l('Order Ref') . '</th>
                    <th>' . $this->l('Date') . '</th>
                    <th>' . $this->l('Points Earned') . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($results as $row) {
            $html .= '<tr>
                    <td>' . htmlspecialchars($row['reference']) . '</td>
                    <td>' . htmlspecialchars($row['date_add']) . '</td>
                    <td>' . (int)$row['points_granted'] . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }


    private function generateSpentPointsTable($month, $brandId)
    {
        $startDate = $month . '-01 00:00:00';
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));

        $sql = '
        SELECT o.reference, o.date_add, ocr.value AS discount_used
        FROM ' . _DB_PREFIX_ . 'orders o
        INNER JOIN ' . _DB_PREFIX_ . 'order_cart_rule ocr ON o.id_order = ocr.id_order
        INNER JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.id_cart_rule = ocr.id_cart_rule
        INNER JOIN ' . _DB_PREFIX_ . 'manufacturer m 
            ON m.id_manufacturer = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cr.code, "_", -2), "_", 1) AS UNSIGNED)
        WHERE cr.code LIKE "LOYALTY_BRAND\_%" 
        AND o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"
        AND m.id_manufacturer = ' . (int)$brandId . '
        ORDER BY o.date_add DESC
    ';
        $results = Db::getInstance()->executeS($sql);

        if (empty($results)) {
            return '<div class="alert alert-info">' . $this->l('No points used for this brand in this month.') . '</div>';
        }

        $html = '<h4>' . $this->l('Orders That Used Points') . '</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>' . $this->l('Order Ref') . '</th>
                    <th>' . $this->l('Date') . '</th>
                    <th>' . $this->l('Discount Used') . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($results as $row) {
            $html .= '<tr>
            <td>' . htmlspecialchars($row['reference']) . '</td>
            <td>' . htmlspecialchars($row['date_add']) . '</td>
            <td>' . Tools::displayPrice((float)$row['discount_used'], $this->context->currency) . '</td>
        </tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    protected function exportReportCsv($month, $brandId)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=loyalty_report_' . $month . '_brand_' . $brandId . '.csv');

        $output = fopen('php://output', 'w');

        // Add headers
        fputcsv($output, ['--- Summary ---']);
        fputcsv($output, ['Brand', 'Orders Used', 'Total Discount']);

        $summaryData = $this->getSummaryData($month, $brandId);
        foreach ($summaryData as $row) {
            fputcsv($output, [$row['brand_name'], $row['orders_count'], $row['total_discount']]);
        }

        fputcsv($output, []);
        fputcsv($output, ['--- Earned Points ---']);
        fputcsv($output, ['Order Ref', 'Date', 'Points Earned']);

        $earnedData = $this->getEarnedPointsData($month, $brandId);
        foreach ($earnedData as $row) {
            fputcsv($output, [$row['reference'], $row['date_add'], $row['points_granted']]);
        }

        fputcsv($output, []);
        fputcsv($output, ['--- Spent Points ---']);
        fputcsv($output, ['Order Ref', 'Date', 'Discount Used']);

        $spentData = $this->getSpentPointsData($month, $brandId);
        foreach ($spentData as $row) {
            fputcsv($output, [$row['reference'], $row['date_add'], $row['discount_used']]);
        }

        fclose($output);
    }

    private function getSummaryData($month, $brandId)
    {
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
        AND o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"
        AND m.id_manufacturer = ' . (int)$brandId . '
        GROUP BY m.id_manufacturer
        ORDER BY m.name ASC
    ';

        return Db::getInstance()->executeS($sql);
    }

    private function getEarnedPointsData($month, $brandId)
    {
        $startDate = $month . '-01 00:00:00';
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));

        $sql = '
        SELECT DISTINCT o.reference, o.date_add, lpob.points_granted
        FROM ' . _DB_PREFIX_ . 'loyalty_points_order_brand lpob
        INNER JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = lpob.id_order
        WHERE o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"
        AND lpob.id_manufacturer = ' . (int)$brandId . '
        ORDER BY o.date_add DESC
    ';

        return Db::getInstance()->executeS($sql);
    }

    private function getSpentPointsData($month, $brandId)
    {
        $startDate = $month . '-01 00:00:00';
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));

        $sql = '
        SELECT o.reference, o.date_add, ocr.value AS discount_used
        FROM ' . _DB_PREFIX_ . 'orders o
        INNER JOIN ' . _DB_PREFIX_ . 'order_cart_rule ocr ON o.id_order = ocr.id_order
        INNER JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.id_cart_rule = ocr.id_cart_rule
        INNER JOIN ' . _DB_PREFIX_ . 'manufacturer m 
            ON m.id_manufacturer = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cr.code, "_", -2), "_", 1) AS UNSIGNED)
        WHERE cr.code LIKE "LOYALTY_BRAND\_%" 
        AND o.date_add BETWEEN "' . pSQL($startDate) . '" AND "' . pSQL($endDate) . '"
        AND m.id_manufacturer = ' . (int)$brandId . '
        ORDER BY o.date_add DESC
    ';

        return Db::getInstance()->executeS($sql);
    }
}
