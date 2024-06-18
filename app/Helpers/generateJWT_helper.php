<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

function generateJWTToken($email, $userRole, $userID): string
{
    $key = getenv('FIREBASE_SECRET_KEY');
    $payload = [
        'sub' => $email,
        'iss' => 'http://MindoroAutoParts.com/',
        'aud' => 'MindoroAutoParts',
        'iat' => time(),
        'nbf' => time(),
        'exp' => time() + 3600,
        'role' => $userRole,
        'user_id' => $userID 
    ];

    return JWT::encode($payload, $key, 'HS256');
}
