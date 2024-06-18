<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use Config\Services;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use \Kreait\Firebase\Exception\Auth\AuthError;
use \Firebase\JWT\JWT;

class UserController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;
    protected $auth;

    public function __construct()
    {
        // Initialize models using the service
        $this->userModel = Services::userModel();
        $factory = (new Factory)->withServiceAccount(getenv('FIREBASE_CREDENTIALS'));
        $this->auth = $factory->createAuth();
    }

    public function registerCustomer(): ResponseInterface
    {
        $jsonData = $this->request->getJSON();

        if (!$jsonData) {
            // Use apiErrorResponse for handling invalid or missing JSON data
            return apiErrorResponse('validation', 'Invalid or missing JSON data.', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $result = $this->userModel->registerCustomer($jsonData);

        if ($result['error']) {
            // Handle registration failure with apiErrorResponse
            return apiErrorResponse('internal_error', $result['message'], ResponseInterface::HTTP_BAD_REQUEST);
        }

        // Use apiResponse for a successful registration
        return apiResponse('success', ['token' => $result['token']], 'Registration successful', ResponseInterface::HTTP_CREATED);
    }

    public function login(): ResponseInterface
    {
        $jsonData = $this->request->getJSON();
        $idTokenString = $jsonData->idToken ?? '';
        $fcmToken = $jsonData->fcmToken ?? '';

        if (empty($idTokenString)) {
            return apiErrorResponse('bad_request', 'ID Token is required.', 400);
        }

        try {
            // Verify the ID token
            $verifiedIdToken = $this->auth->verifyIdToken($idTokenString, true);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            // You might also retrieve additional user information here if needed
            $user = $this->userModel->where('firebase_id', $firebaseUid)->first();

            if (!$user) {
                return apiErrorResponse('not_found', 'User not found.', 404);
            }

            // Update the FCM token in your database for the given Firebase UID
            $this->userModel->updateFcmTokenByFirebaseUid($firebaseUid, $fcmToken);

            // Generate a custom JWT token for your application, if needed
            $jwtToken = generateJWTToken($user['email'], $user['user_role'], $user['id']);

            return apiResponse(true, ['token' => $jwtToken], 'Login successful.', ResponseInterface::HTTP_OK);
        } catch (\Kreait\Firebase\Exception\Auth\InvalidToken $e) {
            return apiErrorResponse('unauthorized', 'The provided token is invalid.', 401);
        } catch (\Throwable $e) {
            // Catch-all for any other exceptions
            return apiErrorResponse('internal_error', 'An error occurred during login.', 500);
        }
    }

    public function getNameAndPhoto($userId)
    {
        $userInfo = $this->userModel->getNameAndPhoto($userId);

        if (!$userInfo) {
            return $this->failNotFound('User not found');
        }

        return $this->respond([
            'status' => 200,
            'message' => 'User info retrieved successfully',
            'data' => $userInfo
        ]);
    }

    public function registerByAdmin()
{
    $db = db_connect();
    $jsonData = $this->request->getJSON();

    // Check for existing user with the same email or Firebase ID
    $existingUser = $db->table('users')
                       ->where('email', $jsonData->email)
                       ->orWhere('firebase_id', $jsonData->firebaseId)
                       ->get()
                       ->getRowArray();

    if ($existingUser) {
        return $this->fail('User already exists', 409);
    }

    // Insert the new user into the database
    $data = [
        'email' => $jsonData->email,
        'user_role' => $jsonData->role,
        'first_name' => $jsonData->firstName,
        'last_name' => $jsonData->lastName,
        'firebase_id' => $jsonData->firebaseId,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $db->table('users')->insert($data);

    if ($db->affectedRows() > 0) {
        return $this->respondCreated('User registered successfully');
    } else {
        return $this->failServerError('Failed to register user');
    }
}

public function viewProfile($id)
    {
        $user = $this->userModel->find($id);
        if (!$user)
        {
            return $this->failNotFound('User not found');
        }

        // Unset fields that should not be included in the response
        unset(
            $user['created_at'],
            $user['updated_at'],
            $user['deleted_at'],
            $user['fcm_token'],
            $user['firebase_id'],
            $user['user_role'],
            $user['user_image'],
            $user['email']
        );

        return $this->respond([
            'status' => 200,
            'message' => 'User info retrieved successfully',
            'data' => $user
        ]);
    }

    public function editProfile()
    {
        $json = $this->request->getJSON();

        // Ensure there is data in the request
        if (!$json) {
            return $this->fail('No data provided', 400);
        }

        
        // Cast the JSON object to an array if needed
        $data = (array) $json;
        
        $id = $data['id'];

        // Attempt to update the user
        $update = $this->userModel->updateUser($id, $data);
        if ($update) {
            return $this->respondUpdated(['message' => 'Profile updated successfully']);
        } else {
            return $this->fail('Failed to update profile');
        }
    }




}
