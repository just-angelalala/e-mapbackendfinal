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

class InventoryController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;

    public function __construct()
    {
        // Initialize models using the service
        $this->userModel = Services::userModel();
        $this->productModel = Services::productModel();
        $this->categoryModel = Services::categoryModel();
    }

    public function getAllCategories()
    {
        $categories = $this->categoryModel->getAllCategories();
        if (!$categories) {
            return $this->failNotFound('No categories found');
        }

        return $this->respond(['categories' => $categories]);
    }

    public function getAllProductsGroupedByCategory()
    {
        $groupedProducts = $this->productModel->getAllProductsGroupedByCategory();

        if (empty($groupedProducts)) {
            return $this->failNotFound('No products found');
        }

        return $this->respond(['groupedProducts' => $groupedProducts]);
    }

    public function getAllProductsUngroupedPos()
    {
        $ungroupedProducts = $this->productModel->getAllProductsUngroupedPos();

        if (empty($ungroupedProducts)) {
            return $this->failNotFound('No products found');
        }

        return $this->respond(['data' => $ungroupedProducts]);
    }

    public function getAllProductsUngroupedWithLowStock()
    {
        $ungroupedProducts = $this->productModel->getAllProductsUngroupedWithLowStock();
    
        if (empty($ungroupedProducts)) {
            return $this->respond(['data' => [], 'message' => 'No products found'], 200);
        }
    
        return $this->respond(['data' => $ungroupedProducts]);
    }
    

    public function getAutoCompleteSuggestions()
    {
        $productName = $this->productModel->getAllProductsDetails();

        if (empty($productName)) {
            return $this->failNotFound('No products found');
        }

        return $this->respond(['productName' => $productName]);
    }

    public function uploadProductImage()
    {
        $files = $this->request->getFiles();

        if (!$files) {
            return $this->fail('No files uploaded', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $uploadedFiles = [];
        $uploadPath = FCPATH . 'uploads/product';

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        foreach ($files['demo'] as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                $originalName = $file->getClientName();
                $file->move($uploadPath, $originalName);
                $uploadedFiles[] = $originalName;
            } else {
                return $this->fail('Failed to upload file ' . $file->getName(), ResponseInterface::HTTP_BAD_REQUEST);
            }
        }

        return $this->respondCreated([
            'message' => 'Files uploaded successfully',
            'files' => $uploadedFiles
        ]);
    }

    public function addProduct()
    {
        $json = $this->request->getJSON(true);
        
        if (!isset($json['category']) || !isset($json['name']) || !isset($json['price'])) {
            return apiErrorResponse('validation', 'Required fields: category, name, and price.', 400);
        }

        $result = $this->productModel->addProductWithCategory($json, $this->categoryModel);

        if ($result['error']) {
            return apiErrorResponse('database_error', $result['message'], 500);
        }

        return apiResponse('success', 'Product successfully added.', 201);
    }

    public function updateProduct($productId)
    {
        $json = $this->request->getJSON(true);
        
        // Validation now doesn't need to check 'id' in $json
        if (!isset($json['category']) || !isset($json['name']) || !isset($json['price'])) {
            return apiErrorResponse('validation', 'Required fields: category, name, and price.', 400);
        }

        // Pass along the productId directly
        $result = $this->productModel->updateProductWithCategory($json, $productId, $this->categoryModel);

        if ($result['error']) {
            return apiErrorResponse('database_error', $result['message'], 500);
        }

        return apiResponse('success', null, 'Product successfully updated.', 200);
    }



    public function updateProductQuantity()
    {
        $json = $this->request->getJSON();

        $productId = $json->product_id ?? null;
        $newQuantity = $json->quantity ?? null;

        if ($productId === null || $newQuantity === null) {
            return $this->fail('Invalid JSON format. Product ID and quantity are required', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $result = $this->productModel->updateProductQuantity($productId, $newQuantity);

        if ($result) {
            return $this->respond(['message' => 'Quantity updated successfully'], ResponseInterface::HTTP_OK);
        } else {
            return $this->failNotFound('Product not found');
        }
    }

    public function deleteProduct()
    {
        $json = $this->request->getJSON(true);
        $productIds = $json['productIds'] ?? null;

        if ($productIds === null || !is_array($productIds)) {
            return apiErrorResponse('validation', 'Product IDs are required and must be an array.', 400);
        }

        $result = $this->productModel->deleteProduct($productIds);

        if ($result['error']) {
            return apiErrorResponse('database_error', $result['message'], 500);
        } else {
            return apiResponse('success', ['deletedIds' => $result['deletedIds']], 'Products successfully deleted.', 200);
        }
    }

    public function restoreProduct()
    {
        $json = $this->request->getJSON(true);
        $productIds = $json['productIds'] ?? null;

        if ($productIds === null || !is_array($productIds)) {
            return apiErrorResponse('validation', 'Product IDs are required and must be an array.', 400);
        }

        $result = $this->productModel->restoreProducts($productIds);

        if ($result['error']) {
            return apiErrorResponse('database_error', $result['message'], 500);
        } else {
            return apiResponse('success', ['restoredIds' => $result['restoredIds']], 'Products successfully restored.', 200);
        }
    }

    // public function exportProductsUsingTemplate()
    // {
    //     $products = $this->productModel->fetchProductsWithCosts();

    //     // Load your template file
    //     $templatePath = WRITEPATH . 'excelTemplates/InventoryReport.xlsx';
    //     $spreadsheet = IOFactory::load($templatePath);
    //     $sheet = $spreadsheet->getActiveSheet();

    //     // Assuming the data starts at row 7 based on your template setup
    //     $startRow = 7;
    //     $currentRow = $startRow;

    //     foreach ($products as $product) {
    //         // Insert a new row before filling in data to preserve any formatting or formulas
    //         $sheet->insertNewRowBefore($currentRow, 1);

    //         // Populate the data
    //         $sheet->setCellValue('A' . $currentRow, $product['name']);
    //         $sheet->setCellValue('B' . $currentRow, $product['description']);
    //         $sheet->setCellValue('C' . $currentRow, $product['remarks']);
    //         $sheet->setCellValue('D' . $currentRow, $product['unit_cost']);
    //         $sheet->setCellValue('E' . $currentRow, $product['quantity_in_stocks']);
    //         $sheet->setCellValue('F' . $currentRow, $product['unit_of_measurement']);
    //         $sheet->setCellValue('G' . $currentRow, $product['total_cost']);
    //         $sheet->setCellValue('H' . $currentRow, $product['total_inventory_net_vat']);

    //         $currentRow++;
    //     }

    //     // Delete the placeholder row if your template has one
    //     $sheet->removeRow($startRow, 1);

    //     // Save the updated spreadsheet to a temporary file
    //     $fileName = 'Updated_InventoryReport';
    //     $excelFilePath = WRITEPATH . 'uploads/' . $fileName . '.xlsx';
    //     $writer = new Xlsx($spreadsheet);
    //     $writer->save($excelFilePath);

    //     // Return the file for download
    //     return $this->response->download($excelFilePath, null)->setFileName($fileName . '.xlsx');
    // }
    
    public function exportProductsUsingTemplate($format = 'xlsx')
    {
        $products = $this->productModel->fetchProductsWithCosts();

        // Date for dynamic header
        $currentDate = date('F d, Y'); // Format date as 'Month day, Year'

        if ($format === 'pdf') {
            $fileName = 'Updated_InventoryReport';
            $pdfFilePath = WRITEPATH . 'uploads/' . $fileName . '.pdf';
                // Generate the PDF content
                $this->generatePDFReport($products, "MERCHANDISE INVENTORY as of {$currentDate}", $pdfFilePath, 10);

            // Send the PDF
            return $this->sendPDF($pdfFilePath, $fileName);
        } else {
            // Load your Excel template file
            $templatePath = WRITEPATH . 'excelTemplates/InventoryReport.xlsx';
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Update the date in the Excel template
            $sheet->setCellValue('A3', "As of {$currentDate}");

            $currentRow = 7; // Assuming data starts at row 7
            $totalCostSum = 0;
            $totalNetVatSum = 0;
            foreach ($products as $product) {
                $sheet->insertNewRowBefore($currentRow, 1);
                $sheet->setCellValue('A' . $currentRow, $product['name']);
                $sheet->setCellValue('B' . $currentRow, $product['description']);
                $sheet->setCellValue('C' . $currentRow, $product['remarks']);
                $sheet->setCellValue('D' . $currentRow, $product['unit_cost']);
                $sheet->setCellValue('E' . $currentRow, $product['quantity_in_stocks']);
                $sheet->setCellValue('F' . $currentRow, $product['unit_of_measurement']);
                $sheet->setCellValue('G' . $currentRow, $product['total_cost']);
                $sheet->setCellValue('H' . $currentRow, $product['total_inventory_net_vat']);
                $totalCostSum += $product['total_cost'];
                $totalNetVatSum += $product['total_inventory_net_vat'];
                $currentRow++;
            }
            $sheet->setCellValue('G' . $currentRow, $totalCostSum);
            $sheet->setCellValue('H' . $currentRow, $totalNetVatSum);

            $boldStyle = [
                'font' => [
                    'bold' => true,
                ],
            ];
            $sheet->getStyle('F' . $currentRow . ':H' . $currentRow)->applyFromArray($boldStyle);

            $numberFormat = '#,##0.00';
            $sheet->getStyle('G7:G' . $currentRow)->getNumberFormat()->setFormatCode($numberFormat);
            $sheet->getStyle('H7:H' . $currentRow)->getNumberFormat()->setFormatCode($numberFormat);

                // Auto-size columns for better visibility
            foreach (range('B', 'H') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $sheet->removeRow(7); // Remove the placeholder row

            $fileName = 'Updated_InventoryReport.xlsx';
            $filePath = WRITEPATH . 'uploads/' . $fileName;
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            return $this->response->download($filePath, null)->setFileName($fileName);
        }
    }

    private function generatePDFReport($products, $title, $pdfFilePath, $productsPerPage = 10)
    {
        $dompdf = new Dompdf();
        $dompdf->setPaper('A4', 'landscape');

        $htmlContent = "<h1 style='text-align: center;'>MINDORO AUTO PARTS</h1><h2 style='text-align: center;'>{$title}</h2>";
        $htmlContent .= "<style>th, td { text-align: center; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; }</style>";

        $pageCount = ceil(count($products) / $productsPerPage);
        for ($page = 0; $page < $pageCount; $page++) {
            if ($page > 0) {
                $htmlContent .= '<div style="page-break-after: always;"></div>'; // Add a page break after each full table except the last one
            }
            $htmlContent .= '<table>';
            $htmlContent .= '<tr><th>Product Name</th><th>Description</th><th>Remarks</th><th>Unit Cost</th><th>Quantity in Stocks</th><th>Unit of Measurement</th><th>Total Cost</th><th>Total Inventory Net of VAT</th></tr>';

            $start = $page * $productsPerPage;
            $end = min(($start + $productsPerPage), count($products));
            for ($i = $start; $i < $end; $i++) {
                $product = $products[$i];
                $htmlContent .= '<tr>';
                $htmlContent .= '<td>' . htmlspecialchars($product['name']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['description']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['remarks']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['unit_cost']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['quantity_in_stocks']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['unit_of_measurement']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['total_cost']) . '</td>';
                $htmlContent .= '<td>' . htmlspecialchars($product['total_inventory_net_vat']) . '</td>';
                $htmlContent .= '</tr>';
            }
            $htmlContent .= '</table>';
        }

        $dompdf->loadHtml($htmlContent);
        $dompdf->render();
        file_put_contents($pdfFilePath, $dompdf->output());
    }



    private function sendPDF($pdfFilePath, $fileName)
    {
        $fileContent = file_get_contents($pdfFilePath);
        if ($fileContent === false) {
            return $this->failNotFound('Failed to read PDF file.');
        }

        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'inline; filename="' . $fileName . '.pdf"');
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $this->response->setHeader('Accept-Ranges', 'bytes');

        return $this->response->setBody($fileContent);
    }




}
