<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

function extractDataFromToken(string $token) {
    try {
        $key = getenv('FIREBASE_SECRET_KEY');
        $token = str_replace('Bearer ', '', $token);
        // Create a Key object for the decode method
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        return [
            'userId' => $decoded->user_id ?? null,
            'userRole' => $decoded->role ?? null
        ];
    } catch (ExpiredException $e) {
        log_message('error', 'JWT expired: ' . $e->getMessage());
        return null;
    } catch (\Exception $e) {
        log_message('error', 'JWT decoding error: ' . $e->getMessage());
        return null;
    }
}
