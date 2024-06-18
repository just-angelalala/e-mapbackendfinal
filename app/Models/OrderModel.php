<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['customer_id', 'session_id', 'order_date', 'status', 'total_price', 'tendered', 'change', 'gcash_receipt_photo', 'feedback', 'feedback_photo', 'rating'];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function createOrder($data) {
        $latestOrder = $this->getLatestOrder();
    
        $orderData = [
            'session_id' => $data['session_id'],
            'order_date' => date('Y-m-d H:i:s'),
            'status' => 'unpaid',
        ];
    
        if ($latestOrder && $latestOrder['total_price'] == 0) {
            $result = updateData(
                $this,
                ['id' => $latestOrder['id']],
                $orderData,
                'Failed to update order.',
                $this,
                false
            );
    
            if (!$result['error']) {
                $result['id'] = $latestOrder['id'];
            }
    
            return $result;
        } else {
            $result = insertData(
                $this,
                $orderData,
                'Failed to create order.',
                $this,
                false,
                true
            );
    
            return $result;
        }
    }

    public function createOrderEcommerce($userId, $gcashReceipt, $orderData)
{
    log_message('debug', print_r($orderData, true));
    $data = [
        'customer_id' => $userId,
        'order_date' => date('Y-m-d H:i:s'), // Current date and time
        'status' => $orderData['status'] ?? 'pending', // Default status if not provided
        'gcash_receipt_photo' => $gcashReceipt, // Handle the receipt photo
        'total_price' => $orderData['subtotal']
    ];

    return $this->insert($data);
}

public function getOrdersWithDetails()
{
    // Start the query builder
    $builder = $this->db->table($this->table);

    // Select orders with required fields including feedback, feedback_photo, and rating
    $builder->select('orders.id AS order_id, orders.order_date, orders.status, orders.total_price, ' .
                    'orders.gcash_receipt_photo, orders.feedback, orders.feedback_photo, orders.rating, ' .
                    'users.id AS customer_id, users.first_name, users.last_name, users.email, ' .
                    'DATE_ADD(orders.order_date, INTERVAL 5 DAY) AS valid_date')
            ->join('users', 'orders.customer_id = users.id', 'left')  // Join with user table
            ->select("'' AS order_details")  // Placeholder for order details
            ->where('orders.session_id IS NULL')  // Check if session_id is NULL
            ->where('orders.customer_id IS NOT NULL')  // Ensure there is a customer tied to the order
            ->where('orders.status !=', 'unpaid');  // Exclude orders with status 'unpaid'

    // Execute the query
    $query = $builder->get();
    if (!$query) {
        // Handle error; possibly log it and return or throw an exception
        log_message('error', 'Failed to fetch orders.');
        return [];  // Or handle as appropriate
    }

    $orders = $query->getResultArray();

    // Populate order details and restructure the result set
    foreach ($orders as &$order) {
        $order['order_details'] = $this->getOrderDetailsByOrderId($order['order_id']);
        $order['customer'] = [
            'customer_id' => $order['customer_id'],
            'first_name' => $order['first_name'],
            'last_name' => $order['last_name'],
            'email' => $order['email'],
        ];

        // Remove now redundant individual customer fields
        unset($order['customer_id'], $order['first_name'], $order['last_name'], $order['email']);
    }

    return $orders;
}

public function getOrdersByStatusAndDate($status, $days)
{
    $dateThreshold = date('Y-m-d H:i:s', strtotime("-$days days"));

    return $this->where('status', $status)
                ->where('order_date <=', $dateThreshold)
                ->findAll();
}



    public function getCustomerWithOrders($userId)
    {
        // Start the query builder
        $builder = $this->db->table('users');
    
        // Select the customer with required fields
        $builder->select('users.id AS customer_id, users.first_name, users.last_name, users.email')
                ->select("'' AS orders")  // Placeholder for orders
                ->where('users.id', $userId);  // Filter by the specific user ID
    
        // Execute the query
        $query = $builder->get();
        if (!$query) {
            // Handle error; possibly log it and return or throw an exception
            log_message('error', 'Failed to fetch customer with ID: ' . $userId);
            return [];  // Or handle as appropriate
        }
    
        $customer = $query->getRowArray();  // Fetch a single row
    
        if ($customer) {
            // Populate orders for the customer
            $customer['orders'] = $this->getOrdersByCustomerId($customer['customer_id']);
        }
    
        return $customer;
    }

