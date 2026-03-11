<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
date_default_timezone_set("Asia/Kolkata");

// Fetch daily calorie totals from food_logs
$sql = "SELECT log_date AS day, SUM(calories) AS total_calories
        FROM food_logs
        WHERE username = ?
        GROUP BY log_date
        ORDER BY day ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
$calories = [];

while ($row = $result->fetch_assoc()) {
    $dates[] = $row['day'];
    $calories[] = $row['total_calories'];
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Progress - BiteBalance</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      margin: 0;
      font-family: "Poppins", sans-serif;
      background: #f5f6fa;
      color: #333;
      display: flex;
    }
    .sidebar {
      width: 180px;
      background: #2c3e50;
      color: #fff;
      height: 100vh;
      position: fixed;
      left: 0; top: 0;
      display: flex;
      flex-direction: column;
      padding: 20px 15px;
    }
    .sidebar h2 { color: #eebd1c; }
    .sidebar ul { list-style: none; padding: 0; }
    .sidebar ul li { margin: 15px 0; }
    .sidebar ul li a {
      color: #fff; text-decoration: none;
      padding: 10px; display: block;
      border-radius: 8px;
    }
    .sidebar ul li a:hover { background: #34495e; }

    .main {
      margin-left: 180px;
      padding: 30px;
      flex: 1;
    }
    .card {
      background: #fff;
      padding: 25px;
      border-radius: 16px;
      box-shadow: 0 6px 12px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }
    h1 {
      color: #2c3e50;
    }
    canvas {
      width: 100%;
      height: 400px;
    }
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
    <h1>📈 Your Progress Over Time</h1>
    <div class="card">
      <canvas id="calorieChart"></canvas>
    </div>
  </div>

  <script>
    const ctx = document.getElementById('calorieChart');
    const calorieChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($dates); ?>,
        datasets: [{
          label: 'Daily Calorie Intake',
          data: <?= json_encode($calories); ?>,
          fill: true,
          borderColor: '#2c3e50',
          backgroundColor: 'rgba(238,189,28,0.2)',
          tension: 0.3,
          pointBackgroundColor: '#eebd1c',
          borderWidth: 2
        }]
      },
      options: {
        scales: {
          x: { title: { display: true, text: 'Date' } },
          y: { title: { display: true, text: 'Calories' }, beginAtZero: true }
        },
        plugins: {
          legend: { display: true },
          tooltip: { enabled: true }
        }
      }
    });
  </script>

</body>
</html>

