<?php
session_start();
include 'db.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$username = $_SESSION['username'];

// Fetch user info
$stmt = $conn->prepare("SELECT fullname, email, weight, height, age, gender, activity_level 
                        FROM users WHERE fullname=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) exit("User not found.");

// Fetch today's calorie data
$stmt = $conn->prepare("
    SELECT SUM(calories) AS total_cal, MAX(alert_sent) AS alert_sent
    FROM food_logs 
    WHERE username=? AND log_date=CURDATE()
");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$calories_consumed = $row['total_cal'] ?? 0;
$alert_sent = $row['alert_sent'] ?? 0;

// Calculate calorie goal
function calculateCalorieGoal($w, $h, $a, $g, $act) {
    if (!$w || !$h || !$a || !$g) return 0;
    $bmr = (strtolower($g) === 'male')
        ? 10*$w + 6.25*$h - 5*$a + 5
        : 10*$w + 6.25*$h - 5*$a - 161;

    $mult = ['sedentary'=>1.2, 'light'=>1.375, 'moderate'=>1.55, 'active'=>1.725, 'extra'=>1.9];
    return round($bmr * ($mult[strtolower($act)] ?? 1.2));
}

$calorie_goal = calculateCalorieGoal(
    $user['weight'], $user['height'], $user['age'], $user['gender'], $user['activity_level']
);

if ($alert_sent || $calories_consumed <= $calorie_goal || $calorie_goal <= 0) {
    exit("No email needed.");
}

// === Send email alert ===
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = '99effc001@smtp-brevo.com';
    $mail->Password = 'YOUR_SMTP_KEY'; // 🔑 your API key or env var
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('richikanneboina@gmail.com', 'BiteBalance');
    $mail->addAddress($user['email'], $user['fullname']);

    $mail->isHTML(true);
    $mail->Subject = '⚠️ Daily Calorie Limit Exceeded - BiteBalance Alert';
    $mail->Body = "
        <h2>Hey {$user['fullname']} 👋</h2>
        <p>You’ve consumed <strong>{$calories_consumed} kcal</strong> today, 
        which exceeds your target of <strong>{$calorie_goal} kcal</strong>.</p>
        <p>💡 Try going for a short walk, drink water, and keep your next meal light.</p>
        <br>
        <p>Stay balanced,<br><strong>The BiteBalance Team</strong></p>
    ";

    $mail->send();

    // Update flag so email isn’t resent
    $stmt = $conn->prepare("
        UPDATE food_logs 
        SET alert_sent=1 
        WHERE username=? AND log_date=CURDATE()
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();

    echo "Email sent successfully.";
} catch (Exception $e) {
    error_log("Email Error: {$mail->ErrorInfo}");
    echo "Failed to send email.";
}
?>

