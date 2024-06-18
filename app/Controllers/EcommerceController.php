<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Config\Services;

class EcommerceController extends ResourceController
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

    public function checkout()
    {
        $json = $this->request->getJSON();
        log_message('debug', print_r($json, true));

        // Check product quantities without updating
        if (!$this->productModel->checkQuantities($json->details)) {
            return $this->fail('Insufficient stock for one or more products.');
        }

        // Begin transaction
        $this->productModel->transStart();

        // Create order with a status indicating it's pending approval
        $orderId = $this->orderModel->createOrderEcommerce($json->user_id, $json->gcashReceipt, ['status' => 'pending_approval', 'subtotal' => $json->subtotal]);
        $this->userModel->updatePhoneNumber($json->user_id, $json->phoneNumber);

        if ($orderId) {
            $orderDetailsResult = $this->orderDetailsModel->addOrderDetailsEcommerce($orderId, $json->details);

            if ($orderDetailsResult) {
                // Decrement quantities for each product in the order
                foreach ($json->details as $detail) {
                    if (!$this->productModel->decrementQuantity($detail->product_id, $detail->quantity)) {
                        $this->productModel->transRollback();
                        return $this->failServerError('Failed to update product quantities');
                    }
                }

                // If all operations were successful, commit the transaction
                $this->productModel->transComplete();

                if ($this->productModel->transStatus() === FALSE) {
                    return $this->failServerError('Transaction failed');
                }

                return $this->respondCreated(['message' => 'Order successfully processed and awaiting approval']);
            } else {
                $this->productModel->transRollback();
                return $this->failServerError('Failed to add order details');
            }
        } else {
            $this->productModel->transRollback();
            return $this->failServerError('Failed to create order');
        }
    }

    public function showTopProduct($categoryName)
    {
        $topProduct = $this->productModel->getTopSellingProductsByCategory($categoryName);

        // Handle the topProduct, e.g., send it to a view or return as JSON
        return $this->response->setJSON(['data' => $topProduct]);
    }

    public function getAllProductsByCategory($categoryName)
    {
        $topProduct = $this->productModel->getAllProductsByCategory($categoryName);

        // Handle the topProduct, e.g., send it to a view or return as JSON
        return $this->response->setJSON(['data' => $topProduct]);
    }

    public function getGcashReceipt()
    {
        try {
            // Adjusting the allowed file types to typical image formats used for receipts
            $uploadResult = uploadGcashReceipt('gcashReceipt', 'receipts', 10240, ['jpg', 'jpeg', 'png', 'pdf']);
            
            // Response message updated to reflect the nature of the content - GCash receipts
            return apiResponse('success', ['fileNames' => $uploadResult['fileNames']], 'GCash receipts uploaded successfully', 201);
        } catch (\Exception $e) {
            // Ensuring the error response is appropriate for potential upload issues
            return apiErrorResponse('validation', $e->getMessage(), 400);
        }
    }

    public function setOrderForPickup()
    {
        // Get order data from the request
        $orderData = $this->request->getJSON(true);
    
        // Update order status to 'For Pickup'
        $this->orderModel->update($orderData['order_id'], ['status' => 'For Pickup']);
    
        // Retrieve customer's phone number
        $phoneNumber = $this->getCustomerPhoneNumber($orderData['order_id']);
    
        // Send SMS Notification
        if ($phoneNumber) {
            $this->sendSMSNotification($phoneNumber, 'Your order is now ready for pickup. Thank you for choosing MAP.');
        } else {
            return $this->respond(['message' => 'Order status updated to For Pickup but failed to retrieve customer phone number.'], 200);
        }
    
        return $this->respond(['message' => 'Order status updated to For Pickup and SMS notification sent.'], 200);
    }
    
    private function getCustomerPhoneNumber($orderId)
    {
        // Assuming you have models set up for your tables
        $db = \Config\Database::connect();
        $builder = $db->table('orders');
        $builder->select('users.phone_number');
        $builder->join('users', 'orders.customer_id = users.id');
        $builder->where('orders.id', $orderId);
        $query = $builder->get();
        $result = $query->getRow();
    
        if ($result) {
            return $result->phone_number;
        }
    
        return null;
    }
    
    private function sendSMSNotification($number, $message)
    {
        $ch = curl_init();
    
        $parameters = [
            'apikey' => getenv('SEMAPHORE_API_KEY'),
            'number' => $number,
            'message' => $message,
            'sendername' => 'SEMAPHORE'
        ];
    
        curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response = curl_exec($ch);
        curl_close($ch);
    
        return $response;
    }
    public function markOrderAsFinished()
    {
        // Get order data from the request
        $orderData = $this->request->getJSON(true);

        $this->orderModel->update($orderData['order_id'], ['status' => 'Finished']);
                // Retrieve customer's phone number
                $phoneNumber = $this->getCustomerPhoneNumber($orderData['order_id']);
    
                // Send SMS Notification
                if ($phoneNumber) {
                    $this->sendSMSNotification($phoneNumber, 'Your order is now finished.  Thank you for choosing MAP.');
                } else {
                    return $this->respond(['message' => 'Order status updated to For Pickup but failed to retrieve customer phone number.'], 200);
                }
        return $this->respond(['message' => 'Order status updated to For Pickup and quantities deducted.'], 200);
    }

    public function submitFeedback()
    {
        // Get order data from the request
        $orderData = $this->request->getJSON(true);
    
        // Update feedback and feedback photo
        $updateFeedback = $this->orderModel->updateFeedbackAndPhoto(
            $orderData['order_id'],
            $orderData['feedback'],
            $orderData['feedback_photo'] ?? null,  // Use null as a default value if not provided
            $orderData['rating']
        );
    
        if ($updateFeedback) {
            return $this->respond(['message' => 'Feedback submitted and order status updated to Reviewed.'], 200);
        } else {
            return $this->fail('Failed to update order.', 400);
        }
    }
    
    

    public function getOrdersWithDetails()
    {
        try {
            // Call the model function to get orders with details
            $orders = $this->orderModel->getOrdersWithDetails();

            if (empty($orders)) {
                // No orders found
                return $this->failNotFound('No orders found');
            }

            // Return the orders with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Orders with details fetched successfully',
                'data'     => $orders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching orders with details: ' . $e->getMessage());
        }
    }

    public function getOrdersWithDetailsForCustomer($userId)
    {
        try {
            // Call the model function to get orders with details
            $orders = $this->orderModel->getCustomerWithOrders($userId);

            if (empty($orders)) {
                // No orders found
                return $this->failNotFound('No orders found');
            }

            // Return the orders with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Orders with details fetched successfully',
                'data'     => $orders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching orders with details: ' . $e->getMessage());
        }
    }


    public function getFeedbacks()
    {
        try {
            // Fetch feedbacks from finished orders
            $feedbacks = $this->orderModel->getFeedbacks();

            if (empty($feedbacks)) {
                // No feedbacks found
                return $this->respond([
                    'status'   => ResponseInterface::HTTP_OK,
                    'error'    => null,
                    'messages' => 'No feedbacks found',
                    'data'     => []
                ]);
            }

            // Ensure null values are handled
            foreach ($feedbacks as &$feedback) {
                $feedback['first_name'] = $feedback['first_name'] ?? 'N/A';
                $feedback['last_name'] = $feedback['last_name'] ?? 'N/A';
                $feedback['feedback'] = $feedback['feedback'] ?? 'No feedback provided';
                $feedback['rating'] = $feedback['rating'] ?? 'No rating given';
            }

            // Return the feedbacks
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Feedbacks fetched successfully',
                'data'     => $feedbacks
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching feedbacks: ' . $e->getMessage());
        }
    }

    public function updateOrderStatusIfNotPickedUp()
    {
        // Get all orders that are "For Pickup" and where the order_date is more than 5 days ago
        $orders = $this->orderModel->getOrdersByStatusAndDate("For Pickup", 5);
    
        if (!empty($orders)) {
            foreach ($orders as $order) {
                // Fetch order details for the current order
                $orderDetails = $this->orderDetailsModel->where('order_id', $order['id'])->findAll();
    
                // Increment product quantities back to stock
                $this->productModel->incrementProductQuantities($orderDetails);
    
                // Update the status of each order to "Not Picked Up"
                $this->orderModel->update($order['id'], ['status' => 'Not Picked Up']);

                                // Retrieve customer's phone number
        $phoneNumber = $this->getCustomerPhoneNumber($orderData['order_id']);
    
        // Send SMS Notification
        if ($phoneNumber) {
            $this->sendSMSNotification($phoneNumber, 'Your order is not picked up.');
        } else {
            return $this->respond(['message' => 'Order status updated to For Pickup but failed to retrieve customer phone number.'], 200);
        }

            }
    
            return $this->respond(['message' => 'Orders updated to Not Picked Up and product quantities restored.'], ResponseInterface::HTTP_OK);
        } else {
            return $this->respond(['message' => 'No orders require updating.'], ResponseInterface::HTTP_OK);
        }
    }
    
    
    public function uploadFeedbackImage()
    {
        $files = $this->request->getFiles();

        if (!$files) {
            return $this->fail('No files uploaded', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $uploadedFiles = [];
        $uploadPath = FCPATH . 'uploads/feedback';

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

    // Dashboard
    
    public function getRecentlySoldProducts()
    {
        try {
            // Call the model function to get orders with details
            $orders = $this->productModel->fetchRecentlySoldProducts();

            if (empty($orders)) {
                // No orders found
                return $this->failNotFound('No orders found');
            }

            // Return the orders with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Orders with details fetched successfully',
                'data'     => $orders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching orders with details: ' . $e->getMessage());
        }
    }

    public function getCustomerCountByProvince()
    {
        try {
            // Call the model function to get orders with details
            $orders = $this->userModel->getCustomerCountByProvince();

            if (empty($orders)) {
                // No orders found
                return $this->failNotFound('No orders found');
            }

            // Return the orders with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Orders with details fetched successfully',
                'data'     => $orders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching orders with details: ' . $e->getMessage());
        }
    }

    public function getOrderCounts()
    {
        try {
            // Call the model function to get orders with details
            $orders = $this->orderModel->getOrderCounts();

            if (empty($orders)) {
                // No orders found
                return $this->failNotFound('No orders found');
            }

            // Return the orders with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Orders with details fetched successfully',
                'data'     => $orders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching orders with details: ' . $e->getMessage());
        }
    }

    public function getMonthlySalesData()
    {
        try {
            // Call the model function to get orders with details
            $orders = $this->orderModel->getDailySalesData();

            if (empty($orders)) {
                // No orders found
                return $this->failNotFound('No orders found');
            }

            // Return the orders with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Orders with details fetched successfully',
                'data'     => $orders
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching orders with details: ' . $e->getMessage());
        }
    }

    public function displayBestSellingProducts($basis = 'quantity')
    {
        try {
            // Validate the 'basis' parameter to ensure it matches 'quantity' or 'revenue'
            if (!in_array($basis, ['quantity', 'revenue'])) {
                return $this->fail('Invalid parameter for basis. Please use "quantity" or "revenue".', 400);
            }
    
            // Depending on the basis, call the respective model function
            $products = ($basis === 'revenue') ?
                        $this->orderModel->getBestSellingProductsByRevenue() :
                        $this->orderModel->getBestSellingProductsByQuantity();
    
            if (empty($products)) {
                // No products found
                return $this->failNotFound('No best-selling products found.');
            }
    
            // Return the products with details
            return $this->respond([
                'status'   => ResponseInterface::HTTP_OK,
                'error'    => null,
                'messages' => 'Best-selling products fetched successfully based on ' . $basis,
                'data'     => $products
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during fetch operation
            return $this->failServerError('An error occurred while fetching best-selling products: ' . $e->getMessage());
        }
    }
    


}