private function getOrdersByCustomerId($customerId)
{
    // Start the query builder for orders
    $builder = $this->db->table('orders');

    // Select orders along with required fields
    $builder->select('orders.id AS order_id, orders.order_date, orders.status, orders.gcash_receipt_photo, orders.total_price, orders.feedback, orders.rating, orders.feedback_photo,  DATE_ADD(orders.order_date, INTERVAL 5 DAY) AS valid_date')
            ->where('orders.customer_id', $customerId);

    // Execute the query
    $query = $builder->get();
    if (!$query) {
        // Handle error; possibly log it and return or throw an exception
        log_message('error', 'Failed to fetch orders for customer_id: ' . $customerId);
        return [];  // Or handle as appropriate
    }

    $orders = $query->getResultArray();

    // Populate order details for each order
    foreach ($orders as &$order) {
        $order['order_details'] = $this->getOrderDetailsByOrderId($order['order_id']);
    }

    return $orders;
}

private function getOrderDetailsByOrderId($orderId)
{
    // Start the query builder for order details
    $builder = $this->db->table('order_details');

    // Select order details along with product name
    $builder->select('order_details.id, order_details.order_id, order_details.product_id, order_details.quantity, order_details.total_price, ' .
                     'product.name AS product_name')
            ->join('product', 'order_details.product_id = product.id', 'left')
            ->where('order_details.order_id', $orderId);

    // Execute the query
    $query = $builder->get();
    if (!$query) {
        // Handle error; possibly log it and return or throw an exception
        log_message('error', 'Failed to fetch order details for order_id: ' . $orderId);
        return [];  // Or handle as appropriate
    }

    return $query->getResultArray();
}



    
    public function orderExists($orderId)
    {
        return $this->where('id', $orderId)->first() !== null;
    }

    public function updateOrderTotalPrice($orderId, $orderDetailsModel) {
        $details = $orderDetailsModel->where('order_id', $orderId)->findAll();
        
        $totalPrice = array_sum(array_map(function ($detail) {
            return $detail['total_price'] * $detail['quantity'];
        }, $details));
    
        $updateData = ['total_price' => $totalPrice];
        $condition = ['id' => $orderId];
        
        $result = updateData($this, $condition, $updateData, 'Failed to update order total price.', $this);
        
        if (!$result['error']) {
            $result['total_price'] = $totalPrice;
        }
    
        return $result;
    }

    public function finalizeOrder($orderId, $tendered, $customerId = null) {
        $order = $this->find($orderId);
        if (!$order) {
            return ['error' => true, 'message' => "Order with ID $orderId not found."];
        }
    
        $change = max(0, $tendered - $order['total_price']);
    
        $dataToUpdate = [
            'status' => 'paid',
            'tendered' => $tendered,
            'change' => $change,
        ];
    
        if ($customerId !== null) {
            $dataToUpdate['customer_id'] = $customerId;
        }
    
        $result = updateData(
            $this,                          
            ['id' => $orderId],             
            $dataToUpdate,                  
            'Failed to finalize the order', 
            $this                           
        );
    
        if ($result['error']) {
            return $result;
        } else {
            return ['error' => false, 'message' => "Order finalized successfully.", 'change' => $change];
        }
    }   

    public function voidOrderStatus($orderId) {
        return updateData($this, ['id' => $orderId], ['status' => 'void'], 'Failed to void the order.', $this);
    }
    
    public function getLatestOrder() {
        $latestOrder = $this->orderBy('created_at', 'DESC')
                            ->first();
    
        return $latestOrder;
    }

    public function updateFeedbackAndPhoto(int $orderId, string $feedback, $feedbackPhoto = null, $rating): bool
    {
        $data = [
            'feedback' => $feedback,
            'rating' => $rating,
            'status' => 'Reviewed'
        ];

        // Add feedback photo to the update only if provided
        if (!is_null($feedbackPhoto) && $feedbackPhoto !== '') {
            $data['feedback_photo'] = $feedbackPhoto;
        }

        return $this->update($orderId, $data);
    }


    public function getFeedbacks()
    {
        $builder = $this->db->table($this->table);
        // Use COALESCE to return 0 if rating is null
        $builder->select('orders.id, orders.feedback, COALESCE(orders.rating, 0) as rating, users.first_name, users.last_name, orders.feedback_photo');
        $builder->join('users', 'users.id = orders.customer_id');
        $builder->where('orders.feedback IS NOT NULL'); // Ensure only feedbacks that exist are fetched
        $builder->where('orders.status', 'Reviewed'); // Only fetch finished orders
        $query = $builder->get();
    
        return $query->getResultArray(); // Change this if you prefer different return types
    }
    

    public function updateStatusToFinished(int $orderId): bool
{
    $dataToUpdate = ['status' => 'Finished'];

    // Attempt to update the order
    $updateResult = $this->update($orderId, $dataToUpdate);

    if (!$updateResult) {
        log_message('error', "Failed to update status for Order ID: $orderId to 'Finished'");
        return false;
    }

    return true;
}

