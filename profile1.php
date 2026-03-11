<?php
include 'db.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// ===== Handle new log submission =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_name']) && !isset($_POST['update_id'])) {
    $food_name = $_POST['food_name'] ?? '';
    $quantity = $_POST['quantity'] ?? '1';
    $meal_type = $_POST['meal_type'] ?? 'Breakfast'; // default

    if ($food_name) {
        $stmt = $conn->prepare("SELECT calories, protein, carbs, fat, fiber, serving_unit FROM nutrition_database WHERE food_name=? LIMIT 1");
        $stmt->bind_param("s", $food_name);
        $stmt->execute();
        $food = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($food) {
            // Insert into food_logs
            $insert = $conn->prepare("INSERT INTO food_logs (username, food_name, quantity, serving_unit, calories, protein, carbs, fat, fiber, meal_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param(
                "sssdddddds",
                $username,
                $food_name,
                $quantity,
                $food['serving_unit'],
                $food['calories'],
                $food['protein'],
                $food['carbs'],
                $food['fat'],
                $food['fiber'],
                $meal_type
            );
            $insert->execute();
            $insert->close();

            $message = "Food logged successfully!";
        } else {
            $error = "Food not found in local database.";
        }
    } else {
        $error = "Please enter a food name.";
    }
}

// ===== Handle deletion =====
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM food_logs WHERE id=? AND username=?");
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    $stmt->close();
    header("Location: log_food.php");
    exit;
}

// ===== Handle quantity update =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = intval($_POST['update_id']);
    $new_qty = $_POST['update_quantity'];

    // Get original nutrition from nutrition_database
    $stmt = $conn->prepare("SELECT calories, protein, carbs, fat, fiber FROM nutrition_database WHERE food_name=(SELECT food_name FROM food_logs WHERE id=? AND username=?) LIMIT 1");
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    $food = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($food) {
        $calories = $food['calories'] * $new_qty;
        $protein = $food['protein'] * $new_qty;
        $carbs = $food['carbs'] * $new_qty;
        $fat = $food['fat'] * $new_qty;
        $fiber = $food['fiber'] * $new_qty;

        $update = $conn->prepare("UPDATE food_logs SET quantity=?, calories=?, protein=?, carbs=?, fat=?, fiber=? WHERE id=? AND username=?");
        $update->bind_param("ddddddis", $new_qty, $calories, $protein, $carbs, $fat, $fiber, $id, $username);
        $update->execute();
        $update->close();

        $message = "Quantity updated successfully!";
    }
}

// ===== Fetch today's logs using log_date =====
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM food_logs WHERE username=? AND log_date=?");
$stmt->bind_param("ss", $username, $today);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
$total_calories = $total_protein = $total_carbs = $total_fat = $total_fiber = 0;
while($row = $result->fetch_assoc()) {
    $logs[] = $row;
    $total_calories += $row['calories'];
    $total_protein += $row['protein'];
    $total_carbs += $row['carbs'];
    $total_fat += $row['fat'];
    $total_fiber += $row['fiber'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Log Food</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        input[type=number] { width: 60px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(function(){
        $("#food_name").on("input", function(){
            var query = $(this).val();
            if(query.length >= 1){
                $.ajax({
                    url: "autocomplete_food.php",
                    method: "GET",
                    data: { q: query },
                    success: function(data){
                        var foods = JSON.parse(data);
                        var list = "";
                        foods.forEach(f => {
                            // f = "Food Name | unit | calories"
                            list += `<option value="${f}">`;
                        });
                        $("#food_list").html(list);
                    }
                });
            }
        });
    });
    </script>
</head>
<body>

<h2>Log Food</h2>
<?php if(isset($message)) echo "<p style='color:green;'>$message</p>"; ?>
<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form method="POST">
    <input list="food_list" name="food_name" id="food_name" placeholder="Enter food name" required>
    <datalist id="food_list"></datalist>
    Quantity: <input type="number" name="quantity" step="0.1" value="1" min="0.1" required>
    Meal: 
    <select name="meal_type">
        <option value="Breakfast">Breakfast</option>
        <option value="Lunch">Lunch</option>
        <option value="Dinner">Dinner</option>
        <option value="Snack">Snack</option>
    </select>
    <button type="submit">Log Food</button>
</form>

<h3>Today's Food Logs (<?= $today ?>)</h3>
<table>
<tr>
    <th>Food Name</th>
    <th>Quantity</th>
    <th>Unit</th>
    <th>Calories</th>
    <th>Protein</th>
    <th>Carbs</th>
    <th>Fat</th>
    <th>Fiber</th>
    <th>Meal</th>
    <th>Actions</th>
</tr>
<?php foreach($logs as $log): ?>
<tr>
    <td><?= htmlspecialchars($log['food_name']) ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="update_id" value="<?= $log['id'] ?>">
            <input type="number" name="update_quantity" step="0.1" value="<?= $log['quantity'] ?>" min="0.1">
            <button type="submit">Update</button>
        </form>
    </td>
    <td><?= htmlspecialchars($log['serving_unit']) ?></td>
    <td><?= $log['calories'] ?></td>
    <td><?= $log['protein'] ?></td>
    <td><?= $log['carbs'] ?></td>
    <td><?= $log['fat'] ?></td>
    <td><?= $log['fiber'] ?></td>
    <td><?= $log['meal_type'] ?></td>
    <td><a href="?delete=<?= $log['id'] ?>" onclick="return confirm('Delete this entry?')">Delete</a></td>
</tr>
<?php endforeach; ?>
<tr>
    <th>Total</th>
    <th>-</th>
    <th>-</th>
    <th><?= $total_calories ?></th>
    <th><?= $total_protein ?></th>
    <th><?= $total_carbs ?></th>
    <th><?= $total_fat ?></th>
    <th><?= $total_fiber ?></th>
    <th>-</th>
    <th>-</th>
</tr>
</table>
</body>
</html>

