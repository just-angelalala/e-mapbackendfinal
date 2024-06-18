<?php namespace App\Libraries;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;

class FirebaseNotificationLib
{
    private $database;

    public function __construct()
    {
        $serviceAccount = getenv('FIREBASE_CREDENTIALS');
        $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            ->createDatabase();

        $this->database = $firebase;
    }


    public function sendNotificationToUser($userId, $title, $message)
    {
        $newNotification = $this->database
            ->getReference('notifications/' . $userId)
            ->push([
                'title' => $title,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'isRead' => false
            ]);
        
        return $newNotification->getKey();
    }

    public function markNotificationAsRead($userId, $notificationId)
    {
        $this->database
            ->getReference('notifications/' . $userId . '/' . $notificationId . '/isRead')
            ->set(true);
    }
}