public function getOrderCounts() {
    $ordersTable = $this->db->table('orders');
    $usersTable = $this->db->table('users');

    // Define current date parameters
    $currentYear = date('Y');
    $currentMonth = date('m');
    $startOfMonth = date('Y-m-01'); // First day of the current month
    $endOfToday = date('Y-m-d 23:59:59'); // End of today

    // Get total online and shop orders
    $totalOnlineOrders = $ordersTable->where('session_id', null)->countAllResults();
    $totalShopOrders = $ordersTable->where('session_id !=', null)->countAllResults();

    // Get monthly online and shop orders
    $monthlyOnlineOrders = $ordersTable->where('session_id', null)
                                       ->where('order_date >=', $startOfMonth)
                                       ->where('order_date <=', $endOfToday)
                                       ->countAllResults();
    $monthlyShopOrders = $ordersTable->where('session_id !=', null)
                                     ->where('order_date >=', $startOfMonth)
                                     ->where('order_date <=', $endOfToday)
                                     ->countAllResults();

    // Calculate total sales this month
    $totalSales = $ordersTable->selectSum('total_price')
                              ->where('order_date >=', $startOfMonth)
                              ->where('order_date <=', $endOfToday)
                              ->get()
                              ->getRow()->total_price;

    // Calculate average sales per day
    $numDays = date('j'); // Number of days from the 1st to today
    $averageSalesPerDay = ($totalSales && $numDays) ? $totalSales / $numDays : 0;

    // Count newly registered accounts this month
    $newAccountsThisMonth = $usersTable->where('created_at >=', $startOfMonth)
                                       ->where('created_at <=', $endOfToday)
                                       ->countAllResults();

    return [
        'totalOnlineOrders' => $totalOnlineOrders,
        'totalShopOrders' => $totalShopOrders,
        'monthlyOnlineOrders' => $monthlyOnlineOrders,
        'monthlyShopOrders' => $monthlyShopOrders,
        'averageSalesPerDay' => $averageSalesPerDay,
        'newAccountsThisMonth' => $newAccountsThisMonth
    ];
}

public function getBestSellingProductsByQuantity() {
    $builder = $this->db->table('order_details');
    $builder->select('product.name, category.name as category_name, SUM(order_details.quantity) as total_quantity');
    $builder->join('product', 'product.id = order_details.product_id');
    $builder->join('category', 'category.id = product.category_id'); // Joining the category table
    $builder->groupBy('product.id'); // Group by product.id to avoid duplication
    $builder->orderBy('total_quantity', 'DESC');
    $query = $builder->get();

    return $query->getResultArray();
}

public function getBestSellingProductsByRevenue() {
    $builder = $this->db->table('order_details');
    $builder->select('product.name, category.name as category_name, SUM(order_details.total_price) as total_revenue');
    $builder->join('product', 'product.id = order_details.product_id');
    $builder->join('category', 'category.id = product.category_id'); // Joining the category table
    $builder->groupBy('product.id'); // Group by product.id to avoid duplication
    $builder->orderBy('total_revenue', 'DESC');
    $query = $builder->get();

    return $query->getResultArray();
}

public function getMonthlySalesData() {
    $currentYear = date('Y');

    $builder = $this->db->table('orders');
    $builder->select('MONTH(order_date) as month, 
                      SUM(CASE WHEN session_id IS NULL THEN total_price ELSE 0 END) as online_sales,
                      SUM(CASE WHEN session_id IS NOT NULL THEN total_price ELSE 0 END) as shop_sales');
    $builder->where('YEAR(order_date)', $currentYear);
    $builder->groupBy('MONTH(order_date)');
    $builder->orderBy('MONTH(order_date)', 'asc');

    $query = $builder->get();
    return $query->getResultArray();
}

public function getDailySalesData() {
    $currentYear = date('Y');

    $builder = $this->db->table('orders');
    $builder->select('DATE(order_date) as day, 
                      SUM(CASE WHEN session_id IS NULL THEN total_price ELSE 0 END) as online_sales,
                      SUM(CASE WHEN session_id IS NOT NULL THEN total_price ELSE 0 END) as shop_sales');
    $builder->where('YEAR(order_date)', $currentYear);
    $builder->groupBy('DATE(order_date)');
    $builder->orderBy('DATE(order_date)', 'asc');

    $query = $builder->get();
    return $query->getResultArray();
}





    

}
