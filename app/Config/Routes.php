<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', function($routes) {

    $routes->group('auth', function($routes) {
        $routes->post('registerCustomer', 'UserController::registerCustomer');
        $routes->post('registerByAdmin', 'UserController::registerByAdmin');
        $routes->post('editProfile', 'UserController::editProfile');
        $routes->post('login', 'UserController::login');
        $routes->get('getNameAndPhoto/(:segment)', 'UserController::getNameAndPhoto/$1');
        $routes->get('viewProfile/(:segment)', 'UserController::viewProfile/$1');
    });

    $routes->group('inventory', function($routes) {
        $routes->get('getAllProductsGroupedByCategory', 'InventoryController::getAllProductsGroupedByCategory');
        $routes->get('getAllProductsUngrouped', 'InventoryController::getAllProductsUngrouped');
        $routes->get('getAllProductsUngroupedPos', 'InventoryController::getAllProductsUngroupedPos');
        $routes->get('getAllProductsUngroupedWithLowStock', 'InventoryController::getAllProductsUngroupedWithLowStock');
        $routes->get('getAllCategories', 'InventoryController::getAllCategories');
        $routes->get('getAutoCompleteSuggestions', 'InventoryController::getAutoCompleteSuggestions');
        $routes->get('exportProductsUsingTemplate/(:segment)', 'InventoryController::exportProductsUsingTemplate/$1');
        $routes->post('addProduct', 'InventoryController::addProduct');
        $routes->post('uploadProductImage', 'InventoryController::uploadProductImage');
        $routes->patch('updateProductQuantity', 'InventoryController::updateProductQuantity');
        $routes->put('updateProduct/(:segment)', 'InventoryController::updateProduct/$1');
        $routes->delete('deleteProduct', 'InventoryController::deleteProduct');
        $routes->patch('restoreProduct', 'InventoryController::restoreProduct');
    });

    $routes->group('session', function($routes) {
        $routes->post('startSession', 'PointOfSaleController::startSession');
        $routes->get('fetchSessions', 'PointOfSaleController::fetchSessions');
        $routes->get('getSessionDetails/(:segment)', 'PointOfSaleController::getSessionDetails/$1');
    });

    $routes->group('pointOfSale', function($routes) {
        $routes->patch('updateOrder/(:segment)', 'PointOfSaleController::addOrderDetailsAndUpdateTotal/$1');
        $routes->post('addOrderPayment/(:segment)', 'PointOfSaleController::addOrderPayment/$1');
        $routes->post('createNewOrder', 'PointOfSaleController::createNewOrder');
        $routes->post('finalizeOrder', 'PointOfSaleController::finalizeOrder');
        $routes->patch('voidSingleOrder/:num', 'PointOfSaleController::voidSingleOrder/$1');
        $routes->get('orderDetails/(:segment)', 'PointOfSaleController::getOrderDetails/$1'); // New route for fetching order details
        $routes->post('updateOrderDetails/(:segment)', 'PointOfSaleController::updateOrderDetails/$1'); // New route for fetching order details
        $routes->delete('deleteOrderDetailAndUpdateQuantity/(:segment)', 'PointOfSaleController::deleteOrderDetailAndUpdateQuantity/$1'); // New route for fetching order details
        $routes->delete('clearAllOrderDetails/(:segment)', 'PointOfSaleController::clearAllOrderDetails/$1'); // New route for fetching order details
    });
    
    
    $routes->group('ecommerce', function($routes) {
        $routes->post('checkout', 'EcommerceController::checkout');
        $routes->get('getOrdersWithDetails', 'EcommerceController::getOrdersWithDetails');
        $routes->get('getFeedbacks', 'EcommerceController::getFeedbacks');
        $routes->post('uploadFeedbackImage', 'EcommerceController::uploadFeedbackImage');
        $routes->get('getOrdersWithDetailsForCustomer/(:segment)', 'EcommerceController::getOrdersWithDetailsForCustomer/$1');
        $routes->get('showTopProduct/(:segment)', 'EcommerceController::showTopProduct/$1');
        $routes->get('getAllProductsByCategory/(:segment)', 'EcommerceController::getAllProductsByCategory/$1');
        $routes->post('getGcashReceipt', 'EcommerceController::getGcashReceipt');
        $routes->post('setOrderForPickup', 'EcommerceController::setOrderForPickup');
        $routes->post('submitFeedback', 'EcommerceController::submitFeedback');
        $routes->post('generateProductReport', 'ReportsController::generateProductReport');
        $routes->post('previewProductReport', 'ReportsController::previewProductReport');
        $routes->post('generateSalesReport', 'ReportsController::generateSalesReport');
        $routes->post('previewReport', 'ReportsController::previewReport');
        $routes->post('updateOrderStatusIfNotPickedUp', 'EcommerceController::updateOrderStatusIfNotPickedUp');
        $routes->post('markOrderAsFinished', 'EcommerceController::markOrderAsFinished');
        
    });
    
    $routes->group('dashboard', function($routes) {
        $routes->get('getRecentlySoldProducts', 'EcommerceController::getRecentlySoldProducts');
        $routes->get('getCustomerCountByProvince', 'EcommerceController::getCustomerCountByProvince');
        $routes->get('getOrderCounts', 'EcommerceController::getOrderCounts');
        $routes->get('getMonthlySalesData', 'EcommerceController::getMonthlySalesData');
        $routes->get('displayBestSellingProducts/(:segment)', 'EcommerceController::displayBestSellingProducts/$1');
    });
});