<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\API\ResponseTrait;
use Config\Services;

class PointOfSaleController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;

    public function __construct()
    {
        // Initialize models using the service
        $this->userModel = Services::userModel();
        $this->sessionModel = Services::sessionModel();
        $this->orderDetailsModel = Services::orderDetailsModel();
        $this->orderModel = Services::orderModel();
        $this->productModel = Services::productModel();
    }

    public function startSession()
    {
        try {
            $requestData = $this->request->getJSON(true);
            $token = $this->request->getHeaderLine('Authorization');
            $cashierData = extractDataFromToken($token);

            $sessionResult = $this->sessionModel->processStartSessionRequest($cashierData['userId'], $requestData);

            if (!$sessionResult['error']) {
                $orderData = [
                    'session_id' => $sessionResult['id'],
                ];
                $orderResult = $this->orderModel->createOrder($orderData);

                if (!$orderResult['error']) {
                    return apiResponse('success', [
                        'session_id' => $sessionResult['id'],
                        'order_id' => $orderResult['id']
                    ], 'Session started and order initialized successfully', 201);
                } else {
                    return apiErrorResponse('database_error', $orderResult['message'], 400);
                }
            } else {
                return apiErrorResponse('validation', $sessionResult['message'], 400);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage());
        }
    }



    public function fetchSessions()
    {
        try {

            $sessions = $this->sessionModel->fetchSessions($conditions = [], $options = []);

            if (!empty($sessions)) {
                return apiResponse('success', $sessions, 'Sessions fetched successfully', 200);
            } else {
                return apiErrorResponse('not_found', 'No sessions found', 404);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function getSessionDetails($sessionId)
    {
        try {
            $sessionDetails = $this->sessionModel->fetchAllOrdersWithDetailsFormatted($sessionId);

            if (!empty($sessionDetails)) {
                return apiResponse('success', $sessionDetails, 'Session details fetched successfully', 200);
            } else {
                return apiErrorResponse('not_found', 'Session not found', 404);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function addOrderDetailsAndUpdateTotal($orderId)
{
    $db = db_connect(); // Ensure you have the database connection
    log_message('info', 'Starting addOrderDetailsAndUpdateTotal function.');

    try {
        $requestData = $this->request->getJSON(true);
        log_message('debug', 'Request Data: ' . json_encode($requestData));

        // Extract variables directly from request data
        $orderId = $requestData['order_id'] ?? null;
        $productId = $requestData['product_id'] ?? null;
        $quantity = $requestData['quantity'] ?? null;

        if (!$orderId || !$productId || !$quantity) {
            log_message('error', 'Validation Error: Missing order ID, product ID, or quantity.');
            return apiErrorResponse('validation', 'Missing order ID, product ID, or quantity.', 400);
        }

        $db->transBegin(); // Start transaction

        // Check if there is enough stock before adding or updating the order detail
        $product = $db->table('product')
                      ->where('id', $productId)
                      ->get()
                      ->getRowArray();

        if (!$product) {
            log_message('error', 'Product not found for ID: ' . $productId);
            $db->transRollback();
            return apiErrorResponse('stock_error', 'Product not found for ID: ' . $productId, 404);
        }

        if ($product['quantity'] < $quantity) {
            log_message('error', 'Insufficient stock for product ID: ' . $productId);
            $db->transRollback();
            return apiErrorResponse('stock_error', 'Insufficient stock for product ID: ' . $productId, 400);
        }

        // Check if an order detail for this product already exists
        $existingDetail = $db->table('order_details')
                             ->where('order_id', $orderId)
                             ->where('product_id', $productId)
                             ->get()
                             ->getRowArray();

        if ($existingDetail) {
            // Update the existing order detail
            $newQuantity = $existingDetail['quantity'] + $quantity;
            $db->table('order_details')
               ->where('id', $existingDetail['id'])
               ->update(['quantity' => $newQuantity, 'total_price' => $newQuantity * $product['price']]);
            log_message('info', 'Updated existing order detail for Product ID: ' . $productId);
        } else {
            // Add a new order detail
            $detail = [
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'total_price' => $quantity * $product['price']  // Assuming price is directly from product table
            ];
            $db->table('order_details')->insert($detail);
            log_message('info', 'Inserted new order detail: ' . json_encode($detail));
        }

        // Deduct the quantity from the product stock
        $newProductQuantity = $product['quantity'] - $quantity;
        $db->table('product')
           ->where('id', $productId)
           ->update(['quantity' => $newProductQuantity]);

        // Update order total price
        $currentTotal = $db->table('orders')
                           ->select('total_price')
                           ->where('id', $orderId)
                           ->get()
                           ->getRow()->total_price;
        $newTotal = $currentTotal + ($quantity * $product['price']);
        $db->table('orders')
           ->where('id', $orderId)
           ->update(['total_price' => $newTotal]);

        log_message('info', 'Updated order total price to: ' . $newTotal);

        if ($db->transStatus() === false) {
            log_message('error', 'Transaction failed and was rolled back for Order ID: ' . $orderId);
            $db->transRollback();
            return apiErrorResponse('internal_error', 'Transaction failed and was rolled back.', 500);
        } else {
            $db->transCommit();
            log_message('info', 'Transaction committed successfully for Order ID: ' . $orderId);
            return apiResponse('success', ['total_price' => $newTotal], 'Order details added and total price updated successfully.', 200);
        }
    } catch (\Exception $e) {
        log_message('error', 'Exception occurred: ' . $e->getMessage());
        $db->transRollback();
        return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
    }
}




    public function updateOrderDetails($orderId)
{
    $db = db_connect(); // Get the database connection

    // Retrieve the single order detail from the request JSON body
    $detail = $this->request->getJSON(true);
    if (!$detail || !isset($detail['product_id'], $detail['quantity'])) {
        return $this->fail('No order details provided or missing essential information.', 400);
    }

    try {
        $db->transBegin();

        // Get the current order detail using the provided detail ID and the order ID
        $orderDetail = $db->table('order_details')
                          ->getWhere(['id' => $detail['id'], 'order_id' => $orderId])
                          ->getRowArray();

        if (!$orderDetail) {
            $db->transRollback();
            return $this->failNotFound('Order detail not found.');
        }

        $currentQuantity = $orderDetail['quantity'];
        $newQuantity = $detail['quantity'];
        $difference = $newQuantity - $currentQuantity;

        // Check product availability
        $product = $db->table('product')
                      ->getWhere(['id' => $detail['product_id']])
                      ->getRowArray();

        if (!$product || ($difference > 0 && $product['quantity'] < $difference)) {
            $db->transRollback();
            return $this->fail('Insufficient product inventory for the update.', 400);
        }

        // Update the order detail with the new quantity and recalculated total price
        $db->table('order_details')
           ->where('id', $orderDetail['id'])
           ->update([
               'quantity' => $newQuantity,
               'total_price' => ($orderDetail['total_price'] / $currentQuantity) * $newQuantity
           ]);

        // Update product inventory
        $newProductQuantity = $product['quantity'] - $difference;
        $db->table('product')
           ->where('id', $product['id'])
           ->update(['quantity' => $newProductQuantity]);

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->failServerError('Transaction failed and was rolled back.');
        } else {
            $db->transCommit();
            return $this->respondUpdated('Order details and product inventory successfully updated.');
        }
    } catch (\Exception $e) {
        $db->transRollback();
        return $this->failServerError('An error occurred: ' . $e->getMessage());
    }
}

public function deleteOrder($orderId)
{
    $db = db_connect(); // Get the database connection

    try {
        $db->transBegin();

        // Retrieve all order details associated with the order to update product quantities
        $orderDetails = $db->table('order_details')
                           ->where('order_id', $orderId)
                           ->get()
                           ->getResultArray();

        if (!$orderDetails) {
            $db->transRollback();
            return $this->failNotFound('No order details found for this order.');
        }

        // Update product quantities for each order detail
        foreach ($orderDetails as $detail) {
            $product = $db->table('product')
                          ->where('id', $detail['product_id'])
                          ->get()
                          ->getRowArray();

            if ($product) {
                $newQuantity = $product['quantity'] + $detail['quantity'];
                $db->table('product')
                   ->where('id', $product['id'])
                   ->update(['quantity' => $newQuantity]);
            }
        }

        // Delete the order details first (foreign key constraints)
        $db->table('order_details')
           ->where('order_id', $orderId)
           ->delete();

        // Delete the order
        $deleteResult = $db->table('orders')
                           ->where('id', $orderId)
                           ->delete();

        if (!$deleteResult) {
            $db->transRollback();
            return $this->failServerError('Failed to delete the order.');
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->failServerError('Transaction failed and was rolled back.');
        } else {
            $db->transCommit();
            return $this->respondDeleted('Order and its details successfully deleted and product quantities updated.');
        }
    } catch (\Exception $e) {
        $db->transRollback();
        return $this->failServerError('An error occurred: ' . $e->getMessage());
    }
}

public function deleteOrderDetailAndUpdateOrder($orderDetailId, $orderId)
{
    $db = db_connect(); // Get the database connection

    try {
        $db->transBegin();

        // Retrieve the specific order detail
        $orderDetail = $db->table('order_details')
                          ->where('id', $orderDetailId)
                          ->get()
                          ->getRowArray();

        if (!$orderDetail) {
            $db->transRollback();
            return $this->failNotFound('Order detail not found.');
        }

        // Update product quantities
        $product = $db->table('product')
                      ->where('id', $orderDetail['product_id'])
                      ->get()
                      ->getRowArray();

        if ($product) {
            $newQuantity = $product['quantity'] + $orderDetail['quantity'];
            $db->table('product')
               ->where('id', $product['id'])
               ->update(['quantity' => $newQuantity]);
        }

        // Delete the specific order detail
        $db->table('order_details')
           ->where('id', $orderDetailId)
           ->delete();

        // Check if there are any remaining order details for this order
        $remainingDetails = $db->table('order_details')
                               ->where('order_id', $orderId)
                               ->countAllResults();

        if ($remainingDetails == 0) {
            // Delete the order if there are no remaining details
            $deleteResult = $db->table('orders')
                               ->where('id', $orderId)
                               ->delete();

            if (!$deleteResult) {
                $db->transRollback();
                return $this->failServerError('Failed to delete the order.');
            }
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->failServerError('Transaction failed and was rolled back.');
        } else {
            $db->transCommit();
            return $this->respondDeleted('Order detail deleted and product quantities updated. Order deleted if no remaining details.');
        }
    } catch (\Exception $e) {
        $db->transRollback();
        return $this->failServerError('An error occurred: ' . $e->getMessage());
    }
}

public function deleteOrderDetailAndUpdateQuantity($orderDetailId)
{
    $db = db_connect(); // Get the database connection

    try {
        $db->transBegin();

        // Retrieve the specific order detail
        $orderDetail = $db->table('order_details')
                          ->where('id', $orderDetailId)
                          ->get()
                          ->getRowArray();

        if (!$orderDetail) {
            $db->transRollback();
            return $this->failNotFound('Order detail not found.');
        }

        // Update product quantities
        $product = $db->table('product')
                      ->where('id', $orderDetail['product_id'])
                      ->get()
                      ->getRowArray();

        if ($product) {
            $newQuantity = $product['quantity'] + $orderDetail['quantity'];
            $db->table('product')
               ->where('id', $product['id'])
               ->update(['quantity' => $newQuantity]);
        }

        // Delete the specific order detail
        $db->table('order_details')
           ->where('id', $orderDetailId)
           ->delete();

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->failServerError('Transaction failed and was rolled back.');
        } else {
            $db->transCommit();
            return $this->respondDeleted('Order detail deleted and product quantities updated.');
        }
    } catch (\Exception $e) {
        $db->transRollback();
        return $this->failServerError('An error occurred: ' . $e->getMessage());
    }
}

public function clearAllOrderDetails($orderId)
{
    $db = db_connect(); // Get the database connection

    try {
        $db->transBegin();

        // Retrieve all order details associated with the order
        $orderDetails = $db->table('order_details')
                           ->where('order_id', $orderId)
                           ->get()
                           ->getResultArray();

        if (!$orderDetails) {
            $db->transRollback();
            return $this->failNotFound('No order details found for this order.');
        }

        // Update product quantities for each order detail and delete the detail
        foreach ($orderDetails as $detail) {
            $product = $db->table('product')
                          ->where('id', $detail['product_id'])
                          ->get()
                          ->getRowArray();

            if ($product) {
                $newQuantity = $product['quantity'] + $detail['quantity'];
                $db->table('product')
                   ->where('id', $product['id'])
                   ->update(['quantity' => $newQuantity]);
            }

            // Delete the order detail
            $db->table('order_details')
               ->where('id', $detail['id'])
               ->delete();
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->failServerError('Transaction failed and was rolled back.');
        } else {
            $db->transCommit();
            return $this->respondDeleted('All order details cleared and product quantities updated.');
        }
    } catch (\Exception $e) {
        $db->transRollback();
        return $this->failServerError('An error occurred: ' . $e->getMessage());
    }
}







    public function finalizeOrder()
    {
        try {
            $requestData = $this->request->getJSON(true);

            if (!isset($requestData['orderId'], $requestData['tendered'])) {
                return apiErrorResponse('validation', 'Missing required fields: orderId or tendered.', 400);
            }

            $orderId = $requestData['orderId'];
            $tendered = $requestData['tendered'];
            $customerId = $requestData['customerId'] ?? null;

            $result = $this->orderModel->finalizeOrder($orderId, $tendered, $customerId);

            if (!$result['error']) {
                return apiResponse('success', ['change' => $result['change']], 'Order finalized successfully.', 200);
            } else {
                return apiErrorResponse('database_error', $result['message'], 400);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function voidSingleOrder($orderId)
    {
        $this->db->transBegin();

        $statusResult = $this->orderModel->voidOrderStatus($orderId);
        if ($statusResult['error']) {
            $this->db->transRollback();
            return apiErrorResponse('database_error', $statusResult['message'], 400);
        }

        $detailsResult = $this->orderDetailsModel->softDeleteOrderDetails($orderId);
        if ($detailsResult['error']) {
            $this->db->transRollback();
            return apiErrorResponse('database_error', $detailsResult['message'], 400);
        }

        $restoreResult = $this->productModel->restoreProductQuantitiesForOrder($orderId, $this->orderDetailsModel);
        if ($restoreResult['error']) {
            $this->db->transRollback();
            return apiErrorResponse('database_error', $restoreResult['message'], 400);
        }

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            return apiErrorResponse('internal_error', 'Transaction failed and was rolled back.', 500);
        } else {
            $this->db->transCommit();
            return apiResponse('success', null, 'Order voided successfully.', 200);
        }
    }

    public function createNewOrder()
    {
        try {
            $requestData = $this->request->getJSON(true);

            $orderData = [
                'session_id' => $requestData['id'],
            ];

            $orderResult = $this->orderModel->createOrder($orderData);

            if (!$orderResult['error']) {
                return apiResponse('success', [
                    'order_id' => $orderResult['id']
                ], 'New order initialized successfully', 201);
            } else {
                return apiErrorResponse('database_error', $orderResult['message'], 400);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function addOrderDetails($orderId)
    {
        try {
            $requestData = $this->request->getJSON(true);

            if (!isset($requestData['product_id'], $requestData['quantity'])) {
                return apiErrorResponse('validation', 'Missing required fields: product_id or quantity.', 400);
            }

            // Fetch product details from ProductModel
            $product = $this->productModel->find($requestData['product_id']);
            if (!$product) {
                return apiErrorResponse('not_found', 'Product not found', 404);
            }

            $productPrice = $product['price'];
            $productName = $product['name'];
            $totalPrice = $productPrice * $requestData['quantity'];

            // Start transaction
            $this->orderDetailsModel->db->transStart();

            // Check if order detail already exists for this product
            $existingDetail = $this->orderDetailsModel->where([
                'order_id' => $orderId,
                'product_id' => $requestData['product_id']
            ])->first();

            if ($existingDetail) {
                // Update the existing record
                $newQuantity = $existingDetail['quantity'] + $requestData['quantity'];
                $newTotalPrice = $productPrice * $newQuantity;
                $this->orderDetailsModel->update($existingDetail['id'], [
                    'quantity' => $newQuantity,
                    'total_price' => $newTotalPrice
                ]);
            } else {
                // Insert new order detail
                $this->orderDetailsModel->insert([
                    'order_id' => $orderId,
                    'product_id' => $requestData['product_id'],
                    'quantity' => $requestData['quantity'],
                    'total_price' => $totalPrice
                ]);
            }

            // Update the total price in the order model
            $newOrderTotal = $this->orderDetailsModel
                ->selectSum('total_price')
                ->where('order_id', $orderId)
                ->get()
                ->getRow()
                ->total_price;

            $this->orderModel->update($orderId, ['total_price' => $newOrderTotal]);

            // Commit transaction
            $this->orderDetailsModel->db->transComplete();

            if ($this->orderDetailsModel->db->transStatus() === false) {
                return apiErrorResponse('database_error', 'Transaction failed.', 500);
            } else {
                return apiResponse('success', [
                    'order_id' => $orderId,
                    'product_id' => $requestData['product_id'],
                    'quantity' => isset($newQuantity) ? $newQuantity : $requestData['quantity'],
                    'total_price' => isset($newTotalPrice) ? $newTotalPrice : $totalPrice,
                    'product_name' => $productName,
                    'order_total' => $newOrderTotal
                ], 'Order details updated successfully.', 200);
            }
        } catch (\Exception $e) {
            $this->orderDetailsModel->db->transRollback();
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function getOrderDetails($orderId)
    {
        try {
            if (empty($orderId)) {
                return apiErrorResponse('validation', 'Order ID is required.', 400);
            }

            // Start by fetching the total price from the order model
            $order = $this->orderModel->select('total_price')->find($orderId);
            if (!$order) {
                return apiErrorResponse('not_found', 'Order not found', 404);
            }

            // Fetch order details and join with the products table to get product names and prices
            $orderDetails = $this->orderDetailsModel
                ->select('order_details.*, product.name as product_name, product.price as price')
                ->join('product', 'product.id = order_details.product_id')
                ->where('order_details.order_id', $orderId)
                ->findAll();

            if (!empty($orderDetails)) {
                $response = [
                    'total_price' => $order['total_price'],
                    'details' => $orderDetails
                ];
                return apiResponse('success', $response, 'Order details fetched successfully.', 200);
            } else {
                // If there are no order details, still return the total price with an empty details list
                return apiResponse('success', ['total_price' => $order['total_price'], 'details' => []], 'No order details found, but order exists.', 200);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }


    public function addOrderPayment($orderId)
    {
        try {
            $requestData = $this->request->getJSON(true);

            // Validate required payment fields
            if (!isset($requestData['payment']) || !isset($requestData['change'])) {
                return apiErrorResponse('validation', 'Missing required fields: payment or change.', 400);
            }

            // Fetch the existing order to update
            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return apiErrorResponse('not_found', 'Order not found', 404);
            }

            $orderDetails = $this->orderDetailsModel->where('order_id', $orderId)->findAll();

            log_message('debug', print_r($orderDetails, true));

            foreach ($orderDetails as $detail) {
                $result = $this->productModel->deductProductQuantity($detail['product_id'], $detail['quantity']);
                
                if ($result['error']) {
                    // Handle error (e.g., log the error or roll back the transaction)
                    log_message('error', $result['message']);
                    // Optionally, you can break the loop if an error occurs
                    break;
                } else {
                    log_message('info', $result['message']);
                }
            }

            // Prepare the data to update
            $updateData = [
                'tendered' => $requestData['payment'],
                'change' => $requestData['change']
            ];

            // Update the order with payment information
            if ($this->orderModel->update($orderId, $updateData)) {
                return apiResponse('success', $updateData, 'Payment details added successfully.', 200);
            } else {
                return apiErrorResponse('database_error', 'Failed to update payment details.', 400);
            }
        } catch (\Exception $e) {
            return apiErrorResponse('internal_error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }
}
