<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = '99effc001@smtp-brevo.com'; // Your Brevo SMTP login
    $mail->Password = 'YOUR_SMTP_KEY';       // Your Brevo SMTP password/API key
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Recipient
    $mail->setFrom('richikanneboina@gmail.com', 'BiteBalance');
    $mail->addAddress('prakarshaveldandi@gmail.com', 'Test User'); // Replace with your email

    // Email content
    $mail->isHTML(true);
    $mail->Subject = '✅ Test Email from BiteBalance';
    $mail->Body    = "Hello,<br><br>This is a test email sent via Brevo SMTP.<br><br>Regards,<br>BiteBalance";
    $mail->AltBody = "Hello, this is a plain text version of your BiteBalance test email.";


    $mail->send();
    echo "✅ Test email sent successfully!";
} catch (Exception $e) {
    echo "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
}

