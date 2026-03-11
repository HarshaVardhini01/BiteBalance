<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Your Nutritionix credentials
$NUTRITIONIX_APP_ID  = "4f8a1044";
$NUTRITIONIX_APP_KEY = "cd66b57315404deb292f7d8ba93893d6"; // ✅ make sure there's no extra space or dash

// Example food name
$query = "1 cup cooked rice";

// API endpoint
$url = "https://trackapi.nutritionix.com/v2/natural/nutrients";

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-app-id: $NUTRITIONIX_APP_ID",
    "x-app-key: $NUTRITIONIX_APP_KEY",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "query" => $query,
    "timezone" => "Asia/Kolkata"
]));

// Execute and capture response
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "<b>cURL Error:</b> " . curl_error($ch);
    exit;
}

$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Decode JSON response
$data = json_decode($response, true);

// 🧠 Debug Output
echo "<h2>🔍 Nutritionix API Debug Output</h2>";
echo "<b>Query Sent:</b> $query<br>";
echo "<b>HTTP Status Code:</b> $http_status<br><br>";

echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if (isset($data['foods'][0])) {
    echo "<h3>✅ Parsed Nutrition Info:</h3>";
    echo "<b>Food:</b> " . htmlspecialchars($data['foods'][0]['food_name']) . "<br>";
    echo "<b>Calories:</b> " . htmlspecialchars($data['foods'][0]['nf_calories']) . " kcal<br>";
    echo "<b>Protein:</b> " . htmlspecialchars($data['foods'][0]['nf_protein']) . " g<br>";
    echo "<b>Carbs:</b> " . htmlspecialchars($data['foods'][0]['nf_total_carbohydrate']) . " g<br>";
    echo "<b>Fat:</b> " . htmlspecialchars($data['foods'][0]['nf_total_fat']) . " g<br>";
} else {
    echo "<h3>⚠️ Could not find nutrition data.</h3>";
}
?>

