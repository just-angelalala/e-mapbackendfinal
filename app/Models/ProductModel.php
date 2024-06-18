<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\Exceptions\DataException;

class ProductModel extends Model
{
    protected $table            = 'product';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['code', 'name', 'description', 'price', 'quantity', 'ideal_count', 'unit_of_measurement', 'remarks', 'category_id', 'photo'];

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

    public function getAllProducts()
    {
        return $this->findAll();
    }

    public function getAllProductsUngrouped()
    {
        $query = $this->select('product.*, category.name as category_name')
            ->join('category', 'category.id = product.category_id')
            ->orderBy('category.name', 'ASC')
            ->orderBy('product.name', 'ASC')
            ->get()
            ->getResultArray();

        $ungroupedProducts = [];

        foreach ($query as $product) {
            $product['category_name'] = $product['category_name'];
            $ungroupedProducts[] = $product;
        }

        return $ungroupedProducts;
    }

    public function getAllProductsUngroupedPos()
    {
        $query = $this->select('product.*, category.name as category_name')
            ->join('category', 'category.id = product.category_id')
            ->where('product.quantity >', 0)
            ->orderBy('category.name', 'ASC')
            ->orderBy('product.name', 'ASC')
            ->get()
            ->getResultArray();

        $ungroupedProducts = [];

        foreach ($query as $product) {
            $product['category_name'] = $product['category_name'];
            $ungroupedProducts[] = $product;
        }

        return $ungroupedProducts;
    }

    public function getAllProductsDetails()
    {
        return $this->select('id, name, photo')->findAll();
    }


    public function getAllProductsGroupedByCategory()
    {
        $query = $this->select('product.*, category.name as category_name')
            ->join('category', 'category.id = product.category_id')
            ->orderBy('category.name', 'ASC')
            ->orderBy('product.name', 'ASC')
            ->get()
            ->getResultArray();

        $groupedProducts = [];

        foreach ($query as $product) {
            $categoryName = $product['category_name'];
            unset($product['category_name']);

            $groupedProducts[$categoryName][] = $product;
        }

        return $groupedProducts;
    }

    public function deductProductQuantity($productId, $quantity) {
        $product = $this->find($productId);
        if (!$product) {
            return ['error' => true, 'message' => "Product with ID $productId not found."];
        }
    
        if ($product['quantity'] < $quantity) {
            return ['error' => true, 'message' => "Insufficient stock for product ID $productId."];
        }
    
        $newQuantity = $product['quantity'] - $quantity;
        $updateResult = $this->update($productId, ['quantity' => $newQuantity]);
    
        if (!$updateResult) {
            return ['error' => true, 'message' => "Failed to update quantity for product ID $productId."];
        } else {
            return ['error' => false, 'message' => "Quantity updated successfully for product ID $productId.", 'new_quantity' => $newQuantity];
        }
    }
    

    public function restoreProductQuantitiesForOrder($orderId, $orderDetailsModel) {
        $details = $orderDetailsModel->select('product_id, SUM(quantity) as total_quantity')
                                        ->where('order_id', $orderId)
                                        ->groupBy('product_id')
                                        ->findAll();
    
        if (empty($details)) {
            return ['error' => false];
        }
    
        $updateBatchData = [];
        foreach ($details as $detail) {
            $currentProduct = $this->find($detail['product_id']);
            if (!$currentProduct) {
                continue;
            }
    
            $updateBatchData[] = [
                'id' => $detail['product_id'],
                'quantity' => $currentProduct['quantity'] + $detail['total_quantity']
            ];
        }
    
        if (!empty($updateBatchData)) {
            $result = $this->updateBatch($updateBatchData, 'id');
            if (!$result) {
                return ['error' => true, 'message' => 'Failed to restore product quantities.'];
            }
        }
    
        return ['error' => false];
    }

