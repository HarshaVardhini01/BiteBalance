<?php
include 'db.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare("SELECT id, food_name FROM nutrition_database WHERE food_name LIKE CONCAT('%', ?, '%') ORDER BY food_name LIMIT 10");
$stmt->bind_param("s", $q);
$stmt->execute();
$result = $stmt->get_result();

$foods = [];
while ($row = $result->fetch_assoc()) {
    $foods[] = $row;
}

echo json_encode($foods);
$stmt->close();
$conn->close();
?>

