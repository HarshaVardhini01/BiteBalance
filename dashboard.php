<?php
session_start();
include 'db.php';

// ✅ Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$fullname = $_SESSION['username'];

// Fetch user info
$stmt = $conn->prepare("SELECT fullname, email, height, weight, age, gender, activity_level, health_issues, diet_preference, place 
                        FROM users WHERE fullname=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Functions
function calculateCalorieGoal($weight, $height, $age, $gender, $activity){
    if (!$weight || !$height || !$age || !$gender) return 0;
    $gender = strtolower($gender);
    $bmr = ($gender === 'male') 
        ? 10*$weight + 6.25*$height - 5*$age + 5
        : 10*$weight + 6.25*$height - 5*$age - 161;

    $multipliers = [
        'sedentary'=>1.2,
        'light'=>1.375,
        'moderate'=>1.55,
        'active'=>1.725,
        'extra'=>1.9
    ];
    return round($bmr * ($multipliers[$activity] ?? 1.2));
}

function suggestDietType($weight, $height, $age, $gender, $activity){
    $tdee = calculateCalorieGoal($weight, $height, $age, $gender, $activity);
    if($tdee > ($weight * 30)) return 'Weight Gain';
    elseif($tdee < ($weight * 25)) return 'Weight Loss';
    else return 'Maintenance';
}

$calorie_goal = calculateCalorieGoal(
    $user['weight'], $user['height'], $user['age'], 
    $user['gender'], $user['activity_level'] ?? 'sedentary'
);
$diet_type = suggestDietType(
    $user['weight'], $user['height'], $user['age'], 
    $user['gender'], $user['activity_level'] ?? 'sedentary'
);

// Fetch today's calories
$stmt = $conn->prepare("SELECT SUM(calories) as total_cal 
                        FROM food_logs WHERE username=? AND log_date=CURDATE()");
$stmt->bind_param("s", $username);
$stmt->execute();
$cal_log = $stmt->get_result()->fetch_assoc();
$calories_consumed = $cal_log['total_cal'] ?? 0;
$stmt->close();

// ✅ EMAIL ALERT LOGIC (top of dashboard)
if ($calories_consumed > $calorie_goal && $calorie_goal > 0) {
    if (!isset($_SESSION['email_sent_today'])) { // Prevent duplicate emails per session
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = '99effc001@smtp-brevo.com'; // your Brevo SMTP login
            $mail->Password = 'YOUR_SMTP_KEY'; // replace with your Brevo key
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('richikanneboina@gmail.com', 'BiteBalance');
            $mail->addAddress($user['email'], $user['fullname']);

            $mail->isHTML(true);
            $mail->Subject = ' Calorie Limit Exceeded - BiteBalance Alert';
            $mail->Body = "
                <p>Hi <b>{$user['fullname']}</b>,</p>
                <p>You've consumed <b>{$calories_consumed} kcal</b> today, 
                which exceeds your goal of <b>{$calorie_goal} kcal</b>.</p>
                <p Tip: Try light activity or smaller portions in your next meal.</p>
                <p>Stay balanced,<br><b>BiteBalance Team</b></p>
            ";
            $mail->AltBody = "You've consumed {$calories_consumed} kcal today, exceeding your goal of {$calorie_goal} kcal.";

            $mail->send();
            $_SESSION['email_sent_today'] = true; // flag to avoid repeat sends
        } catch (Exception $e) {
            error_log("Email Error: {$mail->ErrorInfo}");
        }
    }
}

// Personalized Diet Plan Function
function generatePersonalizedDietPlan($user, $diet_type, $calorie_goal) {
    if (!$calorie_goal || $calorie_goal <= 0) {
        return "⚠️ Please complete your profile to calculate your calorie goal.";
    }

    $diet_pref = strtolower($user['diet_preference'] ?? '');
    $health = strtolower($user['health_issues'] ?? '');

    // Macronutrient distribution
    $protein_cal = round($calorie_goal * 0.3);
    $carb_cal = round($calorie_goal * 0.45);
    $fat_cal = round($calorie_goal * 0.25);

    $protein_g = round($protein_cal / 4);
    $carb_g = round($carb_cal / 4);
    $fat_g = round($fat_cal / 9);

    $plan = "";
    if (strpos($health, 'diabetes') !== false) {
        $plan .= "🍎 Health Note: Low-sugar and low-carb foods recommended due to diabetes.\n\n";
    } elseif (strpos($health, 'cholesterol') !== false) {
        $plan .= "💚 Health Note: Choose foods low in saturated fats and rich in fiber.\n";
    }

    $plan .= "💪 Daily Calorie Goal: {$calorie_goal} kcal\n";
    $plan .= "🍗 Protein: {$protein_g}g | 🍚 Carbs: {$carb_g}g | 🥑 Fats: {$fat_g}g\n";

    if ($diet_pref === 'veg' || $diet_pref === 'vegetarian') {
        $plan .= "🥣 Breakfast: Oats with milk, banana, and almonds (~350 kcal)\n";
        $plan .= "🍱 Lunch: Brown rice, mixed dal, paneer curry, and salad (~600 kcal)\n";
        $plan .= "🍜 Dinner: Vegetable soup, 2 chapatis, and curd (~500 kcal)\n";
        $plan .= "🍏 Snacks: Fruit bowl or roasted chana (~200 kcal)\n";
    } elseif ($diet_pref === 'non-veg') {
        $plan .= "🍳 Breakfast: Boiled eggs, toast, and fruit (~350 kcal)\n";
        $plan .= "🍗 Lunch: Grilled chicken, rice, dal, and salad (~650 kcal)\n";
        $plan .= "🥘 Dinner: Fish curry, 2 chapatis, and sautéed veggies (~500 kcal)\n";
        $plan .= "🍎 Snacks: Greek yogurt or nuts (~200 kcal)\n";
    } else {
        $plan .= "🥣 Breakfast: Whole-grain toast with peanut butter (~300 kcal)\n";
        $plan .= "🍱 Lunch: Rice, dal, vegetables, and salad (~600 kcal)\n";
        $plan .= "🍜 Dinner: Soup, 2 chapatis, and a light curry (~500 kcal)\n";
        $plan .= "🍎 Snacks: Mixed fruits or smoothie (~200 kcal)\n";
    }

    if ($diet_type === 'Weight Loss') {
        $plan .= "\n⚖️ Tip: Reduce portion sizes slightly and prefer steamed or grilled items.";
    } elseif ($diet_type === 'Weight Gain') {
        $plan .= "\n💪 Tip: Add an extra snack like peanut butter sandwich or smoothie.";
    } else {
        $plan .= "\n✨ Tip: Maintain a balanced diet with consistent portions.";
    }

    return nl2br($plan);
}

$personalized_plan = generatePersonalizedDietPlan($user, $diet_type, $calorie_goal);

// Fetch last 7 days calories
$weekly_labels = [];
$weekly_data = [];

$stmt = $conn->prepare("
    SELECT log_date, SUM(calories) as total_cal 
    FROM food_logs 
    WHERE username=? AND log_date >= CURDATE() - INTERVAL 6 DAY
    GROUP BY log_date
    ORDER BY log_date ASC
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// Initialize all 7 days with 0
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $weekly_labels[] = date('D', strtotime($day));
    $weekly_data[$day] = 0;
}

// Fill actual data
while ($row = $result->fetch_assoc()) {
    $weekly_data[$row['log_date']] = (int)$row['total_cal'];
}
$stmt->close();

$weekly_values = array_values($weekly_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BiteBalance Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* same styling as your original code */
body { margin:0; font-family:"Poppins",sans-serif; background:#f5f6fa; color:#333; display:flex; }
.sidebar { width:180px; background:#2c3e50; color:#fff; height:100vh; position:fixed; left:0; top:0; display:flex; flex-direction:column; padding:20px 15px; }
.sidebar h2 { margin:0 0 20px; color:#eebd1c; font-size:1.5rem; }
.sidebar ul { list-style:none; padding:0; margin:0; }
.sidebar ul li { margin:15px 0; }
.sidebar ul li a { text-decoration:none; color:#fff; display:flex; align-items:center; gap:10px; padding:10px; border-radius:8px; transition:.3s; }
.sidebar ul li a:hover { background:#34495e; }
.main { margin-left:180px; padding:20px 30px; flex:1; }
.header { background: linear-gradient(135deg,#2c3e50,#3b5998); color:#fff; padding:25px 30px; border-radius:16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; margin-left:20px; box-shadow:0 6px 15px rgba(0,0,0,0.15); }
.header h1 { margin:0; font-size:1.8rem; font-weight:600; }
.header .summary { font-size:1rem; opacity:0.9; }
.note { margin-left:20px; padding:12px 15px; border-radius:10px; font-size:0.95rem; margin-bottom:15px; background:#fff3cd; color:#856404; border-left:5px solid #ffeeba; }
.alert { margin-left:20px; padding:12px 15px; border-radius:10px; font-size:0.95rem; margin-bottom:15px; background:#f8d7da; color:#842029; border-left:5px solid #f5c2c7; font-weight:500; }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:20px; margin-left:20px; }
.card { background:#fff; padding:20px; border-radius:16px; box-shadow:0 6px 12px rgba(0,0,0,0.08); }
.card h2 { margin-top:0; font-size:1.2rem; color:#2c3e50; margin-bottom:15px; display:flex; align-items:center; gap:8px; }
button { padding:8px 15px; border:none; border-radius:20px; background:#2c3e50; color:#fff; cursor:pointer; margin-top:10px; transition:.3s; }
button:hover { background:#eebd1c; color:#2c3e50; }
@media(max-width:576px) { .sidebar { display:none; } .main { margin-left:0; } }
</style>
</head>
<body>

<div class="sidebar">
<h2>BiteBalance</h2>
<ul>
<li><a href="#"><span>🏠</span> Dashboard</a></li>
<li><a href="log_food.php"><span>🍽</span> Log Food</a></li>
<li><a href="diet_plan.php"><span>🥗</span> Diet Plan</a></li>
<li><a href="progress.php"><span>📈</span> Progress</a></li>
<li><a href="profile.php"><span>👤</span> Profile</a></li>
<li><a href="logout.php"><span>🚪</span> Logout</a></li>
</ul>
</div>

<div class="main">
<div class="header">
<div>
<h1>👋 Hello, <?= htmlspecialchars($username); ?></h1>
<p class="summary">Today’s progress: <?= $calories_consumed ?> / <?= $calorie_goal ?> kcal</p>
<p style="font-size:0.9rem;">🏙 Place: <?= htmlspecialchars($user['place'] ?? 'Not set'); ?> | 🥗 Diet Type: <?= htmlspecialchars($diet_type) ?></p>
</div>
<a href="profile.php"><img src="images/profile-removebg-preview.png" alt="User Avatar" style="border-radius:50%" width="100" height="100"></a>
</div>

<div class="note">
💡 You can update your profile anytime to recalculate your calorie target and personalized diet recommendations.
</div>

<?php if ($calories_consumed > $calorie_goal && $calorie_goal > 0): ?>
<div class="alert">
⚠️ You have exceeded your daily calorie target! Try to balance it with light activity or reduce intake in your next meals.
</div>
<?php endif; ?>

<div class="grid">
<div class="card">
<h2>🔥 Calories Breakdown</h2>
<canvas id="calorieChart" height="200"></canvas>
</div>

<div class="card">
<h2>🥗 Personalized Diet Plan</h2>
<p style="white-space:pre-line;"><?= $personalized_plan ?></p>
<button onclick="location.href='diet_plan.php'">Customize Plan</button>
</div>

<div class="card">
<h2>📈 Weekly Progress</h2>
<canvas id="progressChart" height="200"></canvas>
</div>
</div>
</div>

<script>
const consumed = <?= $calories_consumed ?>;
const goal = <?= $calorie_goal ?>;

const ctx1 = document.getElementById('calorieChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Consumed','Remaining'],
        datasets:[{
            data:[consumed, Math.max(goal - consumed,0)],
            backgroundColor:['#eebd1c','#2c3e50'],
            borderWidth:1
        }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom'}}}
});

const ctx2 = document.getElementById('progressChart').getContext('2d');
new Chart(ctx2,{
    type:'line',
    data:{
        labels: <?= json_encode($weekly_labels) ?>,
        datasets:[{
            label:'Calories',
            data: <?= json_encode($weekly_values) ?>,
            borderColor:'#2c3e50',
            backgroundColor:'rgba(44,62,80,0.2)',
            tension:0.3,
            fill:true
        }]
    },
    options:{
        responsive:true,
        scales:{ y:{ beginAtZero:true } }
    }
});
</script>
</body>
</html>

