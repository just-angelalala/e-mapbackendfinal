<?php
namespace App\Libraries;

class FCMService
{
    protected $serverKey;

    public function __construct()
    {
        $this->serverKey = getenv('FCM_SECRET_KEY');
    }

    public function sendNotification($to, $title, $body)
    {
        $headers = [
            'Authorization: key=' . $this->serverKey,
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
}