    public function addProductWithCategory(array $productData, $categoryModel) {
        try {
            $this->db->transBegin();
    
            $category = $categoryModel->where('name', $productData['category'])->first();
    
            if (!$category) {
                $categoryId = $categoryModel->insert(['name' => $productData['category']], true);
                if (!$categoryId) {
                    throw new DataException('Failed to insert new category.');
                }
            } else {
                $categoryId = $category['id'];
            }
    
            $productData['category_id'] = $categoryId;
            unset($productData['category']);
    
            if (!array_key_exists('remarks', $productData)) {
                $productData['remarks'] = null;
            }
    
            if (!$this->insert($productData)) {
                throw new DataException('Failed to insert product.');
            }
    
            $this->db->transCommit();
            return ['error' => false, 'message' => 'Product successfully added with category.'];
        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }    

    public function updateProductQuantity($productId, $quantityToAdd)
    {
        $product = $this->find($productId);

        if (!$product) {
            return false;
        }

        $product['quantity'] += $quantityToAdd;

        $this->save($product);

        return true;
    }

    public function updateProductWithCategory(array $productData, $productId, $categoryModel) {
        try {
            $this->db->transBegin();
        
            $category = $categoryModel->where('name', $productData['category'])->first();
        
            if (!$category) {
                $categoryId = $categoryModel->insert(['name' => $productData['category']], true);
                if (!$categoryId) {
                    throw new DataException('Failed to insert new category.');
                }
            } else {
                $categoryId = $category['id'];
            }
        
            $productData['category_id'] = $categoryId;
            $productData['remarks'] = $productData['remarks'] ? $productData['remarks'] : null;
            unset($productData['category']);
        
            if (!array_key_exists('remarks', $productData)) {
                $productData['remarks'] = null;
            }
        
            if ($productData['code'] == "") {
                $productData['code'] = null;
            }
        

            // Use the provided $productId for the update
            if (!$this->update($productId, $productData)) {
                throw new DataException('Failed to update product.');
            }
        
            $this->db->transCommit();
            return ['error' => false, 'message' => 'Product successfully updated with category.'];
        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function deleteProduct(array $productIds)
    {
        $products = $this->find($productIds);
        if (!$products) {
            return ['error' => true, 'message' => "One or more products not found."];
        }

        $deletedIds = [];
        foreach ($products as $product) {
            if (!isset($product['deleted_at']) || empty($product['deleted_at'])) {
                if ($this->delete($product['id'])) {
                    $deletedIds[] = $product['id'];
                }
            }
        }

        if (empty($deletedIds)) {
            return ['error' => true, 'message' => "Failed to delete the products or products already deleted."];
        } else {
            return ['error' => false, 'message' => "Products successfully deleted.", 'deletedIds' => $deletedIds];
        }
    }

    public function restoreProducts(array $productIds)
    {
        $data = ['deleted_at' => null];
        // Use protect(false) to allow direct manipulation of deleted_at field
        $result = $this->protect(false)->whereIn('id', $productIds)->set($data)->update();

        if (!$result) {
            return ['error' => true, 'message' => 'Failed to restore the products.'];
        } else {
            return ['error' => false, 'message' => 'Products successfully restored.', 'restoredIds' => $productIds];
        }
    }

    public function incrementProductQuantities($orderDetails)
{
    foreach ($orderDetails as $detail) {
        // Get the current quantity of the product
        $product = $this->where('id', $detail['product_id'])->first();

        if ($product) {
            // Increment the product quantity by the amount in the order detail
            $newQuantity = $product['quantity'] + $detail['quantity'];
            $this->update($product['id'], ['quantity' => $newQuantity]);
        }
    }
}

public function fetchRecentlySoldProducts()
    {
        $builder = $this->db->table('order_details');
        $builder->select('product.id, product.code, product.name, product.description, product.price, product.quantity, product.photo, order_details.quantity as sold_quantity, order_details.created_at');
        $builder->join('product', 'product.id = order_details.product_id');
        $builder->orderBy('order_details.created_at', 'DESC');
        $query = $builder->get();

        return $query->getResult();
    }


    public function decrementQuantity($productId, $quantity)
    {
        // Fetch the current quantity of the product
        $product = $this->find($productId);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        // Calculate the new quantity after deduction
        $newQuantity = $product['quantity'] - $quantity;

        // Ensure the quantity doesn't go below zero
        if ($newQuantity < 0) {
            throw new \Exception('Insufficient quantity');
        }

        // Update the quantity in the database
        $this->update($productId, ['quantity' => $newQuantity]);

        return true;
    }

    public function checkAndUpdateQuantities($details)
    {
        $this->transStart();

        foreach ($details as $detail) {
            // If $detail is an object, access properties with -> 
            $productId = $detail->product_id;
            $quantity = $detail->quantity;

            // Retrieve the product based on the product_id
            $product = $this->find($productId);

            // Check if the product exists and if there is enough quantity
            if (!$product || $product['quantity'] < $quantity) {
                $this->transRollback();
                return false; // Insufficient stock or product doesn't exist
            }

            // Calculate the updated quantity
            $updatedQuantity = $product['quantity'] - $quantity;

            // Update the product with the new quantity
            $this->update($productId, ['quantity' => $updatedQuantity]);
        }

        $this->transComplete();
        return $this->transStatus(); // Return the status of the transaction
    }

    public function checkQuantities($details)
    {
        foreach ($details as $detail) {
            // Assuming $detail is an object; if it's an array, adjust accordingly
            $productId = $detail->product_id;
            $requestedQuantity = $detail->quantity;

            // Retrieve the product based on product_id
            $product = $this->find($productId);

            // Check if the product exists and if there is enough quantity
            if (!$product || $product['quantity'] < $requestedQuantity) {
                return false; // Insufficient stock or product doesn't exist
            }
        }

        return true; // All products have sufficient quantities
    }

    public function getAllProductsUngroupedWithLowStock()
{
    $query = $this->select('product.*, category.name as category_name')
        ->join('category', 'category.id = product.category_id')
        ->where('product.quantity < product.ideal_count') // Add condition for low stock based on ideal count
        ->orderBy('category.name', 'ASC')
        ->orderBy('product.name', 'ASC')
        ->get()
        ->getResultArray();

    $ungroupedProducts = [];

    foreach ($query as $product) {
        $ungroupedProducts[] = $product;
    }

    return $ungroupedProducts;
}

    public function getTopSellingProductByCategory($categoryName)
    {
        $builder = $this->db->table('product');
        $builder->select('product.*, SUM(order_details.quantity) as total_quantity');
        $builder->join('order_details', 'order_details.product_id = product.id', 'left');
        $builder->join('category', 'product.category_id = category.id', 'left');
        
        // Apply category filter only if category name is not 'All'
        if ($categoryName !== 'All') {
            $builder->where('category.name', $categoryName);
        }

        $builder->groupBy('product.id');
        $builder->orderBy('total_quantity', 'DESC');
        $builder->limit(5);
        $query = $builder->get();

        return $query->getRowArray();
    }

    public function getTopSellingProductsByCategory($categoryName, $limit = 10)
{
    $builder = $this->db->table('order_details');
    $builder->select('product.*, SUM(order_details.quantity) as quantity_sold');
    $builder->join('product', 'order_details.product_id = product.id');
    $builder->join('category', 'product.category_id = category.id');
    
    if ($categoryName !== 'All') {
        $builder->where('category.name', $categoryName);
    }

    $builder->groupBy('order_details.product_id');
    $builder->orderBy('quantity_sold', 'DESC');
    $builder->limit($limit);
    
    $query = $builder->get();
    
    return $query->getResult();
}

    public function getAllProductsByCategory($categoryName)
    {
        $builder = $this->db->table('product');
        $builder->select('product.*');
        $builder->join('category', 'product.category_id = category.id', 'left');

        // Apply category filter only if category name is not 'All'
        if ($categoryName !== 'All') {
            $builder->where('category.name', $categoryName);
        }

        $query = $builder->get();

        return $query->getResultArray();
    }

    public function fetchProductsWithCosts()
    {
        $builder = $this->db->table($this->table);
        $builder->select("
            name,
            description,
            remarks,
            price AS unit_cost,
            quantity AS quantity_in_stocks,
            unit_of_measurement,
            (price * quantity) AS total_cost,
            ((price * quantity) * 1.12) AS total_inventory_gross,
            (((price * quantity) * 1.12) * 0.88) AS total_inventory_net_vat
        ");

        $builder->where('deleted_at', null); // Assuming 'deleted_at' is used for soft deletes.

        return $builder->get()->getResultArray();
    }





}
