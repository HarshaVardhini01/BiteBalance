<?php
include 'db.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'User';

// Fetch user's diet type
$query = "SELECT diet_type FROM users WHERE fullname = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$diet_type = $user['diet_type'] ?? 'Maintenance';

// ===== Meal Suggestions =====
$meal_suggestions = [
    'Weight Loss' => [
        'breakfast' => ['Oatmeal with fruits', 'Egg white omelette', 'Smoothie with spinach & banana', 'Greek yogurt with berries', 'Avocado toast'],
        'lunch' => ['Grilled chicken salad', 'Vegetable soup', 'Brown rice with tofu', 'Quinoa salad', 'Turkey wrap'],
        'dinner' => ['Steamed veggies with paneer', 'Fish with quinoa', 'Lentil soup with brown bread', 'Grilled shrimp', 'Stuffed bell peppers'],
    ],
    'Maintenance' => [
        'breakfast' => ['Whole grain toast with peanut butter', 'Idli with sambar', 'Boiled eggs with toast', 'Oatmeal with honey', 'Smoothie bowl'],
        'lunch' => ['Chicken curry with rice', 'Dal with chapati', 'Vegetable pulao', 'Paneer sabzi with rice', 'Grilled fish with veggies'],
        'dinner' => ['Chapati with sabzi', 'Fish with veggies', 'Vegetable khichdi', 'Egg curry', 'Roti with dal'],
    ],
    'Weight Gain' => [
        'breakfast' => ['Banana shake with almonds', 'Paneer paratha with curd', 'Oats with peanut butter', 'Milk with dry fruits', 'Egg sandwich'],
        'lunch' => ['Chicken biryani', 'Rajma chawal', 'Paneer butter masala with rice', 'Pasta with chicken', 'Mutton curry with rice'],
        'dinner' => ['Egg curry with rice', 'Roti with ghee and sabzi', 'Milk with dry fruits', 'Cheese sandwich', 'Paneer tikka'],
    ],
    'Diabetics Diet' => [
        'breakfast' => ['Sprouts salad', 'Vegetable upma (no sugar)', 'Oats with skim milk', 'Boiled eggs', 'Vegetable smoothie'],
        'lunch' => ['Brown rice with dal', 'Vegetable soup with multigrain roti', 'Grilled paneer with salad', 'Chicken salad', 'Quinoa with veggies'],
        'dinner' => ['Mixed veg curry', 'Khichdi with low salt', 'Steamed fish and veggies', 'Tofu stir fry', 'Vegetable soup'],
    ],
    'BP Diet' => [
        'breakfast' => ['Low-sodium vegetable sandwich', 'Poha with vegetables', 'Oats with low-fat milk', 'Fruit salad', 'Boiled eggs'],
        'lunch' => ['Steamed rice with veggies', 'Dal with low salt', 'Grilled tofu with salad', 'Vegetable pulao', 'Chickpea salad'],
        'dinner' => ['Boiled vegetables with roti', 'Lentil soup', 'Bajra roti with sabzi', 'Grilled fish', 'Paneer tikka with salad'],
    ],
];

// ===== Randomize meals =====
function random_meals($meals, $count = 3) {
    shuffle($meals);
    return array_slice($meals, 0, $count);
}

// ===== Generate Plan =====
$breakfast_options = $meal_suggestions[$diet_type]['breakfast'];
$lunch_options = $meal_suggestions[$diet_type]['lunch'];
$dinner_options = $meal_suggestions[$diet_type]['dinner'];

$plan = [
    'breakfast' => random_meals($breakfast_options),
    'lunch' => random_meals($lunch_options),
    'dinner' => random_meals($dinner_options),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate'])) {
    $plan = [
        'breakfast' => random_meals($breakfast_options),
        'lunch' => random_meals($lunch_options),
        'dinner' => random_meals($dinner_options),
    ];
}

