<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderDetailsModel extends Model
{
    protected $table            = 'order_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['order_id', 'product_id', 'quantity', 'total_price'];

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

    public function addOrderDetails($orderId, $details)
    {
        foreach ($details as &$detail) {
            $detail['order_id'] = $orderId;
        }
        unset($detail);

        $result = insertData($this, $details, 'Failed to insert order details.', $this, true);

        return $result;
    }

    public function addOrderDetailsEcommerce($orderId, $details)
{
    $batchData = [];

    foreach ($details as $detail) {
        $batchData[] = [
            'order_id' => $orderId,
            'product_id' => $detail->product_id,
            'quantity' => $detail->quantity,
            'total_price' => $detail->price,
        ];
    }

    return $this->insertBatch($batchData);
}
    public function softDeleteOrderDetails($orderId) {
        return deleteData($this, ['order_id' => $orderId], 'Failed to soft-delete order details.', $this);
    }

    public function fetchSalesData($dateType, $dateRange = null, $month = null, $year)
    {
        $this->select('category.name as category_name, SUM(order_details.total_price) as total_sales');
        $this->join('orders', 'order_details.order_id = orders.id');
        $this->join('product', 'order_details.product_id = product.id');
        $this->join('category', 'product.category_id = category.id');
        $this->where('YEAR(orders.order_date)', $year);

        if ($dateType == 'monthly' && $month !== null) {
            $this->where('MONTH(orders.order_date)', $month);
            $this->select("WEEK(orders.order_date) as week");
            $this->groupBy('category.name, WEEK(orders.order_date)');
        } elseif ($dateType == 'weekly' && $dateRange !== null) {
            $startDate = date('Y-m-d', strtotime('monday this week', strtotime($dateRange)));
            $endDate = date('Y-m-d', strtotime('sunday this week', strtotime($dateRange)));
            $this->where('orders.order_date >=', $startDate);
            $this->where('orders.order_date <=', $endDate);
            $this->groupBy('category.name');
        }

        $this->orderBy('category.name');

        return $this->findAll();
    }
    

}
