<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Config\Services;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Dompdf\Dompdf;

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

    public function generateProductReport()
    {
        $input = $this->request->getJSON();
    
        $reportType = $input->reportType;
        $dateType = $input->dateType;
        $dateRange = $input->dateRange;
        $month = $input->month;
        $year = $input->year;
        $fileName = $input->fileName ?? 'Sales_Report';
        $format = $input->format ?? 'excel'; // Default format is Excel, can also be 'pdf'
    
        $data = $this->fetchSalesData($dateType, $dateRange, $month, $year);
        $title = $this->getTitle($reportType, $year, $month, $dateRange);
    
        if ($format == 'excel') {
            return $this->generateExcelReport($data, $title, $fileName);
        } elseif ($format == 'pdf') {
            return $this->generatePDFReport($data, $title, $fileName);
        } else {
            return $this->respond(['message' => 'Invalid format specified'], ResponseInterface::HTTP_BAD_REQUEST);
        }
    }

    private function generateExcelReport($data, $title, $fileName)
    {
        $templatePath = WRITEPATH . 'excelTemplates/ProductReport.xlsx';
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A3', $title);
        $this->populateExcelSheet($sheet, $data);
        $excelFilePath = WRITEPATH . 'uploads/' . $fileName . '.xlsx';
        $this->saveExcelFile($spreadsheet, $excelFilePath);
        return $this->response->download($excelFilePath, null)->setFileName($fileName . '.xlsx');
    }

    private function generatePDFReport($data, $title, $fileName)
    {
        $pdfFilePath = WRITEPATH . 'uploads/' . $fileName . '.pdf';
        $this->generatePDF($data, $title, $pdfFilePath);
    
        // Get the file content
        $fileContent = file_get_contents($pdfFilePath);
        if ($fileContent === false) {
            return $this->failNotFound('Failed to read PDF file.');
        }
    
        // Prepare the response to send the PDF file content
        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'inline; filename="' . $fileName . '.pdf"');
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $this->response->setHeader('Accept-Ranges', 'bytes');
    
        // Directly output the PDF content
        return $this->response->setBody($fileContent);
    }
    

    private function saveExcelFile($spreadsheet, $filePath)
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    private function generatePDF($data, $title, $filePath)
    {
        $dompdf = new Dompdf();
        $html = $this->renderPDFHTML($data, $title);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        file_put_contents($filePath, $dompdf->output());
    }

    private function renderPDFHTML($data, $title)
    {
        $html = "<h1>$title</h1>";
        $html .= "<table border='1' style='width:100%; border-collapse: collapse;'>";
        $html .= "<tr><th>Product Name</th><th>Units Sold</th><th>Total Sales</th></tr>";
        foreach ($data as $item) {
            $html .= "<tr><td>{$item['ProductName']}</td><td>{$item['UnitsSold']}</td><td>{$item['TotalSales']}</td></tr>";
        }
        $html .= "</table>";
        return $html;
    }

    private function getTitle($reportType, $year, $month, $dateRange)
    {
        if ($reportType == 'monthly') {
            return "Monthly Product Sales Report for $month, $year";
        } elseif ($reportType == 'weekly') {
            return "Weekly Product Sales Report for Week $dateRange of $year";
        }
        return "Yearly Product Sales Report for the Year $year"; // Default title for yearly report
    }

    private function populateExcelSheet($sheet, $data) {
        $startRow = 5;  // Starting row for the Excel sheet
    
        // Iterate over each category
        foreach ($data as $category => $salesMonths) {
            // Add category name in the first column
            $sheet->setCellValue('A' . $startRow, $category);
    
            // Each category has monthly data, start a new row for each month
            $currentRow = $startRow + 1; // Start populating data in the row after the category name
            foreach ($salesMonths as $month => $sales) {
                $sheet->setCellValue('B' . $currentRow, $month);
                $sheet->setCellValue('C' . $currentRow, $sales);
                $currentRow++;
            }
    
            // Move startRow to the next block of categories
            $startRow = $currentRow + 1; // Skip a row between categories for readability
        }
    }
    
    

    private function fetchProductData($dateType, $dateRange, $month, $year)
    {
        $builder = $this->orderDetailsModel->builder();
        $builder->select('product.name AS ProductName, SUM(order_details.quantity) AS UnitsSold, SUM(order_details.total_price) AS TotalSales');
        $builder->join('orders', 'orders.id = order_details.order_id');
        $builder->join('product', 'product.id = order_details.product_id');
        $builder->groupBy('product.name');
        $builder->orderBy('UnitsSold', 'DESC');
        $builder->limit(20);

        if ($dateType === 'weekly' && $dateRange) {
            $startDate = date('Y-m-d', strtotime($dateRange[0]));
            $endDate = date('Y-m-d', strtotime($dateRange[1]));
            $builder->where('orders.order_date >=', $startDate);
            $builder->where('orders.order_date <=', $endDate);
        } elseif ($dateType === 'monthly' && $month && $year) {
            $builder->where('YEAR(orders.order_date)', $year);
            $builder->where('MONTH(orders.order_date)', $month);
        } elseif ($dateType === 'yearly' and $year) {
            $builder->where('YEAR(orders.order_date)', $year);
        }

        $query = $builder->get();
        return $query->getResultArray();
    }

    public function previewProductReport()
    {
        $input = $this->request->getJSON();

        $reportType = $input->reportType;
        $dateType = $input->dateType;
        $dateRange = $input->dateRange;
        $month = $input->month;
        $year = $input->year;

        $data = $this->fetchProductData($dateType, $dateRange, $month, $year);
        return $this->respond($data);
    }

    public function previewSalesReport()
    {
        $input = $this->request->getJSON();

        $dateType = $input->dateType;
        $dateRange = $input->dateRange;
        $month = $input->month;
        $year = $input->year;

        $data = $this->fetchSalesData($dateType, $dateRange, $month, $year);
        return $this->respond($data);
    }

    public function previewReport()
    {
        $input = $this->request->getJSON();

        $reportType = $input->reportType;
        $dateType = $input->dateType;
        $dateRange = $input->dateRange;
        $month = $input->month;
        $year = $input->year;
        $fileName = $input->fileName ?? 'Report';
        $format = $input->format ?? 'view'; // Default is view, can also be 'excel' or 'pdf'

        // Determine the data fetching function based on report type
        $dataFetcher = $reportType === 'Product' ? 'fetchProductData' : 'fetchSalesData';
        $data = $this->$dataFetcher($dateType, $dateRange, $month, $year);

        if ($format === 'view') {
            return $this->respond($data);
        } else {
            $title = $this->getTitle($reportType, $year, $month, $dateRange);
            if ($format === 'excel') {
                return $this->generateExcelReport($data, $title, $fileName);
            } elseif ($format === 'pdf') {
                return $this->generatePDFReport($data, $title, $fileName);
            } else {
                return $this->respond(['message' => 'Invalid format specified'], ResponseInterface::HTTP_BAD_REQUEST);
            }
        }
    }

    private function fetchSalesData($dateType, $dateRange, $month, $year)
{
    $db = db_connect(); // Ensure proper database connection handling
    $builder = $db->table('category'); // Start from the category table
    $builder->select('category.name as category_name');

    // Setup the joins for the product and order_details
    $builder->join('product', 'product.category_id = category.id', 'left');
    $builder->join('order_details', 'order_details.product_id = product.id', 'left');
    $builder->join('orders', 'order_details.order_id = orders.id AND YEAR(orders.order_date) = ' . $db->escape($year), 'left');

    // Select sales data based on the dateType
    if ($dateType === 'yearly') {
        $builder->select('MONTH(orders.order_date) as month, IFNULL(SUM(order_details.total_price), 0) as monthly_sales');
        $builder->groupBy('category.name, MONTH(orders.order_date)');
    } elseif ($dateType === 'monthly' && $month !== null) {
        $builder->select('IFNULL(SUM(order_details.total_price), 0) as monthly_sales');
        $builder->where('MONTH(orders.order_date)', $month);
        $builder->groupBy('category.name');
    } elseif ($dateType === 'weekly' && $dateRange !== null) {
        $startDate = date('Y-m-d', strtotime('monday this week', strtotime($dateRange)));
        $endDate = date('Y-m-d', strtotime('sunday this week', strtotime($dateRange)));
        $builder->select('IFNULL(SUM(order_details.total_price), 0) as weekly_sales');
        $builder->where('orders.order_date >=', $startDate);
        $builder->where('orders.order_date <=', $endDate);
        $builder->groupBy('category.name');
    } else {
        // Default: Group by category only to ensure all categories are included
        $builder->select('0 as monthly_sales');
        $builder->groupBy('category.name');
    }

    $builder->orderBy('category.name, month', 'ASC'); // Adjust the order to ensure proper sorting

    $query = $builder->get();
    $data = $query->getResultArray();

    if ($dateType === 'yearly') {
        $monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        $result = [];

        // Initialize all categories with zero sales for all months
        foreach ($db->table('category')->select('name')->get()->getResultArray() as $category) {
            $result[$category['name']] = array_fill_keys($monthNames, 0);
        }

        // Populate actual sales data
        foreach ($data as $entry) {
            if ($entry['month']) { // This check ensures months without sales remain zero
                $result[$entry['category_name']][$monthNames[$entry['month'] - 1]] = $entry['monthly_sales'];
            }
        }

        return $result;
    }

    return $data; // Return the data for other date types
}


    



}
