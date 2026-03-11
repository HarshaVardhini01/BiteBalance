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
$search_results = [];
$selected_food = null;
$success_message = "";
$error_message = "";

// Handle search
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $search = "%" . $search_term . "%";
    $stmt = $conn->prepare("SELECT * FROM nutrition_database WHERE food_name LIKE ? OR category LIKE ? ORDER BY food_name LIMIT 20");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle food selection for details
if (isset($_GET['food_id'])) {
    $food_id = (int)$_GET['food_id'];
    $stmt = $conn->prepare("SELECT * FROM nutrition_database WHERE id=?");
    $stmt->bind_param("i", $food_id);
    $stmt->execute();
    $selected_food = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle form submission - Add food to log
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_food'])) {
    $food_id = (int)$_POST['food_id'];
    $multiplier = (float)$_POST['multiplier'];
    $meal_type = $_POST['meal_type'];

    if ($multiplier <= 0) {
        $error_message = "⚠️ Serving size must be greater than 0";
    } else {
        // Get food details
        $stmt = $conn->prepare("SELECT * FROM nutrition_database WHERE id=?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $food = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($food) {
            $food_name = $food['food_name'];
            $calories = $food['calories'] * $multiplier;
            $protein = $food['protein'] * $multiplier;
            $carbs = $food['carbs'] * $multiplier;
            $fat = $food['fat'] * $multiplier;
            $quantity = ($food['serving_size'] * $multiplier) . " " . $food['serving_unit'];
            $serving_unit = $food['serving_unit']; // added line
$fiber = $food['fiber'] ?? 0; // safe default


            // Insert into food logs
            $stmt = $conn->prepare("INSERT INTO food_logs 
                (username, food_name, calories, protein, carbs, fat, meal_type, quantity,serving_unit, fiber, log_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?,?,?, CURDATE())");
            $stmt->bind_param("ssddddssss", 
                $username, $food_name, $calories, $protein, $carbs, $fat, $meal_type, $quantity, $serving_unit, $fiber);
            
            if ($stmt->execute()) {
                $success_message = "✅ Successfully logged: " . htmlspecialchars($food_name) . " (" . htmlspecialchars($quantity) . ")";
                $selected_food = null; // Clear selection after successful add
            } else {
                $error_message = "⚠️ Failed to log food. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Handle delete food log
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $log_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM food_logs WHERE id=? AND username=?");
    $stmt->bind_param("is", $log_id, $username);
    if ($stmt->execute()) {
        $success_message = "✅ Food item deleted successfully!";
    }
    $stmt->close();
    header("Location: log_food.php");
    exit();
}

// Fetch today's logs
date_default_timezone_set("Asia/Kolkata");
$sql = "SELECT id, food_name, calories, protein, carbs, fat, meal_type, quantity, log_date 
        FROM food_logs 
        WHERE username=? AND DATE(log_date)=CURDATE()
        ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$totalCalories = $totalProtein = $totalCarbs = $totalFat = 0;
$foods = [];
while ($row = $result->fetch_assoc()) {
    $foods[] = $row;
    $totalCalories += (float) $row['calories'];
    $totalProtein += (float) $row['protein'];
    $totalCarbs += (float) $row['carbs'];
    $totalFat += (float) $row['fat'];
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log Food - BiteBalance</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
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
      overflow-y: auto;
    }
    .sidebar h2 { 
      color: #eebd1c; 
      margin-bottom: 20px;
      font-size: 1.3rem;
    }
    .sidebar ul { list-style: none; padding: 0; margin: 0; }
    .sidebar ul li { margin: 12px 0; }
    .sidebar ul li a {
      color: #fff; 
      text-decoration: none;
      padding: 10px; 
      display: flex;
      align-items: center;
      gap: 8px;
      border-radius: 8px;
      transition: 0.3s;
      font-size: 0.95rem;
    }
    .sidebar ul li a:hover { background: #34495e; }
    
    .main {
      margin-left: 180px;
      padding: 25px;
      flex: 1;
      max-width: 1400px;
    }
    
    h1 { 
      color: #2c3e50; 
      margin-bottom: 10px;
      font-size: 2rem;
    }
    
    .subtitle {
      color: #666;
      font-size: 0.95rem;
      margin-bottom: 25px;
    }
    
    .card {
      background: #fff;
      padding: 25px;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      margin-bottom: 25px;
    }
    
    .card h2 { 
      color: #2c3e50; 
      margin-top: 0;
      margin-bottom: 18px;
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    /* Search Box */
    .search-box {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
    }
    .search-box input {
      flex: 1;
      padding: 14px 18px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 1rem;
      font-family: "Poppins", sans-serif;
      transition: 0.3s;
    }
    .search-box input:focus {
      outline: none;
      border-color: #2c3e50;
    }
    .search-box button {
      background: #2c3e50;
      color: #fff;
      border: none;
      padding: 14px 30px;
      border-radius: 10px;
      cursor: pointer;
      transition: 0.3s;
      font-size: 1rem;
      font-weight: 600;
    }
    .search-box button:hover {
      background: #eebd1c;
      color: #2c3e50;
      transform: translateY(-2px);
    }
    
    /* Search Results */
    .search-results {
      display: grid;
      gap: 12px;
      margin-bottom: 20px;
    }
    .food-item {
      background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
      padding: 16px 20px;
      border-radius: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      transition: 0.3s;
      border: 2px solid transparent;
    }
    .food-item:hover {
      border-color: #eebd1c;
      transform: translateX(5px);
      box-shadow: 0 4px 12px rgba(238, 189, 28, 0.2);
    }
    .food-item-info h3 {
      margin: 0 0 6px 0;
      color: #2c3e50;
      font-size: 1.1rem;
    }
    .food-item-info p {
      margin: 0;
      color: #666;
      font-size: 0.9rem;
    }
    .food-item-category {
      display: inline-block;
      background: #e8f5e9;
      color: #2e7d32;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 0.8rem;
      margin-top: 5px;
    }
    .food-item-button {
      background: #2c3e50;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: 0.3s;
    }
    .food-item-button:hover {
      background: #eebd1c;
      color: #2c3e50;
    }
    
    /* Selected Food Details */
    .food-details {
      background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
      padding: 25px;
      border-radius: 14px;
      margin-bottom: 20px;
      border-left: 5px solid #4caf50;
    }
    .food-details h3 {
      color: #2c3e50;
      margin: 0 0 8px 0;
      font-size: 1.5rem;
    }
    .food-details .serving-info {
      color: #666;
      margin-bottom: 20px;
      font-size: 0.95rem;
    }
    
    .nutrition-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 15px;
      margin: 20px 0;
    }
    .nutrition-item {
      background: #fff;
      padding: 16px;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .nutrition-item strong {
      display: block;
      font-size: 1.6rem;
      color: #2c3e50;
      margin-bottom: 5px;
      font-weight: 700;
    }
    .nutrition-item span {
      color: #666;
      font-size: 0.85rem;
    }
    
    /* Form Styles */
    label {
      display: block;
      margin-top: 15px;
      margin-bottom: 6px;
      font-weight: 600;
      color: #2c3e50;
    }
    input[type="number"], select {
      width: 100%;
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 1rem;
      font-family: "Poppins", sans-serif;
      transition: 0.3s;
    }
    input[type="number"]:focus, select:focus {
      outline: none;
      border-color: #2c3e50;
    }
    
    button.submit-btn {
      background: #4caf50;
      color: #fff;
      border: none;
      padding: 14px 30px;
      border-radius: 25px;
      cursor: pointer;
      transition: 0.3s;
      font-size: 1.05rem;
      font-weight: 600;
      margin-top: 20px;
      width: 100%;
    }
    button.submit-btn:hover {
      background: #45a049;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }
    
    /* Today's Log Table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    th, td {
      padding: 14px 12px;
      text-align: left;
      border-bottom: 1px solid #e0e0e0;
    }
    th {
      background: #f8f9fa;
      font-weight: 600;
      color: #2c3e50;
    }
    td {
      color: #555;
    }
    tr:hover {
      background: #f8f9fa;
    }
    
    .delete-btn {
      background: #f44336;
      color: #fff;
      border: none;
      padding: 6px 14px;
      border-radius: 15px;
      cursor: pointer;
      font-size: 0.85rem;
      transition: 0.3s;
    }
    .delete-btn:hover {
      background: #d32f2f;
      transform: scale(1.05);
    }
    
    .total-summary {
      margin-top: 20px;
      padding: 20px;
      background: linear-gradient(135deg, #2c3e50 0%, #3b5998 100%);
      border-radius: 12px;
      color: #fff;
    }
    .total-summary h3 {
      margin: 0 0 15px 0;
      font-size: 1.2rem;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 15px;
    }
    .summary-item {
      text-align: center;
      padding: 12px;
      background: rgba(255,255,255,0.1);
      border-radius: 8px;
    }
    .summary-item strong {
      display: block;
      font-size: 1.8rem;
      margin-bottom: 5px;
    }
    .summary-item span {
      font-size: 0.9rem;
      opacity: 0.9;
    }
    
    /* Alerts */
    .success {
      background: #d4edda;
      color: #155724;
      padding: 14px 18px;
      border-radius: 10px;
      margin-bottom: 20px;
      border-left: 4px solid #28a745;
      font-weight: 500;
    }
    .error {
      background: #f8d7da;
      color: #721c24;
      padding: 14px 18px;
      border-radius: 10px;
      margin-bottom: 20px;
      border-left: 4px solid #dc3545;
      font-weight: 500;
    }
    .info-text {
      color: #666;
      font-style: italic;
      margin-bottom: 20px;
      font-size: 0.95rem;
    }
    .no-results {
      text-align: center;
      padding: 40px;
      color: #999;
    }
    
    /* Responsive */
    @media(max-width: 768px) {
      .sidebar { 
        width: 70px;
        padding: 15px 8px;
      }
      .sidebar h2 {
        font-size: 1rem;
        text-align: center;
      }
      .sidebar ul li a {
        justify-content: center;
        padding: 12px 8px;
      }
      .sidebar ul li a span:last-child {
        display: none;
      }
      .main { 
        margin-left: 70px;
        padding: 15px;
      }
      h1 {
        font-size: 1.5rem;
      }
      .search-box {
        flex-direction: column;
      }
      .nutrition-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      table {
        font-size: 0.85rem;
      }
      th, td {
        padding: 10px 8px;
      }
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>BiteBalance</h2>
    <ul>
      <li><a href="dashboard.php"><span>🏠</span><span>Dashboard</span></a></li>
      <li><a href="log_food.php"><span>🍽</span><span>Log Food</span></a></li>
      <li><a href="diet_plan.php"><span>🥗</span><span>Diet Plan</span></a></li>
      <li><a href="progress.php"><span>📈</span><span>Progress</span></a></li>
      <li><a href="profile.php"><span>👤</span><span>Profile</span></a></li>
      <li><a href="logout.php"><span>🚪</span><span>Logout</span></a></li>
    </ul>
  </div>

  <div class="main">
    <h1>🍽 Log Your Food</h1>
    <p class="subtitle">Search from our database of 200+ Indian & international foods</p>

    <?php if ($success_message): ?>
      <div class="success"><?= $success_message ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
      <div class="error"><?= $error_message ?></div>
    <?php endif; ?>

    <!-- Search Box -->
    <div class="card">
      <h2>🔍 Search Food Items</h2>
      <form class="search-box" method="GET" onsubmit="return handleSubmit(event)">
  <input 
    type="text" 
    id="foodSearch" 
    name="search" 
    placeholder="Type food name (e.g., rice, chicken, idli, banana)..." 
    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
    autocomplete="off"
    list="foodSuggestions"
  >
  <datalist id="foodSuggestions"></datalist>
  <button type="submit">Search</button>
</form>

<script>
const input = document.getElementById("foodSearch");
const datalist = document.getElementById("foodSuggestions");
let lastQuery = "";

input.addEventListener("input", () => {
  const query = input.value.trim();
  if (query.length < 2 || query === lastQuery) return;
  lastQuery = query;

  fetch(`autocomplete_food.php?q=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(data => {
      datalist.innerHTML = "";
      data.forEach(item => {
        const option = document.createElement("option");
        option.value = item.food_name;
        datalist.appendChild(option);
      });
    })
    .catch(err => console.error("Autocomplete error:", err));
});

function handleSubmit(event) {
  // optional: prevent empty searches
  if (input.value.trim() === "") {
    event.preventDefault();
    alert("Please enter a food name to search.");
    return false;
  }
  return true;
}
</script>


      <?php if (!empty($search_results)): ?>
        <div class="search-results">
          <p style="color: #666; margin-bottom: 12px;">Found <?= count($search_results) ?> results</p>
          <?php foreach ($search_results as $food): ?>
            <div class="food-item" onclick="window.location.href='?food_id=<?= $food['id'] ?>'">
              <div class="food-item-info">
                <h3><?= htmlspecialchars(ucfirst($food['food_name'])) ?></h3>
                <p><?= htmlspecialchars($food['serving_size'] . ' ' . $food['serving_unit']) ?> 
                   • <?= number_format($food['calories']) ?> kcal 
                   • P: <?= number_format($food['protein'], 1) ?>g 
                   • C: <?= number_format($food['carbs'], 1) ?>g 
                   • F: <?= number_format($food['fat'], 1) ?>g</p>
                <span class="food-item-category"><?= htmlspecialchars($food['category']) ?></span>
              </div>
              <button class="food-item-button" type="button">Select →</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif (isset($_GET['search']) && !empty($_GET['search'])): ?>
        <div class="no-results">
          <p>😕 No foods found matching "<?= htmlspecialchars($_GET['search']) ?>"</p>
          <p style="font-size: 0.9rem; margin-top: 10px;">Try different keywords or check spelling</p>
        </div>
      <?php else: ?>
        <p class="info-text">💡 Try searching: rice, chicken, dal, idli, banana, samosa, chapati...</p>
      <?php endif; ?>
    </div>

    <!-- Selected Food Details -->
    <?php if ($selected_food): ?>
      <div class="card">
        <div class="food-details">
          <h3>📋 <?= htmlspecialchars(ucfirst($selected_food['food_name'])) ?></h3>
          <p class="serving-info">Standard Serving: <strong><?= htmlspecialchars($selected_food['serving_size'] . ' ' . $selected_food['serving_unit']) ?></strong></p>
          
          <div class="nutrition-grid">
            <div class="nutrition-item">
              <strong><?= number_format($selected_food['calories']) ?></strong>
              <span>Calories</span>
            </div>
            <div class="nutrition-item">
              <strong><?= number_format($selected_food['protein'], 1) ?>g</strong>
              <span>Protein</span>
            </div>
            <div class="nutrition-item">
              <strong><?= number_format($selected_food['carbs'], 1) ?>g</strong>
              <span>Carbs</span>
            </div>
            <div class="nutrition-item">
              <strong><?= number_format($selected_food['fat'], 1) ?>g</strong>
              <span>Fat</span>
            </div>
            <?php if ($selected_food['fiber'] > 0): ?>
            <div class="nutrition-item">
              <strong><?= number_format($selected_food['fiber'], 1) ?>g</strong>
              <span>Fiber</span>
            </div>
            <?php endif; ?>
          </div>

          <form method="POST">
            <input type="hidden" name="food_id" value="<?= $selected_food['id'] ?>">
            <input type="hidden" name="add_food" value="1">
            
            <label>How many servings?</label>
            <input type="number" name="multiplier" step="0.1" min="0.1" value="1" required>
            <p style="font-size: 0.85rem; color: #666; margin-top: 5px;">
              Example: 1 = <?= htmlspecialchars($selected_food['serving_size'] . ' ' . $selected_food['serving_unit']) ?>, 
              2 = <?= htmlspecialchars(($selected_food['serving_size'] * 2) . ' ' . $selected_food['serving_unit']) ?>
            </p>
            
            <label>Meal Type</label>
            <select name="meal_type" required>
              <option value="">Select meal type...</option>
              <option value="Breakfast">Breakfast</option>
              <option value="Lunch">Lunch</option>
              <option value="Dinner">Dinner</option>
              <option value="Snack">Snack</option>
            </select>

            <button type="submit" class="submit-btn">➕ Add to Log</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- Today's Logged Foods -->
    <div class="card">
      <h2>📊 Today's Food Log</h2>
      <?php if (count($foods) > 0): ?>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>Food Name</th>
                <th>Quantity</th>
                <th>Calories</th>
                <th>Protein (g)</th>
                <th>Carbs (g)</th>
                <th>Fat (g)</th>
                <th>Meal</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($foods as $f): ?>
                <tr>
                  <td><strong><?= htmlspecialchars(ucfirst($f['food_name'] ?? '')) ?></strong></td>
<td><?= htmlspecialchars($f['quantity'] ?? '') ?></td>
<td><?= number_format((float)($f['calories'] ?? 0), 1) ?></td>
<td><?= number_format((float)($f['protein'] ?? 0), 1) ?></td>
<td><?= number_format((float)($f['carbs'] ?? 0), 1) ?></td>
<td><?= number_format((float)($f['fat'] ?? 0), 1) ?></td>
<td><?= htmlspecialchars($f['meal_type'] ?? '') ?></td>

                  <td>
                    <a href="?delete=<?= $f['id'] ?>" onclick="return confirm('Delete this food item?')">
                      <button class="delete-btn">🗑️ Delete</button>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <div class="total-summary">
          <h3>📈 Today's Totals</h3>
          <div class="summary-grid">
            <div class="summary-item">
              <strong><?= number_format($totalCalories, 1) ?></strong>
              <span>kcal</span>
            </div>
            <div class="summary-item">
              <strong><?= number_format($totalProtein, 1) ?>g</strong>
              <span>Protein</span>
            </div>
            <div class="summary-item">
              <strong><?= number_format($totalCarbs, 1) ?>g</strong>
              <span>Carbs</span>
            </div>
            <div class="summary-item">
              <strong><?= number_format($totalFat, 1) ?>g</strong>
              <span>Fat</span>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="no-results">
          <p>📝 No foods logged yet today.</p>
          <p style="font-size: 0.9rem; margin-top: 10px;">Start by searching and adding foods above!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
<?php $conn->close(); ?>