// ===== Fetch today's meal logs =====
date_default_timezone_set("Asia/Kolkata");
$sql = "SELECT log_date, meal_type, food_name FROM food_logs WHERE username=? AND DATE(log_date)=CURDATE() ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$meal_logs = [];
while ($row = $result->fetch_assoc()) {
    $meal_logs[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Diet Planner - BiteBalance</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body { margin:0; font-family: 'Poppins', sans-serif; display: flex; background: #f5f6fa; }
.sidebar { width: 180px; background: #2c3e50; color: #fff; height: 100vh; padding: 20px; flex-shrink: 0; }
.sidebar h2 { color: #eebd1c; margin-top: 0; }
.sidebar ul { list-style: none; padding: 0; }
.sidebar ul li { margin: 15px 0; }
.sidebar ul li a { color: #fff; text-decoration: none; padding: 10px; display: block; border-radius: 8px; }
.sidebar ul li a:hover { background: #34495e; }
.main { flex:1; padding: 30px; }
h2, h3 { margin: 0 0 15px 0; color: #2c3e50; }
.diet-plan { background: #fff; padding: 20px; border-radius: 16px; box-shadow:0 6px 12px rgba(0,0,0,0.08); margin-bottom:30px; }
.diet-plan ul { padding-left:20px; }
.diet-plan li { margin-bottom: 8px; }
.diet-plan button { margin-top: 15px; padding:10px 20px; background:#2c3e50; color:#fff; border:none; border-radius:20px; cursor:pointer; transition:0.3s; }
.diet-plan button:hover { background:#eebd1c; color:#2c3e50; }
.meal-logs { background:#fff; padding:20px; border-radius:16px; box-shadow:0 6px 12px rgba(0,0,0,0.08); }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #ddd; text-align:left; }
th { background:#f0f0f0; }
.extra-options { font-size: 0.9em; color: #555; margin-left: 15px; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>BiteBalance</h2>
    <ul>
        <li><a href="dashboard.php">🏠 Dashboard</a></li>
      <li><a href="log_food.php">🍽 Log Food</a></li>
      <li><a href="diet_plan.php">🥗 Diet Plan</a></li>
      <li><a href="progress.php">📈 Progress</a></li>
      <li><a href="profile.php">👤 Profile</a></li>
      <li><a href="logout.php">🚪 Logout</a></li>
    </ul>
</div>

<div class="main">
    <h2>Welcome, <?= htmlspecialchars($username ?? 'User'); ?> 👋</h2>
    <h3>Your Diet Type: <?= htmlspecialchars($diet_type ?? 'Maintenance'); ?></h3>

    <div class="diet-plan">
        <h3>Today's Meal Plan</h3>
        <ul>
            <li><b>Breakfast:</b> <?= htmlspecialchars($plan['breakfast'][0]); ?>
                <div class="extra-options">Other options: <?= htmlspecialchars($plan['breakfast'][1]); ?>, <?= htmlspecialchars($plan['breakfast'][2]); ?></div>
            </li>
            <li><b>Lunch:</b> <?= htmlspecialchars($plan['lunch'][0]); ?>
                <div class="extra-options">Other options: <?= htmlspecialchars($plan['lunch'][1]); ?>, <?= htmlspecialchars($plan['lunch'][2]); ?></div>
            </li>
            <li><b>Dinner:</b> <?= htmlspecialchars($plan['dinner'][0]); ?>
                <div class="extra-options">Other options: <?= htmlspecialchars($plan['dinner'][1]); ?>, <?= htmlspecialchars($plan['dinner'][2]); ?></div>
            </li>
        </ul>
        <form method="post">
            <button type="submit" name="regenerate">Generate New Plan 🔄</button>
        </form>
    </div>

    <div class="meal-logs">
        <h3>📅 Today's Meal Logs</h3>
        <?php if(count($meal_logs) > 0): ?>
            <table>
                <tr><th>Date</th><th>Meal Type</th><th>Food Name</th></tr>
                <?php foreach($meal_logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['log_date'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($log['meal_type'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($log['food_name'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No meal logs found today. Start logging your meals! 🍽️</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

