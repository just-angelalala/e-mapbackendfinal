<?php

function hashSecretKey($secretKey)
{
    $pepper = getenv('PEPPER');
    if (false === $pepper || empty($pepper)) {
        // Handle the error appropriately
        throw new Exception('Pepper is not defined in the environment variables.');
    }
    
    $pepperedKey = hash_hmac('sha256', $secretKey, $pepper);
    return password_hash($pepperedKey, PASSWORD_ARGON2ID);
}
