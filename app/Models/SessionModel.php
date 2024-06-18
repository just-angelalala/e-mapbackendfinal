<?php

namespace App\Models;

use CodeIgniter\Model;

class SessionModel extends Model
{
    protected $table            = 'sessions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['cashier_id', 'start_time', 'end_time', 'initial_cash', 'closing_cash_manual', 'closing_cash_auto', 'status', 'notes'];

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

    /**
     * Processes a start session request.
     *
     * @param array $requestData The decoded JSON request data as an associative array.
     * @return array Result of the operation with 'error' and 'message' keys, and optionally 'id'.
     */
    public function processStartSessionRequest($cashierData, $requestData)
    {
        if (!isset($cashierData) || !isset($requestData['initial_cash'])) {
            return ['error' => true, 'message' => 'Missing required fields: cashier_id, initial_cash'];
        }

        // Extracting necessary data
        $cashierId = $cashierData;
        $initialCash = $requestData['initial_cash'];
        $notes = $requestData['notes'] ?? null;

        // Proceed to start the session
        $result = $this->startSession($cashierId, $initialCash, $notes);

        return $result;
    }

    public function startSession($cashierId, $initialCash, $notes = null)
    {
        $data = [
            'cashier_id' => $cashierId,
            'start_time' => date('Y-m-d H:i:s'),
            'initial_cash' => $initialCash,
            'status' => 'open',
            'notes' => $notes
        ];

        $result = insertData($this, $data, 'Failed to start session', $this, false, true);

        return $result;
    }

    public function fetchSessions($conditions = [], $options = [])
    {
        return fetchData($this, $conditions, $options);
    }

    public function fetchSessionWithDetails($sessionId)
    {
        $options = [
            'joins' => [
                [
                    'table' => 'orders',
                    'condition' => 'sessions.id = orders.session_id',
                    'type' => 'left'
                ],
                [
                    'table' => 'order_details',
                    'condition' => 'orders.id = order_details.order_id',
                    'type' => 'left'
                ]
            ],
            'orderBy' => 'orders.order_date DESC',
        ];

        $conditions = ['sessions.id' => $sessionId];

        return fetchData($this, $conditions, $options);
    }

    public function fetchAllOrdersWithDetailsFormatted($sessionId)
    {

        $builder = $this->db->table('orders');
        $builder->select('orders.id AS order_id, orders.total_price AS order_total_price, orders.order_date, 
                        order_details.quantity, order_details.total_price AS detail_total_price, 
                        product.name AS product_name, users.first_name, users.last_name');
        $builder->join('order_details', 'orders.id = order_details.order_id', 'left');
        $builder->join('product', 'order_details.product_id = product.id', 'left');
        $builder->join('users', 'orders.customer_id = users.id', 'left');
        $builder->where('orders.session_id', $sessionId);
        $builder->orderBy('orders.id', 'ASC');

        $query = $builder->get();
        $orders = $query->getResultArray();

        $formattedOrders = [];

        foreach ($orders as $order) {
            // Convert order date string to DateTime object
            $orderDateTime = new \DateTime($order['order_date']);

            // Format time as desired
            $orderTimeFormatted = $orderDateTime->format('g:i A');

            $orderIdKey = "order-{$order['order_id']}";
            if (!isset($formattedOrders[$orderIdKey])) {
                $customerName = "{$order['first_name']} {$order['last_name']}";
                $formattedOrders[$orderIdKey] = [
                    'key' => $orderIdKey,
                    'data' => [
                        'name' => "{$order['order_id']}",
                        'size' => "{$order['order_total_price']}",
                        'type' => $orderTimeFormatted,
                        'customer' => "{$customerName}"
                    ],
                    'children' => []
                ];
            }

            // Check if there are order details
            if (!empty($order['quantity']) && !empty($order['product_name'])) {
                $formattedOrders[$orderIdKey]['children'][] = [
                    'key' => "{$orderIdKey}-detail",
                    'data' => [
                        'name' => "{$order['quantity']} X {$order['product_name']}",
                        'size' => $order['detail_total_price'],
                        'type' => null
                    ]
                ];
            } else {
                // If no order details, display "No orders yet"
                $formattedOrders[$orderIdKey]['children'][] = [
                    'key' => "{$orderIdKey}-detail",
                    'data' => [
                        'name' => "No orders yet",
                        'size' => null,
                        'type' => null
                    ]
                ];
            }
        }

        return array_values($formattedOrders);
    }
}
