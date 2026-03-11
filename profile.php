<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$fullname = $_SESSION['username'];

// Fetch user info
$sql = "SELECT fullname, email, height, weight, age, gender, activity_level, health_issues, diet_preference 
        FROM users WHERE fullname = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $fullname);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Function to calculate calorie goal
function calculateCalorieGoal($weight, $height, $age, $gender, $activity){
    if (!$weight || !$height || !$age || !$gender) return 0;

    $gender = strtolower($gender);
    if($gender === 'male') {
        $bmr = 10*$weight + 6.25*$height - 5*$age + 5;
    } else {
        $bmr = 10*$weight + 6.25*$height - 5*$age - 161;
    }

    $multipliers = [
        'sedentary'=>1.2,
        'light'=>1.375,
        'moderate'=>1.55,
        'active'=>1.725,
        'extra'=>1.9
    ];

    $tdee = $bmr * ($multipliers[$activity] ?? 1.2);

    return round($tdee);
}

// Function to suggest diet type
function suggestDietType($weight, $height, $age, $gender, $activity){
    $tdee = calculateCalorieGoal($weight, $height, $age, $gender, $activity);
    $current_weight = $weight;
    // For simplicity: if TDEE > weight*30 => gain, if TDEE < weight*25 => loss
    if($tdee > ($weight*30)) return 'Weight Gain';
    elseif($tdee < ($weight*25)) return 'Weight Loss';
    else return 'Maintenance';
}

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $height = !empty($_POST['height']) ? $_POST['height'] : NULL;
    $weight = !empty($_POST['weight']) ? $_POST['weight'] : NULL;
    $age = !empty($_POST['age']) ? $_POST['age'] : NULL;
    $gender = $_POST['gender'] ?? NULL;
    $activity_level = $_POST['activity_level'] ?? 'sedentary';
    $health_issues = $_POST['health_issues'] ?? '';
    $diet_preference = $_POST['diet_preference'] ?? 'Veg';
	$place=$_POST['place']??'';
    $update = $conn->prepare("UPDATE users 
    SET fullname=?, email=?, height=?, weight=?, age=?, gender=?, place=?, activity_level=?, health_issues=?, diet_preference=? 
    WHERE fullname=?");
$update->bind_param("ssdddssssss", 
    $new_fullname, 
    $email, 
    $height, 
    $weight, 
    $age, 
    $gender, 
    $place, 
    $activity_level, 
    $health_issues, 
    $diet_preference, 
    $fullname
);

    $update->execute();
    $update->close();

    $_SESSION['username'] = $new_fullname;
    header("Location: profile.php?updated=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile - BiteBalance</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body { margin: 0; font-family: "Poppins", sans-serif; background: #f5f6fa; color: #333; display: flex; }
.sidebar { width: 180px; background: #2c3e50; color: #fff; height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; padding: 20px 15px; }
.sidebar h2 { color: #eebd1c; margin-bottom: 20px; }
.sidebar ul { list-style: none; padding: 0; }
.sidebar ul li { margin: 15px 0; }
.sidebar ul li a { color: #fff; text-decoration: none; padding: 10px; display: block; border-radius: 8px; }
.sidebar ul li a:hover { background: #34495e; }

.main { margin-left: 200px; padding: 30px; flex: 1; }
.card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 6px 12px rgba(0,0,0,0.08); max-width: 600px; }
h1 { color: #2c3e50; }
label { display: block; margin-top: 12px; font-weight: 600; }
input, select { width: 100%; padding: 10px; margin-top: 5px; border-radius: 8px; border: 1px solid #ccc; font-family: "Poppins", sans-serif; }
button { margin-top: 20px; background: #2c3e50; color: #fff; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; transition: 0.3s; }
button:hover { background: #eebd1c; color: #2c3e50; }
.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
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
<h1>👤 Your Profile</h1>
<div class="card">
<?php if (isset($_GET['updated'])): ?>
    <div class="success">Profile updated successfully!</div>
<?php endif; ?>

<form method="POST" action="">
<label>Full Name</label>
<input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? ''); ?>" required>

<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>

<label>Height (cm)</label>
<input type="number" step="0.01" name="height" value="<?= htmlspecialchars($user['height'] ?? ''); ?>">

<label>Weight (kg)</label>
<input type="number" step="0.01" name="weight" value="<?= htmlspecialchars($user['weight'] ?? ''); ?>">

<label>Age (years)</label>
<input type="number" step="1" name="age" value="<?= htmlspecialchars($user['age'] ?? ''); ?>">

<label>Gender</label>
<select name="gender" required>
    <option value="">-- Select --</option>
    <option value="male" <?= (isset($user['gender']) && $user['gender']=='male')?'selected':'' ?>>Male</option>
    <option value="female" <?= (isset($user['gender']) && $user['gender']=='female')?'selected':'' ?>>Female</option>
</select>

<label>Place</label>
<input type="text" name="place" value="<?= htmlspecialchars($user['place'] ?? ''); ?>">

<label>Activity Level</label>
<select name="activity_level" required>
    <option value="sedentary" <?= (isset($user['activity_level']) && $user['activity_level']=='sedentary')?'selected':'' ?>>Sedentary — Little or no exercise</option>
    <option value="light" <?= (isset($user['activity_level']) && $user['activity_level']=='light')?'selected':'' ?>>Light — Light exercise/sports 1-3 days/week</option>
    <option value="moderate" <?= (isset($user['activity_level']) && $user['activity_level']=='moderate')?'selected':'' ?>>Moderate — Moderate exercise/sports 3-5 days/week</option>
    <option value="active" <?= (isset($user['activity_level']) && $user['activity_level']=='active')?'selected':'' ?>>Active — Hard exercise/sports 6-7 days/week</option>
    <option value="extra" <?= (isset($user['activity_level']) && $user['activity_level']=='extra')?'selected':'' ?>>Extra — Very hard exercise & physical job</option>
</select>
<label>Health Issues</label>
<textarea name="health_issues" rows="3"><?= htmlspecialchars($user['health_issues'] ?? ''); ?></textarea>

<label>Diet Preference</label>
<select name="diet_preference">
    <?php 
    $preferences = ['Veg','Non-Veg','Egg Included','Vegan'];
    foreach ($preferences as $pref) {
        $selected = ($user['diet_preference'] ?? 'Veg') === $pref ? 'selected' : '';
        echo "<option value='$pref' $selected>$pref</option>";
    }
    ?>
</select>


<!-- Calorie goal and diet type are calculated automatically -->
<p style="margin-top:15px; font-weight:600;">
Recommended Calorie Goal: <?= calculateCalorieGoal($user['weight'], $user['height'], $user['age'], $user['gender'], $user['activity_level'] ?? 'sedentary'); ?> kcal<br>
Suggested Diet Type: <?= suggestDietType($user['weight'], $user['height'], $user['age'], $user['gender'], $user['activity_level'] ?? 'sedentary'); ?>
</p>

<button type="submit">Save Changes</button>
</form>
</div>
</div>
</body>
</html>

