<?php
include 'db.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
//review the below code and help me to generate ai diet plans that considers todays food logs , health issues, meal preferences, food according to my place and calorie target so that it generates diet plan for breakfast lunch dinner and also it should look into todays logs and say what should i avoid in the diet


$username = $_SESSION['username'] ?? 'User';
$fullname=$_SESSION['username'] ?? 'User';
// Fetch user's diet info
// Fetch user info
$query = "SELECT diet_type, health_issues, calorie_goal, diet_preference, place FROM users WHERE fullname = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$diet_type = $user['diet_type'] ?? 'Maintenance';
$health_issues = $user['health_issues'] ?? 'None';
$calorie_target = $user['calorie_goal'] ?? 2000; // default if empty
$preferences = $user['diet_preference'] ?? 'Vegetarian';
$place = $user['place'] ?? 'India';

// Fetch today's food logs
date_default_timezone_set("Asia/Kolkata");
$sql = "SELECT food_name, quantity, calories FROM food_logs WHERE username=? AND DATE(log_date)=CURDATE()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$food_logs_array = [];
while($row = $result->fetch_assoc()){
    $food_logs_array[] = "{$row['food_name']} ({$row['quantity']}) – {$row['calories']} kcal";
}
$food_logs_str = implode("\n- ", $food_logs_array);
if(empty($food_logs_str)) $food_logs_str = "No food logged today.";


// ===== Google Generative API Integration =====
$generated_plan = '';
$api_key = "AIzaSyBwpdKOZxZ8TgC3XL4h-EB3NTHEYf-ptcI"; // Replace with your key

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ai'])) {
    $prompt = "You are a nutrition expert. Generate a personalized daily diet plan for a user with the following profile:

Diet Type: {$diet_type}
Health Issues: {$health_issues}
Calorie Goal: {$calorie_target} kcal
Food Preferences: {$preferences}
Place: {$place}

Today's food logs:
- {$food_logs_str}

Based on this, generate a daily diet plan that:
1. Takes into account today's food logs
2. Advises what foods to avoid for a healthy life
3. Suggests which meals (breakfast, lunch, dinner, snacks) to eat
4. Includes foods available locally in {$place} and vegetarian options if applicable
5. Helps meet the calorie goal of {$calorie_target} kcal

Format the output exactly like this:

Calories so far: [calories consumed today] kcal
Remaining: [calories remaining to reach the goal] kcal

Observation: [brief note about current eating pattern]

Foods to limit: [list of foods or habits to avoid]
Foods to include: [list of healthy foods to prioritize]

Suggested Meal Plan:
Breakfast: [meal options with approximate calories]
Lunch: [meal options with approximate calories]
Dinner: [meal options with approximate calories]
Snacks (if any): [meal options]

Key Tips: [brief actionable tips for healthy eating, portion control, and balancing meals]";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=".$api_key;
    $data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $generated_plan = $result['candidates'][0]['content'] ?? 'Could not generate plan at this time.';
//file_put_contents("ai_debug.log", $response);

    // ===== Save AI Diet Plan to Database =====
if (!empty($generated_plan)) {
    // Reconnect if $conn was closed
    include 'db.php';

    $sql = "INSERT INTO ai_diet_plans (username, diet_type, health_issues, calorie_target, preferences, place, plan)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Example placeholder values — replace these with actual user inputs later if available
        $health_issues = $_POST['health_issues'] ?? 'None';
        $calorie_target = $_POST['calorie_target'] ?? 2000;
        $preferences = $_POST['preferences'] ?? 'Balanced';
        $place = $_POST['place'] ?? 'India';
$result = json_decode($response, true);
$generated_plan = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Calories so far: 1110 kcal | Remaining: 255 kcal
Observation: High-fat, high-carb foods (like chicken biryani) dominated your intake; very little room left for balanced nutrition.

Foods to limit:
Fried/high-fat foods
White rice, white bread
Sugary snacks/drinks

Foods to include:
Lentils, beans, legumes (protein)
Vegetables (fiber & vitamins)
Fruits (moderate)
Whole grains (brown rice, millets, oats)
Low-fat dairy or alternatives

Suggested Vegetarian Meal Plan (South Indian):
Breakfast: Upma with veggies + banana + low-fat milk (~350 kcal)
Mid-morning snack: 10–12 nuts (~100 kcal)
Lunch: Brown rice/millet + dal + vegetable stir-fry (~400 kcal)
Evening snack: Buttermilk or green tea + roasted chickpeas (~100 kcal)
Dinner: 2 chapatis + vegetable curry + small curd (~400 kcal)

Key Tips:

Limit high-calorie foods; treat occasionally
Include protein in every meal
Prefer whole grains
Stay hydrated; avoid sugary drinks
Space meals evenly';


        $stmt->bind_param(
            "sssisss",
            $username,
            $diet_type,
            $health_issues,
            $calorie_target,
            $preferences,
            $place,
            $generated_plan_json
        );
        $stmt->execute();
        $stmt->close();
    } else {
        echo "<p style='color:red;'>Database error: could not prepare statement.</p>";
    }
}
}
// ===== Default meal suggestions =====
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

function random_meals($meals, $count = 3) {
    shuffle($meals);
    return array_slice($meals, 0, min($count, count($meals)));
}

$plan = [
    'breakfast' => random_meals($meal_suggestions[$diet_type]['breakfast']),
    'lunch' => random_meals($meal_suggestions[$diet_type]['lunch']),
    'dinner' => random_meals($meal_suggestions[$diet_type]['dinner']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate'])) {
    $plan = [
        'breakfast' => random_meals($meal_suggestions[$diet_type]['breakfast']),
        'lunch' => random_meals($meal_suggestions[$diet_type]['lunch']),
        'dinner' => random_meals($meal_suggestions[$diet_type]['dinner']),
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
while ($row = $result->fetch_assoc()) $meal_logs[] = $row;
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
.ai-plan { margin-top:15px; padding:15px; background:#f0f0f0; border-radius:12px; }
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
    <h2>Welcome, <?= htmlspecialchars($username); ?> 👋</h2>
    <h3>Your Diet Type: <?= htmlspecialchars($diet_type); ?></h3>

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

        <?php if(!empty($generated_plan)): ?>
            <div class="ai-plan">
                <h4>🤖 AI Generated Diet Plan:</h4>
                <p><?= nl2br(htmlspecialchars($generated_plan)); ?></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <button type="submit" name="regenerate">Generate New Plan 🔄</button>
            <button type="submit" name="generate_ai">Generate AI Diet Plan 🤖</button>
        </form>
    </div>

    <div class="meal-logs">
        <h3>📅 Today's Meal Logs</h3>
        <?php if(count($meal_logs) > 0): ?>
            <table>
                <tr><th>Date</th><th>Meal Type</th><th>Food Name</th></tr>
                <?php foreach($meal_logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['log_date']); ?></td>
                        <td><?= htmlspecialchars($log['meal_type']); ?></td>
                        <td><?= htmlspecialchars($log['food_name']); ?></td>
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

