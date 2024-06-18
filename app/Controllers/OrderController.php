<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Config\Services;

class OrderController extends ResourceController
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
    }

    public function addOrder()
    {
        $json = $this->request->getJSON(true);

        $orderDataResponse = $this->orderModel->processOrderData(json_encode($json['order']));
        if ($orderDataResponse['error']) {
            return $this->fail($orderDataResponse['messages']);
        }

        $orderDetailsDataResponse = $this->orderDetailsModel->processOrderDetailsData(json_encode($json['orderDetails']));
        if ($orderDetailsDataResponse['error']) {
            return $this->fail($orderDetailsDataResponse['messages']);
        }

    }

}
