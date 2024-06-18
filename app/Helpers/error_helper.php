<?php

function apiErrorResponse($errorType, $message = null, $code = 500, $additionalData = null) {
    $response = service('response');
    $status = 'error';
    
    // Map error types to HTTP status codes and messages
    switch ($errorType) {
        case 'validation':
            $code = 400; // Bad Request
            $message = $message ?: 'Validation errors occurred.';
            break;
        case 'unauthorized':
            $code = 401; // Unauthorized
            $message = $message ?: 'Authentication is required and has failed or has not yet been provided.';
            break;
        case 'forbidden':
            $code = 403; // Forbidden
            $message = $message ?: 'You do not have permission to access this resource.';
            break;
        case 'not_found':
            $code = 404; // Not Found
            $message = $message ?: 'The requested resource could not be found.';
            break;
        case 'method_not_allowed':
            $code = 405; // Method Not Allowed
            $message = $message ?: 'The request method is not supported for the requested resource.';
            break;
        case 'conflict':
            $code = 409; // Conflict
            $message = $message ?: 'The request could not be completed due to a conflict with the current state of the resource.';
            break;
        case 'gone':
            $code = 410; // Gone
            $message = $message ?: 'The resource requested is no longer available and will not be available again.';
            break;
        case 'internal_error':
            $code = 500; // Internal Server Error
            $message = $message ?: 'An unexpected condition was encountered.';
            break;
        case 'not_implemented':
            $code = 501; // Not Implemented
            $message = $message ?: 'The server does not support the functionality required to fulfill the request.';
            break;
        case 'bad_gateway':
            $code = 502; // Bad Gateway
            $message = $message ?: 'The server received an invalid response from the upstream server.';
            break;
        case 'service_unavailable':
            $code = 503; // Service Unavailable
            $message = $message ?: 'The server is currently unable to handle the request due to temporary overloading or maintenance.';
            break;
        case 'gateway_timeout':
            $code = 504; // Gateway Timeout
            $message = $message ?: 'The server did not receive a timely response from the upstream server.';
            break;
        case 'database_error':
            $code = 500; // Internal Server Error for database-related issues
            $message = $message ?: 'A database error occurred.';
            break;
        // Add more specific error cases as needed
        default:
            $message = $message ?: 'An unexpected error occurred.';
            break;
    }

    $responseData = [
        'status' => $status,
        'message' => $message,
        // Include additional data if provided
        'data' => $additionalData 
    ];
    return $response->setStatusCode($code)->setJSON($responseData);
}
