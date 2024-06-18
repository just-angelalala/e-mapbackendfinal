<?php

namespace App\Libraries;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Config\EmailSettings;

class Emailer
{
    public function sendEmail($to, $subject, $body)
    {
        $emailConfig = new EmailSettings();
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host       = $emailConfig->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $emailConfig->smtpUser;
            $mail->Password   = $emailConfig->smtpPass;
            $mail->SMTPSecure = $emailConfig->smtpCrypto;
            $mail->Port       = $emailConfig->smtpPort;

            //Recipients
            $mail->setFrom($emailConfig->fromEmail, $emailConfig->fromName);
            $mail->addAddress($to);

            //Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
