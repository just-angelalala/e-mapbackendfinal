<?php

namespace Config;

use CodeIgniter\Config\BaseService;

// Models
use App\Models\UserModel;
use App\Models\SessionModel;
use App\Models\CategoryModel;
use App\Models\OrderDetailsModel;
use App\Models\OrderModel;
use App\Models\ProductModel;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    // Models
    public static function userModel($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userModel');
        }

        return new UserModel();
    }

    public static function sessionModel($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('sessionModel');
        }

        return new SessionModel();
    }
    
    public static function categoryModel($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('categoryModel');
        }

        return new CategoryModel();
    }

    public static function orderDetailsModel($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('orderDetailsModel');
        }

        return new OrderDetailsModel();
    }

    public static function orderModel($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('orderModel');
        }

        return new OrderModel();
    }

    public static function productModel($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('productModel');
        }

        return new ProductModel();
    }
}
