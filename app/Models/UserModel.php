<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['first_name', 'email', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'sitio', 'barangay', 'city', 'province', 'detailed_address', 'latitude', 'longitude', 'phone_number', 'user_image', 'user_role', 'firebase_id', 'fcm_token'];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages = [];    
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

    public function registerCustomer($jsonData)
    {
        // Transform and sanitize JSON data
        $data = $this->transformAndSanitizeInput($jsonData);

        // Insert sanitized data into the database using the helper function, and request the ID of the inserted record
        $insertResult = insertData($this, $data, 'Registration failed.', $this, false, true);

        if ($insertResult['error']) {
            return $insertResult; // Return the error details directly
        }

        // Retrieve the ID of the inserted record from the result of insertData
        $userId = $insertResult['id'];

        // Generate a JWT token for the user using the globally accessible helper function
        $token = generateJWTToken($data['email'], $data['user_role'], $userId);

        return ['error' => false, 'message' => 'Registration successful', 'token' => $token];
    }


    protected function transformAndSanitizeInput($jsonData)
    {
        // Transform from PascalCase (JSON) to snake_case (DB) and sanitize input
        $transformedData = [
            'email' => $jsonData->email ?? '',
            'last_name' => $jsonData->lastName ?? '',
            'first_name' => $jsonData->firstName ?? '',
            'middle_name' => $jsonData->middleName ?? '',
            'date_of_birth' => $jsonData->dateOfBirth ?? '',
            'gender' => $jsonData->gender ?? '',
            'sitio' => $jsonData->sitio ?? '',
            'barangay' => $jsonData->barangay ?? '',
            'city' => $jsonData->city ?? '',
            'province' => $jsonData->province ?? '',
            'detailed_address' => $jsonData->detailedAddress ?? '',
            'latitude' => $jsonData->latitude ?? '',
            'longitude' => $jsonData->longitude ?? '',
            'phone_number' => $jsonData->phoneNumber ?? '',
            'user_role' => 'Customer',
            'firebase_id' => $jsonData->firebaseId ?? '',
        ];

        return $transformedData;
    }

    public function updateFcmTokenByFirebaseUid(string $firebaseUid, string $fcmToken)
    {
        return $this->where('firebase_id', $firebaseUid)->set('fcm_token', $fcmToken)->update();
    }

    public function getNameAndPhoto(int $userId): ?array
    {
        $user = $this->asArray()
                        ->select(['first_name', 'user_image'])
                        ->find($userId);

        if (!$user) {
            return null;
        }

        return [
            'username' => $user['first_name'],
            'photo' => $user['user_image']
        ];
    }

    public function updatePhoneNumber($userId, $phoneNumber)
    {
        return $this->update($userId, ['phone_number' => $phoneNumber]);
    }

    public function getCustomerCountByProvince() {
        $builder = $this->db->table('users');
        $builder->select('city, COUNT(*) as count');
        $builder->where('user_role', 'Customer');
        $builder->groupBy('city');
        $query = $builder->get();
    
        return $query->getResultArray();
    }

    public function updateUser($id, $data)
    {
        return $this->update($id, $data);
    }
    



}
