<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Config\Services;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReportsController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;

    protected $categoryModel;
    protected $productModel;
    protected $orderModel;
    protected $orderDetailsModel;

    public function __construct()
    {
        // Initialize models using the service
        $this->userModel = Services::userModel();
        $this->orderDetailsModel = Services::orderDetailsModel();
        $this->orderModel = Services::orderModel();
        $this->productModel = Services::productModel();
    }

    public function generateSalesReport()
    {
        // Get parameters from the request
        $request = service('request');
        $reportType = $request->getPost('reportType');
        $dateType = $request->getPost('dateType');
        $dateRange = $request->getPost('dateRange');
        $month = $request->getPost('month');
        $year = $request->getPost('year');
        $fileName = $request->getPost('fileName') ?: 'Sales_Report';

        // Fetch sales data
        $data = $this->fetchSalesData($dateType, $dateRange, $month, $year);

        // Load the Excel template
        $templatePath = WRITEPATH . 'excelTemplates/SalesReportYearly.xlsx';
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Fetch categories from the database
        $db = \Config\Database::connect();
        $categoriesQuery = $db->table('category')->get();
        $categories = $categoriesQuery->getResultArray();

        // Calculate the last column based on the number of categories
        $lastColumn = chr(65 + count($categories) + 1); // A + number of categories + 1 (for Total column)

        // Set the report title and headers dynamically based on categories
        $sheet->setCellValue('A1', 'MINDORO AUTO PARTS');
        $sheet->setCellValue('A2', 'SALES REPORT');
        $sheet->setCellValue('A3', strtoupper($dateType) . ' REPORT');
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Define headers based on the categories
        $column = 'B';
        foreach ($categories as $category) {
            $sheet->setCellValue("{$column}4", $category['name']);
            $column++;
        }
        $sheet->setCellValue("{$column}4", 'Total');
        $sheet->getStyle("B4:{$column}4")->getFont()->setBold(true);

        // Add data to the sheet
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        $row = 5;
        foreach ($months as $index => $monthName) {
            $sheet->setCellValue("A{$row}", $monthName);
            $column = 'B';
            $total = 0;
            foreach ($categories as $category) {
                $categoryId = $category['id'];
                $categoryData = $data[$index + 1][$categoryId] ?? 0;
                $sheet->setCellValue("{$column}{$row}", $categoryData);
                $total += $categoryData;
                $column++;
            }
            $sheet->setCellValue("{$column}{$row}", $total);
            $row++;
        }

        // Set headers for the file download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');

        // Write the file and send it to the browser
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }


    private function fetchSalesData($dateType, $dateRange, $month, $year)
{
    $db = \Config\Database::connect();
    $builder = $db->table('order_details');
    $builder->select('MONTH(orders.order_date) as month, product.category_id, SUM(order_details.total_price) as total_price');
    $builder->join('product', 'order_details.product_id = product.id');
    $builder->join('orders', 'order_details.order_id = orders.id');
    $builder->where('YEAR(orders.order_date)', $year);
    $builder->groupBy('month, product.category_id');

    // Log the generated SQL query
    $sql = $builder->getCompiledSelect();
    log_message('debug', 'SQL Query: ' . $sql);

    $query = $builder->get();
    $results = $query->getResultArray();

    // Log the raw results
    log_message('debug', 'Query Results: ' . json_encode($results));

    // Initialize the data array with all months and categories set to 0
    $categoriesQuery = $db->table('category')->get();
    $categories = $categoriesQuery->getResultArray();
    $categoriesMap = array_column($categories, 'id');
    $data = array_fill(1, 12, array_fill_keys($categoriesMap, 0));

    // Log the initialized data array
    log_message('debug', 'Initialized Data Array: ' . json_encode($data));

    // Fill the data array with actual sales data
    foreach ($results as $result) {
        $month = $result['month'];
        $categoryId = $result['category_id'];
        $totalPrice = $result['total_price'];
        $data[$month][$categoryId] = $totalPrice;
    }

    // Log the populated data array
    log_message('debug', 'Populated Data Array: ' . json_encode($data));

    return $data;
}


    public function previewSalesReport()
    {
        // Get parameters from the request
        $request = service('request');
        $reportType = $request->getPost('reportType');
        $dateType = $request->getPost('dateType');
        $dateRange = $request->getPost('dateRange');
        $month = $request->getPost('month');
        $year = $request->getPost('year');

        // Fetch sales data
        $data = $this->fetchSalesData($dateType, $dateRange, $month, $year);

        // Return data as JSON
        return $this->response->setJSON([
            'status' => 200,
            'error' => null,
            'messages' => 'Sales data fetched successfully',
            'data' => $data
        ]);
    }
}
