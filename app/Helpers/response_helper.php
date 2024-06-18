<?php

function apiResponse($status, $data = null, $message = null, $code = 200) {
    $response = service('response');
    $responseData = [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];
    return $response->setStatusCode($code)->setJSON($responseData);
}
