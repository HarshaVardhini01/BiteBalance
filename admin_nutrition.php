<?php
include 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simple admin authentication (you can enhance this)
$admin_password = "admin123"; // Change this to a secure password

if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        if ($_POST['admin_pass'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $login_error = "Invalid password!";
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Admin Login</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f6fa; }
                .login-box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; }
                input { padding: 12px; margin: 10px 0; width: 250px; border: 1px solid #ddd; border-radius: 8px; }
                button { padding: 12px 30px; background: #2c3e50; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
                button:hover { background: #eebd1c; color: #2c3e50; }
                .error { color: red; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Admin Login</h2>
                <form method="POST">
                    <input type="password" name="admin_pass" placeholder="Admin Password" required>
                    <br>
                    <button type="submit" name="admin_login">Login</button>
                </form>
                <?php if (isset($login_error)): ?>
                    <p class="error"><?= $login_error ?></p>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

$success_message = "";
$error_message = "";

// Handle Add New Food
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food'])) {
    $food_name = trim($_POST['food_name']);
    $serving_size = trim($_POST['serving_size']);
    $serving_unit = trim($_POST['serving_unit']);
    $calories = (float)$_POST['calories'];
    $protein = (float)$_POST['protein'];
    $carbs = (float)$_POST['carbs'];
    $fat = (float)$_POST['fat'];
    $fiber = (float)($_POST['fiber'] ?? 0);
    $category = trim($_POST['category']);
    
    $stmt = $conn->prepare("INSERT INTO nutrition_database 
        (food_name, serving_size, serving_unit, calories, protein, carbs, fat, fiber, category) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssddddds", $food_name, $serving_size, $serving_unit, $calories, $protein, $carbs, $fat, $fiber, $category);
    
    if ($stmt->execute()) {
        $success_message = "✅ Food item added successfully!";
    } else {
        $error_message = "❌ Failed to add food item.";
    }
    $stmt->close();
}

// Handle Edit Food
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_food'])) {
    $id = (int)$_POST['food_id'];
    $food_name = trim($_POST['food_name']);
    $serving_size = trim($_POST['serving_size']);
    $serving_unit = trim($_POST['serving_unit']);
    $calories = (float)$_POST['calories'];
    $protein = (float)$_POST['protein'];
    $carbs = (float)$_POST['carbs'];
    $fat = (float)$_POST['fat'];
    $fiber = (float)($_POST['fiber'] ?? 0);
    $category = trim($_POST['category']);
    
    $stmt = $conn->prepare("UPDATE nutrition_database 
        SET food_name=?, serving_size=?, serving_unit=?, calories=?, protein=?, carbs=?, fat=?, fiber=?, category=? 
        WHERE id=?");
    $stmt->bind_param("sssdddddsi", $food_name, $serving_size, $serving_unit, $calories, $protein, $carbs, $fat, $fiber, $category, $id);
    
    if ($stmt->execute()) {
        $success_message = "✅ Food item updated successfully!";
    } else {
        $error_message = "❌ Failed to update food item.";
    }
    $stmt->close();
}

// Handle Delete Food
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM nutrition_database WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success_message = "✅ Food item deleted successfully!";
    }
    $stmt->close();
}

// Get food for editing
$edit_food = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM nutrition_database WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_food = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all foods with search and filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "food_name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= 's';
}
if (!empty($category_filter)) {
    $where_clauses[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT * FROM nutrition_database $where_sql ORDER BY category, food_name";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$foods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM nutrition_database ORDER BY category");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total_foods,
    COUNT(DISTINCT category) as total_categories
    FROM nutrition_database")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nutrition Database Admin - BiteBalance</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: "Poppins", sans-serif;
      background: #f5f6fa;
      color: #333;
    }
    
    .header {
      background: linear-gradient(135deg, #2c3e50, #3b5998);
      color: #fff;
      padding: 20px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .header h1 {
      font-size: 1.8rem;
    }
    .logout-btn {
      background: #dc3545;
      color: #fff;
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .logout-btn:hover {
      background: #c82333;
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 30px;
    }
    
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      text-align: center;
    }
    .stat-card h3 {
      font-size: 2.5rem;
      color: #2c3e50;
      margin-bottom: 5px;
    }
    .stat-card p {
      color: #666;
      font-size: 0.95rem;
    }
    
    .card {
      background: #fff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }
    
    .card h2 {
      color: #2c3e50;
      margin-bottom: 20px;
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .alert {
      padding: 12px 18px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    .alert-success {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid #28a745;
    }
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border-left: 4px solid #dc3545;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
    }
    label {
      margin-bottom: 5px;
      font-weight: 600;
      color: #555;
      font-size: 0.9rem;
    }
    input, select {
      padding: 10px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 0.95rem;
      font-family: "Poppins", sans-serif;
    }
    input:focus, select:focus {
      outline: none;
      border-color: #2c3e50;
    }
    
    .btn {
      padding: 12px 25px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 600;
      transition: 0.3s;
    }
    .btn-primary {
      background: #2c3e50;
      color: #fff;
    }
    .btn-primary:hover {
      background: #eebd1c;
      color: #2c3e50;
      transform: translateY(-2px);
    }
    .btn-success {
      background: #28a745;
      color: #fff;
    }
    .btn-success:hover {
      background: #218838;
    }
    .btn-danger {
      background: #dc3545;
      color: #fff;
      padding: 8px 15px;
      font-size: 0.85rem;
    }
    .btn-danger:hover {
      background: #c82333;
    }
    .btn-warning {
      background: #ffc107;
      color: #000;
      padding: 8px 15px;
      font-size: 0.85rem;
    }
    .btn-warning:hover {
      background: #e0a800;
    }
    
    .search-filter {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .search-filter input, .search-filter select {
      flex: 1;
      min-width: 200px;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    th, td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e0e0e0;
    }
    th {
      background: #f8f9fa;
      font-weight: 600;
      color: #2c3e50;
      position: sticky;
      top: 0;
    }
    tr:hover {
      background: #f8f9fa;
    }
    .actions {
      display: flex;
      gap: 8px;
    }
    
    .category-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 0.8rem;
      background: #e8f5e9;
      color: #2e7d32;
    }
    
    @media(max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      .search-filter {
        flex-direction: column;
      }
      table {
        font-size: 0.85rem;
      }
      th, td {
        padding: 8px;
      }
    }
  </style>
</head>
<body>

  <div class="header">
    <h1>🍽 Nutrition Database Admin</h1>
    <a href="?logout=1" class="logout-btn">🚪 Logout</a>
  </div>

  <div class="container">
    
    <?php if ($success_message): ?>
      <div class="alert alert-success"><?= $success_message ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
      <div class="alert alert-error"><?= $error_message ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats">
      <div class="stat-card">
        <h3><?= $stats['total_foods'] ?></h3>
        <p>Total Food Items</p>
      </div>
      <div class="stat-card">
        <h3><?= $stats['total_categories'] ?></h3>
        <p>Categories</p>
      </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="card">
      <h2><?= $edit_food ? '✏️ Edit Food Item' : '➕ Add New Food Item' ?></h2>
      <form method="POST">
        <?php if ($edit_food): ?>
          <input type="hidden" name="food_id" value="<?= $edit_food['id'] ?>">
        <?php endif; ?>
        
        <div class="form-grid">
          <div class="form-group">
            <label>Food Name *</label>
            <input type="text" name="food_name" value="<?= htmlspecialchars($edit_food['food_name'] ?? '') ?>" required>
          </div>
          
          <div class="form-group">
            <label>Serving Size *</label>
            <input type="text" name="serving_size" placeholder="e.g., 1, 100" value="<?= htmlspecialchars($edit_food['serving_size'] ?? '') ?>" required>
          </div>
          
          <div class="form-group">
            <label>Serving Unit *</label>
            <input type="text" name="serving_unit" placeholder="e.g., bowl, grams" value="<?= htmlspecialchars($edit_food['serving_unit'] ?? '') ?>" required>
          </div>
          
          <div class="form-group">
            <label>Category *</label>
            <input type="text" name="category" placeholder="e.g., Grains, Protein" value="<?= htmlspecialchars($edit_food['category'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label>Calories (kcal) *</label>
            <input type="number" step="0.1" name="calories" value="<?= $edit_food['calories'] ?? '' ?>" required>
          </div>
          
          <div class="form-group">
            <label>Protein (g) *</label>
            <input type="number" step="0.1" name="protein" value="<?= $edit_food['protein'] ?? '' ?>" required>
          </div>
          
          <div class="form-group">
            <label>Carbs (g) *</label>
            <input type="number" step="0.1" name="carbs" value="<?= $edit_food['carbs'] ?? '' ?>" required>
          </div>
          
          <div class="form-group">
            <label>Fat (g) *</label>
            <input type="number" step="0.1" name="fat" value="<?= $edit_food['fat'] ?? '' ?>" required>
          </div>
          
          <div class="form-group">
            <label>Fiber (g)</label>
            <input type="number" step="0.1" name="fiber" value="<?= $edit_food['fiber'] ?? '0' ?>">
          </div>
        </div>

        <?php if ($edit_food): ?>
          <button type="submit" name="edit_food" class="btn btn-success">💾 Update Food Item</button>
          <a href="admin_nutrition.php" class="btn btn-primary" style="text-decoration: none; display: inline-block;">Cancel</a>
        <?php else: ?>
          <button type="submit" name="add_food" class="btn btn-primary">➕ Add Food Item</button>
        <?php endif; ?>
      </form>
    </div>

    <!-- Food List -->
    <div class="card">
      <h2>📋 Food Database</h2>
      
      <form method="GET" class="search-filter">
        <input type="text" name="search" placeholder="Search food name..." value="<?= htmlspecialchars($search) ?>">
        <select name="category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat['category']) ?>" 
              <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['category']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="admin_nutrition.php" class="btn btn-warning" style="text-decoration: none;">Clear</a>
      </form>

      <?php if (count($foods) > 0): ?>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Food Name</th>
                <th>Serving</th>
                <th>Calories</th>
                <th>Protein</th>
                <th>Carbs</th>
                <th>Fat</th>
                <th>Fiber</th>
                <th>Category</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($foods as $food): ?>
                <tr>
                  <td><?= $food['id'] ?></td>
                  <td><strong><?= htmlspecialchars($food['food_name']) ?></strong></td>
                  <td><?= htmlspecialchars($food['serving_size'] . ' ' . $food['serving_unit']) ?></td>
                  <td><?= number_format($food['calories'], 1) ?></td>
                  <td><?= number_format($food['protein'], 1) ?>g</td>
                  <td><?= number_format($food['carbs'], 1) ?>g</td>
                  <td><?= number_format($food['fat'], 1) ?>g</td>
                  <td><?= number_format($food['fiber'], 1) ?>g</td>
                  <td><span class="category-badge"><?= htmlspecialchars($food['category']) ?></span></td>
                  <td>
                    <div class="actions">
                      <a href="?edit=<?= $food['id'] ?>">
                        <button class="btn btn-warning">✏️ Edit</button>
                      </a>
                      <a href="?delete=<?= $food['id'] ?>" onclick="return confirm('Delete this food item?')">
                        <button class="btn btn-danger">🗑️ Delete</button>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="text-align: center; padding: 40px; color: #999;">No food items found.</p>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>

<?php
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_nutrition.php");
    exit();
}

$conn->close();
?>
