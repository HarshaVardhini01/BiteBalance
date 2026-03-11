<?php
$to = "prakarshaveldandi@gmail.com";
$subject = "Mail Test from XAMPP";
$message = "This is a test email from PHP Sendmail.";
$headers = "From: bitebalance@localhost\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Email sending failed.";
}
?>

