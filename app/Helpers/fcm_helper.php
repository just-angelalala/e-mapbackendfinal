<?php

function sendFCMNotification($to, $title, $body) {
    $serverKey = getenv('FCM_SECRET_KEY');

    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json',
    ];

    $postData = [
        'to' => $to,
        'notification' => [
            'title' => $title,
            'body' => $body,
        ],
    ];

    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
